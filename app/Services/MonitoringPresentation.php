<?php

namespace App\Services;

use App\Models\MonitoringSample;
use App\Models\NetworkDevice;
use App\Models\Server;
use Illuminate\Support\Collection;

class MonitoringPresentation
{
    public function defaultTarget(string $scenario): string
    {
        if ($scenario === 'outage') {
            $id = NetworkDevice::query()->where('hostname', config('plantgrid.outage_hostname'))->value('id');
            if ($id) {
                return 'network_device:'.$id;
            }
        }

        if ($scenario === 'degraded') {
            $id = Server::query()->where('hostname', config('plantgrid.degraded_hostname'))->value('id');
            if ($id) {
                return 'server:'.$id;
            }
        }

        $id = Server::query()->where('hostname', 'PLT-APP01')->value('id') ?? Server::query()->value('id');

        return 'server:'.$id;
    }

    public function resolveTarget(?string $requested, string $scenario): array
    {
        $target = $requested ?: $this->defaultTarget($scenario);
        [$type, $id] = array_pad(explode(':', (string) $target, 2), 2, null);
        $id = (int) $id;

        if (! in_array($type, ['server', 'network_device'], true) || $id < 1) {
            $fallback = $this->defaultTarget($scenario);
            [$type, $id] = explode(':', $fallback, 2);
            $id = (int) $id;
        }

        $hostname = $type === 'server'
            ? Server::query()->whereKey($id)->value('hostname')
            : NetworkDevice::query()->whereKey($id)->value('hostname');

        if ($hostname === null) {
            $fallback = $this->defaultTarget($scenario);
            [$type, $id] = explode(':', $fallback, 2);
            $id = (int) $id;
            $hostname = $type === 'server'
                ? Server::query()->whereKey($id)->value('hostname')
                : NetworkDevice::query()->whereKey($id)->value('hostname');
        }

        return [
            'type' => $type,
            'id' => $id,
            'selected' => $type.':'.$id,
            'hostname' => $hostname ?? 'Unknown',
        ];
    }

    public function chart(string $type, int $id, string $scenario): array
    {
        $metricKeys = $type === 'server'
            ? ['cpu' => 'CPU %', 'memory' => 'Memory %', 'disk' => 'Disk %', 'availability' => 'Availability %']
            : ['availability' => 'Availability %', 'response_time' => 'Response ms', 'packet_loss' => 'Packet loss %'];

        $samples = MonitoringSample::query()
            ->with('metric')
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->whereHas('metric', fn ($query) => $query->whereIn('metric_key', array_keys($metricKeys)))
            ->latest('collected_at')
            ->limit(120)
            ->get()
            ->reverse()
            ->values();

        $labels = $samples
            ->map(fn ($sample) => $sample->collected_at->timezone(config('plantgrid.display_timezone'))->format('d M H:i'))
            ->unique()
            ->values();

        $colors = [
            'cpu' => '#0176D3',
            'memory' => '#032D60',
            'disk' => '#DD7A01',
            'availability' => '#EA001E',
            'response_time' => '#0176D3',
            'packet_loss' => '#DD7A01',
        ];

        $datasets = [];
        foreach ($metricKeys as $key => $label) {
            $series = $samples->filter(fn ($sample) => $sample->metric?->metric_key === $key);
            $data = $labels->map(function ($labelTime) use ($series) {
                $match = $series->first(fn ($sample) => $sample->collected_at->timezone(config('plantgrid.display_timezone'))->format('d M H:i') === $labelTime);

                return $match ? (float) $match->value : null;
            })->all();

            if (! collect($data)->filter(fn ($value) => $value !== null)->count()) {
                continue;
            }

            $datasets[] = [
                'label' => $label,
                'data' => $data,
                'borderColor' => $colors[$key] ?? '#706E6B',
                'backgroundColor' => $colors[$key] ?? '#706E6B',
                'tension' => 0.2,
                'spanGaps' => true,
                'pointRadius' => $key === 'availability' ? 3 : 2,
                'borderWidth' => $key === 'availability' ? 2.5 : 1.5,
                'yAxisID' => $key === 'response_time' ? 'yMs' : 'y',
            ];
        }

        $hasMs = collect($datasets)->contains(fn ($set) => ($set['yAxisID'] ?? 'y') === 'yMs');

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels->all(),
                'datasets' => $datasets,
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['position' => 'bottom'],
                ],
                'scales' => array_filter([
                    'y' => [
                        'beginAtZero' => true,
                        'suggestedMax' => 100,
                        'title' => ['display' => true, 'text' => '%'],
                    ],
                    'yMs' => $hasMs ? [
                        'beginAtZero' => true,
                        'position' => 'right',
                        'grid' => ['drawOnChartArea' => false],
                        'title' => ['display' => true, 'text' => 'ms'],
                    ] : null,
                    'x' => ['grid' => ['display' => false]],
                ]),
            ],
        ];
    }

    public function latestReadings(string $type, int $id): Collection
    {
        $keys = $type === 'server'
            ? ['cpu', 'memory', 'disk', 'availability', 'response_time']
            : ['availability', 'response_time', 'packet_loss'];

        return collect($keys)->mapWithKeys(function (string $key) use ($type, $id) {
            $sample = MonitoringSample::query()
                ->with('metric')
                ->where('target_type', $type)
                ->where('target_id', $id)
                ->whereHas('metric', fn ($query) => $query->where('metric_key', $key))
                ->latest('collected_at')
                ->first();

            return [$key => $sample];
        })->filter();
    }

    public function scenarioBanner(string $scenario, string $selectedHostname): array
    {
        $outageHost = (string) config('plantgrid.outage_hostname');
        $degradedHost = (string) config('plantgrid.degraded_hostname');

        return match ($scenario) {
            'outage' => [
                'tone' => 'critical',
                'text' => 'Outage scenario is active. '.$outageHost.' availability is simulated at 0%. '
                    .($selectedHostname === $outageHost
                        ? 'This chart is the outage target; availability should fall to the baseline.'
                        : 'You are viewing '.$selectedHostname.', which is not the outage target. Switch the chart target to '.$outageHost.' to see the drop.'),
            ],
            'degraded' => [
                'tone' => 'warning',
                'text' => 'Degraded scenario is active. '.$degradedHost.' disk and '.$outageHost.' response time are elevated. '
                    .($selectedHostname === $degradedHost
                        ? 'This chart is the degraded server; watch Disk %.'
                        : 'Switch the chart target to '.$degradedHost.' to see the disk pressure.'),
            ],
            default => [
                'tone' => 'info',
                'text' => 'Normal keeps values inside the demonstration thresholds. Degraded raises '.$degradedHost.' disk and '.$outageHost.' response. Outage marks '.$outageHost.' unavailable.',
            ],
        };
    }
}
