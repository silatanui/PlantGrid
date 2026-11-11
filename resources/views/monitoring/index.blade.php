@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Monitoring</h1>
    <p class="pg-page-lead">All readings on this page are simulated. The active scenario is <strong>{{ $scenario }}</strong>.</p>
    <div class="pg-alert pg-alert-{{ $banner['tone'] }}">{{ $banner['text'] }}</div>

    @if (auth()->user()->hasPermission('monitoring.manage'))
        <form class="d-flex flex-wrap gap-2 mb-3" method="post" action="{{ route('monitoring.scenario') }}">
            @csrf
            <label class="visually-hidden" for="scenario">Scenario</label>
            <select class="form-select" id="scenario" name="scenario" style="max-width: 220px;">
                @foreach (['normal', 'degraded', 'outage'] as $option)
                    <option value="{{ $option }}" @selected($scenario === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <button class="btn btn-pg" type="submit">Apply scenario</button>
        </form>
        <form class="mb-3" method="post" action="{{ route('monitoring.collect') }}">
            @csrf
            <button class="btn btn-pg-line" type="submit">Collect a sample now</button>
        </form>
    @endif

    <form class="mb-3" method="get">
        <label class="form-label" for="target">Chart target</label>
        <select class="form-select" id="target" name="target" style="max-width: 320px;" onchange="this.form.submit()">
            @foreach ($servers as $server)
                <option value="server:{{ $server->id }}" @selected($selected === 'server:'.$server->id)>
                    {{ $server->hostname }}@if ($server->hostname === $degradedHost) (degraded demo)@endif
                </option>
            @endforeach
            @foreach ($devices as $device)
                <option value="network_device:{{ $device->id }}" @selected($selected === 'network_device:'.$device->id)>
                    {{ $device->hostname }}@if ($device->hostname === $outageHost) (outage demo)@endif
                </option>
            @endforeach
        </select>
        @if ($selected !== $recommendedTarget)
            <p class="text-muted small mt-2 mb-0">
                Recommended for {{ $scenario }}:
                <a href="{{ route('monitoring.index', ['target' => $recommendedTarget]) }}">open the affected host</a>
            </p>
        @endif
    </form>

    <div class="pg-monitor-kpis mb-3 {{ $isOutageTarget ? 'is-outage' : '' }}">
        <div>
            <span class="label">Target</span>
            <strong>{{ $targetHostname }}</strong>
            <span class="hint">{{ $targetType === 'server' ? 'Server' : 'Network device' }}</span>
        </div>
        <div class="{{ $isOutageTarget || (isset($availability) && (float) $availability < 100) ? 'tone-critical' : '' }}">
            <span class="label">Availability</span>
            <strong>{{ isset($availability) ? rtrim(rtrim(number_format((float) $availability, 1), '0'), '.').'%' : '-' }}</strong>
            <span class="hint">{{ $isOutageTarget ? 'Simulated outage' : 'Latest sample' }}</span>
        </div>
        @php
            $packet = optional($readings->get('packet_loss'))->value;
            $response = optional($readings->get('response_time'))->value;
            $disk = optional($readings->get('disk'))->value;
            $cpu = optional($readings->get('cpu'))->value;
        @endphp
        @if ($targetType === 'network_device')
            <div class="{{ $isOutageTarget ? 'tone-critical' : '' }}">
                <span class="label">Packet loss</span>
                <strong>{{ isset($packet) ? rtrim(rtrim(number_format((float) $packet, 1), '0'), '.').'%' : '-' }}</strong>
                <span class="hint">Latest sample</span>
            </div>
            <div>
                <span class="label">Response</span>
                <strong>{{ isset($response) ? rtrim(rtrim(number_format((float) $response, 0), '0'), '.').' ms' : '-' }}</strong>
                <span class="hint">{{ $isOutageTarget ? 'No response' : 'Latest sample' }}</span>
            </div>
        @else
            <div>
                <span class="label">CPU</span>
                <strong>{{ isset($cpu) ? rtrim(rtrim(number_format((float) $cpu, 1), '0'), '.').'%' : '-' }}</strong>
                <span class="hint">Latest sample</span>
            </div>
            <div class="{{ $scenario === 'degraded' && $targetHostname === $degradedHost ? 'tone-warning' : '' }}">
                <span class="label">Disk</span>
                <strong>{{ isset($disk) ? rtrim(rtrim(number_format((float) $disk, 1), '0'), '.').'%' : '-' }}</strong>
                <span class="hint">{{ $scenario === 'degraded' && $targetHostname === $degradedHost ? 'Degraded pressure' : 'Latest sample' }}</span>
            </div>
        @endif
    </div>

    <section class="pg-card pg-card-pad mb-3 {{ $isOutageTarget ? 'pg-monitor-outage' : '' }}">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
            <div>
                <h2 class="pg-section-title mb-0">Simulated history · {{ $targetHostname }}</h2>
                <p class="pg-page-lead mb-0">
                    @if ($targetType === 'network_device')
                        Availability, response time and packet loss for this device.
                    @else
                        CPU, memory, disk and availability for this server.
                    @endif
                </p>
            </div>
            @if ($isOutageTarget)
                <span class="pg-badge pg-badge-critical">Outage visible</span>
            @endif
        </div>
        <div class="pg-chart" data-chart='@json($chart)'><canvas></canvas></div>
    </section>

    <section class="pg-card">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Alerts</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="message" label="Message" :sort="$alertSort" route-name="monitoring.index" />
                    <x-sort-th column="severity" label="Severity" :sort="$alertSort" route-name="monitoring.index" />
                    <x-sort-th column="status" label="Status" :sort="$alertSort" route-name="monitoring.index" />
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($alerts as $alert)
                <tr>
                    <td>{{ $alert->message }}<div class="text-muted">{{ \App\Support\Format::date($alert->triggered_at) }} · {{ $alert->source }}</div></td>
                    <td>{!! \App\Support\Labels::badge($alert->severity) !!}</td>
                    <td>{!! \App\Support\Labels::badge($alert->status) !!}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('alerts.acknowledge') && $alert->status === 'open')
                            <form method="post" action="{{ route('monitoring.acknowledge', $alert) }}">@csrf<button class="btn btn-sm btn-pg-line" type="submit">Acknowledge</button></form>
                        @endif
                        @if (auth()->user()->hasPermission('monitoring.manage') && $alert->status !== 'resolved')
                            <form method="post" action="{{ route('monitoring.resolve', $alert) }}">@csrf<button class="btn btn-sm btn-pg-line mt-1" type="submit">Resolve</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">No alerts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
