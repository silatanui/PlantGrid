<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vlan extends Model
{
    protected $fillable = ['vlan_number', 'name', 'subnet_id', 'description'];

    public function subnet(): BelongsTo
    {
        return $this->belongsTo(NetworkSubnet::class, 'subnet_id');
    }
}
