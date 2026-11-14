@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Analytics</div>
            <h1 class="pg-page-title">Reports</h1>
            <p class="pg-page-lead">{{ $catalog[$selected]['label'] }} · {{ $total }} rows. The table shows the first 50. Names and codes open the related record.</p>
        </div>
        <div class="pg-page-intro-actions">
            @if (auth()->user()->hasAnyPermission(['assets.view', 'network.view']))
                <a class="btn btn-pg-line" href="{{ route('locations.index') }}">Plant map</a>
            @endif
            <a class="btn btn-pg-line" href="{{ route('performance.index') }}">Worker performance</a>
            @if ($canExport)
                <a class="btn btn-pg" href="{{ route('reports.export', ['report' => $selected]) }}">Export CSV</a>
            @endif
        </div>
    </section>
    <div class="pg-page-tabs">
        @foreach ($catalog as $key => $report)
            <a class="btn btn-sm {{ $selected === $key ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('reports.index', ['report' => $key]) }}">{{ $report['label'] }}</a>
        @endforeach
    </div>
    <div class="pg-card table-responsive">
        <table class="pg-table">
            <thead><tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        @php
                            $text = is_array($cell) ? ($cell['text'] ?? '') : $cell;
                            $url = is_array($cell) ? ($cell['url'] ?? null) : null;
                        @endphp
                        <td>
                            @if ($url)
                                <a href="{{ $url }}">{{ $text }}</a>
                            @else
                                {{ $text }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="text-muted">No rows.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
