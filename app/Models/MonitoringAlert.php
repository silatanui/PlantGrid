<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlert extends Model
{
    protected $fillable = [
        'monitoring_metric_id', 'target_type', 'target_id', 'severity', 'message', 'observed_value',
        'threshold_value', 'status', 'source', 'triggered_at', 'acknowledged_by', 'acknowledged_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_value' => 'decimal:2',
            'threshold_value' => 'decimal:2',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(MonitoringMetric::class, 'monitoring_metric_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
