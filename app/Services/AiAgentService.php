<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiAgentService
{
    public function __construct(private readonly TicketService $tickets) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function toolsFor(User $user): array
    {
        $tools = [];

        if ($user->hasPermission('tickets.create')) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'propose_create_case',
                    'description' => 'Propose opening a new PlantGrid helpdesk case. Use when the user asks to create, open, log, or raise a case/ticket. Not saved until the user confirms.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'subject' => ['type' => 'string', 'description' => 'Short case subject.'],
                            'description' => ['type' => 'string', 'description' => 'Case description.'],
                            'department' => ['type' => 'string', 'description' => 'Department name or code from the catalog.'],
                            'category' => ['type' => 'string', 'description' => 'Ticket category name from the catalog.'],
                            'priority' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low']],
                            'asset_tag' => ['type' => 'string', 'description' => 'Optional asset tag such as PG-LBL-0142.'],
                        ],
                        'required' => ['subject', 'description', 'department', 'category', 'priority'],
                    ],
                ],
            ];
        }

        if ($user->hasAnyPermission(['tickets.assign', 'tickets.update'])) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'propose_take_ownership',
                    'description' => 'Propose assigning an existing open case to the current user (Salesforce-style take ownership). Use when the user asks to take, accept, claim, or own a case.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ticket_number' => [
                                'type' => 'string',
                                'description' => 'Case number such as PG-2026-0148.',
                            ],
                        ],
                        'required' => ['ticket_number'],
                    ],
                ],
            ];
        }

        if ($user->hasPermission('tickets.close')) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'propose_close_duplicate',
                    'description' => 'Propose closing an open case as a duplicate of another surviving case. Not saved until the user confirms.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ticket_number' => [
                                'type' => 'string',
                                'description' => 'Case number to close as duplicate.',
                            ],
                            'duplicate_of' => [
                                'type' => 'string',
                                'description' => 'Surviving original case number.',
                            ],
                            'note' => [
                                'type' => 'string',
                                'description' => 'Optional closure note.',
                            ],
                        ],
                        'required' => ['ticket_number', 'duplicate_of'],
                    ],
                ],
            ];
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $assistantMessage
     * @return array{reply: string, proposal: ?array<string, mixed>}
     */
    public function handleToolCalls(User $user, array $assistantMessage, string $originalMessage): array
    {
        $calls = $assistantMessage['tool_calls'] ?? [];
        if (! is_array($calls) || $calls === []) {
            return [
                'reply' => trim((string) ($assistantMessage['content'] ?? 'I could not complete that request.')),
                'proposal' => null,
            ];
        }

        foreach ($calls as $call) {
            $name = data_get($call, 'function.name');
            $rawArgs = (string) data_get($call, 'function.arguments', '{}');
            $args = json_decode($rawArgs, true);
            if (! is_array($args)) {
                $args = [];
            }

            return match ($name) {
                'propose_create_case' => $this->proposeCreateCase($user, $args, $originalMessage),
                'propose_take_ownership' => $this->proposeTakeOwnership($user, $args, $originalMessage),
                'propose_close_duplicate' => $this->proposeCloseDuplicate($user, $args, $originalMessage),
                default => [
                    'reply' => 'That action is not available in this demo assistant yet.',
                    'proposal' => null,
                ],
            };
        }

        return [
            'reply' => 'That action is not available in this demo assistant yet.',
            'proposal' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{reply: string, proposal: ?array<string, mixed>}
     */
    public function proposeCreateCase(User $user, array $args, string $originalMessage = ''): array
    {
        if (! $user->hasPermission('tickets.create')) {
            return [
                'reply' => 'Your account cannot create cases. Ask an ICT manager or use an account with case-create access.',
                'proposal' => null,
            ];
        }

        $subject = trim((string) ($args['subject'] ?? ''));
        $description = trim((string) ($args['description'] ?? ''));
        $priority = strtolower(trim((string) ($args['priority'] ?? 'medium')));
        $departmentQuery = trim((string) ($args['department'] ?? ''));
        $categoryQuery = trim((string) ($args['category'] ?? ''));
        $assetTag = trim((string) ($args['asset_tag'] ?? ''));

        if ($subject === '' || $description === '') {
            return [
                'reply' => 'I need a subject and description before I can propose a case.',
                'proposal' => null,
            ];
        }

        if (! in_array($priority, ['critical', 'high', 'medium', 'low'], true)) {
            $priority = 'medium';
        }

        $department = $this->resolveDepartment($departmentQuery);
        if (! $department) {
            return [
                'reply' => 'I could not match department "'.$departmentQuery.'". Use a catalog department such as Production (PRD), ICT, Warehouse (WHS), Quality Assurance (QA), Maintenance (MNT), or Finance (FIN).',
                'proposal' => null,
            ];
        }

        if ($user->limitsRecordsToDepartment() && (int) $user->department_id !== (int) $department->id) {
            $own = $user->department?->name ?? 'your department';

            return [
                'reply' => 'Your account can only open cases for '.$own.'. Ask me to create it for that department, or use an ICT account.',
                'proposal' => null,
            ];
        }

        $category = $this->resolveCategory($categoryQuery);
        if (! $category) {
            return [
                'reply' => 'I could not match category "'.$categoryQuery.'". Pick one from the ticket category catalog in the live snapshot.',
                'proposal' => null,
            ];
        }

        $asset = null;
        if ($assetTag !== '') {
            $asset = Asset::query()
                ->visibleTo($user)
                ->where('asset_tag', $assetTag)
                ->first();
            if (! $asset) {
                return [
                    'reply' => 'I could not find asset tag '.$assetTag.' in the register visible to you.',
                    'proposal' => null,
                ];
            }
        }

        $payload = [
            'department_id' => $department->id,
            'ticket_category_id' => $category->id,
            'asset_id' => $asset?->id,
            'system_application_id' => null,
            'subject' => mb_substr($subject, 0, 180),
            'description' => mb_substr($description, 0, 5000),
            'priority' => $priority,
        ];

        return $this->storeProposal($user, 'create_case', $payload, $originalMessage, [
            'title' => 'Create case',
            'summary' => $payload['subject'],
            'confirm_label' => 'Confirm create',
            'reply' => 'I can open this case for '.$department->name.'. Review the details and confirm to create it.',
            'details' => array_values(array_filter([
                ['label' => 'Department', 'value' => $department->name.' ('.$department->code.')'],
                ['label' => 'Category', 'value' => $category->name],
                ['label' => 'Priority', 'value' => ucfirst($priority)],
                $asset ? ['label' => 'Asset', 'value' => $asset->asset_tag] : null,
                ['label' => 'Subject', 'value' => $payload['subject']],
                ['label' => 'Description', 'value' => $payload['description']],
            ])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{reply: string, proposal: ?array<string, mixed>}
     */
    public function proposeTakeOwnership(User $user, array $args, string $originalMessage = ''): array
    {
        if (! $user->hasAnyPermission(['tickets.assign', 'tickets.update'])) {
            return [
                'reply' => 'Your account cannot take ownership of cases.',
                'proposal' => null,
            ];
        }

        $ticket = $this->findVisibleTicket($user, $args['ticket_number'] ?? null);
        if (! $ticket) {
            return [
                'reply' => 'I could not find that case in your visible queue.',
                'proposal' => null,
            ];
        }

        if (! $this->tickets->canTakeOwnership($ticket, $user)) {
            return [
                'reply' => $ticket->assigned_to === $user->id
                    ? 'You already own '.$ticket->ticket_number.'.'
                    : 'You cannot take ownership of '.$ticket->ticket_number.' in its current state.',
                'proposal' => null,
            ];
        }

        return $this->storeProposal($user, 'take_ownership', [
            'ticket_id' => $ticket->id,
        ], $originalMessage, [
            'title' => 'Take ownership',
            'summary' => $ticket->ticket_number.' · '.$ticket->subject,
            'confirm_label' => 'Confirm take ownership',
            'reply' => 'I can assign '.$ticket->ticket_number.' to you. Confirm to take ownership.',
            'details' => [
                ['label' => 'Case', 'value' => $ticket->ticket_number],
                ['label' => 'Subject', 'value' => $ticket->subject],
                ['label' => 'Current owner', 'value' => $ticket->assignee?->name ?? 'Unassigned'],
                ['label' => 'New owner', 'value' => $user->name],
                ['label' => 'Status', 'value' => $ticket->status === 'new' ? 'new → assigned' : $ticket->status],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{reply: string, proposal: ?array<string, mixed>}
     */
    public function proposeCloseDuplicate(User $user, array $args, string $originalMessage = ''): array
    {
        if (! $user->hasPermission('tickets.close')) {
            return [
                'reply' => 'Your account cannot close cases as duplicates.',
                'proposal' => null,
            ];
        }

        $ticket = $this->findVisibleTicket($user, $args['ticket_number'] ?? null);
        if (! $ticket) {
            return [
                'reply' => 'I could not find the case to close in your visible queue.',
                'proposal' => null,
            ];
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return [
                'reply' => $ticket->ticket_number.' is already '.$ticket->status.'.',
                'proposal' => null,
            ];
        }

        try {
            $original = $this->tickets->resolveDuplicateOriginal($ticket, $args['duplicate_of'] ?? null);
        } catch (ValidationException $exception) {
            return [
                'reply' => collect($exception->errors())->flatten()->first() ?: 'Invalid duplicate reference.',
                'proposal' => null,
            ];
        }

        $note = trim((string) ($args['note'] ?? ''));

        return $this->storeProposal($user, 'close_duplicate', [
            'ticket_id' => $ticket->id,
            'duplicate_of' => $original->ticket_number,
            'note' => $note !== '' ? $note : null,
            'resolution_notes' => $note !== '' ? $note : null,
        ], $originalMessage, [
            'title' => 'Close as duplicate',
            'summary' => $ticket->ticket_number.' → '.$original->ticket_number,
            'confirm_label' => 'Confirm close duplicate',
            'reply' => 'I can close '.$ticket->ticket_number.' as a duplicate of '.$original->ticket_number.'. Confirm to proceed.',
            'details' => array_values(array_filter([
                ['label' => 'Close', 'value' => $ticket->ticket_number.' · '.$ticket->subject],
                ['label' => 'Keep', 'value' => $original->ticket_number.' · '.$original->subject],
                $note !== '' ? ['label' => 'Note', 'value' => $note] : null,
            ])),
        ]);
    }

    /**
     * @return array{reply: string, ticket?: array<string, mixed>}
     */
    public function confirm(User $user, string $proposalId): array
    {
        $cached = Cache::pull($this->cacheKey($user, $proposalId));
        if (! is_array($cached) || empty($cached['action'])) {
            throw new RuntimeException('That proposed action expired or was already used. Ask me again.');
        }

        $payload = $cached['payload'] ?? [];
        if (! is_array($payload)) {
            throw new RuntimeException('The proposed action payload is invalid.');
        }

        return match ($cached['action']) {
            'create_case' => $this->confirmCreateCase($user, $payload),
            'take_ownership' => $this->confirmTakeOwnership($user, $payload),
            'close_duplicate' => $this->confirmCloseDuplicate($user, $payload),
            default => throw new RuntimeException('Unknown proposed action.'),
        };
    }

    public function cancel(User $user, string $proposalId): void
    {
        Cache::forget($this->cacheKey($user, $proposalId));
    }

    public function catalogSnippet(): string
    {
        $departments = Department::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['name', 'code']);
        $categories = TicketCategory::query()->orderBy('name')->pluck('name');

        return implode("\n", [
            'AGENT TOOLS:',
            '- propose_create_case: open a new case (needs confirm).',
            '- propose_take_ownership: assign an open case to the current user (needs confirm).',
            '- propose_close_duplicate: close a case as duplicate of another (needs confirm).',
            'Departments: '.$departments->map(fn (Department $d) => $d->name.' ['.$d->code.']')->implode('; '),
            'Aliases: Packaging/Packaging Hall → Production/PRD; Warehouse → WHS; QA/Quality → Quality Assurance/QA; IT/ICT → ICT.',
            'Categories: '.$categories->implode('; '),
            'Priorities: critical, high, medium, low.',
            'Never invent ticket numbers. Actions only run after the user confirms the proposal card.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{title: string, summary: string, confirm_label: string, reply: string, details: list<array{label: string, value: string}>}  $card
     * @return array{reply: string, proposal: array<string, mixed>}
     */
    private function storeProposal(User $user, string $action, array $payload, string $originalMessage, array $card): array
    {
        $proposalId = (string) Str::uuid();
        Cache::put($this->cacheKey($user, $proposalId), [
            'action' => $action,
            'payload' => $payload,
            'created_at' => now()->toIso8601String(),
            'source_message' => mb_substr($originalMessage, 0, 500),
        ], now()->addMinutes(20));

        return [
            'reply' => $card['reply'],
            'proposal' => [
                'id' => $proposalId,
                'action' => $action,
                'title' => $card['title'],
                'summary' => $card['summary'],
                'confirm_label' => $card['confirm_label'],
                'details' => $card['details'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{reply: string, ticket: array<string, mixed>}
     */
    private function confirmCreateCase(User $user, array $payload): array
    {
        if (! $user->hasPermission('tickets.create')) {
            throw new RuntimeException('Your account cannot create cases.');
        }

        if ($user->limitsRecordsToDepartment()) {
            $payload['department_id'] = $user->department_id;
        }

        if (! empty($payload['asset_id'])) {
            $allowed = Asset::query()->visibleTo($user)->whereKey($payload['asset_id'])->exists();
            if (! $allowed) {
                throw new RuntimeException('The linked asset is not available to your account.');
            }
        }

        $ticket = $this->tickets->create($user, $payload)->load(['department', 'category']);

        return [
            'reply' => 'Created '.$ticket->ticket_number.' for '.($ticket->department?->name ?? 'the selected department').'.',
            'ticket' => $this->ticketPayload($ticket),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{reply: string, ticket: array<string, mixed>}
     */
    private function confirmTakeOwnership(User $user, array $payload): array
    {
        $ticket = SupportTicket::query()->visibleTo($user)->whereKey($payload['ticket_id'] ?? 0)->first();
        if (! $ticket) {
            throw new RuntimeException('That case is no longer available.');
        }

        $ticket = $this->tickets->takeOwnership($ticket, $user);

        return [
            'reply' => 'You now own '.$ticket->ticket_number.'.',
            'ticket' => $this->ticketPayload($ticket),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{reply: string, ticket: array<string, mixed>}
     */
    private function confirmCloseDuplicate(User $user, array $payload): array
    {
        if (! $user->hasPermission('tickets.close')) {
            throw new RuntimeException('Your account cannot close cases.');
        }

        $ticket = SupportTicket::query()->visibleTo($user)->whereKey($payload['ticket_id'] ?? 0)->first();
        if (! $ticket) {
            throw new RuntimeException('That case is no longer available.');
        }

        $ticket = $this->tickets->transition($ticket, $user, 'closed', [
            'duplicate_of' => $payload['duplicate_of'] ?? null,
            'note' => $payload['note'] ?? null,
            'resolution_notes' => $payload['resolution_notes'] ?? null,
        ])->load(['department', 'category', 'duplicateOf']);

        return [
            'reply' => 'Closed '.$ticket->ticket_number.' as a duplicate of '.($ticket->duplicateOf?->ticket_number ?? 'the original case').'.',
            'ticket' => $this->ticketPayload($ticket),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'department' => $ticket->department?->name,
            'url' => route('helpdesk.show', $ticket),
        ];
    }

    private function findVisibleTicket(User $user, mixed $reference): ?SupportTicket
    {
        $value = trim((string) $reference);
        if ($value === '') {
            return null;
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->with(['assignee', 'department'])
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('ticket_number', $value)
            )
            ->first();
    }

    private function cacheKey(User $user, string $proposalId): string
    {
        return 'ai.proposal.'.$user->id.'.'.$proposalId;
    }

    private function resolveDepartment(string $query): ?Department
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $normalized = Str::lower($query);
        $aliases = [
            'packaging' => 'prd',
            'packaging hall' => 'prd',
            'packing' => 'prd',
            'production' => 'prd',
            'warehouse' => 'whs',
            'stores' => 'whs',
            'quality' => 'qa',
            'quality assurance' => 'qa',
            'it' => 'ict',
            'information technology' => 'ict',
            'maintenance' => 'mnt',
            'finance' => 'fin',
            'admin' => 'fin',
        ];

        if (isset($aliases[$normalized])) {
            $byCode = Department::query()->whereRaw('LOWER(code) = ?', [$aliases[$normalized]])->first();
            if ($byCode) {
                return $byCode;
            }
        }

        $exactCode = Department::query()->whereRaw('LOWER(code) = ?', [$normalized])->first();
        if ($exactCode) {
            return $exactCode;
        }

        $exactName = Department::query()->whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($exactName) {
            return $exactName;
        }

        return Department::query()
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(name) like ?', ['%'.$normalized.'%'])
                    ->orWhereRaw('LOWER(code) like ?', ['%'.$normalized.'%']);
            })
            ->orderBy('name')
            ->first();
    }

    private function resolveCategory(string $query): ?TicketCategory
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $normalized = Str::lower($query);
        $exact = TicketCategory::query()->whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($exact) {
            return $exact;
        }

        $partial = TicketCategory::query()
            ->whereRaw('LOWER(name) like ?', ['%'.$normalized.'%'])
            ->orderBy('name')
            ->get();

        if ($partial->count() === 1) {
            return $partial->first();
        }

        $hints = [
            'printer' => 'Printer or scanner',
            'scanner' => 'Printer or scanner',
            'label' => 'Production technology',
            'network' => 'Network connectivity',
            'wifi' => 'Network connectivity',
            'vpn' => 'Network connectivity',
            'server' => 'Server and infrastructure',
            'security' => 'Security',
            'erp' => 'ERP and business application',
            'production' => 'Production technology',
        ];

        foreach ($hints as $needle => $name) {
            if (str_contains($normalized, $needle)) {
                $hit = TicketCategory::query()->where('name', $name)->first();
                if ($hit) {
                    return $hit;
                }
            }
        }

        return $partial->first();
    }
}
