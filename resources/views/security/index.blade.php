@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Security and compliance</h1>
    <p class="pg-page-lead">Administration records only. Do not store passwords, recovery codes or private keys here.</p>
    <div class="row g-3 my-1">
        <div class="col-lg-6">
            <section class="pg-card h-100">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Incidents</h2></div>
                <table class="pg-table">
                    <tbody>
                    @foreach ($incidents as $incident)
                        <tr>
                            <td>{{ $incident->incident_type }}<div class="text-muted">{{ $incident->description }}</div></td>
                            <td>{!! \App\Support\Labels::badge($incident->severity) !!}<div class="mt-1">{!! \App\Support\Labels::badge($incident->status) !!}</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="pg-card h-100">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Controls</h2></div>
                <table class="pg-table">
                    <thead>
                        <tr>
                            <x-sort-th column="name" label="Control" :sort="$controlSort" route-name="security.index" />
                            <x-sort-th column="status" label="Status" :sort="$controlSort" route-name="security.index" />
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($controls as $control)
                        <tr>
                            <td>{{ $control->name }}<div class="text-muted">{{ $control->control_category }} · next review {{ \App\Support\Format::date($control->next_review_at, 'd M Y') }}</div></td>
                            <td>{!! \App\Support\Labels::badge($control->status) !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        </div>
    </div>
    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Recent checks</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Reviewer</th>
                    <x-sort-th column="review_date" label="Date" :sort="$checkSort" route-name="security.index" />
                    <x-sort-th column="result" label="Result" :sort="$checkSort" route-name="security.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($checks as $check)
                <tr>
                    <td>{{ $check->control->name }}</td>
                    <td>{{ $check->checker->name }}</td>
                    <td>{{ \App\Support\Format::date($check->review_date, 'd M Y') }}</td>
                    <td>{!! \App\Support\Labels::badge($check->result) !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @if (auth()->user()->hasPermission('security.manage'))
        <div class="row g-3">
            <div class="col-lg-6">
                <form class="pg-card pg-card-pad" method="post" action="{{ route('security.incidents.store') }}">
                    @csrf
                    <h2 class="pg-section-title">Record an incident</h2>
                    <label class="form-label" for="incident_type">Type</label>
                    <input class="form-control mb-2" id="incident_type" name="incident_type" required>
                    <label class="form-label" for="severity">Severity</label>
                    <select class="form-select mb-2" id="severity" name="severity"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <label class="form-label mb-0" for="description">What happened</label>
                        <div class="pg-ai-field-actions" data-ai-field-actions>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="description">Refine with AI</button>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="description">Paraphrase</button>
                        </div>
                    </div>
                    <textarea class="form-control mb-2" id="description" name="description" rows="3" required data-ai-assist></textarea>
                    <button class="btn btn-pg" type="submit">Save incident</button>
                </form>
            </div>
            <div class="col-lg-6">
                <form class="pg-card pg-card-pad" method="post" action="{{ route('security.checks.store') }}">
                    @csrf
                    <h2 class="pg-section-title">Record a check</h2>
                    <label class="form-label" for="security_control_id">Control</label>
                    <select class="form-select mb-2" id="security_control_id" name="security_control_id">@foreach ($controls as $control)<option value="{{ $control->id }}">{{ $control->name }}</option>@endforeach</select>
                    <label class="form-label" for="review_date">Review date</label>
                    <input class="form-control mb-2" id="review_date" name="review_date" type="date" required>
                    <label class="form-label" for="result">Result</label>
                    <select class="form-select mb-2" id="result" name="result"><option value="pass">Pass</option><option value="partial">Partial</option><option value="fail">Fail</option></select>
                    <button class="btn btn-pg" type="submit">Save check</button>
                </form>
            </div>
        </div>
    @endif
@endsection
