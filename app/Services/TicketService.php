<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetStatusHistory;
use App\Models\MaintenanceRecord;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SlaService $sla,
        private readonly NotificationService $notifications,
    ) {}

    public function nextNumber(): string
    {
        $year = now()->year;
        $prefix = 'PG-'.$year.'-';
        $latest = SupportTicket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }

    public function create(User $requester, array $data): SupportTicket
    {
        return DB::transaction(function () use ($requester, $data) {
            $ticket = SupportTicket::query()->create([
                'ticket_number' => $this->nextNumber(),
                'requester_id' => $requester->id,
                'department_id' => $data['department_id'],
                'ticket_category_id' => $data['ticket_category_id'],
                'asset_id' => $data['asset_id'] ?? null,
                'system_application_id' => $data['system_application_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'status' => 'new',
                'reported_at' => now(),
                'due_at' => $data['due_at'] ?? null,
            ]);

            $this->sla->applyOnCreate($ticket);
            $ticket->save();

            $this->history($ticket, null, 'new', $requester, 'Ticket opened.');
            $this->audit->log('ticket.created', 'support_ticket', $ticket->id, [], [
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'subject' => $ticket->subject,
                'sla_due_at' => optional($ticket->sla_due_at)?->toDateTimeString(),
            ]);

            $mentioned = \App\Support\RichText::mentionedUsers(
                ($data['description'] ?? '').' '.($data['subject'] ?? '')
            )->pluck('id')->all();
            $this->notifications->syncPartners($ticket, array_merge([$requester->id], $mentioned), 'requester');

            return $ticket;
        });
    }

    public function allowedTransitions(SupportTicket $ticket, User $user): array
    {
        $map = [
            'new' => ['assigned', 'in_progress', 'closed'],
            'assigned' => ['in_progress', 'pending', 'resolved', 'closed'],
            'in_progress' => ['pending', 'resolved', 'closed'],
            'pending' => ['in_progress', 'resolved', 'closed'],
            'resolved' => ['closed', 'in_progress'],
            'closed' => ['in_progress'],
        ];

        $next = $map[$ticket->status] ?? [];
        $isPartner = $ticket->isPartner($user);
        $canWork = $user->hasPermission('tickets.update') || $isPartner;
        $canResolve = $user->hasPermission('tickets.resolve') || $isPartner;
        $canClose = $user->hasPermission('tickets.close') || $isPartner;
        $canReopen = $user->hasPermission('tickets.reopen') || $isPartner;

        return array_values(array_filter($next, function (string $status) use ($user, $ticket, $canWork, $canResolve, $canClose, $canReopen) {
            return match ($status) {
                'assigned' => $user->hasPermission('tickets.assign'),
                'resolved' => $canResolve,
                'closed' => $canClose,
                'in_progress' => $ticket->status === 'resolved' || $ticket->status === 'closed'
                    ? $canReopen
                    : $canWork,
                'pending' => $canWork,
                default => false,
            };
        }));
    }

    public function canTakeOwnership(SupportTicket $ticket, User $user): bool
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return false;
        }

        if ((int) $ticket->assigned_to === (int) $user->id) {
            return false;
        }

        // Only one owner at a time: partners may claim an unassigned case.
        if ($ticket->assigned_to) {
            return false;
        }

        if ($user->hasPermission('tickets.assign')) {
            return true;
        }

        return $ticket->isPartner($user);
    }

    public function canTransferOwnership(SupportTicket $ticket, User $user): bool
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return false;
        }

        if ($user->hasPermission('tickets.assign')) {
            return true;
        }

        return $ticket->assigned_to && (int) $ticket->assigned_to === (int) $user->id;
    }

    public function takeOwnership(SupportTicket $ticket, User $user, ?string $note = null): SupportTicket
    {
        if (! $this->canTakeOwnership($ticket, $user)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Only a case partner can take ownership when the case is unassigned.',
            ]);
        }

        $this->notifications->syncPartners($ticket, [$user->id], 'ownership');

        return $this->assignTo($ticket, $user, $user, $note ?: $user->name.' took ownership of the case.');
    }

    public function assignTo(SupportTicket $ticket, User $assignee, User $actor, ?string $note = null): SupportTicket
    {
        $isSelfClaim = (int) $assignee->id === (int) $actor->id && $this->canTakeOwnership($ticket, $actor);
        $canAssign = $actor->hasPermission('tickets.assign') || $this->canTransferOwnership($ticket, $actor) || $isSelfClaim;

        if (! $canAssign) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Only the current owner or an assigner can transfer this case.',
            ]);
        }

        if ($assignee->status !== 'active') {
            throw ValidationException::withMessages([
                'assigned_to' => 'That user is not active.',
            ]);
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Resolved or closed cases cannot be assigned.',
            ]);
        }

        // Non-admin transfers must stay among partners (new owner becomes partner).
        if (! $actor->hasPermission('tickets.assign') && ! $ticket->isPartner($assignee) && (int) $assignee->id !== (int) $actor->id) {
            $this->notifications->syncPartners($ticket, [$assignee->id], 'transfer');
        }

        return DB::transaction(function () use ($ticket, $assignee, $actor, $note) {
            $previous = $ticket->status;
            $previousAssignee = $ticket->assigned_to;
            $ticket->assigned_to = $assignee->id;
            if ($ticket->status === 'new') {
                $ticket->status = 'assigned';
            }
            $ticket->save();

            $this->notifications->syncPartners($ticket, [$assignee->id, $actor->id], 'ownership');

            $this->history(
                $ticket,
                $previous,
                $ticket->status,
                $actor,
                $note ?: $actor->name.' assigned the case to '.$assignee->name.'.'
            );

            $this->audit->log('ticket.assign', 'support_ticket', $ticket->id, [
                'assigned_to' => $previousAssignee,
                'assigned_to_name' => $previousAssignee
                    ? (User::query()->whereKey($previousAssignee)->value('name') ?: 'User #'.$previousAssignee)
                    : 'Unassigned',
                'status' => $previous,
            ], [
                'assigned_to' => $ticket->assigned_to,
                'assigned_to_name' => $assignee->name,
                'status' => $ticket->status,
            ]);

            $fresh = $ticket->fresh();
            $this->notifications->caseAssigned($fresh, $actor, $previousAssignee);

            return $ticket->fresh(['assignee', 'department', 'category', 'partners']);
        });
    }

    public function transition(SupportTicket $ticket, User $user, string $status, array $options = []): SupportTicket
    {
        if (! in_array($status, $this->allowedTransitions($ticket, $user), true)) {
            throw ValidationException::withMessages([
                'status' => 'That status change is not available for this ticket.',
            ]);
        }

        $previous = $ticket->status;
        $needsStepEvidence = $previous !== $status
            && ! ($previous === 'resolved' && $status === 'closed');

        if ($needsStepEvidence && blank($options['resolution_notes'] ?? null)) {
            throw ValidationException::withMessages([
                'resolution_notes' => 'Add a resolution note explaining this next step.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $user, $status, $options, $previous, $needsStepEvidence) {
            $previousAssignee = $ticket->assigned_to;
            $ticket->status = $status;
            $historyNote = filled($options['note'] ?? null)
                ? $options['note']
                : ($options['resolution_notes'] ?? null);

            if ($status === 'assigned' || ($status === 'in_progress' && ! empty($options['assigned_to']))) {
                if (empty($options['assigned_to']) && $status === 'assigned') {
                    throw ValidationException::withMessages([
                        'assigned_to' => 'Choose a technician before assigning this ticket.',
                    ]);
                }
                if (! empty($options['assigned_to'])) {
                    $ticket->assigned_to = $options['assigned_to'];
                }
            }

            if ($needsStepEvidence || $status === 'resolved') {
                if (filled($options['resolution_notes'] ?? null)) {
                    $ticket->resolution_notes = $options['resolution_notes'];
                }
            }

            if ($status === 'resolved') {
                if (blank($options['resolution_notes'] ?? null)) {
                    throw ValidationException::withMessages([
                        'resolution_notes' => 'Resolution notes are required.',
                    ]);
                }
                $ticket->resolution_notes = $options['resolution_notes'];
                $ticket->close_reason = null;
                $ticket->duplicate_of_ticket_id = null;
                $ticket->resolved_at = now();
                $ticket->closed_at = null;
            }

            if ($status === 'closed') {
                if ($previous !== 'resolved') {
                    $original = $this->resolveDuplicateOriginal($ticket, $options['duplicate_of'] ?? null);
                    $ticket->close_reason = 'duplicate';
                    $ticket->duplicate_of_ticket_id = $original->id;
                    $ticket->resolution_notes = filled($options['resolution_notes'] ?? null)
                        ? $options['resolution_notes']
                        : 'Closed as a duplicate of '.$original->ticket_number.'.';
                    $historyNote = filled($historyNote)
                        ? $historyNote
                        : 'Closed as duplicate of '.$original->ticket_number.'.';
                } else {
                    $ticket->close_reason = null;
                    $ticket->duplicate_of_ticket_id = null;
                }

                $ticket->closed_at = now();
                $ticket->resolved_at ??= now();
            }

            if ($status === 'in_progress' && in_array($previous, ['resolved', 'closed'], true)) {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
                $ticket->close_reason = null;
                $ticket->duplicate_of_ticket_id = null;
                $this->sla->refreshForOpenTicket($ticket);
            }

            $ticket->save();
            $this->history($ticket, $previous, $status, $user, $historyNote);

            if (! empty($options['record_maintenance']) && $ticket->asset_id) {
                $this->recordMaintenance($ticket, $user, $options['resolution_notes'] ?? $options['note'] ?? 'Work recorded from the helpdesk.');
            }

            if (! empty($options['restore_asset']) && $ticket->asset) {
                $this->restoreAsset($ticket->asset, $user);
            }

            $this->audit->log('ticket.status', 'support_ticket', $ticket->id, [
                'status' => $previous,
            ], [
                'status' => $status,
                'assigned_to' => $ticket->assigned_to,
            ]);

            $fresh = $ticket->fresh();
            $exclude = [];

            if ($fresh->assigned_to && (int) $fresh->assigned_to !== (int) ($previousAssignee ?? 0)) {
                $this->notifications->caseAssigned($fresh, $user, $previousAssignee);
                $exclude[] = (int) $fresh->assigned_to;
            }

            if ($previous !== $status) {
                $this->notifications->caseUpdated($fresh, $user, $previous, $status, $exclude);
            }

            return $fresh->fresh(['assignee', 'partners', 'comments']);
        });
    }

    public function comment(SupportTicket $ticket, User $user, string $comment, string $visibility): TicketComment
    {
        $entry = $ticket->comments()->create([
            'user_id' => $user->id,
            'comment' => $comment,
            'visibility' => $visibility,
        ]);

        $ticket->touch();

        $this->audit->log('ticket.comment', 'support_ticket', $ticket->id, [], [
            'visibility' => $visibility,
            'comment_id' => $entry->id,
            'preview' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($comment)) ?: ''), 180),
        ]);

        $entry = $entry->load(['author.roles:id,name']);
        $this->notifications->caseCommented($ticket, $entry, $user);

        return $entry;
    }

    public function updateComment(TicketComment $entry, User $user, string $comment, string $visibility): TicketComment
    {
        if (! $user->canEditTicketComment($entry)) {
            throw ValidationException::withMessages([
                'comment' => 'Only the author can edit this note.',
            ]);
        }

        $before = $entry->only(['comment', 'visibility']);
        $entry->forceFill([
            'comment' => $comment,
            'visibility' => $visibility,
            'updated_at' => now(),
        ])->save();

        $entry->ticket?->touch();

        $this->audit->log('ticket.comment.update', 'support_ticket', $entry->support_ticket_id, $before, [
            'comment_id' => $entry->id,
            'visibility' => $visibility,
            'preview' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($comment)) ?: ''), 180),
        ]);

        return $entry->fresh(['author.roles:id,name']);
    }

    public function deleteComment(TicketComment $entry, User $user): void
    {
        if (! $user->canDeleteTicketComment($entry)) {
            throw ValidationException::withMessages([
                'comment' => 'Only the author or a system administrator can delete this note.',
            ]);
        }

        $snapshot = $entry->only(['id', 'comment', 'visibility', 'user_id']);
        $ticketId = $entry->support_ticket_id;
        $ticket = $entry->ticket;
        $entry->delete();
        $ticket?->touch();

        $this->audit->log('ticket.comment.delete', 'support_ticket', $ticketId, $snapshot, []);
    }

    public function resolveDuplicateOriginal(SupportTicket $ticket, mixed $reference): SupportTicket
    {
        $value = trim((string) $reference);
        if ($value === '') {
            throw ValidationException::withMessages([
                'duplicate_of' => 'Enter the original case number this ticket duplicates.',
            ]);
        }

        $original = SupportTicket::query()
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('ticket_number', $value)
            )
            ->first();

        if (! $original) {
            throw ValidationException::withMessages([
                'duplicate_of' => 'No case matches that number.',
            ]);
        }

        if ($original->id === $ticket->id) {
            throw ValidationException::withMessages([
                'duplicate_of' => 'A case cannot be marked as a duplicate of itself.',
            ]);
        }

        return $original;
    }

    private function history(SupportTicket $ticket, ?string $old, string $new, User $user, ?string $notes): void
    {
        TicketStatusHistory::query()->create([
            'support_ticket_id' => $ticket->id,
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $user->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    private function recordMaintenance(SupportTicket $ticket, User $user, string $notes): void
    {
        $record = MaintenanceRecord::query()->create([
            'subject' => 'Helpdesk '.$ticket->ticket_number,
            'description' => $notes,
            'asset_id' => $ticket->asset_id,
            'assigned_technician_id' => $user->id,
            'maintenance_type' => 'corrective',
            'priority' => $ticket->priority === 'critical' ? 'high' : $ticket->priority,
            'status' => 'completed',
            'scheduled_at' => $ticket->reported_at,
            'completed_at' => now(),
            'notes' => 'Created when the linked ticket was resolved.',
        ]);

        AssetMaintenanceRecord::query()->create([
            'asset_id' => $ticket->asset_id,
            'technician_id' => $user->id,
            'maintenance_type' => 'corrective',
            'description' => $notes,
            'scheduled_at' => $ticket->reported_at,
            'completed_at' => now(),
            'status' => 'completed',
            'notes' => 'Helpdesk '.$ticket->ticket_number,
        ]);

        $this->audit->log('maintenance.created', 'maintenance_record', $record->id, [], [
            'ticket_number' => $ticket->ticket_number,
        ]);
    }

    private function restoreAsset(Asset $asset, User $user): void
    {
        $previous = $asset->status;
        if ($previous === 'active') {
            return;
        }

        $asset->update(['status' => 'active', 'condition' => 'good']);
        AssetStatusHistory::query()->create([
            'asset_id' => $asset->id,
            'previous_status' => $previous,
            'new_status' => 'active',
            'changed_by' => $user->id,
            'changed_at' => now(),
            'notes' => 'Returned to service from the helpdesk.',
        ]);
    }
}
