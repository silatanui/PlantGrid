<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Format;
use App\Support\Labels;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CaseTriageService
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly OpenAiService $ai,
        private readonly TicketService $tickets,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{
     *     source: string,
     *     owner: ?array<string, mixed>,
     *     partners: list<array<string, mixed>>,
     *     next_steps: list<string>,
     *     resolution_outline: string,
     *     internal_note: string,
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function suggest(SupportTicket $ticket, User $actor): array
    {
        if (! $actor->hasPermission('tickets.assign')) {
            throw new AuthorizationException('Only assigners can run AI case triage.');
        }

        $ticket->loadMissing(['requester', 'assignee', 'partners', 'department', 'category', 'asset.category', 'asset.location']);

        $candidates = $this->rankCandidates($ticket);
        $heuristic = $this->buildHeuristicSuggestion($ticket, $candidates);

        if ($this->ai->enabled()) {
            try {
                $aiSuggestion = $this->ai->triageCase($ticket, $candidates->take(8)->values()->all());
                $merged = $this->mergeAiSuggestion($ticket, $heuristic, $aiSuggestion, $candidates);

                return [
                    ...$merged,
                    'source' => 'ai',
                    'candidates' => $candidates->take(6)->values()->all(),
                ];
            } catch (RuntimeException) {
                // Fall through to heuristic so triage still works offline.
            }
        }

        return [
            ...$heuristic,
            'source' => 'heuristic',
            'candidates' => $candidates->take(6)->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{owner: ?User, partners: list<User>, note: ?string, message: string}
     */
    public function apply(SupportTicket $ticket, User $actor, array $payload): array
    {
        if (! $actor->hasPermission('tickets.assign')) {
            throw new AuthorizationException('Only assigners can apply AI case triage.');
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            throw new RuntimeException('Resolved or closed cases cannot be triaged.');
        }

        $ownerId = isset($payload['owner_id']) && $payload['owner_id'] !== '' && $payload['owner_id'] !== null
            ? (int) $payload['owner_id']
            : null;
        $partnerIds = collect($payload['partner_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $internalNote = trim((string) ($payload['internal_note'] ?? ''));
        $fillResolution = trim((string) ($payload['resolution_outline'] ?? ''));

        $owner = null;
        if ($ownerId) {
            $owner = User::query()->whereKey($ownerId)->where('status', 'active')->first();
            if (! $owner) {
                throw new RuntimeException('Suggested owner was not found or is inactive.');
            }
            if ((int) $owner->id === (int) $ticket->requester_id) {
                throw new RuntimeException('The requester cannot be assigned as the case owner.');
            }

            $this->tickets->assignTo(
                $ticket,
                $owner,
                $actor,
                $actor->name.' applied AI triage and assigned the case to '.$owner->name.'.'
            );
            $ticket = $ticket->fresh(['assignee', 'partners']);
        }

        $partners = [];
        foreach ($partnerIds as $partnerId) {
            if ($owner && (int) $partnerId === (int) $owner->id) {
                continue;
            }
            if ((int) $partnerId === (int) $ticket->requester_id) {
                continue;
            }
            $partner = User::query()->whereKey($partnerId)->where('status', 'active')->first();
            if (! $partner) {
                continue;
            }
            $this->notifications->syncPartners($ticket, [$partner->id], 'manual');
            $partners[] = $partner;
        }

        $note = null;
        if ($internalNote !== '') {
            $note = $this->tickets->comment($ticket, $actor, $internalNote, 'internal')->loadMissing(['author.roles:id,name']);
        }

        if ($fillResolution !== '' && blank($ticket->resolution_notes)) {
            $ticket->resolution_notes = $fillResolution;
            $ticket->save();
        }

        $parts = [];
        if ($owner) {
            $parts[] = 'Owner set to '.$owner->name;
        }
        if ($partners !== []) {
            $parts[] = 'Partners added: '.collect($partners)->pluck('name')->implode(', ');
        }
        if ($note) {
            $parts[] = 'Internal triage note posted';
        }

        $ticket = $ticket->fresh(['assignee', 'partners', 'histories.changer']);

        return [
            'owner' => $owner,
            'partners' => $partners,
            'note' => $note?->comment,
            'message' => $parts === [] ? 'No triage changes were applied.' : implode('. ', $parts).'.',
            'ticket' => $this->ticketSnapshot($ticket),
            'comment' => $note ? $this->commentSnapshot($note, $actor) : null,
            'history' => $this->latestHistorySnapshot($ticket),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSnapshot(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'status_label' => Labels::status((string) $ticket->status),
            'status_badge' => Labels::badge((string) $ticket->status),
            'assigned_to' => $ticket->assigned_to,
            'owner' => $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
                'url' => route('people.show', $ticket->assignee),
            ] : null,
            'partners' => $ticket->partners->map(fn (User $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'url' => route('people.show', $partner),
                'source' => $partner->pivot->source ?? 'manual',
            ])->values()->all(),
            'resolution_notes' => $ticket->resolution_notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentSnapshot($comment, User $actor): array
    {
        $author = $comment->author ?? $actor;

        return [
            'id' => $comment->id,
            'author' => $author?->name ?? $actor->name,
            'author_id' => $comment->user_id,
            'author_initials' => $author?->initials() ?? $actor->initials(),
            'author_role' => $author?->shortRoleLabel() ?? $actor->shortRoleLabel(),
            'comment' => $comment->comment,
            'html' => RichText::render($comment->comment),
            'visibility' => $comment->visibility,
            'is_internal' => $comment->visibility === 'internal',
            'created_at' => Format::date($comment->created_at),
            'updated_at' => Format::date($comment->updated_at),
            'edited' => false,
            'can_edit' => $actor->canEditTicketComment($comment),
            'can_delete' => $actor->canDeleteTicketComment($comment),
            'update_url' => route('helpdesk.comment.update', [$comment->support_ticket_id, $comment]),
            'delete_url' => route('helpdesk.comment.destroy', [$comment->support_ticket_id, $comment]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestHistorySnapshot(SupportTicket $ticket): ?array
    {
        $history = $ticket->histories->sortByDesc('created_at')->first();
        if (! $history) {
            return null;
        }

        return [
            'status' => $history->new_status,
            'status_badge' => Labels::badge((string) $history->new_status),
            'notes_html' => RichText::render((string) $history->notes),
            'created_at' => Format::date($history->created_at),
            'changer' => $history->changer?->name ?? 'System',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rankCandidates(SupportTicket $ticket): Collection
    {
        $staff = User::query()
            ->with(['roles:id,name', 'department:id,name,code'])
            ->where('status', 'active')
            ->where('id', '!=', $ticket->requester_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title', 'department_id', 'status', 'last_seen_at']);

        $openLoads = SupportTicket::query()
            ->whereIn('assigned_to', $staff->pluck('id'))
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get(['assigned_to'])
            ->groupBy('assigned_to')
            ->map->count();

        $categoryId = $ticket->ticket_category_id;
        $categoryHits = SupportTicket::query()
            ->whereIn('assigned_to', $staff->pluck('id'))
            ->where('ticket_category_id', $categoryId)
            ->whereIn('status', ['resolved', 'closed'])
            ->get(['assigned_to'])
            ->groupBy('assigned_to')
            ->map->count();

        $assetCategoryId = $ticket->asset?->asset_category_id;
        $assetHits = collect();
        if ($assetCategoryId) {
            $assetHits = SupportTicket::query()
                ->whereIn('assigned_to', $staff->pluck('id'))
                ->whereIn('status', ['resolved', 'closed'])
                ->whereHas('asset', fn ($assets) => $assets->where('asset_category_id', $assetCategoryId))
                ->get(['assigned_to'])
                ->groupBy('assigned_to')
                ->map->count();
        }

        $needles = $this->expertiseNeedles($ticket);

        return $staff
            ->map(function (User $user) use ($ticket, $openLoads, $categoryHits, $assetHits, $needles) {
                $roles = $user->roles->pluck('name')->filter()->values();
                $roleBoost = match (true) {
                    $roles->contains('ICT Technician') => 4.0,
                    $roles->contains('ICT Manager') => 3.0,
                    $roles->contains('System Administrator') => 2.0,
                    $roles->contains('Department Manager') => 1.5,
                    default => 0.5,
                };

                $online = $this->presence->isOnline($user);
                $openLoad = (int) ($openLoads[$user->id] ?? 0);
                $categoryXp = (int) ($categoryHits[$user->id] ?? 0);
                $assetXp = (int) ($assetHits[$user->id] ?? 0);
                $sameDepartment = $ticket->department_id && (int) $user->department_id === (int) $ticket->department_id;
                $title = Str::lower((string) ($user->job_title ?? ''));
                $keywordHits = collect($needles)->filter(fn ($needle) => $needle !== '' && str_contains($title, $needle))->count();

                $score = $roleBoost
                    + ($online ? 4.5 : 0)
                    + ($sameDepartment ? 2.5 : 0)
                    + min(5, $categoryXp * 1.2)
                    + min(3, $assetXp)
                    + min(2, $keywordHits)
                    - min(4, $openLoad * 0.6);

                // Prefer technicians for hands-on work when online or lightly loaded.
                if ($roles->contains('Department Manager') && ! $sameDepartment) {
                    $score -= 1.5;
                }

                $reasons = [];
                if ($online) {
                    $reasons[] = 'Online now';
                }
                if ($sameDepartment) {
                    $reasons[] = 'Same department as the case';
                }
                if ($categoryXp > 0) {
                    $reasons[] = $categoryXp.' prior '.$ticket->category?->name.' case'.($categoryXp === 1 ? '' : 's');
                }
                if ($assetXp > 0) {
                    $reasons[] = 'Experience with this asset type';
                }
                if ($keywordHits > 0 && $user->job_title) {
                    $reasons[] = 'Job title match: '.$user->job_title;
                }
                if ($openLoad === 0) {
                    $reasons[] = 'No open assigned cases';
                } elseif ($openLoad > 0) {
                    $reasons[] = $openLoad.' open assigned case'.($openLoad === 1 ? '' : 's');
                }
                $reasons[] = $roles->implode(' / ') ?: 'Staff';

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'role' => $roles->first() ?: $user->roleLabel(),
                    'roles' => $roles->all(),
                    'department' => $user->department?->name,
                    'online' => $online,
                    'open_load' => $openLoad,
                    'score' => round($score, 2),
                    'reasons' => $reasons,
                    'profile_url' => route('people.show', $user),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array{
     *     owner: ?array<string, mixed>,
     *     partners: list<array<string, mixed>>,
     *     next_steps: list<string>,
     *     resolution_outline: string,
     *     internal_note: string
     * }
     */
    private function buildHeuristicSuggestion(SupportTicket $ticket, Collection $candidates): array
    {
        $ownerable = $candidates->filter(function (array $candidate) {
            $roles = collect($candidate['roles'] ?? []);

            return $roles->contains('ICT Technician')
                || $roles->contains('ICT Manager')
                || $roles->contains('System Administrator');
        })->values();

        if ($ownerable->isEmpty()) {
            $ownerable = $candidates;
        }

        // Prefer an available technician; if nobody is online, still pick the best score.
        $owner = $ownerable->first(fn (array $candidate) => $candidate['online'])
            ?? $ownerable->first();

        $partners = $candidates
            ->reject(fn (array $candidate) => $owner && (int) $candidate['id'] === (int) $owner['id'])
            ->filter(function (array $candidate) use ($ticket) {
                $roles = collect($candidate['roles'] ?? []);
                if ($roles->contains('Department Manager') && $ticket->department_id) {
                    return true;
                }
                if ($roles->contains('ICT Manager') || $roles->contains('ICT Technician')) {
                    return true;
                }

                return ($candidate['score'] ?? 0) >= 4;
            })
            ->take(3)
            ->map(function (array $candidate) {
                return [
                    ...$candidate,
                    'reason' => implode(' · ', array_slice($candidate['reasons'] ?? [], 0, 2)),
                ];
            })
            ->values()
            ->all();

        $ownerPayload = $owner ? [
            ...$owner,
            'reason' => implode(' · ', array_slice($owner['reasons'] ?? [], 0, 3)),
        ] : null;

        $nextSteps = $this->defaultNextSteps($ticket, $ownerPayload, $partners);
        $resolution = $this->defaultResolutionOutline($ticket);
        $internalNote = $this->defaultInternalNote($ticket, $ownerPayload, $partners, $nextSteps);

        return [
            'owner' => $ownerPayload,
            'partners' => $partners,
            'suggested_status' => $this->defaultSuggestedStatus($ticket),
            'next_steps' => $nextSteps,
            'resolution_outline' => $resolution,
            'internal_note' => $internalNote,
        ];
    }

    private function defaultSuggestedStatus(SupportTicket $ticket): string
    {
        return match ($ticket->status) {
            'new' => 'assigned',
            'assigned' => 'in_progress',
            'in_progress' => 'pending',
            'pending' => 'in_progress',
            'resolved' => 'closed',
            'closed' => 'in_progress',
            default => 'in_progress',
        };
    }

    /**
     * @param  array<string, mixed>  $heuristic
     * @param  array<string, mixed>  $aiSuggestion
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array{
     *     owner: ?array<string, mixed>,
     *     partners: list<array<string, mixed>>,
     *     next_steps: list<string>,
     *     resolution_outline: string,
     *     internal_note: string
     * }
     */
    private function mergeAiSuggestion(SupportTicket $ticket, array $heuristic, array $aiSuggestion, Collection $candidates): array
    {
        $byId = $candidates->keyBy('id');

        $owner = $heuristic['owner'];
        $aiOwnerId = (int) ($aiSuggestion['owner_id'] ?? 0);
        if ($aiOwnerId && $byId->has($aiOwnerId)) {
            $owner = [
                ...$byId->get($aiOwnerId),
                'reason' => trim((string) ($aiSuggestion['owner_reason'] ?? '')) ?: ($byId->get($aiOwnerId)['reasons'][0] ?? 'AI recommended owner'),
            ];
        }

        $partners = [];
        foreach (($aiSuggestion['partners'] ?? []) as $row) {
            $id = (int) (is_array($row) ? ($row['user_id'] ?? $row['id'] ?? 0) : $row);
            if (! $id || ! $byId->has($id) || ($owner && (int) $owner['id'] === $id)) {
                continue;
            }
            $partners[] = [
                ...$byId->get($id),
                'reason' => trim((string) (is_array($row) ? ($row['reason'] ?? '') : '')) ?: implode(' · ', array_slice($byId->get($id)['reasons'] ?? [], 0, 2)),
            ];
        }
        if ($partners === []) {
            $partners = $heuristic['partners'];
        } else {
            $partners = array_slice($partners, 0, 3);
        }

        $nextSteps = collect($aiSuggestion['next_steps'] ?? [])
            ->map(fn ($step) => trim((string) $step))
            ->filter()
            ->take(6)
            ->values()
            ->all();
        if ($nextSteps === []) {
            $nextSteps = $heuristic['next_steps'];
        }

        $resolution = trim((string) ($aiSuggestion['resolution_outline'] ?? ''));
        if ($resolution === '') {
            $resolution = $heuristic['resolution_outline'];
        }

        $internalNote = trim((string) ($aiSuggestion['internal_note'] ?? ''));
        if ($internalNote === '') {
            $internalNote = $this->defaultInternalNote($ticket, $owner, $partners, $nextSteps);
        }

        $allowedStatuses = ['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'];
        $suggestedStatus = strtolower(trim((string) ($aiSuggestion['suggested_status'] ?? '')));
        if (! in_array($suggestedStatus, $allowedStatuses, true) || $suggestedStatus === $ticket->status) {
            $suggestedStatus = $heuristic['suggested_status'] ?? $this->defaultSuggestedStatus($ticket);
        }

        return [
            'owner' => $owner,
            'partners' => $partners,
            'suggested_status' => $suggestedStatus,
            'next_steps' => $nextSteps,
            'resolution_outline' => $resolution,
            'internal_note' => $internalNote,
        ];
    }

    /**
     * @return list<string>
     */
    private function expertiseNeedles(SupportTicket $ticket): array
    {
        $haystack = Str::lower(implode(' ', array_filter([
            $ticket->subject,
            $ticket->description,
            $ticket->category?->name,
            $ticket->asset?->name,
            $ticket->asset?->category?->name,
            $ticket->asset?->manufacturer,
            $ticket->asset?->model,
        ])));

        $map = [
            'network' => ['network', 'switch', 'wifi', 'wireless', 'vlan', 'firewall', 'access point'],
            'server' => ['server', 'vm', 'virtual', 'storage', 'backup', 'database'],
            'printer' => ['printer', 'label', 'barcode', 'toner', 'print'],
            'production' => ['production', 'plc', 'packaging', 'line', 'scada', 'sensor'],
            'endpoint' => ['laptop', 'desktop', 'pc', 'workstation', 'endpoint'],
            'security' => ['security', 'phishing', 'malware', 'access', 'account', 'password'],
        ];

        $needles = [];
        foreach ($map as $terms) {
            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $needles[] = $term;
                }
            }
        }

        return array_values(array_unique($needles));
    }

    /**
     * @param  ?array<string, mixed>  $owner
     * @param  list<array<string, mixed>>  $partners
     * @return list<string>
     */
    private function defaultNextSteps(SupportTicket $ticket, ?array $owner, array $partners): array
    {
        $steps = [
            'Confirm impact with '.$ticket->requester?->name.' and verify the affected asset/location.',
        ];

        if ($ticket->asset) {
            $steps[] = 'Inspect '.$ticket->asset->asset_tag.' ('.$ticket->asset->name.') and capture current status/symptoms.';
        } else {
            $steps[] = 'Identify the affected device or service and link the correct asset if known.';
        }

        $steps[] = 'Reproduce or validate the fault, then apply the least-disruptive fix for '.$ticket->category?->name.'.';

        if ($partners !== []) {
            $steps[] = 'Coordinate with '.collect($partners)->pluck('name')->implode(', ').' for department/vendor cover.';
        }

        $steps[] = ($owner['name'] ?? 'Owner').' posts progress in the internal note thread and updates status when work starts.';

        return $steps;
    }

    private function defaultResolutionOutline(SupportTicket $ticket): string
    {
        return 'Investigate '.Labels::status((string) $ticket->priority).' '.$ticket->category?->name
            .' issue "'.$ticket->subject.'"'
            .($ticket->asset ? ' on '.$ticket->asset->asset_tag : '')
            .'. Confirm root cause, restore service, and document the fix for the requester.';
    }

    /**
     * @param  ?array<string, mixed>  $owner
     * @param  list<array<string, mixed>>  $partners
     * @param  list<string>  $nextSteps
     */
    private function defaultInternalNote(SupportTicket $ticket, ?array $owner, array $partners, array $nextSteps): string
    {
        $lines = [
            'AI triage plan for '.$ticket->ticket_number.' ('.$ticket->subject.')',
            '',
            'Owner: '.($owner['name'] ?? 'Unassigned').($owner['reason'] ?? null ? ' - '.$owner['reason'] : ''),
        ];

        if ($partners !== []) {
            $lines[] = 'Partners:';
            foreach ($partners as $partner) {
                $lines[] = '- '.$partner['name'].(empty($partner['reason']) ? '' : ' - '.$partner['reason']);
            }
        } else {
            $lines[] = 'Partners: none suggested';
        }

        $lines[] = '';
        $lines[] = 'Next steps:';
        foreach ($nextSteps as $index => $step) {
            $lines[] = ($index + 1).'. '.$step;
        }

        $lines[] = '';
        $lines[] = 'Please update this case as work progresses and @mention partners if blockers appear.';

        return implode("\n", $lines);
    }
}
