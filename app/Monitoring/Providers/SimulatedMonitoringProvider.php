<?php

namespace App\Monitoring\Providers;

use App\Monitoring\Contracts\MonitoringProvider;

class SimulatedMonitoringProvider implements MonitoringProvider
{
    public function name(): string
    {
        return 'simulated';
    }

    public function collect(string $targetType, int $targetId, string $hostname, string $scenario): array
    {
        $keys = $targetType === 'server'
            ? ['cpu', 'memory', 'disk', 'response_time', 'availability']
            : ['response_time', 'packet_loss', 'availability'];

        $readings = [];

        foreach ($keys as $key) {
            $readings[$key] = $this->reading($key, $hostname, $scenario);
        }

        return $readings;
    }

    public function reading(string $metricKey, string $hostname, string $scenario): ?float
    {
        $outageHost = (string) config('plantgrid.outage_hostname');
        $degradedHost = (string) config('plantgrid.degraded_hostname');

        if ($scenario === 'outage' && $hostname === $outageHost) {
            return match ($metricKey) {
                'availability' => 0.0,
                'packet_loss' => 100.0,
                'response_time' => 0.0,
                'cpu', 'memory', 'disk' => null,
                default => null,
            };
        }

        if ($scenario === 'degraded' && $hostname === $degradedHost && $metricKey === 'disk') {
            return 86.0;
        }

        if ($scenario === 'degraded' && $hostname === $outageHost) {
            return match ($metricKey) {
                'packet_loss' => 3.2,
                'response_time' => 260.0,
                'availability' => 100.0,
                default => $this->normal($metricKey, $hostname),
            };
        }

        return $this->normal($metricKey, $hostname);
    }

    private function normal(string $metricKey, string $hostname): ?float
    {
        $offset = ((int) sprintf('%u', crc32($hostname))) % 9;

        return match ($metricKey) {
            'cpu' => 24.0 + $offset,
            'memory' => 41.0 + $offset,
            'disk' => 46.0 + ($offset % 6),
            'response_time' => 28.0 + ($offset * 3),
            'packet_loss' => 0.1,
            'availability' => 100.0,
            default => null,
        };
    }
}
