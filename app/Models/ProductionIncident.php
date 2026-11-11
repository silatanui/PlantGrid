<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionIncident extends Model
{
    protected $fillable = [
        'production_system_id', 'production_equipment_id', 'support_ticket_id', 'severity',
        'description', 'operational_impact', 'downtime_minutes', 'status', 'reported_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(ProductionSystem::class, 'production_system_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ProductionEquipment::class, 'production_equipment_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
