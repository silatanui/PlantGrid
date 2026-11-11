<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupPlan extends Model
{
    protected $fillable = [
        'name', 'server_id', 'system_application_id', 'frequency', 'backup_type',
        'retention_days', 'status', 'description',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SystemApplication::class, 'system_application_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }
}
