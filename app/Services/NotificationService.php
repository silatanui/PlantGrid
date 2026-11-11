<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use App\Notifications\CaseCommentedNotification;
use App\Notifications\SlaAlertNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function caseAssigned(SupportTicket $ticket, User $assignedBy, ?int $previousAssigneeId = null): void
    {
        if (! $ticket->assigned_to || (int) $ticket->assigned_to === (int) $assignedBy->id) {
            return;
        }

        if ($previousAssigneeId !== null && (int) $previousAssigneeId === (int) $ticket->assigned_to) {
            return;
        }

        $assignee = User::query()->find($ticket->assigned_to);
        if (! $assignee || $assignee->status !== 'active') {
            return;
        }

        $assignee->notify(new CaseAssignedNotification($ticket, $assignedBy));
    }

    public function caseCommented(SupportTicket $ticket, TicketComment $comment, User $author): void
    {
        $recipients = collect();

        if ($ticket->assigned_to && (int) $ticket->assigned_to !== (int) $author->id) {
            $assignee = User::query()->find($ticket->assigned_to);
            if ($assignee && $assignee->status === 'active') {
                $recipients->push($assignee);
            }
        }

        if (
            $comment->visibility === 'requester_visible'
            && $ticket->requester_id
            && (int) $ticket->requester_id !== (int) $author->id
        ) {
            $requester = User::query()->find($ticket->requester_id);
            if ($requester && $requester->status === 'active') {
                $recipients->push($requester);
            }
        }

        $recipients = $recipients->unique('id')->values();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CaseCommentedNotification($ticket, $comment, $author));
    }

    /**
     * @return array{at_risk: int, breached: int}
     */
    public function scanSla(SlaService $sla): array
    {
        $atRisk = 0;
        $breached = 0;

        $tickets = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_due_at')
            ->with(['assignee'])
            ->get();

        foreach ($tickets as $ticket) {
            $status = $sla->status($ticket);

            if ($status === 'breached') {
                $wasNew = $ticket->sla_breached_at === null;
                if ($wasNew) {
                    $ticket->forceFill(['sla_breached_at' => now()])->save();
                    $this->notifySlaWatchers($ticket, 'breached');
                    $breached++;
                }

                continue;
            }

            if ($status === 'at_risk' && $ticket->sla_at_risk_notified_at === null) {
                $ticket->forceFill(['sla_at_risk_notified_at' => now()])->save();
                $this->notifySlaWatchers($ticket, 'at_risk');
                $atRisk++;
            }
        }

        return ['at_risk' => $atRisk, 'breached' => $breached];
    }

    private function notifySlaWatchers(SupportTicket $ticket, string $level): void
    {
        $recipients = collect();

        if ($ticket->assignee && $ticket->assignee->status === 'active') {
            $recipients->push($ticket->assignee);
        }

        $managers = User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'tickets.assign'))
            ->get();

        foreach ($managers as $manager) {
            $recipients->push($manager);
        }

        $recipients = $recipients->unique('id')->values();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SlaAlertNotification($ticket, $level));
    }
}
