<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag', 'name', 'asset_category_id', 'vendor_id', 'manufacturer', 'model',
        'serial_number', 'alternate_id', 'department_id', 'location_id', 'parent_asset_id',
        'purchase_date', 'purchase_cost', 'purchase_currency', 'purchase_order', 'invoice_number',
        'warranty_expiry', 'expected_life_months', 'hostname', 'ip_address', 'mac_address',
        'condition', 'status', 'criticality', 'specifications', 'notes', 'photo_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'purchase_cost' => 'decimal:2',
            'expected_life_months' => 'integer',
            'specifications' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class);
    }

    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->limitsRecordsToDepartment()) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }
}
