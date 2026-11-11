<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRun extends Model
{
    protected $fillable = [
        'backup_plan_id', 'started_at', 'completed_at', 'result', 'size_mb', 'failure_reason', 'data_source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'size_mb' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BackupPlan::class, 'backup_plan_id');
    }

    public function recoveryTests(): HasMany
    {
        return $this->hasMany(RecoveryTest::class);
    }
}
