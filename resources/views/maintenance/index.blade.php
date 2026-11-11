@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Maintenance</h1>
    <p class="pg-page-lead">Planned and corrective work for ICT and production-technology records.</p>
    <div class="pg-card table-responsive my-3">
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="subject" label="Subject" :sort="$sort" route-name="maintenance.index" />
                    <th>Linked record</th>
                    <x-sort-th column="maintenance_type" label="Type" :sort="$sort" route-name="maintenance.index" />
                    <x-sort-th column="scheduled_at" label="When" :sort="$sort" route-name="maintenance.index" />
                    <x-sort-th column="status" label="Status" :sort="$sort" route-name="maintenance.index" />
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($records as $record)
                <tr>
                    <td>{{ $record->subject }}</td>
                    <td>{{ $record->asset->asset_tag ?? $record->server->hostname ?? $record->networkDevice->hostname ?? $record->equipment->equipment_type ?? '-' }}</td>
                    <td>{{ ucfirst($record->maintenance_type) }}</td>
                    <td>{{ \App\Support\Format::date($record->scheduled_at) }}</td>
                    <td>{!! \App\Support\Labels::badge($record->status) !!}</td>
                    <td>
                        @if (auth()->user()->hasPermission('maintenance.manage') && $record->status !== 'completed')
                            <form method="post" action="{{ route('maintenance.update', $record) }}">
                                @csrf
                                <input type="hidden" name="status" value="completed">
                                <button class="btn btn-sm btn-pg-line" type="submit">Complete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mb-3">{{ $records->links() }}</div>
    @if (auth()->user()->hasPermission('maintenance.manage'))
        <form class="pg-card pg-card-pad" method="post" action="{{ route('maintenance.store') }}">
            @csrf
            <h2 class="pg-section-title">New work order</h2>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" required></div>
                <div class="col-md-3"><label class="form-label" for="maintenance_type">Type</label>
                    <select class="form-select" id="maintenance_type" name="maintenance_type"><option value="preventive">Preventive</option><option value="corrective">Corrective</option><option value="inspection">Inspection</option></select>
                </div>
                <div class="col-md-3"><label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select>
                </div>
                <div class="col-md-4"><label class="form-label" for="asset_id">Asset</label>
                    <select class="form-select" id="asset_id" name="asset_id"><option value="">None</option>@foreach ($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_tag }}</option>@endforeach</select>
                </div>
                <div class="col-md-4"><label class="form-label" for="scheduled_at">Scheduled</label><input class="form-control" id="scheduled_at" type="datetime-local" name="scheduled_at"></div>
                <div class="col-md-4"><label class="form-label" for="assigned_technician_id">Technician</label>
                    <select class="form-select" id="assigned_technician_id" name="assigned_technician_id"><option value="">Unassigned</option>@foreach ($technicians as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select>
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <label class="form-label mb-0" for="description">Work to perform</label>
                        <div class="pg-ai-field-actions" data-ai-field-actions>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="description">Refine with AI</button>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="description">Paraphrase</button>
                        </div>
                    </div>
                    <textarea class="form-control mt-2" id="description" name="description" rows="2" data-ai-assist></textarea>
                </div>
                <input type="hidden" name="status" value="scheduled">
            </div>
            <button class="btn btn-pg mt-3" type="submit">Save work order</button>
        </form>
    @endif
@endsection
