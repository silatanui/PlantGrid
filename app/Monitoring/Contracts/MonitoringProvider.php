<?php

namespace App\Monitoring\Contracts;

interface MonitoringProvider
{
    public function name(): string;

    /**
     * Metric readings for one target. Null means the metric does not apply.
     *
     * @return array<string, float|null>
     */
    public function collect(string $targetType, int $targetId, string $hostname, string $scenario): array;

    public function reading(string $metricKey, string $hostname, string $scenario): ?float;
}
