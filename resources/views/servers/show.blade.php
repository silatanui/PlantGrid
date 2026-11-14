@extends('layouts.app')

@section('content')
    @include('partials.nav-back-crumb')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Server</div>
            <h1 class="pg-page-title">{{ $server->hostname }}</h1>
            <p class="pg-page-lead">{{ $server->server_role }} · {{ str_replace('_', ' ', $server->environment) }}</p>
        </div>
    </section>
    @include('partials.device-media', ['media' => \App\Support\DeviceMedia::forServer($server->loadMissing('asset.category'))])
    <section class="pg-card pg-card-pad my-3">
        <dl class="pg-kv">
            <dt>Status</dt><dd>{!! \App\Support\Labels::badge($server->status) !!}</dd>
            <dt>System</dt><dd>{{ $server->operating_system }} {{ $server->os_version }}</dd>
            <dt>CPU / RAM / Disk</dt><dd>{{ $server->cpu_cores }} cores · {{ $server->ram_gb }} GB · {{ $server->storage_gb }} GB</dd>
            <dt>Address</dt><dd class="pg-mono">{{ $server->ipAddress->ip_address ?? '-' }}</dd>
            <dt>Asset</dt><dd>@if ($server->asset)<a href="{{ route('assets.show', $server->asset) }}">{{ $server->asset->asset_tag }}</a>@else - @endif</dd>
            <dt>Notes</dt><dd>{{ $server->notes }}</dd>
        </dl>
    </section>
    <section class="pg-card mb-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Services</h2></div>
        <table class="pg-table">
            <thead><tr><th>Service</th><th>Port</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($server->services as $service)
                <tr>
                    <td>{{ $service->service_name }}<div class="text-muted">{{ $service->description }}</div></td>
                    <td class="pg-mono">{{ $service->port }} {{ $service->protocol }}</td>
                    <td>{!! \App\Support\Labels::badge($service->status) !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @if (auth()->user()->hasPermission('servers.manage'))
        <form class="pg-card pg-card-pad" method="post" action="{{ route('servers.services.store', $server) }}">
            @csrf
            <h2 class="pg-section-title">Add a service record</h2>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label" for="service_name">Name</label><input class="form-control" id="service_name" name="service_name" required></div>
                <div class="col-md-2"><label class="form-label" for="port">Port</label><input class="form-control" id="port" name="port" type="number"></div>
                <div class="col-md-2"><label class="form-label" for="protocol">Protocol</label><select class="form-select" id="protocol" name="protocol"><option value="tcp">TCP</option><option value="udp">UDP</option></select></div>
                <div class="col-md-4"><label class="form-label" for="description">Description</label><input class="form-control" id="description" name="description"></div>
                <input type="hidden" name="status" value="running">
            </div>
            <button class="btn btn-pg mt-3" type="submit">Save service</button>
        </form>
    @endif
@endsection
