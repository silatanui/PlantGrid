<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMaintenanceRecord;
use App\Models\MaintenanceRecord;
use App\Models\NetworkDevice;
use App\Models\ProductionEquipment;
use App\Models\Server;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $sort = TableSort::from(
            $request,
            ['subject', 'maintenance_type', 'scheduled_at', 'status', 'priority'],
            'scheduled_at',
            'desc',
        );

        $records = $sort->apply(
            MaintenanceRecord::query()
                ->with(['asset', 'server', 'networkDevice', 'equipment', 'technician', 'vendor'])
                ->when($request->user()->limitsRecordsToDepartment(), function ($query) use ($request) {
                    $query->whereHas('asset', fn ($asset) => $asset->where('department_id', $request->user()->department_id));
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
        )
            ->paginate(12)
            ->withQueryString();

        return view('maintenance.index', [
            'title' => 'Maintenance',
            'records' => $records,
            'assets' => Asset::query()->orderBy('asset_tag')->limit(200)->get(),
            'servers' => Server::query()->orderBy('hostname')->get(),
            'devices' => NetworkDevice::query()->orderBy('hostname')->get(),
            'equipment' => ProductionEquipment::query()->orderBy('equipment_type')->get(),
            'technicians' => User::query()->where('status', 'active')->orderBy('name')->get(),
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'sort' => $sort,
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'server_id' => ['nullable', 'exists:servers,id'],
            'network_device_id' => ['nullable', 'exists:network_devices,id'],
            'production_equipment_id' => ['nullable', 'exists:production_equipment,id'],
            'assigned_technician_id' => ['nullable', 'exists:users,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'maintenance_type' => ['required', Rule::in(['preventive', 'corrective', 'inspection'])],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'pending_vendor', 'completed', 'cancelled'])],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($data['asset_id']) && empty($data['server_id']) && empty($data['network_device_id']) && empty($data['production_equipment_id'])) {
            return back()->withInput()->withErrors(['subject' => 'Link the work order to an asset, server, network device, or production equipment record.']);
        }

        if ($data['status'] === 'completed') {
            $data['completed_at'] = now();
        }

        $record = MaintenanceRecord::query()->create($data);

        if (! empty($data['asset_id'])) {
            AssetMaintenanceRecord::query()->create([
                'asset_id' => $data['asset_id'],
                'technician_id' => $data['assigned_technician_id'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'maintenance_type' => $data['maintenance_type'],
                'description' => $data['description'] ?: $data['subject'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $audit->log('maintenance.created', 'maintenance_record', $record->id, [], ['subject' => $record->subject]);

        return back()->with('status', 'Maintenance record was saved.');
    }

    public function update(Request $request, MaintenanceRecord $maintenance, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'pending_vendor', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $previous = $maintenance->status;
        $maintenance->status = $data['status'];
        $maintenance->notes = $data['notes'] ?? $maintenance->notes;
        $maintenance->completed_at = $data['status'] === 'completed' ? now() : $maintenance->completed_at;
        $maintenance->save();
        $audit->log('maintenance.updated', 'maintenance_record', $maintenance->id, ['status' => $previous], ['status' => $maintenance->status]);

        return back()->with('status', 'Maintenance status updated.');
    }
}
