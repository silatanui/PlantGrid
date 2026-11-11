@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Production systems</h1>
    <p class="pg-page-lead">Technology that supports manufacturing. PlantGrid records incidents only and never sends commands to equipment.</p>
    <div class="row g-3 my-1">
        @foreach ($systems as $system)
            <div class="col-md-4">
                <a class="pg-card pg-card-pad d-block text-decoration-none h-100" href="{{ route('production.show', $system) }}">
                    <h2 class="pg-card-title">{{ $system->name }}</h2>
                    <p class="text-muted mb-2">{{ $system->system_type }}</p>
                    {!! \App\Support\Labels::badge($system->status) !!}
                </a>
            </div>
        @endforeach
    </div>
    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Equipment</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="equipment_type" label="Type" :sort="$equipmentSort" route-name="production.index" />
                    <th>System</th>
                    <th>Asset</th>
                    <th>Location</th>
                    <x-sort-th column="status" label="Status" :sort="$equipmentSort" route-name="production.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($equipment as $item)
                <tr>
                    <td>{{ $item->equipment_type }}</td>
                    <td>{{ $item->system->name ?? '-' }}</td>
                    <td class="pg-mono">{{ $item->asset->asset_tag ?? '-' }}</td>
                    <td>{{ $item->location->name ?? '-' }}</td>
                    <td>{!! \App\Support\Labels::badge($item->status) !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="pg-card mb-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Incidents</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <x-sort-th column="severity" label="Severity" :sort="$incidentSort" route-name="production.index" />
                    <th>Ticket</th>
                    <x-sort-th column="reported_at" label="Reported" :sort="$incidentSort" route-name="production.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($incidents as $incident)
                <tr>
                    <td>{{ $incident->description }}</td>
                    <td>{!! \App\Support\Labels::badge($incident->severity) !!}</td>
                    <td>@if ($incident->ticket)<a href="{{ route('helpdesk.show', $incident->ticket) }}">{{ $incident->ticket->ticket_number }}</a>@else - @endif</td>
                    <td>
                        {{ \App\Support\Format::date($incident->reported_at) }}
                        <div class="mt-1">{!! \App\Support\Labels::badge($incident->status) !!}</div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
