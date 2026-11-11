@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Audit log</h1>
    <p class="pg-page-lead">Sign-in events and important record changes. Passwords are never written here.</p>
    <form class="pg-filters my-3" method="get">
        <div>
            <label class="form-label" for="q">Action or record</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}">
        </div>
        <input type="hidden" name="sort" value="{{ $sort->column }}">
        <input type="hidden" name="direction" value="{{ $sort->direction }}">
        <button class="btn btn-pg" type="submit">Search</button>
    </form>
    <div class="pg-card table-responsive">
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="created_at" label="When" :sort="$sort" route-name="audit.index" />
                    <th>Person</th>
                    <x-sort-th column="action" label="Action" :sort="$sort" route-name="audit.index" />
                    <x-sort-th column="entity_type" label="Record" :sort="$sort" route-name="audit.index" />
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ \App\Support\Format::date($log->created_at) }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td class="pg-mono">{{ $log->action }}</td>
                    <td>{{ $log->entity_type }} {{ $log->entity_id }}</td>
                    <td class="small">{{ $log->new_values ? json_encode($log->new_values) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
