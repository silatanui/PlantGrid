<?php

namespace App\Services;

use App\Models\SupportTicket;
use Carbon\CarbonInterface;

class SlaService
{
    /** @var array<string, int> */
    private const HOURS = [
        'critical' => 4,
        'high' => 8,
        'medium' => 24,
        'low' => 72,
    ];

    public function targetHours(string $priority): int
    {
        return self::HOURS[$priority] ?? self::HOURS['medium'];
    }

    public function dueAt(CarbonInterface $reportedAt, string $priority): CarbonInterface
    {
        return $reportedAt->copy()->addHours($this->targetHours($priority));
    }

    public function applyOnCreate(SupportTicket $ticket): SupportTicket
    {
        $reportedAt = $ticket->reported_at ?? now();
        $slaDue = $this->dueAt($reportedAt, (string) $ticket->priority);
        $ticket->sla_due_at = $slaDue;
        if (blank($ticket->due_at)) {
            $ticket->due_at = $slaDue;
        }
        $ticket->sla_breached_at = null;
        $ticket->sla_at_risk_notified_at = null;

        return $ticket;
    }

    public function refreshForOpenTicket(SupportTicket $ticket): SupportTicket
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return $ticket;
        }

        $reportedAt = $ticket->reported_at ?? $ticket->created_at ?? now();
        $ticket->sla_due_at = $this->dueAt($reportedAt, (string) $ticket->priority);
        if (blank($ticket->due_at) || $ticket->due_at->equalTo($ticket->getOriginal('sla_due_at'))) {
            $ticket->due_at = $ticket->sla_due_at;
        }
        if ($ticket->sla_due_at->isFuture()) {
            $ticket->sla_breached_at = null;
        }

        return $ticket;
    }

    /**
     * @return 'ok'|'at_risk'|'breached'|'done'
     */
    public function status(SupportTicket $ticket): string
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return 'done';
        }

        $deadline = $ticket->sla_due_at ?? $ticket->due_at;
        if (! $deadline) {
            return 'ok';
        }

        if ($ticket->sla_breached_at || $deadline->isPast()) {
            return 'breached';
        }

        $reportedAt = $ticket->reported_at ?? $ticket->created_at ?? now();
        $totalSeconds = max(1, $reportedAt->diffInSeconds($deadline));
        $remaining = now()->diffInSeconds($deadline, false);
        if ($remaining <= ($totalSeconds * 0.25)) {
            return 'at_risk';
        }

        return 'ok';
    }

    public function label(string $status): string
    {
        return match ($status) {
            'breached' => 'SLA breached',
            'at_risk' => 'SLA at risk',
            'done' => 'SLA closed',
            default => 'SLA on track',
        };
    }

    public function badge(SupportTicket $ticket): string
    {
        $status = $this->status($ticket);
        $tone = match ($status) {
            'breached' => 'critical',
            'at_risk' => 'warning',
            'done' => 'muted',
            default => 'info',
        };

        return '<span class="pg-badge pg-badge-'.$tone.'">'.e($this->label($status)).'</span>';
    }
}
