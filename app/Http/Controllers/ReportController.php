<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\BackupRun;
use App\Models\ComplianceCheck;
use App\Models\IpAddress;
use App\Models\MaintenanceRecord;
use App\Models\MonitoringAlert;
use App\Models\NetworkDevice;
use App\Models\ProductionIncident;
use App\Models\SecurityIncident;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Support\Csv;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $catalog = $this->catalog($request);
        $selected = $request->input('report', array_key_first($catalog) ?? 'assets');
        abort_unless(isset($catalog[$selected]), 404);
        $rows = $catalog[$selected]['rows'];

        return view('reports.index', [
            'title' => 'Reports',
            'catalog' => $catalog,
            'selected' => $selected,
            'headers' => $catalog[$selected]['headers'],
            'rows' => $rows->take(50),
            'total' => $rows->count(),
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);
        $catalog = $this->catalog($request);
        $selected = $request->input('report', 'assets');
        abort_unless(isset($catalog[$selected]), 404);

        return Csv::download('plantgrid-'.$selected.'.csv', $catalog[$selected]['headers'], $catalog[$selected]['rows']);
    }

    private function catalog(Request $request): array
    {
        $user = $request->user();
        $reports = [];

        if ($user->hasPermission('assets.view')) {
            $assets = Asset::query()->visibleTo($user)->with(['category', 'department', 'location'])->orderBy('asset_tag')->get();
            $reports['assets'] = [
                'label' => 'ICT asset inventory',
                'headers' => ['Tag', 'Name', 'Category', 'Department', 'Location', 'Status', 'Condition'],
                'rows' => $assets->map(fn ($asset) => [
                    $asset->asset_tag, $asset->name, $asset->category->name ?? '', $asset->department->name ?? '',
                    $asset->location->name ?? '', $asset->status, $asset->condition,
                ]),
            ];
        }

        if ($user->hasPermission('network.view')) {
            $reports['network'] = [
                'label' => 'Network devices',
                'headers' => ['Hostname', 'Type', 'Management IP', 'Status'],
                'rows' => NetworkDevice::query()->orderBy('hostname')->get()->map(fn ($device) => [
                    $device->hostname, $device->device_type, $device->management_ip, $device->status,
                ]),
            ];
            $reports['addresses'] = [
                'label' => 'IP address register',
                'headers' => ['Address', 'Subnet', 'Type', 'Status', 'Device'],
                'rows' => IpAddress::query()->with(['subnet', 'networkDevice'])->orderBy('ip_address')->get()->map(fn ($address) => [
                    $address->ip_address, $address->subnet?->label(), $address->address_type, $address->status, $address->networkDevice->hostname ?? '',
                ]),
            ];
        }

        if ($user->hasPermission('servers.view')) {
            $reports['servers'] = [
                'label' => 'Server inventory',
                'headers' => ['Hostname', 'Role', 'Operating system', 'Environment', 'Status'],
                'rows' => Server::query()->orderBy('hostname')->get()->map(fn ($server) => [
                    $server->hostname, $server->server_role, $server->operating_system.' '.$server->os_version, $server->environment, $server->status,
                ]),
            ];
        }

        if ($user->hasAnyPermission(['tickets.view.all', 'tickets.view.department', 'tickets.view.own'])) {
            $reports['tickets'] = [
                'label' => 'Helpdesk tickets',
                'headers' => ['Number', 'Subject', 'Priority', 'Status', 'Department'],
                'rows' => SupportTicket::query()->visibleTo($user)->with('department')->latest('reported_at')->get()->map(fn ($ticket) => [
                    $ticket->ticket_number, $ticket->subject, $ticket->priority, $ticket->status, $ticket->department->name ?? '',
                ]),
            ];
        }

        if ($user->hasPermission('maintenance.view')) {
            $reports['maintenance'] = [
                'label' => 'Maintenance history',
                'headers' => ['Subject', 'Type', 'Status', 'Scheduled'],
                'rows' => MaintenanceRecord::query()->latest('scheduled_at')->get()->map(fn ($record) => [
                    $record->subject, $record->maintenance_type, $record->status, optional($record->scheduled_at)->toDateTimeString(),
                ]),
            ];
        }

        if ($user->hasPermission('production.view')) {
            $reports['production'] = [
                'label' => 'Production incidents',
                'headers' => ['Severity', 'Status', 'Impact', 'Downtime minutes'],
                'rows' => ProductionIncident::query()->latest('reported_at')->get()->map(fn ($incident) => [
                    $incident->severity, $incident->status, $incident->operational_impact, $incident->downtime_minutes,
                ]),
            ];
        }

        if ($user->hasPermission('security.view')) {
            $reports['security'] = [
                'label' => 'Security incidents',
                'headers' => ['Type', 'Severity', 'Status', 'Reported'],
                'rows' => SecurityIncident::query()->latest('reported_at')->get()->map(fn ($incident) => [
                    $incident->incident_type, $incident->severity, $incident->status, optional($incident->reported_at)->toDateTimeString(),
                ]),
            ];
            $reports['compliance'] = [
                'label' => 'Compliance checks',
                'headers' => ['Control', 'Result', 'Review date'],
                'rows' => ComplianceCheck::query()->with('control')->latest('review_date')->get()->map(fn ($check) => [
                    $check->control->name ?? '', $check->result, optional($check->review_date)->toDateString(),
                ]),
            ];
        }

        if ($user->hasPermission('backups.view')) {
            $reports['backups'] = [
                'label' => 'Backup runs',
                'headers' => ['Plan', 'Result', 'Source', 'Started'],
                'rows' => BackupRun::query()->with('plan')->latest('started_at')->get()->map(fn ($run) => [
                    $run->plan->name ?? '', $run->result, $run->data_source, optional($run->started_at)->toDateTimeString(),
                ]),
            ];
        }

        if ($user->hasPermission('monitoring.view')) {
            $reports['alerts'] = [
                'label' => 'Monitoring alerts',
                'headers' => ['Severity', 'Status', 'Source', 'Message'],
                'rows' => MonitoringAlert::query()->latest('triggered_at')->limit(200)->get()->map(fn ($alert) => [
                    $alert->severity, $alert->status, $alert->source, $alert->message,
                ]),
            ];
        }

        return $reports;
    }
}
