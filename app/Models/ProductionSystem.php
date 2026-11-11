<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSystem extends Model
{
    protected $fillable = [
        'name', 'system_type', 'department_id', 'vendor_id', 'business_owner_id', 'description', 'status',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_owner_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ProductionEquipment::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ProductionIncident::class);
    }
}
