<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\SupportTicket;
use App\Support\Labels;
use App\Support\PlantMap;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMap($request);

        $locations = Location::query()
            ->with('department')
            ->withCount(['assets', 'networkDevices'])
            ->orderBy('name')
            ->get();

        return view('locations.index', [
            'title' => 'Plant map',
            'locations' => $locations,
            'zones' => PlantMap::zones(),
            'activeZone' => null,
            'pins' => $this->locationPins($locations),
        ]);
    }

    public function show(Request $request, Location $location): View
    {
        $this->authorizeMap($request);

        $filter = $request->input('filter', 'all');
        if (! in_array($filter, ['all', 'assets', 'network', 'cases'], true)) {
            $filter = 'all';
        }

        $location->load(['department']);
        $assets = $request->user()->hasPermission('assets.view')
            ? $location->assets()->with(['category', 'department'])->orderBy('asset_tag')->get()
            : collect();
        $devices = $request->user()->hasPermission('network.view')
            ? $location->networkDevices()->orderBy('hostname')->get()
            : collect();

        $cases = collect();
        $caseCount = 0;
        if ($request->user()?->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $assetIds = $location->assets()->pluck('id');
            $casesQuery = SupportTicket::query()
                ->visibleTo($request->user())
                ->with(['assignee:id,name', 'requester:id,name', 'asset:id,asset_tag,name'])
                ->whereIn('asset_id', $assetIds)
                ->latest('reported_at');
            $caseCount = (clone $casesQuery)->count();
            $cases = $filter === 'cases' || $filter === 'all'
                ? $casesQuery->limit($filter === 'cases' ? 50 : 12)->get()
                : collect();
        }

        $locations = Location::query()
            ->withCount(['assets', 'networkDevices'])
            ->orderBy('name')
            ->get();

        return view('locations.show', [
            'title' => $location->name,
            'location' => $location,
            'locations' => $locations,
            'assets' => in_array($filter, ['network', 'cases'], true) ? collect() : $assets,
            'devices' => in_array($filter, ['assets', 'cases'], true) ? collect() : $devices,
            'cases' => $filter === 'assets' || $filter === 'network' ? collect() : $cases,
            'assetCount' => $assets->count(),
            'deviceCount' => $devices->count(),
            'caseCount' => $caseCount,
            'filter' => $filter,
            'zones' => PlantMap::zones(),
            'activeZone' => $location->map_zone,
            'pins' => $this->devicePins($location, $assets, $devices),
        ]);
    }

    private function authorizeMap(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyPermission(['assets.view', 'network.view']),
            403
        );
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array<string, mixed>>
     */
    private function locationPins(Collection $locations): array
    {
        $pins = [];

        foreach ($locations as $location) {
            $zone = PlantMap::zone((string) $location->map_zone);
            if ($zone === null) {
                continue;
            }

            $pins[] = [
                'x' => $zone['x'],
                'y' => $zone['y'],
                'label' => $location->code,
                'kind' => 'location',
                'url' => route('locations.show', $location),
                'title' => $location->name,
                'subtitle' => ($location->department->name ?? 'Plant area').' · '.$location->code,
                'status' => Labels::status((string) $location->status),
                'description' => $location->description
                    ?: 'Campus zone on the Northridge plant plan.',
                'meta' => sprintf(
                    '%d assets · %d network devices',
                    (int) $location->assets_count,
                    (int) $location->network_devices_count
                ),
            ];
        }

        return $pins;
    }

    /**
     * @param  Collection<int, \App\Models\Asset>  $assets
     * @param  Collection<int, \App\Models\NetworkDevice>  $devices
     * @return list<array<string, mixed>>
     */
    private function devicePins(Location $location, Collection $assets, Collection $devices): array
    {
        $slots = PlantMap::pinSlots((string) ($location->map_zone ?: ''));
        $pins = [];
        $index = 0;

        foreach ($assets as $asset) {
            $slot = $slots[$index % count($slots)];
            $index++;
            $pins[] = [
                'x' => $slot[0],
                'y' => $slot[1],
                'label' => $asset->asset_tag,
                'kind' => 'asset',
                'url' => route('assets.show', $asset),
                'title' => $asset->name,
                'subtitle' => $asset->asset_tag.' · '.($asset->category->name ?? 'Asset'),
                'status' => Labels::status((string) $asset->status),
                'description' => trim(($asset->manufacturer ? $asset->manufacturer.' ' : '').($asset->model ?? ''))
                    ?: 'ICT asset installed at '.$location->name.'.',
                'meta' => 'Condition: '.Labels::status((string) $asset->condition),
            ];
        }

        foreach ($devices as $device) {
            $slot = $slots[$index % count($slots)];
            $index++;
            $pins[] = [
                'x' => $slot[0] + (($index % 2) * 4),
                'y' => $slot[1] + (($index % 3) * 3),
                'label' => $device->hostname,
                'kind' => 'device',
                'url' => route('network.show', $device),
                'title' => $device->hostname,
                'subtitle' => Labels::status((string) $device->device_type)
                    .($device->management_ip ? ' · '.$device->management_ip : ''),
                'status' => Labels::status((string) $device->status),
                'description' => trim((string) ($device->notes ?: ''))
                    ?: (($device->manufacturer ?: 'Network device').' at '.$location->name.'.'),
                'meta' => trim(($device->manufacturer ?? '').' '.($device->model ?? '')),
            ];
        }

        return $pins;
    }
}
