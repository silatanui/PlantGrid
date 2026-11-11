<?php

namespace App\Monitoring;

use App\Models\MonitoringMetric;
use App\Models\MonitoringSample;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Monitoring\Contracts\MonitoringProvider;
use Carbon\CarbonInterface;

class MetricCollectionService
{
    public function __construct(
        private readonly MonitoringProvider $provider,
        private readonly ThresholdEvaluationService $thresholds,
    ) {}

    public function collect(?string $scenario = null, ?CarbonInterface $at = null): int
    {
        $scenario ??= SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';
        $at ??= now();
        $metrics = MonitoringMetric::query()->where('is_active', true)->get()->keyBy('metric_key');
        $stored = 0;

        foreach (Server::query()->orderBy('hostname')->get() as $server) {
            $stored += $this->storeTarget('server', $server->id, $server->hostname, $scenario, $at, $metrics);
        }

        foreach (NetworkDevice::query()->orderBy('hostname')->get() as $device) {
            $stored += $this->storeTarget('network_device', $device->id, $device->hostname, $scenario, $at, $metrics);
        }

        return $stored;
    }

    /**
     * Apply a demo scenario and paint enough recent points that the chart shows the change.
     */
    public function demonstrate(string $scenario): int
    {
        $stored = $this->collect($scenario);

        if ($scenario === 'outage') {
            $device = NetworkDevice::query()->where('hostname', config('plantgrid.outage_hostname'))->first();
            if ($device !== null) {
                $metrics = MonitoringMetric::query()->where('is_active', true)->get()->keyBy('metric_key');
                for ($step = 8; $step >= 1; $step--) {
                    $stored += $this->storeTarget(
                        'network_device',
                        $device->id,
                        $device->hostname,
                        'outage',
                        now()->subMinutes($step * 5),
                        $metrics
                    );
                }
            }
        }

        if ($scenario === 'degraded') {
            $server = Server::query()->where('hostname', config('plantgrid.degraded_hostname'))->first();
            if ($server !== null) {
                $metrics = MonitoringMetric::query()->where('is_active', true)->get()->keyBy('metric_key');
                for ($step = 6; $step >= 1; $step--) {
                    $stored += $this->storeTarget(
                        'server',
                        $server->id,
                        $server->hostname,
                        'degraded',
                        now()->subMinutes($step * 5),
                        $metrics
                    );
                }
            }
        }

        return $stored;
    }

    public function record(
        string $targetType,
        int $targetId,
        string $hostname,
        string $metricKey,
        float $value,
        string $scenario,
        CarbonInterface $at,
    ): void {
        $metric = MonitoringMetric::query()->where('metric_key', $metricKey)->first();

        if ($metric === null) {
            return;
        }

        $sample = MonitoringSample::query()->create([
            'monitoring_metric_id' => $metric->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'value' => $value,
            'source' => $this->provider->name(),
            'scenario' => $scenario,
            'collected_at' => $at,
        ]);

        $this->thresholds->evaluate($sample, $metric, $hostname);
    }

    private function storeTarget(string $type, int $id, string $hostname, string $scenario, CarbonInterface $at, $metrics): int
    {
        $count = 0;

        foreach ($this->provider->collect($type, $id, $hostname, $scenario) as $key => $value) {
            if ($value === null || ! isset($metrics[$key])) {
                continue;
            }

            $this->record($type, $id, $hostname, $key, $value, $scenario, $at);
            $count++;
        }

        return $count;
    }
}
