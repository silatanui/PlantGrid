<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Support\PlantMap;
use Illuminate\Http\Request;
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
            'pins' => $this->pinsFor($locations),
        ]);
    }

    public function show(Request $request, Location $location): View
    {
        $this->authorizeMap($request);

        $filter = $request->input('filter', 'all');
        if (! in_array($filter, ['all', 'assets', 'network'], true)) {
            $filter = 'all';
        }

        $location->load(['department']);
        $assets = $request->user()->hasPermission('assets.view')
            ? $location->assets()->with(['category', 'department'])->orderBy('asset_tag')->get()
            : collect();
        $devices = $request->user()->hasPermission('network.view')
            ? $location->networkDevices()->orderBy('hostname')->get()
            : collect();

        $locations = Location::query()
            ->withCount(['assets', 'networkDevices'])
            ->orderBy('name')
            ->get();

        return view('locations.show', [
            'title' => $location->name,
            'location' => $location,
            'locations' => $locations,
            'assets' => $filter === 'network' ? collect() : $assets,
            'devices' => $filter === 'assets' ? collect() : $devices,
            'assetCount' => $assets->count(),
            'deviceCount' => $devices->count(),
            'filter' => $filter,
            'zones' => PlantMap::zones(),
            'activeZone' => $location->map_zone,
            'pins' => $this->pinsFor(collect([$location]), $assets, $devices),
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
     * @param  \Illuminate\Support\Collection<int, Location>  $locations
     * @param  \Illuminate\Support\Collection<int, \App\Models\Asset>|null  $assets
     * @param  \Illuminate\Support\Collection<int, \App\Models\NetworkDevice>|null  $devices
     * @return list<array{x: float, y: float, label: string, kind: string, url: string}>
     */
    private function pinsFor($locations, $assets = null, $devices = null): array
    {
        $pins = [];

        if ($assets !== null || $devices !== null) {
            $location = $locations->first();
            $baseX = (float) ($location?->map_x ?? 500);
            $baseY = (float) ($location?->map_y ?? 320);
            $index = 0;

            foreach ($assets ?? [] as $asset) {
                $offset = $this->pinOffset($index++);
                $pins[] = [
                    'x' => $baseX + $offset[0],
                    'y' => $baseY + $offset[1],
                    'label' => $asset->asset_tag,
                    'kind' => 'asset',
                    'url' => route('assets.show', $asset),
                ];
            }

            foreach ($devices ?? [] as $device) {
                $offset = $this->pinOffset($index++);
                $pins[] = [
                    'x' => $baseX + $offset[0],
                    'y' => $baseY + $offset[1],
                    'label' => $device->hostname,
                    'kind' => 'device',
                    'url' => route('network.show', $device),
                ];
            }

            return $pins;
        }

        foreach ($locations as $location) {
            if ($location->map_x === null || $location->map_y === null) {
                continue;
            }

            $pins[] = [
                'x' => (float) $location->map_x,
                'y' => (float) $location->map_y,
                'label' => $location->code,
                'kind' => 'location',
                'url' => route('locations.show', $location),
            ];
        }

        return $pins;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function pinOffset(int $index): array
    {
        $ring = intdiv($index, 6) + 1;
        $step = $index % 6;
        $angle = deg2rad($step * 60);

        return [
            cos($angle) * 18 * $ring,
            sin($angle) * 14 * $ring,
        ];
    }
}
