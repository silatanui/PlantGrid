<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEquipment extends Model
{
    protected $table = 'production_equipment';

    protected $fillable = [
        'asset_id', 'production_system_id', 'equipment_type', 'manufacturer', 'model',
        'serial_number', 'location_id', 'status', 'notes',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(ProductionSystem::class, 'production_system_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
