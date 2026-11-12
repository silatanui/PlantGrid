@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--cases" aria-hidden="true">
            <i data-lucide="life-buoy"></i>
        </div>
        <div class="pg-object-home-copy">
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
        <div class="pg-object-home-actions">
            @if (auth()->user()->hasPermission('tickets.create'))
                <a class="btn btn-sm btn-pg" href="{{ route('helpdesk.create') }}">New case</a>
            @endif
        </div>
    </section>

    <div class="pg-list">
        <div class="pg-list-views">
            @unless ($employeeView)
                <a class="{{ $scope === 'queue' ? 'active' : '' }}" href="{{ route('helpdesk.index') }}">Queue</a>
            @endunless
            <a class="{{ $scope === 'mine' ? 'active' : '' }}" href="{{ route('helpdesk.mine') }}">My cases</a>
            @if (auth()->user()->hasPermission('tickets.update'))
                <a class="{{ $scope === 'assigned' ? 'active' : '' }}" href="{{ route('helpdesk.assigned') }}">Assigned to me</a>
            @endif
        </div>
        <form class="pg-filters" method="get">
            <input type="hidden" name="scope" value="{{ $scope }}">
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

    <div class="pg-card pg-listview">
        <div class="table-responsive">
            <table class="pg-table pg-table-list">
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
                        <td>
                            <a class="pg-list-primary" href="{{ route('helpdesk.show', $ticket) }}">
                                <span class="pg-list-entity-icon pg-object-icon--cases" aria-hidden="true">
                                    <i data-lucide="life-buoy"></i>
                                </span>
                                <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                            </a>
                        </td>
                        <td>
                            <a class="pg-list-subject" href="{{ route('helpdesk.show', $ticket) }}">{{ $ticket->subject }}</a>
                        </td>
                        <td>
                            @if ($ticket->requester)
                                <a class="pg-person-chip" href="{{ route('people.show', $ticket->requester) }}">
                                    <span class="pg-avatar-sm" aria-hidden="true">{{ strtoupper(substr($ticket->requester->name, 0, 1)) }}</span>
                                    <span>{{ $ticket->requester->name }}</span>
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{!! \App\Support\Labels::badge($ticket->priority) !!}</td>
                        <td>{!! \App\Support\Labels::badge($ticket->status) !!}</td>
                        <td>{!! app(\App\Services\SlaService::class)->badge($ticket) !!}</td>
                        <td>
                            @if ($ticket->assignee)
                                <a class="pg-person-chip" href="{{ route('people.show', $ticket->assignee) }}">
                                    <span class="pg-avatar-sm" aria-hidden="true">{{ strtoupper(substr($ticket->assignee->name, 0, 1)) }}</span>
                                    <span>{{ $ticket->assignee->name }}</span>
                                </a>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No cases in this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $tickets->links() }}</div>
@endsection
