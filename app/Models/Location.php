<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'status',
        'map_zone',
        'map_x',
        'map_y',
    ];

    protected function casts(): array
    {
        return [
            'map_x' => 'float',
            'map_y' => 'float',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function networkDevices(): HasMany
    {
        return $this->hasMany(NetworkDevice::class);
    }

    public function productionEquipment(): HasMany
    {
        return $this->hasMany(ProductionEquipment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
