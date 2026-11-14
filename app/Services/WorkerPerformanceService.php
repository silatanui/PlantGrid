<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkerPerformanceService
{
    public const OPEN_OVERLOAD_THRESHOLD = 5;

    public const WORKER_PERMISSIONS = ['tickets.update', 'tickets.resolve', 'tickets.assign'];

    public const WORKER_ROLE_NAMES = [
        'System Administrator',
        'ICT Manager',
        'ICT Technician',
    ];

    /**
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     days: float,
     *     months: float,
     *     filters: array{department_id:?int, role:?string, user_id:?int, sort:string},
     *     departments: Collection,
     *     roles: Collection,
     *     workers: Collection,
     *     rows: Collection,
     *     team: array<string, mixed>,
     *     selected: ?array,
     *     open_queue: Collection,
     *     trend: Collection
     * }
     */
    public function board(array $input): array
    {
        $from = Carbon::parse($input['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($input['to'] ?? now()->toDateString())->endOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = max($from->diffInDays($to) + 1, 1);
        $months = max($days / 30.437, 1 / 30.437);

        $filters = [
            'department_id' => isset($input['department_id']) && $input['department_id'] !== ''
                ? (int) $input['department_id']
                : null,
            'role' => filled($input['role'] ?? null) ? (string) $input['role'] : null,
            'user_id' => isset($input['user_id']) && $input['user_id'] !== ''
                ? (int) $input['user_id']
                : null,
            'sort' => in_array($input['sort'] ?? '', ['cpm', 'open', 'resolved', 'sla', 'avg_hours', 'name'], true)
                ? (string) $input['sort']
                : 'cpm',
        ];

        $workers = $this->workers($filters);
        $ticketBags = $this->ticketBags($workers->pluck('id')->all(), $from, $to);
        $reopenCounts = $this->reopenCounts($workers->pluck('id')->all(), $from, $to);
        $noteCounts = $this->noteCounts($workers->pluck('id')->all(), $from, $to);
        $firstResponseHours = $this->firstResponseHours($workers->pluck('id')->all(), $from, $to);

        $rows = $workers->map(fn (User $worker) => $this->rowForWorker(
            $worker,
            $ticketBags,
            $reopenCounts,
            $noteCounts,
            $firstResponseHours,
            $months,
            $days
        ));

        $rows = $this->sortRows($rows, $filters['sort']);

        $teamResolved = $rows->sum('resolved');
        $slaWeighted = $rows
            ->filter(fn (array $row) => $row['sla_rate'] !== null && $row['resolved'] > 0)
            ->map(fn (array $row) => $row['sla_rate'] * $row['resolved']);
        $slaWeightTotal = $rows
            ->filter(fn (array $row) => $row['sla_rate'] !== null && $row['resolved'] > 0)
            ->sum('resolved');

        $unassigned = SupportTicket::query()
            ->open()
            ->whereNull('assigned_to')
            ->get(['id', 'reported_at']);

        $team = [
            'workers' => $rows->count(),
            'open' => $rows->sum('open'),
            'resolved' => $teamResolved,
            'cpm' => round($teamResolved / $months, 2),
            'sla_rate' => $slaWeightTotal > 0
                ? round($slaWeighted->sum() / $slaWeightTotal, 1)
                : null,
            'breaches' => $rows->sum('breaches'),
            'unassigned' => $unassigned->count(),
            'unassigned_age_hours' => $unassigned
                ->filter(fn (SupportTicket $ticket) => $ticket->reported_at)
                ->map(fn (SupportTicket $ticket) => $ticket->reported_at->diffInMinutes(now()) / 60)
                ->avg(),
            'overloaded' => $rows->where('overloaded', true)->values(),
            'threshold' => self::OPEN_OVERLOAD_THRESHOLD,
        ];
        $team['unassigned_age_hours'] = $team['unassigned_age_hours'] === null
            ? null
            : round((float) $team['unassigned_age_hours'], 1);

        $selectedRow = null;
        $openQueue = collect();
        $trend = collect();

        if ($filters['user_id']) {
            $selectedRow = $rows->firstWhere('user_id', $filters['user_id']);
            if ($selectedRow) {
                $priorityRank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
                $openQueue = SupportTicket::query()
                    ->with(['department', 'category'])
                    ->where('assigned_to', $filters['user_id'])
                    ->open()
                    ->orderBy('reported_at')
                    ->get()
                    ->sortBy(fn (SupportTicket $ticket) => [
                        $priorityRank[$ticket->priority] ?? 9,
                        optional($ticket->reported_at)?->timestamp ?? 0,
                    ])
                    ->values();
                $trend = $this->monthlyTrend($filters['user_id']);
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'months' => round($months, 2),
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => Role::query()->whereIn('name', self::WORKER_ROLE_NAMES)->orderBy('name')->get(['id', 'name']),
            'workers' => $workers->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->values(),
            'rows' => $rows->values(),
            'team' => $team,
            'selected' => $selectedRow,
            'open_queue' => $openQueue,
            'trend' => $trend,
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function exportRows(array $board): array
    {
        return collect($board['rows'])->map(fn (array $row) => [
            $row['name'],
            $row['role'],
            $row['department'],
            $row['open'],
            $row['intake'],
            $row['resolved'],
            $row['cpm'],
            $row['cpd'],
            $row['avg_hours'],
            $row['median_hours'],
            $row['first_response_hours'],
            $row['sla_rate'],
            $row['breaches'],
            $row['reopen_rate'],
            $row['backlog_age_hours'],
            $row['notes_per_case'],
            $row['critical_share'],
        ])->all();
    }

    public function exportHeaders(): array
    {
        return [
            'Worker', 'Role', 'Department', 'Open', 'Intake', 'Resolved',
            'CPM', 'CPD', 'Avg hours', 'Median hours', 'First response hours',
            'SLA %', 'Breaches', 'Reopen %', 'Backlog age hours', 'Notes / case', 'Critical/high %',
        ];
    }

    /**
     * Chart.js configs for the performance board.
     *
     * @return list<array{title: string, caption: string, height: int, config: array<string, mixed>}>
     */
    public function charts(array $board): array
    {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($board['rows'])->values();
        $charts = [];

        if ($rows->isEmpty()) {
            return $charts;
        }

        $labels = $rows->map(fn (array $row) => $this->shortName((string) $row['name']))->all();
        $height = max(220, min(420, 64 + ($rows->count() * 34)));

        $charts[] = [
            'title' => 'Cases per month',
            'caption' => 'Throughput by worker for the selected range.',
            'height' => $height,
            'config' => $this->horizontalBar($labels, [[
                'label' => 'CPM',
                'data' => $rows->map(fn (array $row) => (float) $row['cpm'])->all(),
                'backgroundColor' => 'pg-accent',
                'borderWidth' => 0,
                'borderRadius' => 2,
                'barThickness' => 16,
            ]], [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(90, 103, 115, 0.12)'],
                    'ticks' => ['precision' => 1],
                ],
            ]),
        ];

        $charts[] = [
            'title' => 'Open vs resolved',
            'caption' => 'Current open load against resolved cases in range.',
            'height' => $height,
            'config' => $this->horizontalBar($labels, [
                [
                    'label' => 'Open',
                    'data' => $rows->map(fn (array $row) => (int) $row['open'])->all(),
                    'backgroundColor' => 'pg-warning',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                    'barThickness' => 12,
                ],
                [
                    'label' => 'Resolved',
                    'data' => $rows->map(fn (array $row) => (int) $row['resolved'])->all(),
                    'backgroundColor' => 'pg-accent',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                    'barThickness' => 12,
                ],
            ], [
                'x' => [
                    'beginAtZero' => true,
                    'stacked' => false,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(90, 103, 115, 0.12)'],
                ],
            ], true),
        ];

        $charts[] = [
            'title' => 'SLA hit rate',
            'caption' => 'Share of resolved cases finished on time. Target guide: 85%.',
            'height' => $height,
            'config' => $this->horizontalBar($labels, [[
                'label' => 'SLA %',
                'data' => $rows->map(fn (array $row) => $row['sla_rate'] ?? 0)->all(),
                'backgroundColor' => $rows->map(function (array $row) {
                    $rate = $row['sla_rate'];
                    if ($rate === null) {
                        return 'pg-muted';
                    }

                    return $rate < 85 ? 'pg-warning' : 'pg-success';
                })->all(),
                'borderWidth' => 0,
                'borderRadius' => 2,
                'barThickness' => 16,
            ]], [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => '__pct',
                        'stepSize' => 25,
                    ],
                    'grid' => ['color' => 'rgba(90, 103, 115, 0.12)'],
                ],
            ]),
        ];

        if (! empty($board['selected']) && collect($board['trend'])->isNotEmpty()) {
            $trend = collect($board['trend']);
            $charts[] = [
                'title' => 'Resolved trend · '.$board['selected']['name'],
                'caption' => 'Monthly resolved cases over the last six months.',
                'height' => 240,
                'config' => [
                    'type' => 'bar',
                    'data' => [
                        'labels' => $trend->pluck('label')->all(),
                        'datasets' => [[
                            'label' => 'Resolved',
                            'data' => $trend->pluck('resolved')->all(),
                            'backgroundColor' => 'pg-accent-soft',
                            'borderColor' => 'pg-accent',
                            'borderWidth' => 2,
                            'borderRadius' => 3,
                            'barPercentage' => 0.65,
                        ]],
                    ],
                    'options' => [
                        'plugins' => [
                            'legend' => ['display' => false],
                            'tooltip' => ['mode' => 'index', 'intersect' => false],
                        ],
                        'scales' => [
                            'y' => [
                                'beginAtZero' => true,
                                'ticks' => ['precision' => 0],
                                'grid' => ['color' => 'rgba(90, 103, 115, 0.12)'],
                            ],
                            'x' => [
                                'grid' => ['display' => false],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $charts;
    }

    /**
     * @return list<array{id: string, label: string, from: string, to: string}>
     */
    public function rangePresets(?Carbon $now = null): array
    {
        $now ??= now();

        return [
            ['id' => '7d', 'label' => '7 days', 'from' => $now->copy()->subDays(6)->toDateString(), 'to' => $now->toDateString()],
            ['id' => '30d', 'label' => '30 days', 'from' => $now->copy()->subDays(29)->toDateString(), 'to' => $now->toDateString()],
            ['id' => '90d', 'label' => '90 days', 'from' => $now->copy()->subDays(89)->toDateString(), 'to' => $now->toDateString()],
            ['id' => 'month', 'label' => 'This month', 'from' => $now->copy()->startOfMonth()->toDateString(), 'to' => $now->toDateString()],
            ['id' => 'quarter', 'label' => 'This quarter', 'from' => $now->copy()->firstOfQuarter()->toDateString(), 'to' => $now->toDateString()],
        ];
    }

    /**
     * Person-scoped workload snapshot for any contact (not only the ICT leaderboard).
     *
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     days: float,
     *     months: float,
     *     selected: array<string, mixed>,
     *     open_queue: Collection,
     *     trend: Collection,
     *     notes_count: int
     * }
     */
    public function forPerson(User $person, array $input = []): array
    {
        $from = Carbon::parse($input['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($input['to'] ?? now()->toDateString())->endOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = max($from->diffInDays($to) + 1, 1);
        $months = max($days / 30.437, 1 / 30.437);

        $person->loadMissing(['roles', 'department']);
        $userIds = [$person->id];
        $ticketBags = $this->ticketBags($userIds, $from, $to);
        $reopenCounts = $this->reopenCounts($userIds, $from, $to);
        $noteCounts = $this->noteCounts($userIds, $from, $to);
        $firstResponseHours = $this->firstResponseHours($userIds, $from, $to);

        $selected = $this->rowForWorker(
            $person,
            $ticketBags,
            $reopenCounts,
            $noteCounts,
            $firstResponseHours,
            $months,
            $days
        );

        $priorityRank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $openQueue = SupportTicket::query()
            ->with(['department', 'category'])
            ->where('assigned_to', $person->id)
            ->open()
            ->orderBy('reported_at')
            ->get()
            ->sortBy(fn (SupportTicket $ticket) => [
                $priorityRank[$ticket->priority] ?? 9,
                optional($ticket->reported_at)?->timestamp ?? 0,
            ])
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'months' => $months,
            'selected' => $selected,
            'open_queue' => $openQueue,
            'trend' => $this->monthlyTrend($person->id),
            'notes_count' => (int) ($noteCounts[$person->id] ?? 0),
        ];
    }

    /**
     * @param  array<int, array{open: Collection, resolved: Collection, intake: Collection}>  $ticketBags
     * @param  array<int, int>  $reopenCounts
     * @param  array<int, int>  $noteCounts
     * @param  array<int, float>  $firstResponseHours
     * @return array<string, mixed>
     */
    private function rowForWorker(
        User $worker,
        array $ticketBags,
        array $reopenCounts,
        array $noteCounts,
        array $firstResponseHours,
        float $months,
        float $days
    ): array {
        $bag = $ticketBags[$worker->id] ?? [
            'open' => collect(),
            'resolved' => collect(),
            'intake' => collect(),
        ];

        /** @var Collection<int, SupportTicket> $open */
        $open = $bag['open'];
        /** @var Collection<int, SupportTicket> $resolved */
        $resolved = $bag['resolved'];
        /** @var Collection<int, SupportTicket> $intake */
        $intake = $bag['intake'];

        $resolutionHours = $resolved
            ->filter(fn (SupportTicket $ticket) => $ticket->reported_at && $ticket->resolved_at)
            ->map(fn (SupportTicket $ticket) => $ticket->reported_at->diffInMinutes($ticket->resolved_at) / 60)
            ->values();

        $slaEligible = $resolved->filter(fn (SupportTicket $ticket) => $ticket->sla_due_at !== null);
        $slaHits = $slaEligible->filter(function (SupportTicket $ticket) {
            if ($ticket->sla_breached_at) {
                return false;
            }

            return $ticket->resolved_at && $ticket->resolved_at->lessThanOrEqualTo($ticket->sla_due_at);
        })->count();

        $breaches = $resolved->filter(fn (SupportTicket $ticket) => $ticket->sla_breached_at !== null)->count()
            + $open->filter(fn (SupportTicket $ticket) => $ticket->sla_breached_at !== null)->count();

        $resolvedCount = $resolved->count();
        $openCount = $open->count();
        $reopens = (int) ($reopenCounts[$worker->id] ?? 0);
        $notes = (int) ($noteCounts[$worker->id] ?? 0);
        $criticalResolved = $resolved->whereIn('priority', ['critical', 'high'])->count();

        $avgHours = $resolutionHours->avg();
        $medianHours = $this->median($resolutionHours->all());
        $backlogAgeHours = $open
            ->filter(fn (SupportTicket $ticket) => $ticket->reported_at)
            ->map(fn (SupportTicket $ticket) => $ticket->reported_at->diffInMinutes(now()) / 60)
            ->avg();

        $slaRate = $slaEligible->isEmpty()
            ? null
            : round(($slaHits / $slaEligible->count()) * 100, 1);

        $roleName = $worker->roles->pluck('name')->first() ?? '-';

        return [
            'user_id' => $worker->id,
            'name' => $worker->name,
            'email' => $worker->email,
            'role' => $roleName,
            'department' => $worker->department->name ?? '-',
            'open' => $openCount,
            'intake' => $intake->count(),
            'resolved' => $resolvedCount,
            'cpm' => round($resolvedCount / $months, 2),
            'cpd' => round($resolvedCount / $days, 2),
            'avg_hours' => $avgHours === null ? null : round((float) $avgHours, 1),
            'median_hours' => $medianHours === null ? null : round((float) $medianHours, 1),
            'first_response_hours' => isset($firstResponseHours[$worker->id])
                ? round((float) $firstResponseHours[$worker->id], 1)
                : null,
            'sla_rate' => $slaRate,
            'breaches' => $breaches,
            'reopen_rate' => $resolvedCount === 0
                ? ($reopens > 0 ? 100.0 : 0.0)
                : round(($reopens / $resolvedCount) * 100, 1),
            'reopens' => $reopens,
            'backlog_age_hours' => $backlogAgeHours === null ? null : round((float) $backlogAgeHours, 1),
            'notes_per_case' => $resolvedCount === 0
                ? null
                : round($notes / $resolvedCount, 1),
            'notes' => $notes,
            'critical_share' => $resolvedCount === 0
                ? null
                : round(($criticalResolved / $resolvedCount) * 100, 1),
            'overloaded' => $openCount > self::OPEN_OVERLOAD_THRESHOLD,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array<string, mixed>>  $datasets
     * @param  array<string, mixed>  $xScale
     * @return array<string, mixed>
     */
    private function horizontalBar(array $labels, array $datasets, array $xScale, bool $legend = false): array
    {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => [
                'indexAxis' => 'y',
                'plugins' => [
                    'legend' => [
                        'display' => $legend,
                        'position' => 'bottom',
                        'labels' => ['boxWidth' => 10, 'boxHeight' => 10],
                    ],
                    'tooltip' => ['mode' => 'nearest', 'intersect' => true],
                ],
                'scales' => [
                    'x' => $xScale,
                    'y' => [
                        'grid' => ['display' => false],
                        'ticks' => ['autoSkip' => false],
                    ],
                ],
            ],
        ];
    }

    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return $name;
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1];

        return $first.' '.mb_substr($last, 0, 1).'.';
    }

    /**
     * @param  array{department_id:?int, role:?string, user_id:?int, sort:string}  $filters
     * @return Collection<int, User>
     */
    private function workers(array $filters): Collection
    {
        $query = User::query()
            ->with(['roles', 'department'])
            ->where(function ($outer) {
                $outer->whereHas('roles.permissions', function ($permissions) {
                    $permissions->whereIn('name', self::WORKER_PERMISSIONS);
                })->orWhereHas('assignedTickets');
            })
            ->orderBy('name');

        if ($filters['department_id']) {
            $query->where('department_id', $filters['department_id']);
        }

        if ($filters['role']) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $filters['role']));
        } else {
            $query->where(function ($outer) {
                $outer->whereHas('roles', fn ($roles) => $roles->whereIn('name', self::WORKER_ROLE_NAMES))
                    ->orWhereHas('assignedTickets');
            });
        }

        if ($filters['user_id']) {
            // Keep full leaderboard when drilling into a person.
        }

        return $query->get()->unique('id')->values();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{open: Collection, resolved: Collection, intake: Collection}>
     */
    private function ticketBags(array $userIds, Carbon $from, Carbon $to): array
    {
        $bags = [];
        foreach ($userIds as $id) {
            $bags[$id] = [
                'open' => collect(),
                'resolved' => collect(),
                'intake' => collect(),
            ];
        }

        if ($userIds === []) {
            return $bags;
        }

        SupportTicket::query()
            ->whereIn('assigned_to', $userIds)
            ->get()
            ->each(function (SupportTicket $ticket) use (&$bags, $from, $to) {
                $id = (int) $ticket->assigned_to;
                if (! isset($bags[$id])) {
                    return;
                }

                if (! in_array($ticket->status, ['resolved', 'closed'], true)) {
                    $bags[$id]['open']->push($ticket);
                }

                if ($ticket->resolved_at && $ticket->resolved_at->between($from, $to)) {
                    $bags[$id]['resolved']->push($ticket);
                }

                if ($ticket->reported_at && $ticket->reported_at->between($from, $to)) {
                    $bags[$id]['intake']->push($ticket);
                }
            });

        return $bags;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function reopenCounts(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = TicketStatusHistory::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('old_status', ['resolved', 'closed'])
            ->whereNotIn('new_status', ['resolved', 'closed'])
            ->whereHas('ticket', fn ($tickets) => $tickets->whereIn('assigned_to', $userIds))
            ->with('ticket:id,assigned_to')
            ->get();

        $counts = array_fill_keys($userIds, 0);
        foreach ($rows as $row) {
            $assignee = (int) ($row->ticket->assigned_to ?? 0);
            if (isset($counts[$assignee])) {
                $counts[$assignee]++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function noteCounts(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        return TicketComment::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * Average first-response hours (assignee's first note after report) for tickets resolved in range.
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    private function firstResponseHours(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $tickets = SupportTicket::query()
            ->whereIn('assigned_to', $userIds)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->whereNotNull('reported_at')
            ->get(['id', 'assigned_to', 'reported_at']);

        if ($tickets->isEmpty()) {
            return [];
        }

        $comments = TicketComment::query()
            ->whereIn('support_ticket_id', $tickets->pluck('id'))
            ->whereIn('user_id', $userIds)
            ->orderBy('created_at')
            ->get(['support_ticket_id', 'user_id', 'created_at']);

        $hours = [];
        $samples = [];

        foreach ($tickets as $ticket) {
            $first = $comments->first(function (TicketComment $comment) use ($ticket) {
                return (int) $comment->support_ticket_id === (int) $ticket->id
                    && (int) $comment->user_id === (int) $ticket->assigned_to
                    && $comment->created_at
                    && $comment->created_at->greaterThanOrEqualTo($ticket->reported_at);
            });

            if (! $first) {
                continue;
            }

            $assignee = (int) $ticket->assigned_to;
            $samples[$assignee][] = $ticket->reported_at->diffInMinutes($first->created_at) / 60;
        }

        foreach ($samples as $assignee => $values) {
            $hours[$assignee] = array_sum($values) / max(count($values), 1);
        }

        return $hours;
    }

    /**
     * @return Collection<int, array{label: string, resolved: int}>
     */
    private function monthlyTrend(int $userId): Collection
    {
        $start = now()->startOfMonth()->subMonths(5);

        $resolved = SupportTicket::query()
            ->where('assigned_to', $userId)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $start)
            ->get(['resolved_at']);

        $buckets = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $buckets[$key] = [
                'label' => $month->format('M Y'),
                'resolved' => 0,
            ];
        }

        foreach ($resolved as $ticket) {
            $key = $ticket->resolved_at->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['resolved']++;
            }
        }

        return collect(array_values($buckets));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'open' => $rows->sortByDesc('open'),
            'resolved' => $rows->sortByDesc('resolved'),
            'sla' => $rows->sortByDesc(fn (array $row) => $row['sla_rate'] ?? -1),
            'avg_hours' => $rows->sortBy(fn (array $row) => $row['avg_hours'] ?? PHP_FLOAT_MAX),
            'name' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            default => $rows->sortByDesc('cpm'),
        };
    }

    /**
     * @param  list<float|int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$mid];
        }

        return ((float) $values[$mid - 1] + (float) $values[$mid]) / 2;
    }
}
