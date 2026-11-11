<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSample extends Model
{
    protected $fillable = [
        'monitoring_metric_id', 'target_type', 'target_id', 'value', 'source', 'scenario', 'collected_at',
    ];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'collected_at' => 'datetime'];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(MonitoringMetric::class, 'monitoring_metric_id');
    }
}
