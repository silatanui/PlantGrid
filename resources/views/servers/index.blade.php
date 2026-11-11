@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Servers</h1>
    <p class="pg-page-lead">Inventory of fictional servers. PlantGrid does not connect to them.</p>
    <div class="pg-card table-responsive my-3">
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="hostname" label="Hostname" :sort="$sort" route-name="servers.index" />
                    <x-sort-th column="server_role" label="Role" :sort="$sort" route-name="servers.index" />
                    <x-sort-th column="operating_system" label="System" :sort="$sort" route-name="servers.index" />
                    <th>Address</th>
                    <th>Services</th>
                    <x-sort-th column="status" label="Status" :sort="$sort" route-name="servers.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($servers as $server)
                <tr>
                    <td class="pg-mono"><a href="{{ route('servers.show', $server) }}">{{ $server->hostname }}</a></td>
                    <td>{{ $server->server_role }}</td>
                    <td>{{ $server->operating_system }} {{ $server->os_version }}</td>
                    <td class="pg-mono">{{ $server->ipAddress->ip_address ?? '-' }}</td>
                    <td>{{ $server->services->count() }}</td>
                    <td>{!! \App\Support\Labels::badge($server->status) !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if (auth()->user()->hasPermission('servers.manage'))
        <form class="pg-card pg-card-pad" method="post" action="{{ route('servers.store') }}">
            @csrf
            <h2 class="pg-section-title">Register a server record</h2>
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label" for="hostname">Hostname</label><input class="form-control pg-mono" id="hostname" name="hostname" required></div>
                <div class="col-md-3"><label class="form-label" for="server_role">Role</label><input class="form-control" id="server_role" name="server_role" required></div>
                <div class="col-md-3"><label class="form-label" for="operating_system">Operating system</label><input class="form-control" id="operating_system" name="operating_system" required></div>
                <div class="col-md-3"><label class="form-label" for="environment">Environment</label>
                    <select class="form-select" id="environment" name="environment">
                        <option value="simulated_production">Simulated production</option>
                        <option value="test">Test</option>
                        <option value="development">Development</option>
                    </select>
                </div>
                <input type="hidden" name="status" value="active">
            </div>
            <button class="btn btn-pg mt-3" type="submit">Save server</button>
        </form>
    @endif
@endsection
