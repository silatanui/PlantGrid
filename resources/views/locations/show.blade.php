@extends('layouts.app')

@section('content')
    @include('partials.nav-back-crumb')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Location</div>
            <h1 class="pg-page-title">{{ $location->name }}</h1>
            <p class="pg-page-lead">
                <span class="pg-mono">{{ $location->code }}</span>
                @if ($location->department)
                    · {{ $location->department->name }}
                @endif
                @if ($location->description)
                    - {{ $location->description }}
                @endif
            </p>
        </div>
        <div class="pg-page-intro-actions">
            <a class="btn btn-pg-line" href="{{ route('locations.index') }}">Full plant map</a>
            @if (auth()->user()->hasPermission('reports.view'))
                <a class="btn btn-pg-line" href="{{ route('reports.index', ['report' => 'assets']) }}">Reports</a>
            @endif
        </div>
    </section>

    @include('locations.partials.plant-map', [
        'zones' => $zones,
        'locations' => $locations,
        'activeZone' => $activeZone,
        'pins' => $pins,
    ])

    <div class="pg-list-views mt-3 mb-2">
        <a class="{{ $filter === 'all' ? 'active' : '' }}" href="{{ route('locations.show', ['location' => $location, 'filter' => 'all']) }}">All ({{ $assetCount + $deviceCount }})</a>
        @if (auth()->user()->hasPermission('assets.view'))
            <a class="{{ $filter === 'assets' ? 'active' : '' }}" href="{{ route('locations.show', ['location' => $location, 'filter' => 'assets']) }}">Assets ({{ $assetCount }})</a>
        @endif
        @if (auth()->user()->hasPermission('network.view'))
            <a class="{{ $filter === 'network' ? 'active' : '' }}" href="{{ route('locations.show', ['location' => $location, 'filter' => 'network']) }}">Network ({{ $deviceCount }})</a>
        @endif
        @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']))
            <a class="{{ $filter === 'cases' ? 'active' : '' }}" href="{{ route('locations.show', ['location' => $location, 'filter' => 'cases']) }}">Cases ({{ $caseCount }})</a>
        @endif
    </div>

    <div class="row g-3">
        @if (auth()->user()->hasPermission('assets.view') && ! in_array($filter, ['network', 'cases'], true))
            <div class="col-lg-6">
                <section class="pg-card">
                    <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Assets here</h2></div>
                    <table class="pg-table">
                        <thead><tr><th>Tag</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td><a class="pg-mono" href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a></td>
                                <td><a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a></td>
                                <td>{!! \App\Support\Labels::badge($asset->status) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No assets at this location.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        @endif

        @if (auth()->user()->hasPermission('network.view') && ! in_array($filter, ['assets', 'cases'], true))
            <div class="col-lg-6">
                <section class="pg-card">
                    <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Network devices here</h2></div>
                    <table class="pg-table">
                        <thead><tr><th>Hostname</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td><a class="pg-mono" href="{{ route('network.show', $device) }}">{{ $device->hostname }}</a></td>
                                <td>{{ \App\Support\Labels::status((string) $device->device_type) }}</td>
                                <td>{!! \App\Support\Labels::badge($device->status) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No network devices at this location.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        @endif

        @if (auth()->user()->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']) && ! in_array($filter, ['assets', 'network'], true))
            <div class="{{ $filter === 'cases' ? 'col-12' : 'col-12' }}">
                <section class="pg-card">
                    <div class="pg-card-pad d-flex flex-wrap justify-content-between gap-2 align-items-center">
                        <h2 class="pg-section-title mb-0">Cases linked to assets here</h2>
                        @if ($filter !== 'cases' && $caseCount > ($cases?->count() ?? 0))
                            <a class="btn btn-pg-line btn-sm" href="{{ route('locations.show', ['location' => $location, 'filter' => 'cases']) }}">View all cases</a>
                        @endif
                    </div>
                    <table class="pg-table">
                        <thead>
                            <tr>
                                <th>Case</th>
                                <th>Subject</th>
                                <th>Asset</th>
                                <th>Owner</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($cases as $case)
                            <tr>
                                <td><a class="pg-mono" href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a></td>
                                <td><a href="{{ route('helpdesk.show', $case) }}">{{ $case->subject }}</a></td>
                                <td>
                                    @if ($case->asset)
                                        <a class="pg-mono" href="{{ route('assets.show', $case->asset) }}">{{ $case->asset->asset_tag }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $case->assignee->name ?? 'Unassigned' }}</td>
                                <td>{!! \App\Support\Labels::badge($case->status) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No cases currently linked to assets at this location.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        @endif
    </div>
@endsection
