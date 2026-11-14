@extends('layouts.app')

@section('content')
    @php
        $filters = $board['filters'];
        $team = $board['team'];
        $queryBase = array_filter([
            'from' => $board['from']->toDateString(),
            'to' => $board['to']->toDateString(),
            'department_id' => $filters['department_id'],
            'role' => $filters['role'],
            'sort' => $filters['sort'] !== 'cpm' ? $filters['sort'] : null,
        ], fn ($value) => $value !== null && $value !== '');
        $resetQuery = array_filter([
            'from' => now()->subDays(29)->toDateString(),
            'to' => now()->toDateString(),
        ]);
        $activeFilterBits = array_filter([
            $filters['department_id']
                ? (data_get($board['departments']->firstWhere('id', $filters['department_id']), 'name') ?: 'Department')
                : null,
            $filters['role'],
            $filters['user_id']
                ? (data_get($board['workers']->firstWhere('id', $filters['user_id']), 'name') ?: 'Person')
                : null,
        ]);
    @endphp

    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--performance" aria-hidden="true">
            <i data-lucide="gauge"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Assurance</div>
            <h1 class="pg-page-title">Worker performance</h1>
            <p class="pg-page-lead">
                Throughput, workload, and SLA quality for ICT staff
                · {{ $board['from']->format('d M Y') }} - {{ $board['to']->format('d M Y') }}
                · {{ rtrim(rtrim(number_format($board['months'], 2), '0'), '.') }} months
            </p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-pg-line" href="{{ route('reports.index') }}">Inventory reports</a>
            @if ($canExport)
                <a class="btn btn-pg" href="{{ route('performance.export', request()->query()) }}">Export CSV</a>
            @endif
        </div>
    </section>

    <form class="pg-perf-panel mb-3" method="get" action="{{ route('performance.index') }}">
        <div class="pg-perf-panel-top">
            <div>
                <div class="pg-perf-panel-kicker">Date range</div>
                <div class="pg-perf-range" role="group" aria-label="Quick date ranges">
                    @foreach ($rangePresets as $preset)
                        <a
                            class="pg-perf-chip {{ $activeRange === $preset['id'] ? 'is-active' : '' }}"
                            href="{{ route('performance.index', array_filter([
                                'from' => $preset['from'],
                                'to' => $preset['to'],
                                'department_id' => $filters['department_id'],
                                'role' => $filters['role'],
                                'user_id' => $filters['user_id'],
                                'sort' => $filters['sort'] !== 'cpm' ? $filters['sort'] : null,
                            ], fn ($value) => $value !== null && $value !== '')) }}"
                        >{{ $preset['label'] }}</a>
                    @endforeach
                    @if ($activeRange === 'custom')
                        <span class="pg-perf-chip is-active">Custom</span>
                    @endif
                </div>
            </div>
            @if ($activeFilterBits !== [])
                <div class="pg-perf-active">
                    <span class="text-muted">Filtered by</span>
                    @foreach ($activeFilterBits as $bit)
                        <span class="pg-perf-chip is-soft">{{ $bit }}</span>
                    @endforeach
                    <a class="pg-perf-reset" href="{{ route('performance.index', $resetQuery) }}">Reset</a>
                </div>
            @endif
        </div>

        <div class="pg-perf-filters">
            <div>
                <label class="form-label" for="from">From</label>
                <input class="form-control" type="date" id="from" name="from" value="{{ $board['from']->toDateString() }}">
            </div>
            <div>
                <label class="form-label" for="to">To</label>
                <input class="form-control" type="date" id="to" name="to" value="{{ $board['to']->toDateString() }}">
            </div>
            <div>
                <label class="form-label" for="department_id">Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">All departments</option>
                    @foreach ($board['departments'] as $department)
                        <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All ICT roles</option>
                    @foreach ($board['roles'] as $role)
                        <option value="{{ $role->name }}" @selected($filters['role'] === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="user_id">Person</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All workers</option>
                    @foreach ($board['workers'] as $worker)
                        <option value="{{ $worker['id'] }}" @selected($filters['user_id'] === $worker['id'])>{{ $worker['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="sort">Sort charts</label>
                <select class="form-select" id="sort" name="sort">
                    @foreach (['cpm' => 'CPM', 'resolved' => 'Resolved', 'open' => 'Open load', 'sla' => 'SLA %', 'avg_hours' => 'Avg hours', 'name' => 'Name'] as $key => $label)
                        <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pg-perf-filters-actions">
                <button class="btn btn-pg" type="submit">Apply filters</button>
            </div>
        </div>
    </form>

    <div class="pg-perf-kpis mb-3">
        <div class="pg-perf-kpi">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="gauge"></i></span>
            <div>
                <span class="label">Team CPM</span>
                <strong>{{ number_format($team['cpm'], 2) }}</strong>
                <span class="hint">{{ $team['resolved'] }} resolved · {{ $team['workers'] }} workers</span>
            </div>
        </div>
        <div class="pg-perf-kpi {{ ($team['sla_rate'] !== null && $team['sla_rate'] < 85) ? 'tone-warning' : '' }}">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
            <div>
                <span class="label">Avg SLA hit</span>
                <strong>{{ $team['sla_rate'] === null ? '-' : number_format($team['sla_rate'], 1).'%' }}</strong>
                <span class="hint">{{ $team['breaches'] }} breach{{ $team['breaches'] === 1 ? '' : 'es' }}</span>
            </div>
        </div>
        <div class="pg-perf-kpi {{ $team['unassigned'] > 0 ? 'tone-warning' : '' }}">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="inbox"></i></span>
            <div>
                <span class="label">Unassigned open</span>
                <strong>{{ $team['unassigned'] }}</strong>
                <span class="hint">
                    @if ($team['unassigned_age_hours'] === null)
                        No waiting queue
                    @else
                        Avg age {{ number_format($team['unassigned_age_hours'], 1) }} h
                    @endif
                </span>
            </div>
        </div>
        <div class="pg-perf-kpi {{ $team['overloaded']->isNotEmpty() ? 'tone-critical' : '' }}">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="alert-triangle"></i></span>
            <div>
                <span class="label">Overloaded</span>
                <strong>{{ $team['overloaded']->count() }}</strong>
                <span class="hint">Open &gt; {{ $team['threshold'] }} assigned</span>
            </div>
        </div>
    </div>

    @if ($team['overloaded']->isNotEmpty())
        @component('partials.alert', ['tone' => 'warning', 'key' => 'performance-overloaded', 'class' => 'mb-3'])
            Overloaded: {{ $team['overloaded']->pluck('name')->join(', ') }}.
            Rebalance intake before chasing CPM alone.
        @endcomponent
    @endif

    @if ($charts !== [])
        <div class="pg-perf-charts mb-3">
            @foreach ($charts as $chart)
                <section class="pg-card pg-card-pad pg-perf-chart-card {{ str_contains($chart['title'], 'trend') ? 'is-wide' : '' }}">
                    <div class="pg-perf-chart-head">
                        <div>
                            <h2 class="pg-section-title mb-0">{{ $chart['title'] }}</h2>
                            <p class="text-muted small mb-0">{{ $chart['caption'] }}</p>
                        </div>
                    </div>
                    <div class="pg-chart pg-chart-perf" style="height: {{ $chart['height'] }}px" data-chart='@json($chart['config'])'>
                        <canvas aria-label="{{ $chart['title'] }}"></canvas>
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <section class="pg-card mb-3">
        <div class="pg-card-pad pb-0 d-flex justify-content-between align-items-end gap-2 flex-wrap">
            <div>
                <h2 class="pg-section-title mb-0">Leaderboard</h2>
                <p class="text-muted small mb-0">Click a worker for trend and open queue. Charts above follow the same sort.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="pg-table pg-perf-table">
                <thead>
                    <tr>
                        <th>Worker</th>
                        <th>Open</th>
                        <th>Intake</th>
                        <th>Resolved</th>
                        <th>CPM</th>
                        <th>Avg h</th>
                        <th>SLA %</th>
                        <th>Breaches</th>
                        <th>Reopen %</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($board['rows'] as $row)
                    <tr class="{{ $filters['user_id'] === $row['user_id'] ? 'is-selected' : '' }} {{ $row['overloaded'] ? 'is-overloaded' : '' }}">
                        <td>
                            <a href="{{ route('performance.index', $queryBase + ['user_id' => $row['user_id'], 'sort' => $filters['sort']]) }}">{{ $row['name'] }}</a>
                            <div class="text-muted small">{{ $row['role'] }} · {{ $row['department'] }}</div>
                        </td>
                        <td>{{ $row['open'] }}</td>
                        <td>{{ $row['intake'] }}</td>
                        <td>{{ $row['resolved'] }}</td>
                        <td><strong>{{ number_format($row['cpm'], 2) }}</strong></td>
                        <td>{{ $row['avg_hours'] === null ? '-' : number_format($row['avg_hours'], 1) }}</td>
                        <td>
                            @if ($row['sla_rate'] === null)
                                -
                            @else
                                <span class="pg-perf-sla {{ $row['sla_rate'] < 85 ? 'is-low' : 'is-ok' }}">{{ number_format($row['sla_rate'], 1) }}%</span>
                            @endif
                        </td>
                        <td>{{ $row['breaches'] }}</td>
                        <td>{{ number_format($row['reopen_rate'], 1) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">No ICT workers match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($board['selected'])
        @php $person = $board['selected']; @endphp
        <section class="pg-perf-detail mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h2 class="pg-section-title mb-0">{{ $person['name'] }}</h2>
                    <p class="text-muted small mb-0">
                        CPD {{ number_format($person['cpd'], 2) }}
                        · Median {{ $person['median_hours'] === null ? '-' : number_format($person['median_hours'], 1).' h' }}
                        · First response {{ $person['first_response_hours'] === null ? '-' : number_format($person['first_response_hours'], 1).' h' }}
                        · Notes/case {{ $person['notes_per_case'] === null ? '-' : number_format($person['notes_per_case'], 1) }}
                        · Critical/high {{ $person['critical_share'] === null ? '-' : number_format($person['critical_share'], 1).'%' }}
                        · Backlog age {{ $person['backlog_age_hours'] === null ? '-' : number_format($person['backlog_age_hours'], 1).' h' }}
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-pg-line" href="{{ route('people.performance', $person['user_id']) }}">Person page</a>
                    <a class="btn btn-sm btn-pg-line" href="{{ route('performance.index', $queryBase + ['sort' => $filters['sort']]) }}">Clear person</a>
                </div>
            </div>

            <div class="pg-card pg-card-pad">
                <h3 class="pg-section-title">Open queue ({{ $board['open_queue']->count() }})</h3>
                @if ($board['open_queue']->isEmpty())
                    <p class="text-muted mb-0">No open assigned cases.</p>
                @else
                    <ul class="pg-perf-queue">
                        @foreach ($board['open_queue'] as $ticket)
                            <li>
                                <a href="{{ route('helpdesk.show', $ticket) }}">{{ $ticket->ticket_number }}</a>
                                {!! \App\Support\Labels::badge($ticket->priority) !!}
                                <span class="text-muted">{{ $ticket->subject }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    <p class="text-muted small mb-0">
        CPM = resolved cases ÷ months in the selected range. Pair with SLA hit rate and reopen % so throughput is not chased at the expense of quality.
        Compare similar roles; open load above {{ $team['threshold'] }} flags overload for coaching, not ranking.
    </p>
@endsection
