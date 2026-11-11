<?php

namespace App\Monitoring;

use App\Models\MonitoringAlert;
use App\Models\MonitoringMetric;
use App\Models\MonitoringSample;

class ThresholdEvaluationService
{
    public function evaluate(MonitoringSample $sample, MonitoringMetric $metric, string $label): void
    {
        $value = (float) $sample->value;
        $severity = $this->severity($metric, $value);

        $open = MonitoringAlert::query()
            ->where('target_type', $sample->target_type)
            ->where('target_id', $sample->target_id)
            ->where('monitoring_metric_id', $metric->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if ($severity === null) {
            if ($open !== null) {
                $open->update([
                    'status' => 'resolved',
                    'resolved_at' => $sample->collected_at,
                ]);
            }

            return;
        }

        $message = sprintf(
            'Simulated %s: %s %s is %s %s (threshold %s).',
            $severity,
            $label,
            $metric->name,
            $this->format($value),
            $metric->unit,
            $this->format((float) ($severity === 'critical' ? $metric->critical_threshold : $metric->warning_threshold))
        );

        if ($metric->metric_key === 'availability') {
            $message = 'Simulated outage: '.$label.' is unavailable.';
        }

        if ($open === null) {
            MonitoringAlert::query()->create([
                'monitoring_metric_id' => $metric->id,
                'target_type' => $sample->target_type,
                'target_id' => $sample->target_id,
                'severity' => $severity,
                'message' => $message,
                'observed_value' => $value,
                'threshold_value' => $severity === 'critical' ? $metric->critical_threshold : $metric->warning_threshold,
                'status' => 'open',
                'source' => 'simulated',
                'triggered_at' => $sample->collected_at,
            ]);

            return;
        }

        $open->update([
            'severity' => $severity,
            'message' => $message,
            'observed_value' => $value,
            'threshold_value' => $severity === 'critical' ? $metric->critical_threshold : $metric->warning_threshold,
        ]);
    }

    private function severity(MonitoringMetric $metric, float $value): ?string
    {
        if ($metric->metric_key === 'availability') {
            return $value < 100 ? 'critical' : null;
        }

        if ($metric->critical_threshold !== null && $value >= (float) $metric->critical_threshold) {
            return 'critical';
        }

        if ($metric->warning_threshold !== null && $value >= (float) $metric->warning_threshold) {
            return 'warning';
        }

        return null;
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
