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
                'rows' => $assets->map(fn (Asset $asset) => [
                    $this->cell($asset->asset_tag, route('assets.show', $asset)),
                    $this->cell($asset->name, route('assets.show', $asset)),
                    $this->cell($asset->category->name ?? ''),
                    $this->cell($asset->department->name ?? ''),
                    $this->cell(
                        $asset->location->name ?? '',
                        $asset->location ? route('locations.show', $asset->location) : null
                    ),
                    $this->cell($asset->status),
                    $this->cell($asset->condition),
                ]),
            ];
        }

        if ($user->hasPermission('network.view')) {
            $reports['network'] = [
                'label' => 'Network devices',
                'headers' => ['Hostname', 'Type', 'Management IP', 'Status'],
                'rows' => NetworkDevice::query()->orderBy('hostname')->get()->map(fn (NetworkDevice $device) => [
                    $this->cell($device->hostname, route('network.show', $device)),
                    $this->cell($device->device_type),
                    $this->cell($device->management_ip ?? ''),
                    $this->cell($device->status),
                ]),
            ];
            $reports['addresses'] = [
                'label' => 'IP address register',
                'headers' => ['Address', 'Subnet', 'Type', 'Status', 'Device'],
                'rows' => IpAddress::query()->with(['subnet', 'networkDevice'])->orderBy('ip_address')->get()->map(fn (IpAddress $address) => [
                    $this->cell($address->ip_address),
                    $this->cell($address->subnet?->label() ?? ''),
                    $this->cell($address->address_type),
                    $this->cell($address->status),
                    $this->cell(
                        $address->networkDevice->hostname ?? '',
                        $address->networkDevice ? route('network.show', $address->networkDevice) : null
                    ),
                ]),
            ];
        }

        if ($user->hasPermission('servers.view')) {
            $reports['servers'] = [
                'label' => 'Server inventory',
                'headers' => ['Hostname', 'Role', 'Operating system', 'Environment', 'Status'],
                'rows' => Server::query()->orderBy('hostname')->get()->map(fn (Server $server) => [
                    $this->cell($server->hostname, route('servers.show', $server)),
                    $this->cell($server->server_role ?? ''),
                    $this->cell(trim(($server->operating_system ?? '').' '.($server->os_version ?? ''))),
                    $this->cell($server->environment ?? ''),
                    $this->cell($server->status),
                ]),
            ];
        }

        if ($user->hasAnyPermission(['tickets.view.all', 'tickets.view.department', 'tickets.view.own'])) {
            $reports['tickets'] = [
                'label' => 'Helpdesk tickets',
                'headers' => ['Number', 'Subject', 'Priority', 'Status', 'Department'],
                'rows' => SupportTicket::query()->visibleTo($user)->with('department')->latest('reported_at')->get()->map(fn (SupportTicket $ticket) => [
                    $this->cell($ticket->ticket_number, route('helpdesk.show', $ticket)),
                    $this->cell($ticket->subject, route('helpdesk.show', $ticket)),
                    $this->cell($ticket->priority),
                    $this->cell($ticket->status),
                    $this->cell($ticket->department->name ?? ''),
                ]),
            ];
        }

        if ($user->hasPermission('maintenance.view')) {
            $reports['maintenance'] = [
                'label' => 'Maintenance history',
                'headers' => ['Subject', 'Type', 'Status', 'Scheduled'],
                'rows' => MaintenanceRecord::query()->with('asset')->latest('scheduled_at')->get()->map(function (MaintenanceRecord $record) {
                    $url = $record->asset_id ? route('assets.show', $record->asset_id) : null;

                    return [
                        $this->cell($record->subject, $url),
                        $this->cell($record->maintenance_type),
                        $this->cell($record->status),
                        $this->cell(optional($record->scheduled_at)->toDateTimeString() ?? ''),
                    ];
                }),
            ];
        }

        if ($user->hasPermission('production.view')) {
            $reports['production'] = [
                'label' => 'Production incidents',
                'headers' => ['Severity', 'Status', 'Impact', 'Downtime minutes'],
                'rows' => ProductionIncident::query()->latest('reported_at')->get()->map(fn (ProductionIncident $incident) => [
                    $this->cell($incident->severity),
                    $this->cell($incident->status),
                    $this->cell($incident->operational_impact ?? ''),
                    $this->cell((string) ($incident->downtime_minutes ?? '')),
                ]),
            ];
        }

        if ($user->hasPermission('security.view')) {
            $reports['security'] = [
                'label' => 'Security incidents',
                'headers' => ['Type', 'Severity', 'Status', 'Reported'],
                'rows' => SecurityIncident::query()->latest('reported_at')->get()->map(fn (SecurityIncident $incident) => [
                    $this->cell($incident->incident_type),
                    $this->cell($incident->severity),
                    $this->cell($incident->status),
                    $this->cell(optional($incident->reported_at)->toDateTimeString() ?? ''),
                ]),
            ];
            $reports['compliance'] = [
                'label' => 'Compliance checks',
                'headers' => ['Control', 'Result', 'Review date'],
                'rows' => ComplianceCheck::query()->with('control')->latest('review_date')->get()->map(fn (ComplianceCheck $check) => [
                    $this->cell($check->control->name ?? ''),
                    $this->cell($check->result),
                    $this->cell(optional($check->review_date)->toDateString() ?? ''),
                ]),
            ];
        }

        if ($user->hasPermission('backups.view')) {
            $reports['backups'] = [
                'label' => 'Backup runs',
                'headers' => ['Plan', 'Result', 'Source', 'Started'],
                'rows' => BackupRun::query()->with('plan')->latest('started_at')->get()->map(fn (BackupRun $run) => [
                    $this->cell($run->plan->name ?? ''),
                    $this->cell($run->result),
                    $this->cell($run->data_source ?? ''),
                    $this->cell(optional($run->started_at)->toDateTimeString() ?? ''),
                ]),
            ];
        }

        if ($user->hasPermission('monitoring.view')) {
            $reports['alerts'] = [
                'label' => 'Monitoring alerts',
                'headers' => ['Severity', 'Status', 'Source', 'Message'],
                'rows' => MonitoringAlert::query()->latest('triggered_at')->limit(200)->get()->map(fn (MonitoringAlert $alert) => [
                    $this->cell($alert->severity),
                    $this->cell($alert->status),
                    $this->cell($alert->source ?? ''),
                    $this->cell($alert->message),
                ]),
            ];
        }

        return $reports;
    }

    /**
     * @return array{text: string, url: ?string}
     */
    private function cell(string $text, ?string $url = null): array
    {
        return [
            'text' => $text,
            'url' => $url,
        ];
    }
}
