@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--production" aria-hidden="true">
            <i data-lucide="factory"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Infrastructure</div>
            <h1 class="pg-page-title">Production systems</h1>
            <p class="pg-page-lead">
                Manufacturing technology records, line equipment, and operational incidents.
                PlantGrid documents impact only — it never sends commands to plant equipment.
            </p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-pg-line" href="{{ route('helpdesk.index', ['q' => 'production']) }}">Open cases</a>
            <a class="btn btn-pg-line" href="{{ route('assets.index') }}">ICT assets</a>
            <a class="btn btn-pg-line" href="{{ route('maintenance.index') }}">Maintenance</a>
        </div>
    </section>

    <div class="pg-prod-kpis mb-3">
        <div class="pg-prod-kpi">
            <span class="label">Systems</span>
            <strong>{{ $summary['systems'] }}</strong>
            <span class="hint">Documented production tech</span>
        </div>
        <div class="pg-prod-kpi">
            <span class="label">Equipment</span>
            <strong>{{ $summary['equipment'] }}</strong>
            <span class="hint">{{ $summary['under_maintenance'] }} under maintenance</span>
        </div>
        <div class="pg-prod-kpi {{ $summary['open_incidents'] > 0 ? 'tone-warning' : '' }}">
            <span class="label">Open incidents</span>
            <strong>{{ $summary['open_incidents'] }}</strong>
            <span class="hint">Investigating or open</span>
        </div>
        <div class="pg-prod-kpi {{ $summary['downtime_minutes'] > 0 ? 'tone-critical' : '' }}">
            <span class="label">Open downtime</span>
            <strong>{{ number_format($summary['downtime_minutes']) }} m</strong>
            <span class="hint">Logged against open incidents</span>
        </div>
    </div>

    <section class="mb-3">
        <div class="d-flex justify-content-between align-items-end gap-2 flex-wrap mb-2">
            <div>
                <h2 class="pg-section-title mb-0">Systems</h2>
                <p class="text-muted small mb-0">Open a system for owner, vendor, equipment, and linked cases.</p>
            </div>
        </div>
        <div class="pg-prod-systems">
            @forelse ($systems as $system)
                <a class="pg-prod-system {{ $system->status === 'under_maintenance' ? 'is-attention' : '' }}" href="{{ route('production.show', $system) }}">
                    <div class="pg-prod-system-top">
                        <div>
                            <h3 class="pg-prod-system-title">{{ $system->name }}</h3>
                            <p class="pg-prod-system-type">{{ $system->system_type }}</p>
                        </div>
                        {!! \App\Support\Labels::badge($system->status) !!}
                    </div>
                    <dl class="pg-prod-system-meta">
                        <div>
                            <dt>Department</dt>
                            <dd>{{ $system->department->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Owner</dt>
                            <dd>
                                @if ($system->owner)
                                    <span class="pg-prod-linkish">{{ $system->owner->name }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Vendor</dt>
                            <dd>{{ $system->vendor->name ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div class="pg-prod-system-foot">
                        <span>{{ $system->equipment_count }} equipment</span>
                        <span class="{{ $system->open_incidents_count > 0 ? 'is-hot' : '' }}">
                            {{ $system->open_incidents_count }} open incident{{ $system->open_incidents_count === 1 ? '' : 's' }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="pg-card pg-card-pad text-muted">No production systems recorded yet.</div>
            @endforelse
        </div>
    </section>

    <div class="pg-prod-grid mb-3">
        <section class="pg-card">
            <div class="pg-card-pad pb-0">
                <h2 class="pg-section-title mb-0">Equipment register</h2>
                <p class="text-muted small mb-0">Linked ICT assets and plant locations for each device.</p>
            </div>
            <div class="table-responsive">
                <table class="pg-table">
                    <thead>
                        <tr>
                            <x-sort-th column="equipment_type" label="Equipment" :sort="$equipmentSort" route-name="production.index" />
                            <th>System</th>
                            <th>Asset</th>
                            <th>Location</th>
                            <x-sort-th column="status" label="Status" :sort="$equipmentSort" route-name="production.index" />
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($equipment as $item)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $item->equipment_type }}</div>
                                <div class="text-muted small">
                                    {{ trim(($item->manufacturer ?? '').' '.($item->model ?? '')) ?: 'No model on file' }}
                                </div>
                            </td>
                            <td>
                                @if ($item->system)
                                    <a href="{{ route('production.show', $item->system) }}">{{ $item->system->name }}</a>
                                @else
                                    —
                                @endif
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
                        <tr><td colspan="5" class="text-muted">No production equipment recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pg-card">
            <div class="pg-card-pad pb-0">
                <h2 class="pg-section-title mb-0">Related open cases</h2>
                <p class="text-muted small mb-0">Helpdesk cases tied to production incidents or equipment assets.</p>
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
                                    <div class="text-muted small">Requester</div>
                                @endif
                                @if ($case->assignee)
                                    <div class="mt-1">
                                        <a href="{{ route('people.show', $case->assignee) }}">{{ $case->assignee->name }}</a>
                                        <div class="text-muted small">Owner</div>
                                    </div>
                                @endif
                                @if (! $case->requester && ! $case->assignee)
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
                        <tr><td colspan="4" class="text-muted">No open production-linked cases.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="pg-card mb-3">
        <div class="pg-card-pad pb-0">
            <h2 class="pg-section-title mb-0">Incidents</h2>
            <p class="text-muted small mb-0">Operational impact records with links into systems, devices, and cases.</p>
        </div>
        <div class="table-responsive">
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>System / device</th>
                        <x-sort-th column="severity" label="Severity" :sort="$incidentSort" route-name="production.index" />
                        <th>Case / people</th>
                        <x-sort-th column="reported_at" label="Reported" :sort="$incidentSort" route-name="production.index" />
                    </tr>
                </thead>
                <tbody>
                @forelse ($incidents as $incident)
                    <tr>
                        <td>
                            <div>{{ $incident->description }}</div>
                            @if ($incident->operational_impact)
                                <div class="text-muted small">Impact: {{ $incident->operational_impact }}</div>
                            @endif
                            @if ($incident->downtime_minutes)
                                <div class="text-muted small">Downtime {{ number_format($incident->downtime_minutes) }} min</div>
                            @endif
                        </td>
                        <td>
                            @if ($incident->system)
                                <a href="{{ route('production.show', $incident->system) }}">{{ $incident->system->name }}</a>
                            @else
                                —
                            @endif
                            @if ($incident->equipment)
                                <div class="small mt-1">
                                    {{ $incident->equipment->equipment_type }}
                                    @if ($incident->equipment->asset)
                                        · <a class="pg-mono" href="{{ route('assets.show', $incident->equipment->asset) }}">{{ $incident->equipment->asset->asset_tag }}</a>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>{!! \App\Support\Labels::badge($incident->severity) !!}</td>
                        <td>
                            @if ($incident->ticket)
                                <a href="{{ route('helpdesk.show', $incident->ticket) }}">{{ $incident->ticket->ticket_number }}</a>
                                @if ($incident->ticket->assignee)
                                    <div class="small">
                                        Owner
                                        <a href="{{ route('people.show', $incident->ticket->assignee) }}">{{ $incident->ticket->assignee->name }}</a>
                                    </div>
                                @elseif ($incident->ticket->requester)
                                    <div class="small">
                                        Requester
                                        <a href="{{ route('people.show', $incident->ticket->requester) }}">{{ $incident->ticket->requester->name }}</a>
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
                    <tr><td colspan="5" class="text-muted">No production incidents recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
