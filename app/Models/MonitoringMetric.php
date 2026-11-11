<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringMetric extends Model
{
    protected $fillable = [
        'name', 'metric_key', 'unit', 'description', 'warning_threshold', 'critical_threshold', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'warning_threshold' => 'decimal:2',
            'critical_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MonitoringSample::class);
    }
}
