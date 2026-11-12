@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h1 class="pg-page-title">Reports</h1>
            <p class="pg-page-lead">{{ $catalog[$selected]['label'] }} · {{ $total }} rows. The table shows the first 50.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-pg-line" href="{{ route('performance.index') }}">Worker performance</a>
            @if ($canExport)
                <a class="btn btn-pg" href="{{ route('reports.export', ['report' => $selected]) }}">Export CSV</a>
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($catalog as $key => $report)
            <a class="btn btn-sm {{ $selected === $key ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('reports.index', ['report' => $key]) }}">{{ $report['label'] }}</a>
        @endforeach
    </div>
    <div class="pg-card table-responsive">
        <table class="pg-table">
            <thead><tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td class="text-muted">No rows.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
