<?php

namespace App\Http\Controllers;

use App\Models\BackupPlan;
use App\Models\BackupRun;
use App\Models\RecoveryTest;
use App\Models\Server;
use App\Models\SystemApplication;
use App\Services\AuditLogger;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        $planSort = TableSort::from($request, ['name', 'frequency', 'backup_type', 'retention_days'], 'name');
        $runSort = TableSort::from($request, ['started_at', 'result', 'size_mb'], 'started_at', 'desc');
        $sortingRuns = in_array($request->input('sort'), ['started_at', 'result', 'size_mb'], true);

        $plansQuery = BackupPlan::query()->with(['server', 'application']);
        $runsQuery = BackupRun::query()->with('plan');
        if ($sortingRuns) {
            $plansQuery->orderBy('name');
            $runSort->apply($runsQuery);
        } else {
            $planSort->apply($plansQuery);
            $runsQuery->latest('started_at');
        }

        return view('backups.index', [
            'title' => 'Backups and recovery',
            'plans' => $plansQuery->get(),
            'runs' => $runsQuery->limit(20)->get(),
            'tests' => RecoveryTest::query()->with(['run.plan', 'tester'])->latest('test_date')->limit(12)->get(),
            'servers' => Server::query()->orderBy('hostname')->get(),
            'applications' => SystemApplication::query()->orderBy('name')->get(),
            'planSort' => $planSort,
            'runSort' => $runSort,
        ]);
    }

    public function storePlan(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'server_id' => ['nullable', 'exists:servers,id'],
            'system_application_id' => ['nullable', 'exists:system_applications,id'],
            'frequency' => ['required', Rule::in(['hourly', 'daily', 'weekly', 'monthly'])],
            'backup_type' => ['required', Rule::in(['full', 'incremental', 'differential'])],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['status'] = 'active';
        $plan = BackupPlan::query()->create($data);
        $audit->log('backup.plan_created', 'backup_plan', $plan->id, [], ['name' => $plan->name]);

        return back()->with('status', 'Backup plan recorded. PlantGrid does not run the backup itself.');
    }

    public function storeRun(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $data = $request->validate([
            'backup_plan_id' => ['required', 'exists:backup_plans,id'],
            'result' => ['required', Rule::in(['successful', 'failed'])],
            'size_mb' => ['nullable', 'numeric', 'min:0'],
            'failure_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $run = BackupRun::query()->create([
            ...$data,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
            'data_source' => 'simulated',
        ]);
        $audit->log('backup.run_recorded', 'backup_run', $run->id, [], ['result' => $run->result, 'data_source' => 'simulated']);

        return back()->with('status', 'Simulated backup run recorded. This does not mean a real server was backed up.');
    }

    public function storeTest(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $data = $request->validate([
            'backup_run_id' => ['nullable', 'exists:backup_runs,id'],
            'test_date' => ['required', 'date'],
            'result' => ['required', Rule::in(['pass', 'fail'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $test = RecoveryTest::query()->create([
            ...$data,
            'tested_by' => $request->user()->id,
        ]);
        $audit->log('backup.recovery_tested', 'recovery_test', $test->id, [], ['result' => $test->result]);

        return back()->with('status', 'Recovery test recorded.');
    }
}
