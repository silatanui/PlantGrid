<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'asset_id', 'hostname', 'operating_system', 'os_version', 'server_role',
        'environment', 'cpu_cores', 'ram_gb', 'storage_gb', 'ip_address_id', 'status', 'notes',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function ipAddress(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServerService::class);
    }
}
