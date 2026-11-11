@extends('layouts.app')

@section('content')
    <header class="pg-dash-hero">
        <div>
            <div class="pg-object">Operations</div>
            @php
                $hour = (int) now(config('plantgrid.display_timezone'))->format('G');
                $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
            @endphp
            <h1 class="pg-page-title">Good {{ $greeting }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
            <p class="pg-page-lead">
                {{ now(config('plantgrid.display_timezone'))->format('l, j F Y') }} · {{ $plantName }}
                @if (auth()->user()->isEmployeeRequester())
                    · signed in as {{ auth()->user()->roleLabel() }}
                @else
                    · monitoring and backups are simulated
                @endif
            </p>
        </div>
        <div class="pg-dash-actions">
            @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']))
                <a class="btn btn-pg-line btn-sm" href="{{ route('helpdesk.index') }}">{{ auth()->user()->isEmployeeRequester() ? 'My cases' : 'Open cases' }}</a>
            @endif
            @if (auth()->user()->hasPermission('tickets.create'))
                <a class="btn btn-pg btn-sm" href="{{ route('helpdesk.create') }}">New case</a>
            @endif
        </div>
    </header>

    @foreach ($notices as $notice)
        <div class="pg-alert pg-alert-{{ $notice['tone'] }}">{{ $notice['text'] }}</div>
    @endforeach

    <div class="row g-3 mb-3">
        @foreach (array_slice($cards, 0, 4) as $card)
            <div class="col-sm-6 col-xl-3">
                <article class="pg-card pg-card-pad pg-metric h-100">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ $card['value'] }}</div>
                    <div class="hint">{{ $card['hint'] }}</div>
                </article>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <section class="pg-card h-100">
                <div class="pg-card-pad pg-dash-toolbar">
                    <div>
                        <h2 class="pg-section-title mb-0">{{ auth()->user()->isEmployeeRequester() ? 'My case deadlines' : 'Upcoming work' }}</h2>
                        <p class="pg-page-lead">
                            @if (auth()->user()->isEmployeeRequester())
                                Due dates on cases you opened
                            @else
                                {{ count($deadlines) }} deadlines, maintenance windows and reviews
                            @endif
                        </p>
                    </div>
                    @unless (auth()->user()->isEmployeeRequester())
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
        </div>
        <div class="col-xl-5">
            <section class="pg-card mb-3">
                <div class="pg-card-pad d-flex justify-content-between align-items-center">
                    <h2 class="pg-section-title mb-0">Recent cases</h2>
                    @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']))
                        <a class="pg-panel-link" href="{{ route('helpdesk.index') }}">View all</a>
                    @endif
                </div>
                @forelse ($recentTickets as $ticket)
                    <a class="pg-feed" href="{{ route('helpdesk.show', $ticket) }}">
                        <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                        <strong>{{ $ticket->subject }}</strong>
                        <span>{{ $ticket->requester->name ?? '-' }} · {{ ucfirst($ticket->priority) }}</span>
                    </a>
                @empty
                    <p class="text-muted px-3 pb-3 mb-0">No cases in this view.</p>
                @endforelse
            </section>
            <section class="pg-card">
                <div class="pg-card-pad d-flex justify-content-between align-items-center">
                    <h2 class="pg-section-title mb-0">{{ $ticketHeading }}</h2>
                    @if (auth()->user()->hasPermission('audit.view'))
                        <a class="pg-panel-link" href="{{ route('audit.index') }}">Audit</a>
                    @endif
                </div>
                @forelse ($tickets as $ticket)
                    <a class="pg-feed" href="{{ route('helpdesk.show', $ticket) }}">
                        <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                        <strong>{{ $ticket->subject }}</strong>
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
        </div>
    </div>

    @if ($charts !== [])
        <div class="row g-3">
            @foreach (array_slice($charts, 0, 2) as $chart)
                <div class="col-lg-6">
                    <section class="pg-card pg-card-pad h-100">
                        <h2 class="pg-section-title">{{ $chart['title'] }}</h2>
                        <p class="text-muted small">{{ $chart['caption'] }}</p>
                        <div class="pg-chart" data-chart='@json($chart['config'])'>
                            <canvas></canvas>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
    @endif
@endsection
