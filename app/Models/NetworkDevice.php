<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    protected $fillable = [
        'asset_id', 'hostname', 'device_type', 'manufacturer', 'model',
        'management_ip', 'location_id', 'status', 'notes',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sourceLinks(): HasMany
    {
        return $this->hasMany(NetworkLink::class, 'source_device_id');
    }

    public function destinationLinks(): HasMany
    {
        return $this->hasMany(NetworkLink::class, 'destination_device_id');
    }
}
