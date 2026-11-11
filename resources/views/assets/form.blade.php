@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title mb-3">{{ $title }}</h1>
    <form class="pg-card pg-card-pad" method="post" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
        @csrf
        @if ($asset->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="asset_tag">Asset tag</label>
                <input class="form-control pg-mono" id="asset_tag" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" placeholder="Leave blank to generate">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $asset->name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="asset_category_id">Category</label>
                <select class="form-select" id="asset_category_id" name="asset_category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('asset_category_id', $asset->asset_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vendor_id">Vendor</label>
                <select class="form-select" id="vendor_id" name="vendor_id">
                    <option value="">None</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected(old('vendor_id', $asset->vendor_id) == $vendor->id)>{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="manufacturer">Manufacturer</label>
                <input class="form-control" id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $asset->manufacturer) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="model">Model</label>
                <input class="form-control" id="model" name="model" value="{{ old('model', $asset->model) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="serial_number">Serial number</label>
                <input class="form-control pg-mono" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="department_id">Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">Unassigned</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id', $asset->department_id) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="location_id">Location</label>
                <select class="form-select" id="location_id" name="location_id">
                    <option value="">Unassigned</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $asset->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="purchase_date">Purchase date</label>
                <input class="form-control" id="purchase_date" name="purchase_date" type="date" value="{{ old('purchase_date', optional($asset->purchase_date)->toDateString()) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="warranty_expiry">Warranty expiry</label>
                <input class="form-control" id="warranty_expiry" name="warranty_expiry" type="date" value="{{ old('warranty_expiry', optional($asset->warranty_expiry)->toDateString()) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="condition">Condition</label>
                <select class="form-select" id="condition" name="condition">
                    @foreach (['good', 'fair', 'poor'] as $condition)
                        <option value="{{ $condition }}" @selected(old('condition', $asset->condition ?: 'good') === $condition)>{{ ucfirst($condition) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    @foreach (['active', 'spare', 'under_maintenance', 'damaged', 'lost', 'retired'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $asset->status ?: 'active') === $status)>{{ \App\Support\Labels::status($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <label class="form-label mb-0" for="notes">Notes</label>
                    <div class="pg-ai-field-actions" data-ai-field-actions>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="notes">Refine with AI</button>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="notes">Paraphrase</button>
                    </div>
                </div>
                <textarea class="form-control mt-2" id="notes" name="notes" rows="3" data-ai-assist data-mentions placeholder="Asset notes. Use @people and tags like PG-LBL-0142…">{{ old('notes', $asset->notes) }}</textarea>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-pg" type="submit">Save asset</button>
            <a class="btn btn-pg-line" href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}">Cancel</a>
        </div>
    </form>
@endsection
