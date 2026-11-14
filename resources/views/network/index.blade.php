@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Infrastructure</div>
            <h1 class="pg-page-title">Network</h1>
            <p class="pg-page-lead">Documented devices, subnets, VLANs and addresses. Nothing is scanned or configured on a live network.</p>
        </div>
    </section>
    <div class="pg-page-tabs">
        <ul class="nav nav-pills gap-2">
            @foreach (['devices' => 'Devices', 'subnets' => 'Subnets', 'vlans' => 'VLANs', 'addresses' => 'IP addresses', 'topology' => 'Topology'] as $key => $label)
                <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('network.index', ['tab' => $key]) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
    <style>.nav-pills .nav-link { border: 1px solid var(--pg-border); border-radius: var(--pg-radius); color: var(--pg-text); background: #f7f9fb; } .nav-pills .nav-link.active { background: var(--pg-navy); border-color: var(--pg-navy); color: #fff; }</style>

    @if ($tab === 'devices')
        <div class="pg-card table-responsive mb-3">
            <table class="pg-table">
                <thead>
                    <tr>
                        <x-sort-th column="hostname" label="Hostname" :sort="$sort" />
                        <x-sort-th column="device_type" label="Type" :sort="$sort" />
                        <x-sort-th column="management_ip" label="Management IP" :sort="$sort" />
                        <th>Location</th>
                        <x-sort-th column="status" label="Status" :sort="$sort" />
                    </tr>
                </thead>
                <tbody>
                @foreach ($devices as $device)
                    <tr>
                        <td class="pg-mono"><a href="{{ route('network.show', $device) }}">{{ $device->hostname }}</a></td>
                        <td>{{ \App\Support\Labels::status($device->device_type) }}</td>
                        <td class="pg-mono">{{ $device->management_ip }}</td>
                        <td>
                            @if ($device->location)
                                <a href="{{ route('locations.show', $device->location) }}">{{ $device->location->name }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{!! \App\Support\Labels::badge($device->status) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if (auth()->user()->hasPermission('network.manage'))
            <form class="pg-card pg-card-pad" method="post" action="{{ route('network.devices.store') }}">
                @csrf
                <h2 class="pg-section-title">Add device</h2>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label" for="hostname">Hostname</label><input class="form-control pg-mono" id="hostname" name="hostname" required></div>
                    <div class="col-md-3"><label class="form-label" for="device_type">Type</label>
                        <select class="form-select" id="device_type" name="device_type">
                            @foreach (['router', 'switch', 'firewall', 'wireless_access_point', 'appliance'] as $type)
                                <option value="{{ $type }}">{{ \App\Support\Labels::status($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label" for="management_ip">Management IP</label><input class="form-control pg-mono" id="management_ip" name="management_ip"></div>
                    <div class="col-md-3"><label class="form-label" for="location_id">Location</label>
                        <select class="form-select" id="location_id" name="location_id"><option value="">None</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
                    </div>
                    <input type="hidden" name="status" value="active">
                </div>
                <button class="btn btn-pg mt-3" type="submit">Save device</button>
            </form>
        @endif
    @endif

    @if ($tab === 'subnets')
        <div class="pg-card table-responsive mb-3">
            <table class="pg-table">
                <thead>
                    <tr>
                        <x-sort-th column="name" label="Name" :sort="$sort" />
                        <x-sort-th column="network_address" label="Network" :sort="$sort" />
                        <th>Gateway</th>
                        <th>Addresses</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($subnets as $subnet)
                    <tr><td>{{ $subnet->name }}</td><td class="pg-mono">{{ $subnet->label() }}</td><td class="pg-mono">{{ $subnet->gateway }}</td><td>{{ $subnet->ip_addresses_count }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if (auth()->user()->hasPermission('network.manage'))
            <form class="pg-card pg-card-pad" method="post" action="{{ route('network.subnets.store') }}">
                @csrf
                <h2 class="pg-section-title">Add subnet</h2>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required></div>
                    <div class="col-md-3"><label class="form-label" for="network_address">Network</label><input class="form-control pg-mono" id="network_address" name="network_address" required></div>
                    <div class="col-md-2"><label class="form-label" for="cidr">CIDR</label><input class="form-control" id="cidr" name="cidr" type="number" min="8" max="30" required></div>
                    <div class="col-md-3"><label class="form-label" for="gateway">Gateway</label><input class="form-control pg-mono" id="gateway" name="gateway"></div>
                </div>
                <button class="btn btn-pg mt-3" type="submit">Save subnet</button>
            </form>
        @endif
    @endif

    @if ($tab === 'vlans')
        <div class="pg-card table-responsive mb-3">
            <table class="pg-table">
                <thead>
                    <tr>
                        <x-sort-th column="vlan_number" label="VLAN" :sort="$sort" />
                        <x-sort-th column="name" label="Name" :sort="$sort" />
                        <th>Subnet</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($vlans as $vlan)
                    <tr><td class="pg-mono">{{ $vlan->vlan_number }}</td><td>{{ $vlan->name }}</td><td>{{ $vlan->subnet?->label() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if (auth()->user()->hasPermission('network.manage'))
            <form class="pg-card pg-card-pad" method="post" action="{{ route('network.vlans.store') }}">
                @csrf
                <h2 class="pg-section-title">Add VLAN</h2>
                <div class="row g-2">
                    <div class="col-md-2"><label class="form-label" for="vlan_number">Number</label><input class="form-control" id="vlan_number" name="vlan_number" type="number" required></div>
                    <div class="col-md-4"><label class="form-label" for="vlan_name">Name</label><input class="form-control" id="vlan_name" name="name" required></div>
                    <div class="col-md-4"><label class="form-label" for="subnet_id">Subnet</label>
                        <select class="form-select" id="subnet_id" name="subnet_id"><option value="">None</option>@foreach ($subnets as $subnet)<option value="{{ $subnet->id }}">{{ $subnet->name }}</option>@endforeach</select>
                    </div>
                </div>
                <button class="btn btn-pg mt-3" type="submit">Save VLAN</button>
            </form>
        @endif
    @endif

    @if ($tab === 'addresses')
        <div class="pg-card table-responsive mb-3">
            <table class="pg-table">
                <thead>
                    <tr>
                        <x-sort-th column="ip_address" label="Address" :sort="$sort" />
                        <th>Subnet</th>
                        <x-sort-th column="address_type" label="Type" :sort="$sort" />
                        <th>Assigned to</th>
                        <x-sort-th column="status" label="Status" :sort="$sort" />
                    </tr>
                </thead>
                <tbody>
                @foreach ($addresses as $address)
                    <tr>
                        <td class="pg-mono">{{ $address->ip_address }}</td>
                        <td>{{ $address->subnet?->label() }}</td>
                        <td>{{ $address->address_type }}</td>
                        <td>{{ $address->networkDevice->hostname ?? $address->asset->asset_tag ?? '-' }}</td>
                        <td>{!! \App\Support\Labels::badge($address->status) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mb-3">{{ $addresses->links() }}</div>
        @if (auth()->user()->hasPermission('network.manage'))
            <form class="pg-card pg-card-pad" method="post" action="{{ route('network.ips.store') }}">
                @csrf
                <h2 class="pg-section-title">Allocate address</h2>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label" for="ip_address">IP address</label><input class="form-control pg-mono" id="ip_address" name="ip_address" required></div>
                    <div class="col-md-3"><label class="form-label" for="ip_subnet">Subnet</label>
                        <select class="form-select" id="ip_subnet" name="subnet_id" required>@foreach ($subnets as $subnet)<option value="{{ $subnet->id }}">{{ $subnet->label() }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2"><label class="form-label" for="address_type">Type</label>
                        <select class="form-select" id="address_type" name="address_type"><option value="static">Static</option><option value="reserved">Reserved</option><option value="dhcp">DHCP</option></select>
                    </div>
                    <input type="hidden" name="status" value="active">
                </div>
                <button class="btn btn-pg mt-3" type="submit">Save address</button>
            </form>
        @endif
    @endif

    @if ($tab === 'topology')
        <div class="pg-alert pg-alert-info">These maps are built from documented devices and links. They are not live discovery views.</div>
        @if (($graph['legend']['scenario'] ?? 'normal') === 'outage')
            <div class="pg-alert pg-alert-critical">Monitoring outage scenario is active. {{ $graph['legend']['outage_host'] }} and its links are highlighted on the map.</div>
        @endif

        <div class="pg-map-toolbar">
            <div class="pg-seg" role="group" aria-label="Topology view">
                <a class="{{ $mapView === 'physical' ? 'active' : '' }}" href="{{ route('network.index', ['tab' => 'topology', 'map' => 'physical']) }}">Physical map</a>
                <a class="{{ $mapView === 'interactive' ? 'active' : '' }}" href="{{ route('network.index', ['tab' => 'topology', 'map' => 'interactive']) }}">Interactive graph</a>
                <a class="{{ $mapView === 'layers' ? 'active' : '' }}" href="{{ route('network.index', ['tab' => 'topology', 'map' => 'layers']) }}">Subnet and VLAN layers</a>
            </div>
        </div>

        @if ($mapView === 'physical')
            <section class="pg-card mb-3">
                <div class="pg-card-pad d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <h2 class="pg-section-title mb-1">Physical topology</h2>
                        <p class="pg-page-lead">Plant hierarchy by tier: perimeter, core, access and wireless. Click a device to open its record.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="pg-map-legend" aria-label="Map legend">
                            @foreach ($graph['legend']['types'] as $type)
                                <span><i style="background: {{ $type['accent'] }}"></i>{{ $type['label'] }}</span>
                            @endforeach
                            <span><i class="is-packet"></i>Packet flow</span>
                            <span><i class="is-alert"></i>Outage highlight</span>
                        </div>
                        <button class="btn btn-sm btn-pg" type="button" data-packet-toggle aria-pressed="true">Pause packets</button>
                    </div>
                </div>
                <div class="pg-map-frame">
                    <svg class="pg-map-svg" data-packet-flow viewBox="0 0 {{ $graph['physical']['width'] }} {{ $graph['physical']['height'] }}" role="img" aria-label="Physical network topology">
                        @foreach ($graph['physical']['lanes'] as $lane)
                            <rect class="pg-map-lane" x="0" y="{{ $lane['y'] }}" width="{{ $graph['physical']['width'] }}" height="{{ $lane['height'] }}"></rect>
                            <text class="pg-map-lane-label" x="14" y="{{ $lane['y'] + 28 }}">{{ $lane['label'] }}</text>
                        @endforeach
                        @foreach ($graph['physical']['edges'] as $edge)
                            <g class="pg-map-edge {{ ! empty($edge['impacted']) ? 'is-impacted' : '' }}">
                                <path d="{{ $edge['path'] }}"></path>
                            </g>
                        @endforeach
                        @foreach ($graph['physical']['nodes'] as $node)
                            <a class="pg-map-node-link" href="{{ $node['url'] }}">
                                <rect class="pg-map-node {{ ! empty($node['alert']) ? 'is-alert' : '' }}" x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['w'] }}" height="{{ $node['h'] }}"></rect>
                                {!! \App\Support\NetworkTopology::iconMarkup($node['type'], $node['x'] + 28, $node['y'] + ($node['h'] / 2), $node['accent']) !!}
                                <text class="pg-map-host" x="{{ $node['x'] + 52 }}" y="{{ $node['y'] + 24 }}">{{ $node['hostname'] }}</text>
                                <text class="pg-map-meta" x="{{ $node['x'] + 52 }}" y="{{ $node['y'] + 40 }}">{{ $node['type_label'] }}</text>
                                <text class="pg-map-meta" x="{{ $node['x'] + 52 }}" y="{{ $node['y'] + 56 }}">{{ $node['ip'] ?: '-' }}@if ($node['location']) · {{ $node['location'] }}@endif</text>
                                @if (! empty($node['alert']))
                                    <text class="pg-map-alert-tag" x="{{ $node['x'] + $node['w'] - 8 }}" y="{{ $node['y'] + 16 }}">OUTAGE</text>
                                @endif
                            </a>
                        @endforeach
                        <g data-packet-layer></g>
                        <g class="pg-map-edge-labels" aria-hidden="true">
                            @foreach ($graph['physical']['edges'] as $edge)
                                <g class="pg-map-edge-label {{ ! empty($edge['impacted']) ? 'is-impacted' : '' }}">
                                    <rect class="pg-map-edge-label-bg" x="{{ $edge['label_x'] - ($edge['label_w'] / 2) }}" y="{{ $edge['label_y'] - ($edge['label_h'] / 2) }}" width="{{ $edge['label_w'] }}" height="{{ $edge['label_h'] }}"></rect>
                                    @if (! empty($edge['label_port']))
                                        <text class="pg-map-edge-label-type" x="{{ $edge['label_x'] }}" y="{{ $edge['label_y'] - 3 }}">{{ $edge['label_type'] }}</text>
                                        <text class="pg-map-edge-label-port" x="{{ $edge['label_x'] }}" y="{{ $edge['label_y'] + 9 }}">{{ $edge['label_port'] }}</text>
                                    @else
                                        <text class="pg-map-edge-label-type" x="{{ $edge['label_x'] }}" y="{{ $edge['label_y'] + 3 }}">{{ $edge['label_type'] }}</text>
                                    @endif
                                </g>
                            @endforeach
                        </g>
                    </svg>
                </div>
            </section>
        @endif

        @if ($mapView === 'interactive')
            <section class="pg-card mb-3">
                <div class="pg-card-pad d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h2 class="pg-section-title mb-1">Interactive graph</h2>
                        <p class="pg-page-lead">Drag devices to rearrange the view. Packet dots follow the live link paths.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="pg-map-legend" aria-label="Map legend">
                            @foreach ($graph['legend']['types'] as $type)
                                <span><i style="background: {{ $type['accent'] }}"></i>{{ $type['label'] }}</span>
                            @endforeach
                            <span><i class="is-packet"></i>Packet flow</span>
                        </div>
                        <button class="btn btn-sm btn-pg" type="button" data-packet-toggle aria-pressed="true">Pause packets</button>
                        <button class="btn btn-sm btn-pg-line" type="button" data-topology-reset>Reset layout</button>
                    </div>
                </div>
                <div class="pg-map-frame pg-map-interactive" data-topology-graph='@json($graph)'>
                    <svg class="pg-map-svg" data-topology-canvas data-packet-flow viewBox="0 0 {{ $graph['physical']['width'] }} {{ $graph['physical']['height'] }}" role="img" aria-label="Interactive network graph"></svg>
                </div>
            </section>
        @endif

        @if ($mapView === 'layers')
            <section class="pg-card mb-3">
                <div class="pg-card-pad">
                    <h2 class="pg-section-title mb-1">Subnet and VLAN layers</h2>
                    <p class="pg-page-lead">Logical overlays for the same documented plant. Use the filters to focus one layer at a time.</p>
                    <div class="pg-chips mt-2" role="group" aria-label="Layer type">
                        <button class="active" type="button" data-layer-filter="subnet">Subnets</button>
                        <button type="button" data-layer-filter="vlan">VLANs</button>
                    </div>
                </div>
                <div class="pg-layer-board" data-layer-board="subnet">
                    @forelse ($subnetLayers as $layer)
                        <article class="pg-layer-card">
                            <header>
                                <strong>{{ $layer['name'] }}</strong>
                                <span class="pg-mono">{{ $layer['label'] }}</span>
                            </header>
                            <p class="pg-page-lead mb-2">Gateway {{ $layer['gateway'] ?: '-' }} / {{ count($layer['members']) }} device(s)</p>
                            <div class="pg-layer-members">
                                @forelse ($layer['members'] as $member)
                                    <a href="{{ $member['url'] }}">
                                        <strong class="pg-mono">{{ $member['hostname'] }}</strong>
                                        <span>{{ $member['type_label'] }}@if ($member['ip']) / {{ $member['ip'] }}@endif</span>
                                    </a>
                                @empty
                                    <span class="text-muted">No documented devices in this subnet.</span>
                                @endforelse
                            </div>
                        </article>
                    @empty
                        <p class="text-muted px-3 pb-3">No subnets are documented yet.</p>
                    @endforelse
                </div>
                <div class="pg-layer-board" data-layer-board="vlan" hidden>
                    @forelse ($vlanLayers as $layer)
                        <article class="pg-layer-card">
                            <header>
                                <strong>VLAN {{ $layer['number'] }} / {{ $layer['name'] }}</strong>
                                <span class="pg-mono">{{ $layer['subnet'] ?: 'No subnet' }}</span>
                            </header>
                            <p class="pg-page-lead mb-2">{{ count($layer['members']) }} device(s)</p>
                            <div class="pg-layer-members">
                                @forelse ($layer['members'] as $member)
                                    <a href="{{ $member['url'] }}">
                                        <strong class="pg-mono">{{ $member['hostname'] }}</strong>
                                        <span>{{ $member['type_label'] }}@if ($member['ip']) / {{ $member['ip'] }}@endif</span>
                                    </a>
                                @empty
                                    <span class="text-muted">No documented devices in this VLAN.</span>
                                @endforelse
                            </div>
                        </article>
                    @empty
                        <p class="text-muted px-3 pb-3">No VLANs are documented yet.</p>
                    @endforelse
                </div>
            </section>
        @endif

        <section class="pg-card table-responsive mb-3">
            <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Documented links</h2></div>
            <table class="pg-table">
                <thead><tr><th>From</th><th>To</th><th>Type</th><th>Port</th></tr></thead>
                <tbody>
                @forelse ($links as $link)
                    <tr>
                        <td class="pg-mono"><a href="{{ route('network.show', $link->source) }}">{{ $link->source->hostname }}</a></td>
                        <td class="pg-mono"><a href="{{ route('network.show', $link->destination) }}">{{ $link->destination->hostname }}</a></td>
                        <td>{{ \App\Support\Labels::status($link->connection_type) }}</td>
                        <td class="pg-mono">{{ $link->port_or_interface ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No links documented yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @if (auth()->user()->hasPermission('network.manage'))
            <form class="pg-card pg-card-pad" method="post" action="{{ route('network.links.store') }}">
                @csrf
                <h2 class="pg-section-title">Document a link</h2>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label" for="source_device_id">From</label>
                        <select class="form-select" id="source_device_id" name="source_device_id">@foreach ($devices as $device)<option value="{{ $device->id }}">{{ $device->hostname }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label" for="destination_device_id">To</label>
                        <select class="form-select" id="destination_device_id" name="destination_device_id">@foreach ($devices as $device)<option value="{{ $device->id }}">{{ $device->hostname }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label" for="connection_type">Type</label>
                        <select class="form-select" id="connection_type" name="connection_type"><option value="ethernet">Ethernet</option><option value="fiber">Fiber</option><option value="uplink">Uplink</option><option value="wireless">Wireless</option></select>
                    </div>
                    <div class="col-md-3"><label class="form-label" for="port_or_interface">Port</label><input class="form-control" id="port_or_interface" name="port_or_interface"></div>
                </div>
                <button class="btn btn-pg mt-3" type="submit">Save link</button>
            </form>
        @endif
    @endif
@endsection
