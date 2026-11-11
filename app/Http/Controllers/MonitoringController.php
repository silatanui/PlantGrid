<?php

namespace App\Http\Controllers;

use App\Models\MonitoringAlert;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Monitoring\MetricCollectionService;
use App\Services\AuditLogger;
use App\Services\MonitoringPresentation;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringPresentation $presentation)
    {
        $scenario = SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';
        $target = $presentation->resolveTarget($request->input('target'), $scenario);
        $readings = $presentation->latestReadings($target['type'], $target['id']);
        $banner = $presentation->scenarioBanner($scenario, $target['hostname']);
        $outageHost = (string) config('plantgrid.outage_hostname');
        $availability = optional($readings->get('availability'))->value;
        $alertSort = TableSort::from(
            $request,
            ['triggered_at', 'severity', 'status', 'message'],
            'triggered_at',
            'desc',
        );

        return view('monitoring.index', [
            'title' => 'Monitoring',
            'scenario' => $scenario,
            'banner' => $banner,
            'servers' => Server::query()->orderBy('hostname')->get(),
            'devices' => NetworkDevice::query()->orderBy('hostname')->get(),
            'selected' => $target['selected'],
            'targetHostname' => $target['hostname'],
            'targetType' => $target['type'],
            'outageHost' => $outageHost,
            'degradedHost' => (string) config('plantgrid.degraded_hostname'),
            'readings' => $readings,
            'availability' => $availability,
            'isOutageTarget' => $scenario === 'outage' && $target['hostname'] === $outageHost,
            'alerts' => $alertSort->apply(
                MonitoringAlert::query()->with('metric')
            )->limit(25)->get(),
            'alertSort' => $alertSort,
            'chart' => $presentation->chart($target['type'], $target['id'], $scenario),
            'recommendedTarget' => $presentation->defaultTarget($scenario),
        ]);
    }

    public function scenario(Request $request, MetricCollectionService $collector, AuditLogger $audit, MonitoringPresentation $presentation)
    {
        abort_unless($request->user()->hasPermission('monitoring.manage'), 403);
        $data = $request->validate([
            'scenario' => ['required', Rule::in(['normal', 'degraded', 'outage'])],
        ]);
        $previous = SystemSetting::getValue('monitoring_scenario', 'normal');
        SystemSetting::putValue('monitoring_scenario', $data['scenario'], $request->user()->id, 'Active monitoring demonstration scenario');
        $collector->demonstrate($data['scenario']);
        $audit->log('monitoring.scenario', 'system_setting', null, ['scenario' => $previous], ['scenario' => $data['scenario']]);

        return redirect()
            ->route('monitoring.index', ['target' => $presentation->defaultTarget($data['scenario'])])
            ->with('status', 'Scenario set to '.$data['scenario'].'. The chart target was switched to the affected host.');
    }

    public function collect(Request $request, MetricCollectionService $collector)
    {
        abort_unless($request->user()->hasPermission('monitoring.manage'), 403);
        $count = $collector->collect();

        return back()->with('status', $count.' simulated samples were stored.');
    }

    public function acknowledge(Request $request, MonitoringAlert $alert, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('alerts.acknowledge'), 403);
        abort_unless(in_array($alert->status, ['open', 'acknowledged'], true), 422);
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);
        $audit->log('monitoring.acknowledged', 'monitoring_alert', $alert->id);

        return back()->with('status', 'Alert acknowledged. The source remains simulated.');
    }

    public function resolve(Request $request, MonitoringAlert $alert, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('monitoring.manage'), 403);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $audit->log('monitoring.resolved', 'monitoring_alert', $alert->id);

        return back()->with('status', 'Alert marked resolved.');
    }
}
