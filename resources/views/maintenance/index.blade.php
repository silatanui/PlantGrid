@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--maintenance" aria-hidden="true">
            <i data-lucide="wrench"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Operations</div>
            <h1 class="pg-page-title">Maintenance</h1>
            <p class="pg-page-lead">
                Planned and corrective work orders for ICT assets, servers, network gear, and production equipment.
            </p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-pg-line" href="{{ route('assets.index', ['status' => 'under_maintenance']) }}">Assets in maintenance</a>
            <a class="btn btn-pg-line" href="{{ route('production.index') }}">Production</a>
            @if (auth()->user()->hasPermission('maintenance.manage'))
                <a class="btn btn-pg" href="#new-work-order">New work order</a>
            @endif
        </div>
    </section>

    <div class="pg-maint-kpis mb-3">
        <a class="pg-maint-kpi {{ ($filters['status'] ?? '') === '' ? 'is-active' : '' }}" href="{{ route('maintenance.index') }}">
            <span class="label">Open work</span>
            <strong>{{ $summary['open'] }}</strong>
            <span class="hint">Not completed or cancelled</span>
        </a>
        <a class="pg-maint-kpi {{ ($filters['status'] ?? '') === 'scheduled' ? 'is-active' : '' }}" href="{{ route('maintenance.index', ['status' => 'scheduled']) }}">
            <span class="label">Scheduled</span>
            <strong>{{ $summary['scheduled'] }}</strong>
            <span class="hint">Waiting to start</span>
        </a>
        <a class="pg-maint-kpi {{ ($filters['status'] ?? '') === 'in_progress' ? 'is-active' : '' }} {{ $summary['in_progress'] > 0 ? 'tone-warning' : '' }}" href="{{ route('maintenance.index', ['status' => 'in_progress']) }}">
            <span class="label">In progress</span>
            <strong>{{ $summary['in_progress'] }}</strong>
            <span class="hint">Active technicians</span>
        </a>
        <a class="pg-maint-kpi {{ ! empty($filters['overdue']) ? 'is-active' : '' }} {{ $summary['overdue'] > 0 ? 'tone-critical' : '' }}" href="{{ route('maintenance.index', ['overdue' => 1]) }}">
            <span class="label">Overdue</span>
            <strong>{{ $summary['overdue'] }}</strong>
            <span class="hint">Past scheduled time</span>
        </a>
        <a class="pg-maint-kpi {{ ($filters['status'] ?? '') === 'completed' ? 'is-active' : '' }}" href="{{ route('maintenance.index', ['status' => 'completed']) }}">
            <span class="label">Completed</span>
            <strong>{{ $summary['completed'] }}</strong>
            <span class="hint">Can be reopened if needed</span>
        </a>
    </div>

    <form class="pg-filters mb-3" method="get">
        <div>
            <label class="form-label" for="q">Search</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Subject or notes">
        </div>
        <div>
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any open or closed</option>
                @foreach (['scheduled', 'in_progress', 'pending_vendor', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\Labels::status($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">Any</option>
                @foreach (['preventive', 'corrective', 'inspection'] as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="priority">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">Any</option>
                @foreach (['critical', 'high', 'medium', 'low'] as $priority)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ \App\Support\Labels::status($priority) }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $sort->column }}">
        <input type="hidden" name="direction" value="{{ $sort->direction }}">
        <button class="btn btn-pg" type="submit">Apply</button>
    </form>

    <div class="pg-card table-responsive mb-3">
        <table class="pg-table pg-table-list pg-maint-table">
            <thead>
                <tr>
                    <x-sort-th column="subject" label="Work order" :sort="$sort" route-name="maintenance.index" />
                    <th>Linked record</th>
                    <th>Technician</th>
                    <x-sort-th column="maintenance_type" label="Type" :sort="$sort" route-name="maintenance.index" />
                    <x-sort-th column="priority" label="Priority" :sort="$sort" route-name="maintenance.index" />
                    <x-sort-th column="scheduled_at" label="When" :sort="$sort" route-name="maintenance.index" />
                    <x-sort-th column="status" label="Status" :sort="$sort" route-name="maintenance.index" />
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($records as $record)
                @php
                    $linked = $record->linkedTarget();
                    $isOverdue = $record->scheduled_at
                        && $record->scheduled_at->isPast()
                        && ! in_array($record->status, ['completed', 'cancelled'], true);
                @endphp
                <tr class="{{ $isOverdue ? 'is-overdue' : '' }}">
                    <td>
                        <div class="pg-maint-subject">
                            <strong>{{ $record->subject }}</strong>
                            @if ($record->vendor)
                                <span class="text-muted small">Vendor · {{ $record->vendor->name }}</span>
                            @elseif ($record->description)
                                <span class="text-muted small">{{ \Illuminate\Support\Str::limit($record->description, 72) }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if ($linked)
                            <div class="pg-maint-link">
                                <span class="pg-maint-kind">{{ $linked['kind'] }}</span>
                                @if ($linked['url'])
                                    <a class="pg-mono" href="{{ $linked['url'] }}">{{ $linked['label'] }}</a>
                                @else
                                    <span class="pg-mono">{{ $linked['label'] }}</span>
                                @endif
                                @if ($linked['detail'])
                                    <span class="text-muted small">{{ $linked['detail'] }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($record->technician)
                            <a href="{{ route('people.show', $record->technician) }}">{{ $record->technician->name }}</a>
                        @else
                            <span class="text-muted">Unassigned</span>
                        @endif
                    </td>
                    <td>{{ ucfirst($record->maintenance_type) }}</td>
                    <td>{!! \App\Support\Labels::badge($record->priority) !!}</td>
                    <td>
                        <div class="pg-maint-when">
                            <span>{{ \App\Support\Format::date($record->scheduled_at) }}</span>
                            @if ($isOverdue)
                                <span class="pg-maint-overdue">Overdue</span>
                            @elseif ($record->completed_at)
                                <span class="text-muted small">Done {{ \App\Support\Format::date($record->completed_at) }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{!! \App\Support\Labels::badge($record->status) !!}</td>
                    <td>
                        @if (auth()->user()->hasPermission('maintenance.manage'))
                            <div class="pg-maint-actions">
                                @if ($record->status !== 'completed' && $record->status !== 'cancelled')
                                    <form method="post" action="{{ route('maintenance.update', $record) }}" onsubmit="return confirm('Mark this work order as completed?')">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button class="btn btn-sm btn-pg" type="submit">Complete</button>
                                    </form>
                                    @if ($record->status !== 'in_progress')
                                        <form method="post" action="{{ route('maintenance.update', $record) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="in_progress">
                                            <button class="btn btn-sm btn-pg-line" type="submit">Start</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($record->status === 'completed')
                                    <form method="post" action="{{ route('maintenance.update', $record) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="in_progress">
                                        <button class="btn btn-sm btn-pg-line" type="submit" title="Undo complete if this was marked done by mistake">Reopen</button>
                                    </form>
                                @elseif ($record->status === 'cancelled')
                                    <form method="post" action="{{ route('maintenance.update', $record) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="scheduled">
                                        <button class="btn btn-sm btn-pg-line" type="submit">Restore</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-muted">No maintenance records match these filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-3">{{ $records->links() }}</div>

    @if (auth()->user()->hasPermission('maintenance.manage'))
        <form class="pg-card pg-card-pad pg-maint-form" id="new-work-order" method="post" action="{{ route('maintenance.store') }}">
            @csrf
            <div class="pg-maint-form-head">
                <div>
                    <h2 class="pg-section-title mb-0">New work order</h2>
                    <p class="text-muted small mb-0">Link at least one asset, server, network device, or production equipment record.</p>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="subject">Subject</label>
                    <input class="form-control" id="subject" name="subject" value="{{ old('subject') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="maintenance_type">Type</label>
                    <select class="form-select" id="maintenance_type" name="maintenance_type">
                        <option value="preventive" @selected(old('maintenance_type') === 'preventive')>Preventive</option>
                        <option value="corrective" @selected(old('maintenance_type', 'corrective') === 'corrective')>Corrective</option>
                        <option value="inspection" @selected(old('maintenance_type') === 'inspection')>Inspection</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="low" @selected(old('priority') === 'low')>Low</option>
                        <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium</option>
                        <option value="high" @selected(old('priority') === 'high')>High</option>
                        <option value="critical" @selected(old('priority') === 'critical')>Critical</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="asset_id">Asset</label>
                    <select class="form-select" id="asset_id" name="asset_id">
                        <option value="">None</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((string) old('asset_id') === (string) $asset->id)>{{ $asset->asset_tag }} · {{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="server_id">Server</label>
                    <select class="form-select" id="server_id" name="server_id">
                        <option value="">None</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="network_device_id">Network device</label>
                    <select class="form-select" id="network_device_id" name="network_device_id">
                        <option value="">None</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) old('network_device_id') === (string) $device->id)>{{ $device->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="production_equipment_id">Production equipment</label>
                    <select class="form-select" id="production_equipment_id" name="production_equipment_id">
                        <option value="">None</option>
                        @foreach ($equipment as $item)
                            <option value="{{ $item->id }}" @selected((string) old('production_equipment_id') === (string) $item->id)>
                                {{ $item->equipment_type }}@if ($item->system) · {{ $item->system->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="assigned_technician_id">Technician</label>
                    <select class="form-select" id="assigned_technician_id" name="assigned_technician_id">
                        <option value="">Unassigned</option>
                        @foreach ($technicians as $person)
                            <option value="{{ $person->id }}" @selected((string) old('assigned_technician_id') === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="vendor_id">Vendor</label>
                    <select class="form-select" id="vendor_id" name="vendor_id">
                        <option value="">None</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected((string) old('vendor_id') === (string) $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="scheduled_at">Scheduled</label>
                    <input class="form-control" id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}">
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <label class="form-label mb-0" for="description">Work to perform</label>
                        <div class="pg-ai-field-actions" data-ai-field-actions>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="description">Refine with AI</button>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="description">Paraphrase</button>
                        </div>
                    </div>
                    <textarea class="form-control mt-2" id="description" name="description" rows="3" data-ai-assist>{{ old('description') }}</textarea>
                </div>
                <input type="hidden" name="status" value="scheduled">
            </div>
            <button class="btn btn-pg mt-3" type="submit">Save work order</button>
        </form>
    @endif
@endsection
