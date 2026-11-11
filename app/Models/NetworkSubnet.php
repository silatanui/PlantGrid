<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkSubnet extends Model
{
    protected $fillable = ['name', 'network_address', 'cidr', 'gateway', 'description'];

    public function vlans(): HasMany
    {
        return $this->hasMany(Vlan::class, 'subnet_id');
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class, 'subnet_id');
    }

    public function label(): string
    {
        return $this->network_address.'/'.$this->cidr;
    }
}
