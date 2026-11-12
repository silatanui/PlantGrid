@extends('layouts.app')

@section('content')
    @php
        $listQuery = array_merge(request()->except('view'), ['view' => 'list']);
        $boardQuery = array_merge(request()->except('view'), ['view' => 'board']);
    @endphp
    <div class="pg-list">
        <div class="pg-list-bar">
            <div>
                <div class="pg-object">{{ $employeeView ? 'Self-service' : 'Cases' }}</div>
                <h1 class="pg-page-title">{{ $title }}</h1>
                <p class="pg-page-lead">
                    @if ($employeeView)
                        Report an ICT problem and follow the cases you opened. Assignment and closure are handled by ICT staff.
                    @else
                        Track packaging-floor and ICT requests from open through closure.
                    @endif
                </p>
            </div>
            <div class="pg-list-actions">
                <div class="pg-seg" role="group" aria-label="Case layout">
                    <a class="{{ $viewMode === 'list' ? 'active' : '' }}" href="{{ route('helpdesk.index', $listQuery) }}">List</a>
                    <a class="{{ $viewMode === 'board' ? 'active' : '' }}" href="{{ route('helpdesk.index', $boardQuery) }}">Board</a>
                </div>
                @if (auth()->user()->hasPermission('tickets.create'))
                    <a class="btn btn-sm btn-pg" href="{{ route('helpdesk.create') }}">New case</a>
                @endif
            </div>
        </div>
        <div class="pg-list-views">
            @unless ($employeeView)
                <a class="{{ $scope === 'queue' ? 'active' : '' }}" href="{{ route('helpdesk.index', ['view' => $viewMode]) }}">Queue</a>
            @endunless
            <a class="{{ $scope === 'mine' ? 'active' : '' }}" href="{{ route('helpdesk.mine', ['view' => $viewMode]) }}">My cases</a>
            @if (auth()->user()->hasPermission('tickets.update'))
                <a class="{{ $scope === 'assigned' ? 'active' : '' }}" href="{{ route('helpdesk.assigned', ['view' => $viewMode]) }}">Assigned to me</a>
            @endif
        </div>
        <form class="pg-filters" method="get">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <div>
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Number or subject">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Any</option>
                    <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open (active)</option>
                    @foreach (['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\Labels::status($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">Any</option>
                    @foreach (['critical', 'high', 'medium', 'low'] as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort->column }}">
            <input type="hidden" name="direction" value="{{ $sort->direction }}">
            <button class="btn btn-pg" type="submit">Apply</button>
        </form>
    </div>

    @if ($viewMode === 'board')
        <div class="pg-board">
            @foreach ($columns as $status => $items)
                <section class="pg-board-col">
                    <header>
                        <span>{{ \App\Support\Labels::status($status) }}</span>
                        <strong>{{ $items->count() }}</strong>
                    </header>
                    @forelse ($items as $ticket)
                        <a class="pg-board-card" href="{{ route('helpdesk.show', $ticket) }}">
                            <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                            <strong>{{ $ticket->subject }}</strong>
                            <span>{{ $ticket->requester->name ?? '-' }}</span>
                            {!! \App\Support\Labels::badge($ticket->priority) !!}
                            {!! app(\App\Services\SlaService::class)->badge($ticket) !!}
                        </a>
                    @empty
                        <p class="pg-board-empty">None</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @else
        <div class="pg-card table-responsive">
            <table class="pg-table">
                <thead>
                    <tr>
                        <x-sort-th column="ticket_number" label="Case" :sort="$sort" route-name="helpdesk.index" />
                        <x-sort-th column="subject" label="Subject" :sort="$sort" route-name="helpdesk.index" />
                        <th>Requester</th>
                        <x-sort-th column="priority" label="Priority" :sort="$sort" route-name="helpdesk.index" />
                        <x-sort-th column="status" label="Status" :sort="$sort" route-name="helpdesk.index" />
                        <th>SLA</th>
                        <th>Owner</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="pg-mono"><a href="{{ route('helpdesk.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->requester->name ?? '-' }}</td>
                        <td>{!! \App\Support\Labels::badge($ticket->priority) !!}</td>
                        <td>{!! \App\Support\Labels::badge($ticket->status) !!}</td>
                        <td>{!! app(\App\Services\SlaService::class)->badge($ticket) !!}</td>
                        <td>{{ $ticket->assignee->name ?? 'Unassigned' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No cases in this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $tickets->links() }}</div>
    @endif
@endsection
