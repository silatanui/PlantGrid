<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    protected $fillable = [
        'subject', 'description', 'asset_id', 'server_id', 'network_device_id', 'production_equipment_id',
        'assigned_technician_id', 'vendor_id', 'maintenance_type', 'priority', 'status',
        'scheduled_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ProductionEquipment::class, 'production_equipment_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return array{kind: string, label: string, detail: ?string, url: ?string}|null
     */
    public function linkedTarget(): ?array
    {
        if ($this->asset) {
            return [
                'kind' => 'Asset',
                'label' => $this->asset->asset_tag,
                'detail' => $this->asset->name,
                'url' => route('assets.show', $this->asset),
            ];
        }

        if ($this->server) {
            return [
                'kind' => 'Server',
                'label' => $this->server->hostname,
                'detail' => $this->server->server_role ?? null,
                'url' => route('servers.show', $this->server),
            ];
        }

        if ($this->networkDevice) {
            return [
                'kind' => 'Network',
                'label' => $this->networkDevice->hostname,
                'detail' => $this->networkDevice->device_type ?? null,
                'url' => route('network.show', $this->networkDevice),
            ];
        }

        if ($this->equipment) {
            $url = null;
            if ($this->equipment->relationLoaded('system') && $this->equipment->system) {
                $url = route('production.show', $this->equipment->system);
            } elseif ($this->equipment->relationLoaded('asset') && $this->equipment->asset) {
                $url = route('assets.show', $this->equipment->asset);
            } elseif ($this->equipment->production_system_id) {
                $url = route('production.show', $this->equipment->production_system_id);
            } elseif ($this->equipment->asset_id) {
                $url = route('assets.show', $this->equipment->asset_id);
            }

            return [
                'kind' => 'Equipment',
                'label' => $this->equipment->equipment_type,
                'detail' => trim(($this->equipment->manufacturer ?? '').' '.($this->equipment->model ?? '')) ?: null,
                'url' => $url,
            ];
        }

        return null;
    }
}
