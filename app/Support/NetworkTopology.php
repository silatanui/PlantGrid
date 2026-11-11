<?php

namespace App\Support;

use App\Models\IpAddress;
use App\Models\NetworkDevice;
use App\Models\NetworkLink;
use App\Models\NetworkSubnet;
use App\Models\SystemSetting;
use App\Models\Vlan;
use Illuminate\Support\Collection;

class NetworkTopology
{
    public function graph(Collection $devices, Collection $links): array
    {
        $outageHost = (string) config('plantgrid.outage_hostname');
        $scenario = SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';

        $nodes = $devices->map(function (NetworkDevice $device) use ($outageHost, $scenario) {
            $tier = $this->tier($device);

            return [
                'id' => $device->id,
                'hostname' => $device->hostname,
                'type' => $device->device_type,
                'type_label' => Labels::status($device->device_type),
                'ip' => $device->management_ip,
                'status' => $device->status,
                'location' => $device->location->name ?? null,
                'url' => route('network.show', $device),
                'tier' => $tier,
                'tier_label' => $this->tierLabel($tier),
                'icon' => $this->icon($device->device_type),
                'accent' => $this->accent($device->device_type),
                'alert' => $scenario === 'outage' && $device->hostname === $outageHost,
            ];
        })->values();

        $edges = $links->map(function (NetworkLink $link) use ($nodes, $outageHost, $scenario) {
            $source = $nodes->firstWhere('id', $link->source_device_id);
            $target = $nodes->firstWhere('id', $link->destination_device_id);
            $impacted = $scenario === 'outage' && (
                ($source['hostname'] ?? null) === $outageHost
                || ($target['hostname'] ?? null) === $outageHost
            );

            return [
                'id' => $link->id,
                'source' => $link->source_device_id,
                'target' => $link->destination_device_id,
                'type' => $link->connection_type,
                'type_label' => Labels::status($link->connection_type),
                'port' => $link->port_or_interface,
                'status' => $link->status,
                'impacted' => $impacted,
            ];
        })->values();

        return [
            'nodes' => $nodes->all(),
            'edges' => $edges->all(),
            'physical' => $this->physicalLayout($nodes, $edges),
            'legend' => [
                'tiers' => [
                    ['key' => 0, 'label' => 'Edge / perimeter'],
                    ['key' => 1, 'label' => 'Core'],
                    ['key' => 2, 'label' => 'Access'],
                    ['key' => 3, 'label' => 'Wireless'],
                ],
                'types' => [
                    ['key' => 'firewall', 'label' => 'Firewall', 'accent' => '#032d60'],
                    ['key' => 'switch', 'label' => 'Switch', 'accent' => '#0176d3'],
                    ['key' => 'wireless_access_point', 'label' => 'Access point', 'accent' => '#dd7a01'],
                    ['key' => 'router', 'label' => 'Router', 'accent' => '#032d60'],
                ],
                'scenario' => $scenario,
                'outage_host' => $outageHost,
            ],
        ];
    }

    public function subnetLayers(Collection $subnets, Collection $devices): array
    {
        $addresses = IpAddress::query()
            ->with('networkDevice')
            ->whereNotNull('assigned_network_device_id')
            ->get()
            ->groupBy('subnet_id');

        return $subnets->map(function (NetworkSubnet $subnet) use ($devices, $addresses) {
            $linked = collect($addresses->get($subnet->id, collect()))
                ->pluck('networkDevice')
                ->filter()
                ->unique('id');

            $byManagement = $devices->filter(function (NetworkDevice $device) use ($subnet) {
                return $device->management_ip
                    && NetworkAddress::contains($device->management_ip, $subnet->network_address, (int) $subnet->cidr);
            });

            $members = $linked->concat($byManagement)->unique('id')->values();

            return [
                'id' => $subnet->id,
                'name' => $subnet->name,
                'label' => $subnet->label(),
                'gateway' => $subnet->gateway,
                'members' => $members->map(fn (NetworkDevice $device) => [
                    'id' => $device->id,
                    'hostname' => $device->hostname,
                    'type_label' => Labels::status($device->device_type),
                    'ip' => $device->management_ip,
                    'url' => route('network.show', $device),
                ])->all(),
            ];
        })->values()->all();
    }

    public function vlanLayers(Collection $vlans, Collection $devices): array
    {
        $addresses = IpAddress::query()
            ->with('networkDevice')
            ->whereNotNull('vlan_id')
            ->whereNotNull('assigned_network_device_id')
            ->get()
            ->groupBy('vlan_id');

        return $vlans->map(function (Vlan $vlan) use ($devices, $addresses) {
            $members = collect($addresses->get($vlan->id, collect()))
                ->pluck('networkDevice')
                ->filter()
                ->unique('id')
                ->values();

            if ($members->isEmpty() && $vlan->subnet) {
                $members = $devices->filter(function (NetworkDevice $device) use ($vlan) {
                    return $device->management_ip
                        && NetworkAddress::contains($device->management_ip, $vlan->subnet->network_address, (int) $vlan->subnet->cidr);
                })->values();
            }

            return [
                'id' => $vlan->id,
                'number' => $vlan->vlan_number,
                'name' => $vlan->name,
                'subnet' => $vlan->subnet?->label(),
                'members' => $members->map(fn (NetworkDevice $device) => [
                    'id' => $device->id,
                    'hostname' => $device->hostname,
                    'type_label' => Labels::status($device->device_type),
                    'ip' => $device->management_ip,
                    'url' => route('network.show', $device),
                ])->all(),
            ];
        })->values()->all();
    }

    private function tier(NetworkDevice $device): int
    {
        return match ($device->device_type) {
            'firewall', 'router' => 0,
            'switch' => str_contains(strtoupper($device->hostname), 'CORE') ? 1 : 2,
            'wireless_access_point' => 3,
            default => 2,
        };
    }

    private function tierLabel(int $tier): string
    {
        return match ($tier) {
            0 => 'Edge / perimeter',
            1 => 'Core',
            2 => 'Access',
            3 => 'Wireless',
            default => 'Plant network',
        };
    }

    private function icon(string $type): string
    {
        return match ($type) {
            'firewall' => 'shield',
            'router' => 'router',
            'switch' => 'network',
            'wireless_access_point' => 'wifi',
            default => 'hard-drive',
        };
    }

    /**
     * Inline Lucide-style paths for topology nodes (24x24 viewBox).
     */
    public static function iconShapes(string $type): array
    {
        return match ($type) {
            'firewall' => [
                ['path', 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z'],
            ],
            'router' => [
                ['rect', null, ['x' => 2, 'y' => 14, 'width' => 20, 'height' => 8, 'rx' => 2]],
                ['path', 'M6.01 18H6'],
                ['path', 'M10.01 18H10'],
                ['path', 'M15 10v4'],
                ['path', 'M17.84 7.17a4 4 0 0 0-5.66 0'],
                ['path', 'M20.66 4.34a8 8 0 0 0-11.31 0'],
            ],
            'wireless_access_point' => [
                ['path', 'M12 20h.01'],
                ['path', 'M2 8.82a15 15 0 0 1 20 0'],
                ['path', 'M5 12.859a10 10 0 0 1 14 0'],
                ['path', 'M8.5 16.429a5 5 0 0 1 7 0'],
            ],
            'switch' => [
                ['rect', null, ['x' => 16, 'y' => 16, 'width' => 6, 'height' => 6, 'rx' => 1]],
                ['rect', null, ['x' => 2, 'y' => 16, 'width' => 6, 'height' => 6, 'rx' => 1]],
                ['rect', null, ['x' => 9, 'y' => 2, 'width' => 6, 'height' => 6, 'rx' => 1]],
                ['path', 'M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3'],
                ['path', 'M12 12V8'],
            ],
            default => [
                ['rect', null, ['x' => 2, 'y' => 6, 'width' => 20, 'height' => 12, 'rx' => 2]],
                ['path', 'M6 12h.01'],
                ['path', 'M10 12h.01'],
            ],
        };
    }

    public static function iconMarkup(string $type, float $cx, float $cy, string $accent, float $size = 20): string
    {
        $scale = $size / 24;
        $parts = '';
        foreach (self::iconShapes($type) as $shape) {
            $tag = $shape[0];
            if ($tag === 'path') {
                $parts .= '<path d="'.e($shape[1]).'"></path>';
                continue;
            }
            $attrs = $shape[2] ?? [];
            $rendered = '';
            foreach ($attrs as $name => $value) {
                $rendered .= ' '.$name.'="'.e((string) $value).'"';
            }
            $parts .= '<'.$tag.$rendered.'></'.$tag.'>';
        }

        return '<g class="pg-map-device-icon" transform="translate('.$cx.' '.$cy.')">'
            .'<circle class="pg-map-device-icon-bg" cx="0" cy="0" r="16" fill="'.e($accent).'"></circle>'
            .'<g fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" transform="translate('.(-$size / 2).' '.(-$size / 2).') scale('.$scale.')">'
            .$parts
            .'</g></g>';
    }

    private function accent(string $type): string
    {
        return match ($type) {
            'firewall', 'router' => '#032d60',
            'switch' => '#0176d3',
            'wireless_access_point' => '#dd7a01',
            default => '#706e6b',
        };
    }

    private function physicalLayout(Collection $nodes, Collection $edges): array
    {
        $width = 1080;
        $laneLeft = 118;
        $nodeWidth = 188;
        $nodeHeight = 72;
        $lanePad = 16;
        $laneGap = 56;
        $placed = [];
        $lanes = [];

        $tiers = $nodes->groupBy('tier')->sortKeys();
        $y = 24;

        foreach ($tiers as $tier => $tierNodes) {
            $count = max(1, $tierNodes->count());
            $usable = $width - $laneLeft - 36;
            $gap = $usable / ($count + 1);
            $laneHeight = $nodeHeight + ($lanePad * 2);
            $lanes[] = [
                'tier' => (int) $tier,
                'label' => $this->tierLabel((int) $tier),
                'y' => $y,
                'height' => $laneHeight,
            ];

            $index = 1;
            foreach ($tierNodes->sortBy('hostname') as $node) {
                $placed[$node['id']] = [
                    ...$node,
                    'x' => (int) round($laneLeft + ($gap * $index) - ($nodeWidth / 2)),
                    'y' => $y + $lanePad,
                    'w' => $nodeWidth,
                    'h' => $nodeHeight,
                    'cx' => (int) round($laneLeft + ($gap * $index)),
                    'cy' => (int) round($y + $lanePad + ($nodeHeight / 2)),
                ];
                $index++;
            }

            $y += $laneHeight + $laneGap;
        }

        $drawnEdges = $edges->map(function (array $edge) use ($placed) {
            $source = $placed[$edge['source']] ?? null;
            $target = $placed[$edge['target']] ?? null;
            if ($source === null || $target === null) {
                return null;
            }

            $x1 = $source['cx'];
            $y1 = $source['y'] + $source['h'];
            $x2 = $target['cx'];
            $y2 = $target['y'];
            // Keep the horizontal bus near the parent so labels sit on each vertical drop.
            $midY = (int) round($y1 + max(18, ($y2 - $y1) * 0.28));
            $label = trim($edge['type_label'].($edge['port'] ? ' · '.$edge['port'] : ''));
            $labelW = (int) max(78, min(126, 18 + strlen($label) * 6.1));
            $hasPort = filled($edge['port']);
            $labelH = $hasPort ? 28 : 18;

            return [
                ...$edge,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                'path' => "M {$x1} {$y1} L {$x1} {$midY} L {$x2} {$midY} L {$x2} {$y2}",
                'label' => $label,
                'label_type' => $edge['type_label'],
                'label_port' => $edge['port'],
                'label_x' => $x2,
                'label_y' => (int) round($midY + (($y2 - $midY) / 2)),
                'label_w' => $labelW,
                'label_h' => $labelH,
            ];
        })->filter()->values()->all();

        return [
            'width' => $width,
            'height' => max(360, $y + 12),
            'lane_left' => $laneLeft,
            'lanes' => $lanes,
            'nodes' => array_values($placed),
            'edges' => $drawnEdges,
        ];
    }
}
