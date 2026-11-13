@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--assets" aria-hidden="true">
            <i data-lucide="boxes"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Assets</div>
            <h1 class="pg-page-title">ICT assets</h1>
            <p class="pg-page-lead">Computers, servers, network gear and production devices in the fictional register.</p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-sm btn-pg-line" href="{{ route('assets.export', request()->query()) }}">Export CSV</a>
            @if (auth()->user()->hasPermission('assets.create'))
                <a class="btn btn-sm btn-pg" href="{{ route('assets.create') }}">Register asset</a>
            @endif
        </div>
    </section>
    <form class="pg-filters mb-3" method="get">
        <div>
            <label class="form-label" for="q">Search</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any</option>
                @foreach (['active', 'spare', 'under_maintenance', 'damaged', 'lost', 'retired'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\Labels::status($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="category">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">Any</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="department">Department</label>
            <select class="form-select" id="department" name="department">
                <option value="">Any</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $sort->column }}">
        <input type="hidden" name="direction" value="{{ $sort->direction }}">
        <button class="btn btn-pg" type="submit">Apply</button>
    </form>
    <div class="pg-card table-responsive">
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="asset_tag" label="Tag" :sort="$sort" route-name="assets.index" />
                    <x-sort-th column="name" label="Name" :sort="$sort" route-name="assets.index" />
                    <th>Category</th>
                    <th>Department</th>
                    <th>Location</th>
                    <x-sort-th column="status" label="Status" :sort="$sort" route-name="assets.index" />
                </tr>
            </thead>
            <tbody>
            @forelse ($assets as $asset)
                @php $media = \App\Support\DeviceMedia::forAsset($asset); @endphp
                <tr>
                    <td class="pg-mono"><a href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a></td>
                    <td>
                        <div class="pg-asset-row">
                            @if ($media['image'])
                                <img class="pg-asset-thumb" src="{{ $media['image'] }}" alt="" loading="lazy">
                            @endif
                            <div>
                                <a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a>
                                <div class="text-muted small">{{ $asset->manufacturer }} {{ $asset->model }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $asset->category->name ?? '-' }}</td>
                    <td>{{ $asset->department->code ?? '-' }}</td>
                    <td>
                        @if ($asset->location)
                            <a href="{{ route('locations.show', $asset->location) }}">{{ $asset->location->name }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{!! \App\Support\Labels::badge($asset->status) !!}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No assets match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $assets->links() }}</div>
@endsection
