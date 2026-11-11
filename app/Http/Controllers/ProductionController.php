<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\ProductionEquipment;
use App\Models\ProductionIncident;
use App\Models\ProductionSystem;
use App\Models\SupportTicket;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $equipmentSort = TableSort::from(
            $request,
            ['equipment_type', 'status'],
            'equipment_type',
        );
        $incidentSort = TableSort::from(
            $request,
            ['severity', 'reported_at'],
            'reported_at',
            'desc',
        );

        $sortingIncidents = in_array($request->input('sort'), ['severity', 'reported_at'], true);

        $equipmentQuery = ProductionEquipment::query()->with(['system', 'asset', 'location']);
        if ($sortingIncidents) {
            $equipmentQuery->orderBy('equipment_type');
        } else {
            $equipmentSort->apply($equipmentQuery);
        }

        $incidentQuery = ProductionIncident::query()->with(['system', 'equipment', 'ticket']);
        if ($sortingIncidents) {
            $incidentSort->apply($incidentQuery);
        } else {
            $incidentQuery->latest('reported_at');
        }

        return view('production.index', [
            'title' => 'Production systems',
            'systems' => ProductionSystem::query()->with(['department', 'vendor', 'owner'])->orderBy('name')->get(),
            'equipment' => $equipmentQuery->get(),
            'incidents' => $incidentQuery->limit(20)->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'assets' => Asset::query()->orderBy('asset_tag')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'tickets' => SupportTicket::query()->latest('reported_at')->limit(30)->get(),
            'equipmentSort' => $equipmentSort,
            'incidentSort' => $incidentSort,
        ]);
    }

    public function show(ProductionSystem $system)
    {
        $system->load(['department', 'vendor', 'owner', 'equipment.asset', 'incidents.ticket']);

        return view('production.show', [
            'title' => $system->name,
            'system' => $system,
        ]);
    }

    public function storeSystem(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'system_type' => ['required', 'string', 'max:120'],
            'department_id' => ['required', 'exists:departments,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['active', 'under_maintenance', 'retired'])],
        ]);
        $data['business_owner_id'] = $request->user()->id;
        $system = ProductionSystem::query()->create($data);
        $audit->log('production.system_created', 'production_system', $system->id, [], ['name' => $system->name]);

        return redirect()->route('production.show', $system)->with('status', 'Production system recorded. No commands are sent to plant equipment.');
    }

    public function storeEquipment(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'equipment_type' => ['required', 'string', 'max:120'],
            'production_system_id' => ['nullable', 'exists:production_systems,id'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'status' => ['required', Rule::in(['active', 'under_maintenance', 'spare', 'retired'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $equipment = ProductionEquipment::query()->create($data);
        $audit->log('production.equipment_created', 'production_equipment', $equipment->id);

        return back()->with('status', 'Production equipment was added to the register.');
    }

    public function storeIncident(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'production_system_id' => ['nullable', 'exists:production_systems,id'],
            'production_equipment_id' => ['nullable', 'exists:production_equipment,id'],
            'support_ticket_id' => ['nullable', 'exists:support_tickets,id'],
            'severity' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'description' => ['required', 'string', 'max:4000'],
            'operational_impact' => ['nullable', 'string', 'max:2000'],
            'downtime_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['open', 'investigating', 'resolved'])],
        ]);
        $data['reported_at'] = now();
        $data['resolved_at'] = $data['status'] === 'resolved' ? now() : null;
        $incident = ProductionIncident::query()->create($data);
        $audit->log('production.incident_created', 'production_incident', $incident->id, [], ['severity' => $incident->severity]);

        return back()->with('status', 'Production incident was recorded.');
    }
}
