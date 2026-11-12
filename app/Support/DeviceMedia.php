<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\NetworkDevice;
use App\Models\Server;

class DeviceMedia
{
    /**
     * Resolve a visible demo image + identifying specs for ICT hardware.
     *
     * @return array{key: string, image: string|null, label: string, summary: string, specs: array<string, string>}
     */
    public static function forAsset(?Asset $asset): array
    {
        if ($asset === null) {
            return self::fallback();
        }

        $profile = self::profile(
            manufacturer: (string) $asset->manufacturer,
            model: (string) $asset->model,
            category: (string) ($asset->category?->name ?? ''),
            name: (string) $asset->name,
        );

        if (is_array($asset->specifications) && $asset->specifications !== []) {
            $extra = collect($asset->specifications)
                ->except(['image_key', 'summary'])
                ->filter(fn ($value) => is_string($value) || is_numeric($value))
                ->all();
            $profile['specs'] = array_merge($profile['specs'], $extra);
            if (! empty($asset->specifications['summary']) && is_string($asset->specifications['summary'])) {
                $profile['summary'] = $asset->specifications['summary'];
            }
        }

        return $profile;
    }

    /**
     * @return array{key: string, image: string|null, label: string, summary: string, specs: array<string, string>}
     */
    public static function forNetworkDevice(?NetworkDevice $device): array
    {
        if ($device === null) {
            return self::fallback();
        }

        if ($device->relationLoaded('asset') && $device->asset) {
            return self::forAsset($device->asset);
        }

        return self::profile(
            manufacturer: (string) $device->manufacturer,
            model: (string) $device->model,
            category: (string) $device->device_type,
            name: (string) $device->hostname,
        );
    }

    /**
     * @return array{key: string, image: string|null, label: string, summary: string, specs: array<string, string>}
     */
    public static function forServer(?Server $server): array
    {
        if ($server === null) {
            return self::fallback();
        }

        if ($server->relationLoaded('asset') && $server->asset) {
            $profile = self::forAsset($server->asset);
            $profile['specs'] = array_merge($profile['specs'], array_filter([
                'Role' => $server->server_role,
                'OS' => trim($server->operating_system.' '.$server->os_version),
                'CPU' => $server->cpu_cores ? $server->cpu_cores.' cores' : null,
                'Memory' => $server->ram_gb ? $server->ram_gb.' GB RAM' : null,
                'Storage' => $server->storage_gb ? $server->storage_gb.' GB disk' : null,
            ]));

            return $profile;
        }

        return self::fallback('rack-server');
    }

    /**
     * @return array{key: string, image: string|null, label: string, summary: string, specs: array<string, string>}
     */
    public static function profile(string $manufacturer, string $model, string $category = '', string $name = ''): array
    {
        $haystack = strtolower(trim($manufacturer.' '.$model.' '.$category.' '.$name));
        $key = self::resolveKey($haystack, $model);
        $catalog = self::catalog()[$key] ?? self::catalog()['generic'];

        return [
            'key' => $key,
            'image' => self::imageUrl($key),
            'label' => $catalog['label'],
            'summary' => $catalog['summary'],
            'specs' => $catalog['specs'],
        ];
    }

    public static function imageUrl(string $key): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $relative = 'images/devices/'.$key.'.'.$ext;
            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }

    /**
     * Seed-friendly specification payloads keyed by asset model.
     *
     * @return array<string, array{image_key: string, summary: string, specs: array<string, string>}>
     */
    public static function seedSpecifications(): array
    {
        $out = [];
        foreach (self::catalog() as $key => $entry) {
            $out[$key] = [
                'image_key' => $key,
                'summary' => $entry['summary'],
                'specs' => $entry['specs'],
            ];
        }

        return $out;
    }

    private static function resolveKey(string $haystack, string $model): string
    {
        $modelKey = strtolower(trim($model));

        return match (true) {
            str_contains($haystack, 'zt411') || str_contains($haystack, 'label printer') => 'label-printer',
            str_contains($haystack, 'ds2208') || str_contains($haystack, 'barcode scanner') => 'barcode-scanner',
            str_contains($haystack, 'zm510') || str_contains($haystack, 'scale') || str_contains($haystack, 'weigh') => 'bench-scale',
            str_contains($haystack, 'laserjet') || (str_contains($haystack, 'printer') && ! str_contains($haystack, 'label')) => 'laser-printer',
            str_contains($haystack, 'hikvision') || str_contains($haystack, 'camera') || str_contains($haystack, 'cctv') => 'cctv-camera',
            str_contains($haystack, 'fortigate') || str_contains($haystack, 'firewall') => 'firewall',
            str_contains($haystack, 'u6+') || str_contains($haystack, 'access point') || str_contains($haystack, 'wireless') => 'access-point',
            str_contains($haystack, 'cbs') || str_contains($haystack, 'switch') => 'network-switch',
            str_contains($haystack, 'dl380') || str_contains($haystack, 'proliant') => 'rack-server',
            str_contains($haystack, 't350') || str_contains($haystack, 'poweredge') || str_contains($haystack, 'tower') => 'tower-server',
            str_contains($haystack, 'thinkbook') || str_contains($haystack, 'latitude') || str_contains($haystack, 'laptop') => 'laptop',
            str_contains($haystack, 'prodesk') || str_contains($haystack, 'optiplex') || str_contains($haystack, 'compaq') || str_contains($haystack, 'desktop') => 'desktop',
            default => match ($modelKey) {
                'zt411' => 'label-printer',
                'ds2208' => 'barcode-scanner',
                'zm510' => 'bench-scale',
                'laserjet pro' => 'laser-printer',
                'ds-2cd' => 'cctv-camera',
                'fortigate 80f' => 'firewall',
                'u6+' => 'access-point',
                'cbs350-24', 'cbs250-24', 'cbs220-24' => 'network-switch',
                'proliant dl380' => 'rack-server',
                'poweredge t350' => 'tower-server',
                'thinkbook 14', 'latitude 5440' => 'laptop',
                'prodesk 400', 'optiplex 7010', 'compaq 6200' => 'desktop',
                default => 'generic',
            },
        };
    }

    /**
     * @return array{key: string, image: string|null, label: string, summary: string, specs: array<string, string>}
     */
    private static function fallback(string $key = 'generic'): array
    {
        $catalog = self::catalog()[$key] ?? self::catalog()['generic'];

        return [
            'key' => $key,
            'image' => self::imageUrl($key),
            'label' => $catalog['label'],
            'summary' => $catalog['summary'],
            'specs' => $catalog['specs'],
        ];
    }

    /**
     * Demo hardware reference cards (fictional plant inventory).
     *
     * @return array<string, array{label: string, summary: string, specs: array<string, string>}>
     */
    private static function catalog(): array
    {
        return [
            'label-printer' => [
                'label' => 'Industrial label printer',
                'summary' => '4-inch industrial thermal printer used on the packaging line for finished-goods case labels.',
                'specs' => [
                    'Form factor' => 'Industrial desktop printer',
                    'Print method' => 'Thermal transfer / direct thermal',
                    'Resolution' => '203 dpi (300/600 optional)',
                    'Max print width' => '4.09 in (104 mm)',
                    'Connectivity' => 'Ethernet, USB, Serial, Bluetooth',
                    'Display' => '4.3 in colour touchscreen',
                    'Typical use' => 'Packaging hall barcode labels',
                ],
            ],
            'barcode-scanner' => [
                'label' => 'Handheld barcode scanner',
                'summary' => 'Corded 1D imager for scanning case and pallet barcodes on the packaging bench.',
                'specs' => [
                    'Form factor' => 'Handheld gun scanner',
                    'Scan type' => '1D imager',
                    'Interface' => 'USB HID',
                    'Typical use' => 'Packaging / receiving scans',
                ],
            ],
            'bench-scale' => [
                'label' => 'Intake bench scale',
                'summary' => 'Industrial weighing indicator and platform used at intake for batch weight capture.',
                'specs' => [
                    'Form factor' => 'Bench scale + digital indicator',
                    'Typical capacity' => 'Plant intake weighing',
                    'Connectivity' => 'Ethernet to weigh application PC',
                    'Typical use' => 'Processing hall intake',
                ],
            ],
            'laser-printer' => [
                'label' => 'Office laser printer',
                'summary' => 'Monochrome laser printer for QA checklists and laboratory paperwork.',
                'specs' => [
                    'Form factor' => 'Desktop laser printer',
                    'Print type' => 'Monochrome laser',
                    'Connectivity' => 'USB / Ethernet',
                    'Typical use' => 'QA laboratory documents',
                ],
            ],
            'cctv-camera' => [
                'label' => 'Network camera',
                'summary' => 'Fixed IP dome/bullet camera covering the plant entrance gate.',
                'specs' => [
                    'Form factor' => 'Fixed network camera',
                    'Connectivity' => 'PoE Ethernet',
                    'Typical use' => 'Entrance / gate monitoring',
                ],
            ],
            'firewall' => [
                'label' => 'Perimeter firewall',
                'summary' => 'Compact next-generation firewall appliance at the plant perimeter.',
                'specs' => [
                    'Form factor' => 'Desktop / rack appliance',
                    'Role' => 'Perimeter security gateway',
                    'Interfaces' => 'Multiple Gigabit Ethernet',
                    'Typical use' => 'Server room edge protection',
                ],
            ],
            'network-switch' => [
                'label' => 'Ethernet switch',
                'summary' => 'Managed Gigabit access/core switch used for plant floor and office VLANs.',
                'specs' => [
                    'Form factor' => '1U rack switch',
                    'Ports' => '24x Gigabit Ethernet',
                    'Management' => 'Web / CLI managed',
                    'Typical use' => 'VLAN access and uplinks',
                ],
            ],
            'access-point' => [
                'label' => 'Wireless access point',
                'summary' => 'Ceiling/wall Wi-Fi access point covering the packaging hall wireless segment.',
                'specs' => [
                    'Form factor' => 'Ceiling / wall AP',
                    'Radio' => 'Dual-band Wi-Fi 6 class',
                    'Power' => 'PoE',
                    'Typical use' => 'Packaging hall wireless',
                ],
            ],
            'tower-server' => [
                'label' => 'Tower server',
                'summary' => 'Entry tower server for directory or file services in the server room.',
                'specs' => [
                    'Form factor' => '4.5U tower',
                    'CPU' => 'Single-socket Xeon-class',
                    'Memory' => 'Up to 128 GB UDIMM class',
                    'Storage' => 'Multi-bay SAS/SATA',
                    'Management' => 'Out-of-band iDRAC-class',
                    'Typical use' => 'Directory / file services',
                ],
            ],
            'rack-server' => [
                'label' => 'Rack server',
                'summary' => '2U dual-socket class rack server for application and database workloads.',
                'specs' => [
                    'Form factor' => '2U rack mount',
                    'CPU' => 'Dual-socket scalable class',
                    'Expandability' => 'Hot-swap drive bays, PCIe',
                    'Networking' => '10GbE-class options',
                    'Typical use' => 'Application / database hosts',
                ],
            ],
            'laptop' => [
                'label' => 'Business laptop',
                'summary' => '14-inch business notebook for supervisors and ICT technicians on the plant site.',
                'specs' => [
                    'Form factor' => '14 in notebook',
                    'Typical CPU' => 'Business U-series class',
                    'Connectivity' => 'Wi-Fi, Ethernet via dock/USB-C',
                    'Typical use' => 'Floor / ICT mobility',
                ],
            ],
            'desktop' => [
                'label' => 'Desktop PC',
                'summary' => 'Small-form business desktop for fixed desks in QA, warehouse and office areas.',
                'specs' => [
                    'Form factor' => 'Small form-factor desktop',
                    'Connectivity' => 'Ethernet, DisplayPort/HDMI',
                    'Typical use' => 'Fixed desk workloads',
                ],
            ],
            'generic' => [
                'label' => 'ICT device',
                'summary' => 'Registered plant ICT asset in the Northridge demonstration register.',
                'specs' => [
                    'Form factor' => 'ICT hardware',
                    'Typical use' => 'Plant operations support',
                ],
            ],
        ];
    }
}
