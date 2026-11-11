<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['asset_id', 'previous_status', 'new_status', 'changed_by', 'changed_at', 'notes'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
