<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCheck extends Model
{
    protected $fillable = ['security_control_id', 'checked_by', 'review_date', 'result', 'evidence_path', 'notes'];

    protected function casts(): array
    {
        return ['review_date' => 'date'];
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(SecurityControl::class, 'security_control_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
