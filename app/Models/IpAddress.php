<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAddress extends Model
{
    protected $fillable = [
        'ip_address', 'subnet_id', 'vlan_id', 'assigned_asset_id',
        'assigned_network_device_id', 'address_type', 'status', 'notes',
    ];

    public function subnet(): BelongsTo
    {
        return $this->belongsTo(NetworkSubnet::class, 'subnet_id');
    }

    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'assigned_asset_id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'assigned_network_device_id');
    }
}
