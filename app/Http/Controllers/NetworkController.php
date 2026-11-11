<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\IpAddress;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Models\NetworkLink;
use App\Models\NetworkSubnet;
use App\Models\Vlan;
use App\Services\AuditLogger;
use App\Support\NetworkAddress;
use App\Support\NetworkTopology;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkController extends Controller
{
    public function index(Request $request, NetworkTopology $topology)
    {
        $tab = in_array($request->input('tab'), ['devices', 'subnets', 'vlans', 'addresses', 'topology'], true)
            ? $request->input('tab')
            : 'devices';

        $sort = match ($tab) {
            'subnets' => TableSort::from($request, ['name', 'network_address'], 'network_address'),
            'vlans' => TableSort::from($request, ['vlan_number', 'name'], 'vlan_number'),
            'addresses' => TableSort::from($request, ['ip_address', 'address_type', 'status'], 'ip_address'),
            default => TableSort::from($request, ['hostname', 'device_type', 'management_ip', 'status'], 'hostname'),
        };

        $deviceQuery = NetworkDevice::query()->with(['location', 'asset']);
        $devices = ($tab === 'devices' ? $sort->apply($deviceQuery) : $deviceQuery->orderBy('hostname'))->get();

        $links = NetworkLink::query()->with(['source', 'destination'])->orderBy('id')->get();
        $subnetsQuery = NetworkSubnet::query()->withCount('ipAddresses');
        $subnets = ($tab === 'subnets' ? $sort->apply($subnetsQuery) : $subnetsQuery->orderBy('network_address'))->get();
        $vlansQuery = Vlan::query()->with('subnet');
        $vlans = ($tab === 'vlans' ? $sort->apply($vlansQuery) : $vlansQuery->orderBy('vlan_number'))->get();
        $addressesQuery = IpAddress::query()->with(['subnet', 'vlan', 'asset', 'networkDevice']);
        $addresses = ($tab === 'addresses' ? $sort->apply($addressesQuery) : $addressesQuery->orderBy('ip_address'))
            ->paginate(15)
            ->withQueryString();
        $mapView = in_array($request->input('map'), ['physical', 'interactive', 'layers'], true)
            ? $request->input('map')
            : 'physical';

        return view('network.index', [
            'title' => 'Network',
            'tab' => $tab,
            'mapView' => $mapView,
            'devices' => $devices,
            'subnets' => $subnets,
            'vlans' => $vlans,
            'addresses' => $addresses,
            'links' => $links,
            'graph' => $topology->graph($devices, $links),
            'subnetLayers' => $topology->subnetLayers($subnets, $devices),
            'vlanLayers' => $topology->vlanLayers($vlans, $devices),
            'locations' => Location::query()->orderBy('name')->get(),
            'assets' => Asset::query()->orderBy('asset_tag')->get(),
            'sort' => $sort,
        ]);
    }

    public function show(NetworkDevice $device)
    {
        $device->load(['asset', 'location', 'sourceLinks.destination', 'destinationLinks.source']);

        return view('network.show', [
            'title' => $device->hostname,
            'device' => $device,
            'addresses' => IpAddress::query()->where('assigned_network_device_id', $device->id)->orWhere('ip_address', $device->management_ip)->get(),
        ]);
    }

    public function storeDevice(Request $request, AuditLogger $audit)
    {
        $this->manage($request);
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:80', 'unique:network_devices,hostname'],
            'device_type' => ['required', Rule::in(['router', 'switch', 'firewall', 'wireless_access_point', 'appliance'])],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'management_ip' => ['nullable', 'ip'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'status' => ['required', Rule::in(['active', 'spare', 'under_maintenance', 'retired'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $device = NetworkDevice::query()->create($data);
        $audit->log('network.device_created', 'network_device', $device->id, [], ['hostname' => $device->hostname]);

        return redirect()->route('network.show', $device)->with('status', $device->hostname.' was added to the network register.');
    }

    public function storeSubnet(Request $request, AuditLogger $audit)
    {
        $this->manage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'network_address' => ['required', 'ip'],
            'cidr' => ['required', 'integer', 'between:8,30'],
            'gateway' => ['nullable', 'ip'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $subnet = NetworkSubnet::query()->create($data);
        $audit->log('network.subnet_created', 'network_subnet', $subnet->id, [], ['name' => $subnet->name]);

        return redirect()->route('network.index', ['tab' => 'subnets'])->with('status', 'Subnet '.$subnet->label().' was recorded.');
    }

    public function storeVlan(Request $request, AuditLogger $audit)
    {
        $this->manage($request);
        $data = $request->validate([
            'vlan_number' => ['required', 'integer', 'between:1,4094', 'unique:vlans,vlan_number'],
            'name' => ['required', 'string', 'max:120'],
            'subnet_id' => ['nullable', 'exists:network_subnets,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $vlan = Vlan::query()->create($data);
        $audit->log('network.vlan_created', 'vlan', $vlan->id, [], ['vlan_number' => $vlan->vlan_number]);

        return redirect()->route('network.index', ['tab' => 'vlans'])->with('status', 'VLAN '.$vlan->vlan_number.' was recorded.');
    }

    public function storeIp(Request $request, AuditLogger $audit)
    {
        $this->manage($request);
        $data = $request->validate([
            'ip_address' => ['required', 'ip', 'unique:ip_addresses,ip_address'],
            'subnet_id' => ['required', 'exists:network_subnets,id'],
            'vlan_id' => ['nullable', 'exists:vlans,id'],
            'assigned_asset_id' => ['nullable', 'exists:assets,id'],
            'assigned_network_device_id' => ['nullable', 'exists:network_devices,id'],
            'address_type' => ['required', Rule::in(['static', 'reserved', 'dhcp'])],
            'status' => ['required', Rule::in(['active', 'reserved', 'available', 'retired'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subnet = NetworkSubnet::query()->findOrFail($data['subnet_id']);
        if (! NetworkAddress::contains($data['ip_address'], $subnet->network_address, (int) $subnet->cidr)) {
            return back()->withInput()->withErrors([
                'ip_address' => 'That address is outside '.$subnet->label().'.',
            ]);
        }

        if ($data['status'] === 'active') {
            $duplicate = IpAddress::query()->where('ip_address', $data['ip_address'])->where('status', 'active')->exists();
            if ($duplicate) {
                return back()->withInput()->withErrors([
                    'ip_address' => 'An active allocation already uses this address.',
                ]);
            }
        }

        $address = IpAddress::query()->create($data);
        $audit->log('network.ip_created', 'ip_address', $address->id, [], ['ip_address' => $address->ip_address]);

        return redirect()->route('network.index', ['tab' => 'addresses'])->with('status', $address->ip_address.' was allocated.');
    }

    public function storeLink(Request $request, AuditLogger $audit)
    {
        $this->manage($request);
        $data = $request->validate([
            'source_device_id' => ['required', 'exists:network_devices,id', 'different:destination_device_id'],
            'destination_device_id' => ['required', 'exists:network_devices,id'],
            'connection_type' => ['required', Rule::in(['ethernet', 'fiber', 'uplink', 'wireless'])],
            'port_or_interface' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['status'] = 'active';
        $link = NetworkLink::query()->create($data);
        $audit->log('network.link_created', 'network_link', $link->id);

        return redirect()->route('network.index', ['tab' => 'topology'])->with('status', 'The network link was documented.');
    }

    private function manage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('network.manage'), 403);
    }
}
