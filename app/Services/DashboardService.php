<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\BackupPlan;
use App\Models\MaintenanceRecord;
use App\Models\MonitoringAlert;
use App\Models\MonitoringSample;
use App\Models\NetworkDevice;
use App\Models\ProductionEquipment;
use App\Models\ProductionIncident;
use App\Models\SecurityControl;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct() {}

    public function cards(User $user): array
    {
        $cards = [];

        if ($user->hasPermission('assets.view')) {
            $assets = Asset::query()->visibleTo($user);
            $underMaintenance = (clone $assets)->where('status', 'under_maintenance')->count();
            $cards[] = $this->card('ICT assets', (clone $assets)->count(), (clone $assets)->where('status', 'active')->count().' active', 'boxes', route('assets.index'));
            $cards[] = $this->card('Needs maintenance', $underMaintenance, 'Assets under maintenance', 'wrench', route('assets.index', ['status' => 'under_maintenance']), $underMaintenance > 0 ? 'warning' : null);
        }

        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $tickets = SupportTicket::query()->visibleTo($user);
            $openCount = (clone $tickets)->open()->count();
            if ($user->isEmployeeRequester()) {
                $waiting = (clone $tickets)->open()->whereIn('status', ['new', 'assigned', 'in_progress', 'pending'])->count();
                $cards[] = $this->card('My open cases', $openCount, 'Requests you opened that are still open', 'life-buoy', route('helpdesk.index', ['scope' => 'mine']), $openCount > 0 ? 'warning' : null);
                $cards[] = $this->card('Waiting on ICT', $waiting, 'Your cases with ICT staff', 'clock', route('helpdesk.index', ['scope' => 'mine']));
            } else {
                $criticalTickets = (clone $tickets)->open()->where('priority', 'critical')->count();
                $criticalProduction = $user->hasPermission('production.view')
                    ? ProductionIncident::query()->where('status', 'open')->where('severity', 'critical')->count()
                    : 0;
                $criticalTotal = $criticalTickets + $criticalProduction;
                $cards[] = $this->card('Open tickets', $openCount, 'Not resolved or closed', 'life-buoy', route('helpdesk.index', ['status' => 'open']), $openCount > 0 ? 'warning' : null);
                $cards[] = $this->card('Critical incidents', $criticalTotal, 'Open critical tickets and production incidents', 'alert-triangle', route('helpdesk.index', ['priority' => 'critical', 'status' => 'open']), $criticalTotal > 0 ? 'critical' : null);
            }
        }

        if ($user->hasPermission('network.view')) {
            $cards[] = $this->card('Network devices', NetworkDevice::query()->count(), 'Documented devices', 'network', route('network.index'));
        }

        if ($user->hasPermission('servers.view')) {
            $cards[] = $this->card('Servers', Server::query()->count(), 'Simulated production records', 'server', route('servers.index'));
        }

        if ($user->hasPermission('production.view')) {
            $prodMaint = ProductionEquipment::query()->where('status', 'under_maintenance')->count();
            $cards[] = $this->card(
                'Equipment in maintenance',
                $prodMaint,
                'Production equipment',
                'factory',
                route('production.index'),
                $prodMaint > 0 ? 'warning' : null
            );
        }

        if ($user->hasPermission('backups.view')) {
            $latest = BackupPlan::query()->with(['runs' => fn ($query) => $query->latest('started_at')->limit(1)])->get();
            $successful = $latest->filter(fn ($plan) => optional($plan->runs->first())->result === 'successful')->count();
            $failed = $latest->filter(fn ($plan) => optional($plan->runs->first())->result === 'failed')->count();
            $cards[] = $this->card('Backup results', $successful.' / '.$failed, 'Latest simulated success / failure', 'hard-drive', route('backups.index'), $failed > 0 ? 'warning' : null);
        }

        if ($user->hasPermission('monitoring.view')) {
            $activeAlerts = MonitoringAlert::query()->whereIn('status', ['open', 'acknowledged'])->count();
            $cards[] = $this->card(
                'Active alerts',
                $activeAlerts,
                'Simulated monitoring',
                'activity',
                route('monitoring.index'),
                $activeAlerts > 0 ? 'critical' : null
            );
        }

        return $cards;
    }

    /**
     * @return list<array{label: string, route: string, icon: string}>
     */
    public function quickLinks(User $user): array
    {
        $links = [];

        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $links[] = ['label' => $user->isEmployeeRequester() ? 'My cases' : 'Cases', 'route' => 'helpdesk.index', 'icon' => 'life-buoy'];
        }
        if ($user->hasPermission('tickets.create')) {
            $links[] = ['label' => 'New case', 'route' => 'helpdesk.create', 'icon' => 'plus'];
        }
        if ($user->hasPermission('assets.view')) {
            $links[] = ['label' => 'Assets', 'route' => 'assets.index', 'icon' => 'boxes'];
        }
        if ($user->hasPermission('network.view')) {
            $links[] = ['label' => 'Network', 'route' => 'network.index', 'icon' => 'network'];
        }
        if ($user->hasPermission('production.view')) {
            $links[] = ['label' => 'Production', 'route' => 'production.index', 'icon' => 'factory'];
        }
        if ($user->hasPermission('monitoring.view')) {
            $links[] = ['label' => 'Monitoring', 'route' => 'monitoring.index', 'icon' => 'activity'];
        }
        if ($user->hasPermission('reports.view')) {
            $links[] = ['label' => 'Performance', 'route' => 'performance.index', 'icon' => 'gauge'];
        }

        return array_slice($links, 0, 6);
    }

    public function notices(User $user): array
    {
        $notices = [];

        if ($user->hasAnyPermission(['tickets.view.all', 'tickets.assign'])) {
            $unassigned = SupportTicket::query()->where('status', 'new')->whereNull('assigned_to')->count();
            if ($unassigned > 0) {
                $notices[] = [
                    'tone' => 'warning',
                    'text' => $unassigned.' new support '.($unassigned === 1 ? 'ticket is' : 'tickets are').' waiting for assignment.',
                ];
            }
        }

        if ($user->hasPermission('monitoring.view')) {
            $outages = MonitoringAlert::query()->where('status', 'open')->where('severity', 'critical')->count();
            if ($outages > 0) {
                $notices[] = [
                    'tone' => 'critical',
                    'text' => $outages.' critical simulated '.($outages === 1 ? 'alert is' : 'alerts are').' open. These readings are not from live plant equipment.',
                ];
            }
        }

        return $notices;
    }

    public function charts(User $user): array
    {
        $charts = [];

        if ($user->hasAnyPermission(['tickets.view.all', 'tickets.view.department', 'audit.view'])) {
            $tickets = SupportTicket::query()->visibleTo($user)->get();
            $charts[] = [
                'title' => 'Tickets by status',
                'caption' => 'Calculated from the ticket register.',
                'config' => $this->doughnut(
                    $tickets->countBy('status')->sortKeys()->keys()->map(fn ($status) => ucwords(str_replace('_', ' ', $status)))->values()->all(),
                    $tickets->countBy('status')->sortKeys()->values()->all()
                ),
            ];
            $charts[] = [
                'title' => 'Open tickets by priority',
                'caption' => 'Open tickets only.',
                'config' => $this->bar(
                    ['Critical', 'High', 'Medium', 'Low'],
                    [[
                        'label' => 'Tickets',
                        'data' => [
                            $tickets->whereNotIn('status', ['resolved', 'closed'])->where('priority', 'critical')->count(),
                            $tickets->whereNotIn('status', ['resolved', 'closed'])->where('priority', 'high')->count(),
                            $tickets->whereNotIn('status', ['resolved', 'closed'])->where('priority', 'medium')->count(),
                            $tickets->whereNotIn('status', ['resolved', 'closed'])->where('priority', 'low')->count(),
                        ],
                        'backgroundColor' => ['#EA001E', '#DD7A01', '#0176D3', '#706E6B'],
                    ]],
                    false
                ),
            ];
        }

        if ($user->hasPermission('assets.view')) {
            $assets = Asset::query()->visibleTo($user)->with(['category', 'department'])->get();
            $byCategory = $assets->groupBy(fn ($asset) => $asset->category->name ?? 'Uncategorised');
            $charts[] = [
                'title' => 'Assets by category',
                'caption' => 'Current register, including retired items.',
                'config' => $this->doughnut($byCategory->keys()->values()->all(), $byCategory->map->count()->values()->all()),
            ];
            $byDepartment = $assets->groupBy(fn ($asset) => $asset->department->code ?? 'Unassigned');
            $charts[] = [
                'title' => 'Assets by department',
                'caption' => 'Department currently recorded on the asset.',
                'config' => $this->bar(
                    $byDepartment->keys()->values()->all(),
                    [[
                        'label' => 'Assets',
                        'data' => $byDepartment->map->count()->values()->all(),
                        'backgroundColor' => '#0176D3',
                    ]]
                ),
            ];
        }

        if ($user->hasPermission('maintenance.view')) {
            $start = now()->startOfMonth()->subMonths(5);
            $records = MaintenanceRecord::query()->where('scheduled_at', '>=', $start)->get();
            $labels = [];
            $values = [];
            for ($month = $start->copy(); $month <= now()->startOfMonth(); $month->addMonth()) {
                $labels[] = $month->format('M Y');
                $values[] = $records->filter(fn ($record) => optional($record->scheduled_at)->format('Y-m') === $month->format('Y-m'))->count();
            }
            $charts[] = [
                'title' => 'Maintenance activity',
                'caption' => 'Work orders by scheduled month.',
                'config' => $this->bar($labels, [[
                    'label' => 'Work orders',
                    'data' => $values,
                    'backgroundColor' => '#032D60',
                ]]),
            ];
        }

        if ($user->hasPermission('monitoring.view')) {
            $server = Server::query()->where('hostname', 'PLT-APP01')->first();
            if ($server) {
                $charts[] = [
                    'title' => 'PLT-APP01 resource history',
                    'caption' => 'Simulated CPU and memory. Not a live server.',
                    'config' => $this->historyChart('server', $server->id, ['cpu' => 'CPU %', 'memory' => 'Memory %']),
                ];
            }

            $device = NetworkDevice::query()->where('hostname', config('plantgrid.outage_hostname'))->first();
            if ($device) {
                $charts[] = [
                    'title' => $device->hostname.' availability',
                    'caption' => 'Simulated availability for the packaging switch.',
                    'config' => $this->historyChart('network_device', $device->id, ['availability' => 'Availability %']),
                ];
            }
        }

        return array_values(array_filter($charts, fn ($chart) => ! empty($chart['config']['data']['labels'])));
    }

    public function deadlines(User $user): array
    {
        $items = [];

        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $tickets = SupportTicket::query()
                ->visibleTo($user)
                ->where(function ($query) {
                    $query->whereNotNull('sla_due_at')->orWhereNotNull('due_at');
                })
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderByRaw('COALESCE(sla_due_at, due_at)')
                ->limit(8)
                ->get();

            foreach ($tickets as $ticket) {
                $due = $ticket->sla_due_at ?? $ticket->due_at;
                $items[] = $this->deadline('deadline', 'SLA / deadline', $ticket->subject, $ticket->ticket_number, $due, route('helpdesk.show', $ticket));
            }
        }

        if ($user->hasPermission('maintenance.view')) {
            $records = MaintenanceRecord::query()
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get();

            foreach ($records as $record) {
                $items[] = $this->deadline('maintenance', 'Maintenance', $record->subject, 'Work order', $record->scheduled_at, route('maintenance.index'));
            }
        }

        if ($user->hasPermission('security.view')) {
            $controls = SecurityControl::query()
                ->whereNotNull('next_review_at')
                ->orderBy('next_review_at')
                ->limit(6)
                ->get();

            foreach ($controls as $control) {
                $items[] = $this->deadline('compliance', 'Compliance', $control->name, $control->control_category, $control->next_review_at, route('security.index'));
            }
        }

        usort($items, fn (array $left, array $right) => $left['at'] <=> $right['at']);

        return array_slice($items, 0, 8);
    }

    public function recentTickets(User $user)
    {
        if (! $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            return collect();
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->with(['requester', 'assignee', 'asset'])
            ->latest('reported_at')
            ->limit(5)
            ->get();
    }

    public function activity(): \Illuminate\Support\Collection
    {
        return AuditLog::query()->with('user')->latest('created_at')->limit(8)->get();
    }

    public function assignedTickets(User $user)
    {
        if ($user->hasPermission('tickets.update')) {
            return SupportTicket::query()
                ->with(['requester', 'category'])
                ->where('assigned_to', $user->id)
                ->open()
                ->latest('reported_at')
                ->limit(5)
                ->get();
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->with(['category'])
            ->open()
            ->latest('reported_at')
            ->limit(5)
            ->get();
    }

    public function upcomingMaintenance()
    {
        return MaintenanceRecord::query()
            ->with(['asset', 'technician'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }

    public function alerts()
    {
        return MonitoringAlert::query()
            ->with('metric')
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(5)
            ->get();
    }

    private function deadline(string $kind, string $label, string $title, string $detail, mixed $at, string $href): array
    {
        $when = Carbon::parse($at)->timezone(config('plantgrid.display_timezone'));
        $now = now(config('plantgrid.display_timezone'));
        $overdue = $when->isPast();
        $days = max(0, (int) abs($when->diffInDays($now)));

        return [
            'kind' => $kind,
            'label' => $label,
            'title' => $title,
            'detail' => $detail,
            'at' => $when,
            'tone' => $overdue ? 'overdue' : 'upcoming',
            'when' => $overdue ? ($days === 0 ? 'Overdue' : $days.'d overdue') : ($days === 0 ? 'Due today' : 'in '.$days.'d'),
            'href' => $href,
        ];
    }

    private function card(string $label, mixed $value, string $hint, ?string $icon = null, ?string $href = null, ?string $tone = null): array
    {
        return compact('label', 'value', 'hint', 'icon', 'href', 'tone');
    }

    private function doughnut(array $labels, array $values): array
    {
        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $values,
                    'backgroundColor' => ['#0176D3', '#032D60', '#DD7A01', '#EA001E', '#0B5CAB', '#706E6B', '#939393', '#747474'],
                    'borderWidth' => 0,
                ]],
            ],
            'options' => [
                'plugins' => ['legend' => ['position' => 'bottom']],
                'cutout' => '62%',
            ],
        ];
    }

    private function bar(array $labels, array $datasets, bool $beginAtZero = true): array
    {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => count($datasets) > 1]],
                'scales' => [
                    'y' => ['beginAtZero' => $beginAtZero, 'ticks' => ['precision' => 0]],
                    'x' => ['grid' => ['display' => false]],
                ],
            ],
        ];
    }

    private function historyChart(string $type, int $id, array $metrics): array
    {
        $samples = MonitoringSample::query()
            ->with('metric')
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->whereHas('metric', fn ($query) => $query->whereIn('metric_key', array_keys($metrics)))
            ->orderBy('collected_at')
            ->get();

        $labels = $samples->map(fn ($sample) => Carbon::parse($sample->collected_at)->timezone(config('plantgrid.display_timezone'))->format('H:i'))->unique()->values();
        $colors = ['#0176D3', '#032D60', '#DD7A01'];
        $datasets = [];
        $index = 0;

        foreach ($metrics as $key => $label) {
            $points = $samples
                ->filter(fn ($sample) => $sample->metric?->metric_key === $key)
                ->keyBy(fn ($sample) => Carbon::parse($sample->collected_at)->timezone(config('plantgrid.display_timezone'))->format('H:i'));
            $datasets[] = [
                'label' => $label,
                'data' => $labels->map(fn ($labelTime) => isset($points[$labelTime]) ? (float) $points[$labelTime]->value : null)->all(),
                'borderColor' => $colors[$index % count($colors)],
                'backgroundColor' => $colors[$index % count($colors)],
                'tension' => 0.25,
                'pointRadius' => 2,
            ];
            $index++;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels->all(),
                'datasets' => $datasets,
            ],
            'options' => [
                'plugins' => ['legend' => ['position' => 'bottom']],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                    'x' => ['grid' => ['display' => false]],
                ],
            ],
        ];
    }
}
