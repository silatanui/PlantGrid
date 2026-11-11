<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkLink extends Model
{
    protected $fillable = [
        'source_device_id', 'destination_device_id', 'connection_type',
        'port_or_interface', 'status', 'notes',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'source_device_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'destination_device_id');
    }
}
