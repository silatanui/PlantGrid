@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Backups and recovery</h1>
    <div class="pg-alert pg-alert-info">Every run on this page is simulated. A successful result means a sample record was stored. PlantGrid did not back up a real server.</div>
    <section class="pg-card mb-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Plans</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="name" label="Plan" :sort="$planSort" route-name="backups.index" />
                    <th>Target</th>
                    <x-sort-th column="frequency" label="Frequency" :sort="$planSort" route-name="backups.index" />
                    <x-sort-th column="backup_type" label="Type" :sort="$planSort" route-name="backups.index" />
                    <x-sort-th column="retention_days" label="Retention" :sort="$planSort" route-name="backups.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td class="pg-mono">{{ $plan->server->hostname ?? $plan->application->name ?? '-' }}</td>
                    <td>{{ ucfirst($plan->frequency) }}</td>
                    <td>{{ ucfirst($plan->backup_type) }}</td>
                    <td>{{ $plan->retention_days }} days</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="pg-card mb-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Simulated runs</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <x-sort-th column="started_at" label="Started" :sort="$runSort" route-name="backups.index" />
                    <x-sort-th column="result" label="Result" :sort="$runSort" route-name="backups.index" />
                    <th>Source</th>
                    <x-sort-th column="size_mb" label="Size" :sort="$runSort" route-name="backups.index" />
                </tr>
            </thead>
            <tbody>
            @foreach ($runs as $run)
                <tr>
                    <td>{{ $run->plan->name }}</td>
                    <td>{{ \App\Support\Format::date($run->started_at) }}</td>
                    <td>{!! \App\Support\Labels::badge($run->result) !!}@if ($run->failure_reason)<div class="text-muted">{{ $run->failure_reason }}</div>@endif</td>
                    <td>{!! \App\Support\Labels::badge($run->data_source) !!}</td>
                    <td>{{ $run->size_mb ? number_format($run->size_mb, 0) . ' MB' : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="pg-card mb-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Recovery tests</h2></div>
        <table class="pg-table">
            <thead><tr><th>Date</th><th>Tester</th><th>Result</th><th>Notes</th></tr></thead>
            <tbody>
            @foreach ($tests as $test)
                <tr>
                    <td>{{ \App\Support\Format::date($test->test_date, 'd M Y') }}</td>
                    <td>{{ $test->tester->name }}</td>
                    <td>{!! \App\Support\Labels::badge($test->result) !!}</td>
                    <td>{{ $test->notes }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @if (auth()->user()->hasPermission('backups.manage'))
        <form class="pg-card pg-card-pad" method="post" action="{{ route('backups.runs.store') }}">
            @csrf
            <h2 class="pg-section-title">Record a simulated run</h2>
            <div class="row g-2">
                <div class="col-md-5"><label class="form-label" for="backup_plan_id">Plan</label>
                    <select class="form-select" id="backup_plan_id" name="backup_plan_id">@foreach ($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select>
                </div>
                <div class="col-md-3"><label class="form-label" for="result">Result</label>
                    <select class="form-select" id="result" name="result"><option value="successful">Successful</option><option value="failed">Failed</option></select>
                </div>
                <div class="col-md-2"><label class="form-label" for="size_mb">Size MB</label><input class="form-control" id="size_mb" name="size_mb" type="number" step="0.1"></div>
            </div>
            <button class="btn btn-pg mt-3" type="submit">Save simulated run</button>
        </form>
    @endif
@endsection
