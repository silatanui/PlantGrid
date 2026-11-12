@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--production" aria-hidden="true">
            <i data-lucide="factory"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Production system</div>
            <h1 class="pg-page-title">{{ $system->name }}</h1>
            <p class="pg-page-lead">
                {{ $system->system_type }}
                @if ($system->department)
                    · {{ $system->department->name }}
                @endif
            </p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-pg-line" href="{{ route('production.index') }}">All systems</a>
            @if ($openIncidents->isNotEmpty())
                <a class="btn btn-pg-line" href="#incidents">{{ $openIncidents->count() }} open incident{{ $openIncidents->count() === 1 ? '' : 's' }}</a>
            @endif
        </div>
    </section>

    <div class="pg-prod-detail mb-3">
        <section class="pg-card pg-card-pad">
            <h2 class="pg-section-title">Overview</h2>
            <p class="mb-3">{{ $system->description ?: 'No description on file.' }}</p>
            <dl class="pg-kv">
                <dt>Status</dt>
                <dd>{!! \App\Support\Labels::badge($system->status) !!}</dd>
                <dt>Department</dt>
                <dd>{{ $system->department->name ?? '—' }}</dd>
                <dt>Business owner</dt>
                <dd>
                    @if ($system->owner)
                        <a href="{{ route('people.show', $system->owner) }}">{{ $system->owner->name }}</a>
                        <div class="text-muted small">{{ $system->owner->job_title ?: $system->owner->email }}</div>
                    @else
                        —
                    @endif
                </dd>
                <dt>Vendor</dt>
                <dd>{{ $system->vendor->name ?? '—' }}</dd>
                <dt>Equipment</dt>
                <dd>{{ $system->equipment->count() }} linked device{{ $system->equipment->count() === 1 ? '' : 's' }}</dd>
                <dt>Open incidents</dt>
                <dd>{{ $openIncidents->count() }}</dd>
            </dl>
        </section>

        <section class="pg-card">
            <div class="pg-card-pad pb-0">
                <h2 class="pg-section-title mb-0">Linked cases</h2>
                <p class="text-muted small mb-0">Helpdesk work tied to this system’s incidents or assets.</p>
            </div>
            <div class="table-responsive">
                <table class="pg-table">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>People</th>
                            <th>Asset</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($relatedCases as $case)
                        <tr>
                            <td>
                                <a href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a>
                                <div class="text-muted small">{{ $case->subject }}</div>
                            </td>
                            <td>
                                @if ($case->requester)
                                    <a href="{{ route('people.show', $case->requester) }}">{{ $case->requester->name }}</a>
                                @endif
                                @if ($case->assignee)
                                    <div class="{{ $case->requester ? 'mt-1' : '' }}">
                                        <a href="{{ route('people.show', $case->assignee) }}">{{ $case->assignee->name }}</a>
                                        <div class="text-muted small">Owner</div>
                                    </div>
                                @elseif (! $case->requester)
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($case->asset)
                                    <a class="pg-mono" href="{{ route('assets.show', $case->asset) }}">{{ $case->asset->asset_tag }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                {!! \App\Support\Labels::badge($case->priority) !!}
                                <div class="mt-1">{!! \App\Support\Labels::badge($case->status) !!}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No linked cases yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="pg-card mb-3">
        <div class="pg-card-pad pb-0">
            <h2 class="pg-section-title mb-0">Related equipment</h2>
            <p class="text-muted small mb-0">Devices and ICT asset tags that support this system.</p>
        </div>
        <div class="table-responsive">
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Asset</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($system->equipment as $item)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $item->equipment_type }}</div>
                            <div class="text-muted small">
                                {{ trim(($item->manufacturer ?? '').' '.($item->model ?? '')) ?: 'No model on file' }}
                                @if ($item->serial_number)
                                    · SN {{ $item->serial_number }}
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($item->asset)
                                <a class="pg-mono" href="{{ route('assets.show', $item->asset) }}">{{ $item->asset->asset_tag }}</a>
                                <div class="text-muted small">{{ $item->asset->name }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->location->name ?? '—' }}</td>
                        <td>{!! \App\Support\Labels::badge($item->status) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No equipment linked to this system.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="pg-card mb-3" id="incidents">
        <div class="pg-card-pad pb-0">
            <h2 class="pg-section-title mb-0">Incidents</h2>
            <p class="text-muted small mb-0">Operational events recorded against this system.</p>
        </div>
        <div class="table-responsive">
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Device</th>
                        <th>Severity</th>
                        <th>Case</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($system->incidents->sortByDesc('reported_at') as $incident)
                    <tr>
                        <td>
                            <div>{{ $incident->description }}</div>
                            @if ($incident->operational_impact)
                                <div class="text-muted small">{{ $incident->operational_impact }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($incident->equipment)
                                {{ $incident->equipment->equipment_type }}
                                @if ($incident->equipment->asset)
                                    <div>
                                        <a class="pg-mono" href="{{ route('assets.show', $incident->equipment->asset) }}">{{ $incident->equipment->asset->asset_tag }}</a>
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{!! \App\Support\Labels::badge($incident->severity) !!}</td>
                        <td>
                            @if ($incident->ticket)
                                <a href="{{ route('helpdesk.show', $incident->ticket) }}">{{ $incident->ticket->ticket_number }}</a>
                                @if ($incident->ticket->assignee)
                                    <div class="small">
                                        <a href="{{ route('people.show', $incident->ticket->assignee) }}">{{ $incident->ticket->assignee->name }}</a>
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ \App\Support\Format::date($incident->reported_at) }}
                            <div class="mt-1">{!! \App\Support\Labels::badge($incident->status) !!}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No incidents for this system.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
