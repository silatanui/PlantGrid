<?php

namespace App\Http\Controllers;

use App\Models\ComplianceCheck;
use App\Models\SecurityControl;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $checkSort = TableSort::from(
            $request,
            ['review_date', 'result'],
            'review_date',
            'desc',
        );
        $controlSort = TableSort::from(
            $request,
            ['name', 'status', 'next_review_at'],
            'name',
        );
        $sortingChecks = in_array($request->input('sort'), ['review_date', 'result'], true);

        $controlsQuery = SecurityControl::query()->with('owner');
        $checksQuery = ComplianceCheck::query()->with(['control', 'checker']);
        if ($sortingChecks) {
            $controlsQuery->orderBy('name');
            $checkSort->apply($checksQuery);
        } else {
            $controlSort->apply($controlsQuery);
            $checksQuery->latest('review_date');
        }

        return view('security.index', [
            'title' => 'Security and compliance',
            'incidents' => SecurityIncident::query()->with(['reporter', 'assignee'])->latest('reported_at')->get(),
            'controls' => $controlsQuery->get(),
            'checks' => $checksQuery->limit(20)->get(),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(),
            'controlSort' => $controlSort,
            'checkSort' => $checkSort,
        ]);
    }

    public function storeIncident(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('security.manage'), 403);
        $data = $request->validate([
            'incident_type' => ['required', 'string', 'max:120'],
            'severity' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'description' => ['required', 'string', 'max:4000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
        $incident = SecurityIncident::query()->create([
            ...$data,
            'reported_by' => $request->user()->id,
            'status' => 'open',
            'reported_at' => now(),
        ]);
        $audit->log('security.incident_created', 'security_incident', $incident->id, [], ['severity' => $incident->severity]);

        return back()->with('status', 'Security incident recorded. Do not store passwords or secrets in the description.');
    }

    public function updateIncident(Request $request, SecurityIncident $incident, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('security.manage'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'investigating', 'resolved'])],
            'resolution_notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $previous = $incident->status;
        $incident->fill($data);
        $incident->resolved_at = $data['status'] === 'resolved' ? now() : $incident->resolved_at;
        $incident->save();
        $audit->log('security.incident_updated', 'security_incident', $incident->id, ['status' => $previous], ['status' => $incident->status]);

        return back()->with('status', 'Security incident updated.');
    }

    public function storeCheck(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('security.manage'), 403);
        $data = $request->validate([
            'security_control_id' => ['required', 'exists:security_controls,id'],
            'review_date' => ['required', 'date'],
            'result' => ['required', Rule::in(['pass', 'partial', 'fail'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $check = ComplianceCheck::query()->create([
            ...$data,
            'checked_by' => $request->user()->id,
        ]);
        SecurityControl::query()->whereKey($data['security_control_id'])->update([
            'last_reviewed_at' => $data['review_date'],
        ]);
        $audit->log('security.check_recorded', 'compliance_check', $check->id, [], ['result' => $check->result]);

        return back()->with('status', 'Compliance check recorded.');
    }
}
