<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityControl extends Model
{
    protected $fillable = [
        'name', 'control_category', 'description', 'owner_id', 'status',
        'review_frequency', 'last_reviewed_at', 'next_review_at',
    ];

    protected function casts(): array
    {
        return ['last_reviewed_at' => 'datetime', 'next_review_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(ComplianceCheck::class);
    }
}
