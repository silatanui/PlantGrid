@extends('layouts.app')

@section('content')
    @php
        $hour = (int) now(config('plantgrid.display_timezone'))->format('G');
        $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
        $firstName = strtok(auth()->user()->name, ' ');
        $isEmployee = auth()->user()->isEmployeeRequester();
        $metricCards = array_slice($cards, 0, 4);
    @endphp

    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--dashboard" aria-hidden="true">
            <i data-lucide="layout-dashboard"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Operations</div>
            <h1 class="pg-page-title">Good {{ $greeting }}, {{ $firstName }}</h1>
            <p class="pg-page-lead">
                {{ now(config('plantgrid.display_timezone'))->format('l, j F Y') }} · {{ $plantName }}
                @if ($isEmployee)
                    · {{ auth()->user()->roleLabel() }}
                @else
                    · monitoring and backups are simulated
                @endif
            </p>
        </div>
        <div class="pg-object-home-actions">
            @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']))
                <a class="btn btn-pg-line" href="{{ route('helpdesk.index') }}">{{ $isEmployee ? 'My cases' : 'Open cases' }}</a>
            @endif
            @if (auth()->user()->hasPermission('tickets.create'))
                <a class="btn btn-pg" href="{{ route('helpdesk.create') }}">New case</a>
            @endif
        </div>
    </section>

    @foreach ($notices as $notice)
        @component('partials.alert', ['tone' => $notice['tone'], 'key' => 'dashboard-notice-'.md5($notice['text']), 'class' => 'mb-3'])
            {{ $notice['text'] }}
        @endcomponent
    @endforeach

    @if ($metricCards !== [])
        <div class="pg-dash-kpis mb-3">
            @foreach ($metricCards as $card)
                @php
                    $tag = ! empty($card['href']) ? 'a' : 'article';
                    $href = ! empty($card['href']) ? ' href="'.e($card['href']).'"' : '';
                    $tone = $card['tone'] ?? null;
                @endphp
                <{{ $tag }} class="pg-dash-kpi{{ $tone ? ' tone-'.$tone : '' }}"{!! $href !!}>
                    <span class="pg-dash-kpi-icon" aria-hidden="true">
                        <i data-lucide="{{ $card['icon'] ?? 'activity' }}"></i>
                    </span>
                    <div>
                        <span class="label">{{ $card['label'] }}</span>
                        <strong>{{ $card['value'] }}</strong>
                        <span class="hint">{{ $card['hint'] }}</span>
                    </div>
                </{{ $tag }}>
            @endforeach
        </div>
    @endif

    @if ($quickLinks !== [])
        <nav class="pg-dash-links mb-3" aria-label="Dashboard shortcuts">
            @foreach ($quickLinks as $link)
                <a href="{{ route($link['route']) }}">
                    <i data-lucide="{{ $link['icon'] }}"></i>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="pg-dash-board mb-3">
        <section class="pg-card pg-dash-panel">
            <div class="pg-card-pad pg-dash-toolbar">
                <div>
                    <h2 class="pg-section-title mb-0">{{ $isEmployee ? 'My case deadlines' : 'Upcoming work' }}</h2>
                    <p class="text-muted small mb-0">
                        @if ($isEmployee)
                            Due dates on cases you opened
                        @else
                            {{ count($deadlines) }} deadlines, maintenance windows and reviews
                        @endif
                    </p>
                </div>
                @unless ($isEmployee)
                    <div class="pg-chips" role="group" aria-label="Deadline type">
                        <button class="active" type="button" data-deadline-filter="all">All</button>
                        <button type="button" data-deadline-filter="deadline">Cases</button>
                        <button type="button" data-deadline-filter="maintenance">Maintenance</button>
                        <button type="button" data-deadline-filter="compliance">Compliance</button>
                    </div>
                @endunless
            </div>
            <div class="pg-deadline-list">
                @forelse ($deadlines as $item)
                    <a class="pg-deadline" data-deadline="{{ $item['kind'] }}" href="{{ $item['href'] }}">
                        <span class="pg-deadline-kind">{{ $item['label'] }}</span>
                        <span>
                            <strong>{{ $item['title'] }}</strong>
                            <span>{{ $item['detail'] }}</span>
                        </span>
                        <span class="pg-deadline-when {{ $item['tone'] }}">{{ $item['when'] }}</span>
                    </a>
                @empty
                    <p class="text-muted px-3 py-3 mb-0">No dated work is waiting in this view.</p>
                @endforelse
            </div>
        </section>

        <div class="pg-dash-side">
            <section class="pg-card pg-dash-panel">
                <div class="pg-card-pad d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="pg-section-title mb-0">Recent cases</h2>
                        <p class="text-muted small mb-0">Latest activity in your case view</p>
                    </div>
                    @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']))
                        <a class="pg-panel-link" href="{{ route('helpdesk.index') }}">View all</a>
                    @endif
                </div>
                @forelse ($recentTickets as $ticket)
                    <a class="pg-feed pg-feed--case" href="{{ route('helpdesk.show', $ticket) }}">
                        <span class="pg-feed-top">
                            <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                            {!! \App\Support\Labels::badge($ticket->priority) !!}
                        </span>
                        <strong>{{ $ticket->subject }}</strong>
                        <span>
                            {{ $ticket->requester->name ?? '—' }}
                            @if ($ticket->assignee)
                                · {{ $ticket->assignee->name }}
                            @endif
                            · {{ \App\Support\Labels::status($ticket->status) }}
                        </span>
                    </a>
                @empty
                    <p class="text-muted px-3 pb-3 mb-0">No cases in this view.</p>
                @endforelse
            </section>

            <section class="pg-card pg-dash-panel">
                <div class="pg-card-pad d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="pg-section-title mb-0">{{ $ticketHeading }}</h2>
                        <p class="text-muted small mb-0">Your personal work queue</p>
                    </div>
                    @if (auth()->user()->hasPermission('audit.view'))
                        <a class="pg-panel-link" href="{{ route('audit.index') }}">Audit</a>
                    @endif
                </div>
                @forelse ($tickets as $ticket)
                    <a class="pg-feed pg-feed--case" href="{{ route('helpdesk.show', $ticket) }}">
                        <span class="pg-feed-top">
                            <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                            {!! \App\Support\Labels::badge($ticket->status) !!}
                        </span>
                        <strong>{{ $ticket->subject }}</strong>
                        @if ($ticket->category)
                            <span>{{ $ticket->category->name }}</span>
                        @endif
                    </a>
                @empty
                    @forelse ($maintenance as $record)
                        <div class="pg-feed">
                            <strong>{{ $record->subject }}</strong>
                            <span>{{ \App\Support\Format::date($record->scheduled_at) }}</span>
                        </div>
                    @empty
                        @forelse ($activity as $entry)
                            <div class="pg-feed">
                                <strong>{{ str_replace(['.', '_'], ' ', $entry->action) }}</strong>
                                <span>{{ $entry->user->name ?? 'System' }} · {{ \App\Support\Format::date($entry->created_at) }}</span>
                            </div>
                        @empty
                            <p class="text-muted px-3 pb-3 mb-0">Nothing assigned in this view.</p>
                        @endforelse
                    @endforelse
                @endforelse
            </section>

            @if ($alerts->isNotEmpty())
                <section class="pg-card pg-dash-panel">
                    <div class="pg-card-pad d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="pg-section-title mb-0">Simulated alerts</h2>
                            <p class="text-muted small mb-0">Monitoring readings — not live plant signals</p>
                        </div>
                        <a class="pg-panel-link" href="{{ route('monitoring.index') }}">Monitoring</a>
                    </div>
                    @foreach ($alerts as $alert)
                        <div class="pg-feed">
                            <span class="pg-feed-top">
                                <strong>{{ $alert->message }}</strong>
                                {!! \App\Support\Labels::badge($alert->severity) !!}
                            </span>
                            <span>{{ \App\Support\Format::date($alert->triggered_at) }} · {{ $alert->status }}</span>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>

    @if ($charts !== [])
        <div class="pg-dash-charts">
            @foreach (array_slice($charts, 0, 2) as $chart)
                @php
                    $layout = $chart['layout'] ?? 'plain';
                    $segments = $chart['segments'] ?? [];
                    $toneVar = static function (string $token): string {
                        return match ($token) {
                            'pg-accent' => 'var(--pg-navy)',
                            'pg-deep' => 'var(--pg-navy-deep)',
                            'pg-critical' => 'var(--pg-critical)',
                            'pg-warning' => 'var(--pg-warning)',
                            'pg-success' => 'var(--pg-success)',
                            'pg-info' => 'var(--pg-info)',
                            'pg-muted' => 'var(--pg-muted)',
                            default => $token,
                        };
                    };
                @endphp
                <section class="pg-card pg-card-pad pg-dash-chart-card">
                    <div class="pg-dash-chart-head">
                        <div>
                            <h2 class="pg-section-title mb-0">{{ $chart['title'] }}</h2>
                            <p class="text-muted small mb-0">{{ $chart['caption'] }}</p>
                        </div>
                        @if (isset($chart['total']))
                            <div class="pg-dash-chart-total">
                                <strong>{{ $chart['total'] }}</strong>
                                <span>{{ $chart['total_label'] ?? '' }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($layout === 'donut')
                        <div class="pg-dash-chart-body pg-dash-chart-body--donut">
                            <div
                                class="pg-chart pg-chart-dash pg-chart-dash--donut"
                                data-chart='@json($chart['config'])'
                                data-chart-total="{{ $chart['total'] ?? '' }}"
                                data-chart-total-label="{{ $chart['total_label'] ?? '' }}"
                            >
                                <canvas aria-label="{{ $chart['title'] }}"></canvas>
                            </div>
                            <ul class="pg-dash-chart-legend">
                                @foreach ($segments as $segment)
                                    <li>
                                        <span class="pg-dash-swatch" style="--pg-swatch: {{ $toneVar($segment['color']) }}"></span>
                                        <span class="pg-dash-legend-copy">
                                            <strong>{{ $segment['label'] }}</strong>
                                            <em>{{ $segment['pct'] }}%</em>
                                        </span>
                                        <span class="pg-dash-legend-value">{{ $segment['value'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($layout === 'bars')
                        <ul class="pg-dash-chart-meters" aria-label="{{ $chart['title'] }}">
                            @foreach ($segments as $segment)
                                <li>
                                    <div class="pg-dash-meter-meta">
                                        <span>
                                            <span class="pg-dash-swatch" style="--pg-swatch: {{ $toneVar($segment['color']) }}"></span>
                                            {{ $segment['label'] }}
                                        </span>
                                        <strong>{{ $segment['value'] }} <em>{{ $segment['pct'] }}%</em></strong>
                                    </div>
                                    <div class="pg-dash-meter" role="presentation">
                                        <span style="width: {{ max($segment['fill'] ?? $segment['pct'], $segment['value'] > 0 ? 6 : 0) }}%; background: {{ $toneVar($segment['color']) }}"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="pg-chart pg-chart-dash" data-chart='@json($chart['config'])'>
                            <canvas aria-label="{{ $chart['title'] }}"></canvas>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
@endsection
