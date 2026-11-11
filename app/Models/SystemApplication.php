<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemApplication extends Model
{
    protected $fillable = [
        'name', 'application_type', 'vendor_id', 'owner_department_id', 'version', 'description', 'status',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }
}
