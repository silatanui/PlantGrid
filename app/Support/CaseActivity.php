<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaseActivity
{
    /**
     * Timeline for a single case page (status, notes, ownership, partners).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forTicket(SupportTicket $ticket, Collection $comments): Collection
    {
        $items = collect();

        foreach ($ticket->histories as $history) {
            $items->push([
                'type' => 'status',
                'at' => $history->created_at,
                'actor' => $history->changer->name ?? 'System',
                'label' => 'Status → '.Labels::status((string) $history->new_status),
                'badge' => $history->new_status,
                'body' => $history->notes,
                'html' => RichText::render($history->notes),
            ]);
        }

        foreach ($comments as $comment) {
            /** @var TicketComment $comment */
            $internal = $comment->visibility === 'internal';
            $items->push([
                'type' => 'note',
                'at' => $comment->created_at,
                'actor' => $comment->author->name ?? 'User',
                'label' => $internal ? 'Internal note posted' : 'Note posted',
                'badge' => $internal ? 'internal' : 'note',
                'body' => Str::limit(trim(strip_tags((string) $comment->comment)), 160),
                'html' => null,
                'note_id' => $comment->id,
            ]);
        }

        $audits = AuditLog::query()
            ->with('user:id,name')
            ->where('entity_type', 'support_ticket')
            ->where('entity_id', $ticket->id)
            ->whereIn('action', [
                'ticket.assign',
                'ticket.partner.add',
                'ticket.partner.remove',
                'ticket.comment.update',
                'ticket.comment.delete',
                'ticket.attachment.delete',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get();

        foreach ($audits as $log) {
            $items->push([
                'type' => 'audit',
                'at' => $log->created_at,
                'actor' => $log->user->name ?? 'System',
                'label' => self::labelFor($log, $ticket),
                'badge' => self::badgeFor($log->action),
                'body' => self::detailFor($log),
                'html' => null,
            ]);
        }

        return $items
            ->filter(fn (array $item) => $item['at'] !== null)
            ->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * Utility-rail feed of the signed-in user's recent case actions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forUserRail(User $user, int $limit = 8): Collection
    {
        $logs = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('entity_type', 'support_ticket')
            ->whereIn('action', [
                'ticket.created',
                'ticket.assign',
                'ticket.status',
                'ticket.comment',
                'ticket.comment.update',
                'ticket.comment.delete',
                'ticket.partner.add',
                'ticket.partner.remove',
            ])
            ->latest('created_at')
            ->limit($limit * 3)
            ->get();

        if ($logs->isEmpty()) {
            return collect();
        }

        $tickets = SupportTicket::query()
            ->visibleTo($user)
            ->whereIn('id', $logs->pluck('entity_id')->filter()->unique()->all())
            ->get(['id', 'ticket_number', 'subject'])
            ->keyBy('id');

        return $logs
            ->map(function (AuditLog $log) use ($tickets) {
                $ticket = $tickets->get($log->entity_id);
                if (! $ticket) {
                    return null;
                }

                return [
                    'title' => self::labelFor($log, $ticket),
                    'meta' => $ticket->ticket_number.' · '.$ticket->subject,
                    'href' => route('helpdesk.show', $ticket).(
                        $log->action === 'ticket.comment' ? '#notes' : '#activity'
                    ),
                    'when' => optional($log->created_at)->diffForHumans(),
                ];
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    public static function labelFor(AuditLog $log, ?SupportTicket $ticket = null): string
    {
        $number = $ticket?->ticket_number;
        $suffix = $number ? ' on '.$number : '';

        return match ($log->action) {
            'ticket.created' => 'Opened case'.$suffix,
            'ticket.assign' => 'Changed ownership'.$suffix,
            'ticket.status' => 'Updated status'.$suffix,
            'ticket.comment' => (($log->new_values['visibility'] ?? '') === 'internal'
                ? 'Posted an internal note'
                : 'Posted a note').$suffix,
            'ticket.comment.update' => 'Edited a note'.$suffix,
            'ticket.comment.delete' => 'Deleted a note'.$suffix,
            'ticket.partner.add' => 'Added a partner'.$suffix,
            'ticket.partner.remove' => 'Removed a partner'.$suffix,
            'ticket.attachment.delete' => 'Removed an attachment'.$suffix,
            default => Str::of($log->action)->replace('.', ' ')->headline()->toString().$suffix,
        };
    }

    private static function badgeFor(string $action): string
    {
        return match ($action) {
            'ticket.assign' => 'ownership',
            'ticket.partner.add', 'ticket.partner.remove' => 'partner',
            'ticket.comment.update' => 'note edited',
            'ticket.comment.delete' => 'note deleted',
            'ticket.attachment.delete' => 'attachment',
            default => 'update',
        };
    }

    private static function detailFor(AuditLog $log): ?string
    {
        $new = $log->new_values ?? [];
        $old = $log->old_values ?? [];

        return match ($log->action) {
            'ticket.assign' => trim(
                ($old['assigned_to_name'] ?? $old['assigned_to'] ?? 'Unassigned')
                .' → '
                .($new['assigned_to_name'] ?? $new['assigned_to'] ?? 'Unassigned')
            ),
            'ticket.partner.add' => $new['partner_name'] ?? null,
            'ticket.partner.remove' => $old['partner_name'] ?? null,
            'ticket.comment', 'ticket.comment.update' => isset($new['preview'])
                ? Str::limit((string) $new['preview'], 160)
                : null,
            default => null,
        };
    }
}
