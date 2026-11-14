@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Infrastructure</div>
            <h1 class="pg-page-title">Plant map</h1>
            <p class="pg-page-lead">Campus plan with gardens, paths, and doors. Zoom, pan, and click pins for device details.</p>
        </div>
        @if (auth()->user()->hasPermission('reports.view'))
            <div class="pg-page-intro-actions">
                <a class="btn btn-pg-line" href="{{ route('reports.index', ['report' => 'assets']) }}">Asset report</a>
            </div>
        @endif
    </section>

    @include('locations.partials.plant-map', [
        'zones' => $zones,
        'locations' => $locations,
        'activeZone' => $activeZone,
        'pins' => $pins,
    ])

    <section class="pg-card mt-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Locations</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Department</th>
                    <th>Assets</th>
                    <th>Network</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($locations as $place)
                <tr>
                    <td><a href="{{ route('locations.show', $place) }}">{{ $place->name }}</a></td>
                    <td class="pg-mono">{{ $place->code }}</td>
                    <td>{{ $place->department->name ?? '—' }}</td>
                    <td>{{ $place->assets_count }}</td>
                    <td>{{ $place->network_devices_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
