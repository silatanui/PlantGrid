<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use App\Notifications\CaseCommentedNotification;
use App\Notifications\CaseMentionedNotification;
use App\Notifications\CaseUpdatedNotification;
use App\Notifications\SlaAlertNotification;
use App\Support\RichText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function syncPartners(SupportTicket $ticket, iterable $userIds, string $source = 'system'): void
    {
        $ids = collect($userIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $existing = $ticket->partners()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $attach = [];
        foreach ($ids as $id) {
            if (! in_array($id, $existing, true)) {
                $attach[$id] = ['source' => $source];
            }
        }

        if ($attach !== []) {
            $ticket->partners()->attach($attach);
        }
    }

    public function caseAssigned(SupportTicket $ticket, User $assignedBy, ?int $previousAssigneeId = null): void
    {
        $this->syncPartners(
            $ticket,
            array_filter([$ticket->assigned_to, $ticket->requester_id, $assignedBy->id]),
            'assignment'
        );

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
        $mentioned = RichText::mentionedUsers($comment->comment)
            ->reject(fn (User $user) => (int) $user->id === (int) $author->id)
            ->values();

        $this->syncPartners(
            $ticket,
            collect([$ticket->requester_id, $ticket->assigned_to, $author->id])
                ->merge($mentioned->pluck('id'))
                ->all(),
            'note'
        );

        $ticket->loadMissing('partners');

        $partners = $ticket->partners
            ->where('status', 'active')
            ->reject(fn (User $user) => (int) $user->id === (int) $author->id)
            ->values();

        $mentionedIds = $mentioned->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($mentioned->isNotEmpty()) {
            $mentionRecipients = $mentioned->filter(function (User $user) use ($comment) {
                return $comment->visibility !== 'internal' || $this->canReceiveInternalNote($user);
            })->values();

            if ($mentionRecipients->isNotEmpty()) {
                Notification::send($mentionRecipients, new CaseMentionedNotification($ticket, $comment, $author));
            }
        }

        $noteRecipients = $partners->filter(function (User $user) use ($comment, $mentionedIds) {
            if (in_array((int) $user->id, $mentionedIds, true)) {
                return false;
            }

            if ($comment->visibility === 'internal') {
                return $this->canReceiveInternalNote($user);
            }

            return true;
        })->values();

        if ($noteRecipients->isNotEmpty()) {
            Notification::send($noteRecipients, new CaseCommentedNotification($ticket, $comment, $author));
        }
    }

    public function caseUpdated(
        SupportTicket $ticket,
        User $actor,
        string $previousStatus,
        string $newStatus,
        array $excludeUserIds = []
    ): void {
        if ($previousStatus === $newStatus) {
            return;
        }

        $this->syncPartners($ticket, array_filter([$ticket->requester_id, $ticket->assigned_to, $actor->id]), 'status');
        $ticket->loadMissing('partners');

        $excluded = collect($excludeUserIds)
            ->push($actor->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $recipients = $ticket->partners
            ->where('status', 'active')
            ->reject(fn (User $user) => in_array((int) $user->id, $excluded, true))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CaseUpdatedNotification($ticket, $actor, $previousStatus, $newStatus));
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

    private function canReceiveInternalNote(User $user): bool
    {
        return $user->hasAnyPermission([
            'tickets.update',
            'tickets.assign',
            'tickets.view.all',
            'audit.view',
        ]);
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
