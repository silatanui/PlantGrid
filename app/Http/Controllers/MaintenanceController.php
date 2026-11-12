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

        $base = MaintenanceRecord::query()
            ->with([
                'asset',
                'server',
                'networkDevice',
                'equipment.system',
                'equipment.asset',
                'technician',
                'vendor',
            ])
            ->when($request->user()->limitsRecordsToDepartment(), function ($query) use ($request) {
                $query->whereHas('asset', fn ($asset) => $asset->where('department_id', $request->user()->department_id));
            });

        $summaryBase = (clone $base);
        $summary = [
            'open' => (clone $summaryBase)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'scheduled' => (clone $summaryBase)->where('status', 'scheduled')->count(),
            'in_progress' => (clone $summaryBase)->where('status', 'in_progress')->count(),
            'pending_vendor' => (clone $summaryBase)->where('status', 'pending_vendor')->count(),
            'completed' => (clone $summaryBase)->where('status', 'completed')->count(),
            'overdue' => (clone $summaryBase)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<', now())
                ->count(),
        ];

        $records = $sort->apply(
            (clone $base)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->filled('type'), fn ($query) => $query->where('maintenance_type', $request->string('type')))
                ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%'.$request->string('q').'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->where('subject', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('notes', 'like', $term);
                    });
                })
                ->when($request->boolean('overdue'), function ($query) {
                    $query->whereNotIn('status', ['completed', 'cancelled'])
                        ->whereNotNull('scheduled_at')
                        ->where('scheduled_at', '<', now());
                })
        )
            ->paginate(12)
            ->withQueryString();

        return view('maintenance.index', [
            'title' => 'Maintenance',
            'records' => $records,
            'summary' => $summary,
            'filters' => [
                'q' => (string) $request->string('q'),
                'status' => (string) $request->string('status'),
                'type' => (string) $request->string('type'),
                'priority' => (string) $request->string('priority'),
                'overdue' => $request->boolean('overdue'),
            ],
            'assets' => Asset::query()->orderBy('asset_tag')->limit(200)->get(),
            'servers' => Server::query()->orderBy('hostname')->get(),
            'devices' => NetworkDevice::query()->orderBy('hostname')->get(),
            'equipment' => ProductionEquipment::query()->with('system')->orderBy('equipment_type')->get(),
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

        if ($data['status'] === 'completed') {
            $maintenance->completed_at = $maintenance->completed_at ?? now();
        } else {
            // Allow undoing an accidental complete click.
            $maintenance->completed_at = null;
        }

        $maintenance->save();
        $audit->log(
            'maintenance.updated',
            'maintenance_record',
            $maintenance->id,
            ['status' => $previous, 'completed_at' => $previous === 'completed'],
            ['status' => $maintenance->status, 'completed_at' => $maintenance->completed_at]
        );

        $message = $data['status'] === 'completed'
            ? 'Maintenance marked complete.'
            : ($previous === 'completed'
                ? 'Maintenance reopened — status set to '.\App\Support\Labels::status($data['status']).'.'
                : 'Maintenance status updated.');

        return back()->with('status', $message);
    }
}
