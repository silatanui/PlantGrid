@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">{{ $device->hostname }}</h1>
    <p class="pg-page-lead">{{ \App\Support\Labels::status($device->device_type) }} · documented record</p>
    @include('partials.device-media', ['media' => \App\Support\DeviceMedia::forNetworkDevice($device->loadMissing('asset.category'))])
    <section class="pg-card pg-card-pad mt-3">
        <dl class="pg-kv">
            <dt>Status</dt><dd>{!! \App\Support\Labels::badge($device->status) !!}</dd>
            <dt>Management IP</dt><dd class="pg-mono">{{ $device->management_ip ?: '-' }}</dd>
            <dt>Model</dt><dd>{{ $device->manufacturer }} {{ $device->model }}</dd>
            <dt>Location</dt><dd>{{ $device->location->name ?? '-' }}</dd>
            <dt>Asset</dt><dd>@if ($device->asset)<a href="{{ route('assets.show', $device->asset) }}">{{ $device->asset->asset_tag }}</a>@else - @endif</dd>
            <dt>Notes</dt><dd>{{ $device->notes ?: '-' }}</dd>
        </dl>
    </section>
    <section class="pg-card mt-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Documented links</h2></div>
        <table class="pg-table">
            <tbody>
            @foreach ($device->sourceLinks as $link)
                <tr><td class="pg-mono">{{ $device->hostname }} → {{ $link->destination->hostname }}</td><td>{{ $link->connection_type }} {{ $link->port_or_interface }}</td></tr>
            @endforeach
            @foreach ($device->destinationLinks as $link)
                <tr><td class="pg-mono">{{ $link->source->hostname }} → {{ $device->hostname }}</td><td>{{ $link->connection_type }} {{ $link->port_or_interface }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
