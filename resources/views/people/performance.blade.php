@extends('layouts.app')

@section('content')
    @php
        $metrics = $snapshot['selected'];
        $rangeQuery = [
            'from' => $snapshot['from']->toDateString(),
            'to' => $snapshot['to']->toDateString(),
        ];
    @endphp

    <div class="pg-person-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="pg-person-avatar" aria-hidden="true">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 class="pg-page-title mb-1">{{ $person->name }} · Performance</h1>
                <p class="pg-page-lead mb-0">
                    {{ $person->roleLabel() }} · {{ $person->department->name ?? 'No department' }}
                    · {{ $snapshot['from']->format('d M Y') }} – {{ $snapshot['to']->format('d M Y') }}
                </p>
            </div>
        </div>
        <div class="pg-person-hero-actions d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-pg-line" href="{{ route('people.show', $person) }}">Back to contact</a>
            @if ($canViewTeamBoard)
                <a class="btn btn-pg-line" href="{{ route('performance.index', ['user_id' => $person->id] + $rangeQuery) }}">Team board</a>
            @endif
            @if ($person->id !== auth()->id())
                <a class="btn btn-pg" href="{{ route('people.show', $person) }}#contact">Message {{ strtok($person->name, ' ') }}</a>
            @endif
        </div>
    </div>

    <div class="pg-perf-panel mb-3">
        <div class="pg-perf-panel-top">
            <div>
                <div class="pg-perf-panel-kicker">Date range</div>
                <div class="pg-perf-range" role="group" aria-label="Quick date ranges">
                    @foreach ($rangePresets as $preset)
                        <a
                            class="pg-perf-chip {{ $activeRange === $preset['id'] ? 'is-active' : '' }}"
                            href="{{ route('people.performance', [$person, 'from' => $preset['from'], 'to' => $preset['to']]) }}"
                        >{{ $preset['label'] }}</a>
                    @endforeach
                    @if ($activeRange === 'custom')
                        <span class="pg-perf-chip is-active">Custom</span>
                    @endif
                </div>
            </div>
        </div>
        <form class="pg-perf-filters pg-perf-filters--compact" method="get" action="{{ route('people.performance', $person) }}">
            <div>
                <label class="form-label" for="from">From</label>
                <input class="form-control" type="date" id="from" name="from" value="{{ $snapshot['from']->toDateString() }}">
            </div>
            <div>
                <label class="form-label" for="to">To</label>
                <input class="form-control" type="date" id="to" name="to" value="{{ $snapshot['to']->toDateString() }}">
            </div>
            <div class="pg-perf-filters-actions">
                <button class="btn btn-pg" type="submit">Apply</button>
            </div>
        </form>
    </div>

    <div class="pg-perf-kpis mb-3">
        <div class="pg-perf-kpi {{ ! empty($metrics['overloaded']) ? 'tone-critical' : '' }}">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="briefcase"></i></span>
            <div>
                <div class="pg-perf-kpi-label">Open assigned</div>
                <div class="pg-perf-kpi-value">{{ $metrics['open'] }}</div>
            </div>
        </div>
        <div class="pg-perf-kpi">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="inbox"></i></span>
            <div>
                <div class="pg-perf-kpi-label">Intake</div>
                <div class="pg-perf-kpi-value">{{ $metrics['intake'] }}</div>
            </div>
        </div>
        <div class="pg-perf-kpi">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="check-circle-2"></i></span>
            <div>
                <div class="pg-perf-kpi-label">Resolved</div>
                <div class="pg-perf-kpi-value">{{ $metrics['resolved'] }}</div>
            </div>
        </div>
        <div class="pg-perf-kpi">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="gauge"></i></span>
            <div>
                <div class="pg-perf-kpi-label">CPM</div>
                <div class="pg-perf-kpi-value">{{ number_format($metrics['cpm'], 2) }}</div>
            </div>
        </div>
        <div class="pg-perf-kpi {{ ($metrics['sla_rate'] !== null && $metrics['sla_rate'] < 85) ? 'tone-warning' : '' }}">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
            <div>
                <div class="pg-perf-kpi-label">SLA %</div>
                <div class="pg-perf-kpi-value">{{ $metrics['sla_rate'] === null ? '—' : number_format($metrics['sla_rate'], 1).'%' }}</div>
            </div>
        </div>
        <div class="pg-perf-kpi">
            <span class="pg-perf-kpi-icon" aria-hidden="true"><i data-lucide="message-square-text"></i></span>
            <div>
                <div class="pg-perf-kpi-label">Notes</div>
                <div class="pg-perf-kpi-value">{{ $snapshot['notes_count'] }}</div>
            </div>
        </div>
    </div>

    <p class="text-muted small mb-3">
        First response {{ $metrics['first_response_hours'] === null ? '—' : number_format($metrics['first_response_hours'], 1).' h' }}
        · Avg resolve {{ $metrics['avg_hours'] === null ? '—' : number_format($metrics['avg_hours'], 1).' h' }}
        · Notes/case {{ $metrics['notes_per_case'] === null ? '—' : number_format($metrics['notes_per_case'], 1) }}
        · Reopen {{ number_format($metrics['reopen_rate'], 1) }}%
        · Backlog age {{ $metrics['backlog_age_hours'] === null ? '—' : number_format($metrics['backlog_age_hours'], 1).' h' }}
    </p>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Open assigned cases</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($snapshot['open_queue'] as $case)
                        <tr>
                            <td>
                                <a href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a>
                                <div class="text-muted small">{{ $case->subject }}</div>
                            </td>
                            <td class="text-end">
                                {!! \App\Support\Labels::badge($case->priority) !!}
                                {!! \App\Support\Labels::badge($case->status) !!}
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No open cases assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Partner on open cases</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($partnerCases as $case)
                        <tr>
                            <td>
                                <a href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a>
                                <div class="text-muted small">{{ $case->subject }}</div>
                                <div class="text-muted small">Owner: {{ $case->assignee->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="text-end">{!! \App\Support\Labels::badge($case->status) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">Not listed as a partner on open cases.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Open requests raised</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($requestedCases as $case)
                        <tr>
                            <td>
                                <a href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a>
                                <div class="text-muted small">{{ $case->subject }}</div>
                            </td>
                            <td class="text-end">{!! \App\Support\Labels::badge($case->status) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No open requests raised by this person.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="pg-card mb-3">
                <div class="pg-card-pad">
                    <h2 class="pg-section-title mb-0">Notes in range</h2>
                    <p class="text-muted small mb-0">{{ $snapshot['notes_count'] }} note{{ $snapshot['notes_count'] === 1 ? '' : 's' }} authored in the selected dates.</p>
                </div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($notes as $note)
                        <tr>
                            <td>
                                @if ($note->ticket)
                                    <a href="{{ route('helpdesk.show', $note->ticket) }}#notes">{{ $note->ticket->ticket_number }}</a>
                                    <div class="text-muted small">{{ $note->ticket->subject }}</div>
                                @else
                                    <span class="text-muted">Case unavailable</span>
                                @endif
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit(strip_tags($note->comment), 110) }}</div>
                            </td>
                            <td class="text-end text-muted small">{{ optional($note->created_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No notes in this date range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Recent case activity</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($activity as $item)
                        <tr>
                            <td>
                                <a href="{{ $item['href'] }}">{{ $item['title'] }}</a>
                                <div class="text-muted small">{{ $item['meta'] }}</div>
                            </td>
                            <td class="text-end text-muted small">{{ $item['when'] }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No recent case activity recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
@endsection
