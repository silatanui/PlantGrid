<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryTest extends Model
{
    protected $fillable = ['backup_run_id', 'tested_by', 'test_date', 'result', 'notes', 'evidence_path'];

    protected function casts(): array
    {
        return ['test_date' => 'date'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
