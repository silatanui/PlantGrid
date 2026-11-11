@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h1 class="pg-page-title mb-1">Online users</h1>
            <p class="pg-page-lead mb-0">
                People active in the last {{ $onlineWindowMinutes }} minutes.
                @if ($canAssign)
                    Assign an open case directly from this list.
                @endif
            </p>
        </div>
        <a class="btn btn-pg-line" href="{{ route('admin.index') }}">Back to administration</a>
    </div>

    @include('partials.flash')

    <div class="pg-card table-responsive">
        <table class="pg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Last seen</th>
                    @if ($canAssign)
                        <th>Assign case</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($online as $person)
                    <tr>
                        <td>
                            <span class="pg-online-dot" title="Online" aria-hidden="true"></span>
                            <a href="{{ route('people.show', $person) }}">{{ $person->name }}</a>
                            <div class="text-muted small pg-mono">{{ $person->email }}</div>
                        </td>
                        <td>{{ $person->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td>{{ $person->department->name ?? '—' }}</td>
                        <td>{{ \App\Support\Format::date($person->last_seen_at) }}</td>
                        @if ($canAssign)
                            <td style="min-width: 280px;">
                                <form class="d-flex flex-wrap gap-1 align-items-center" method="post" action="{{ route('admin.online.assign', $person) }}">
                                    @csrf
                                    <select class="form-select form-select-sm" name="ticket_id" required>
                                        <option value="">Select open case…</option>
                                        @foreach ($assignableCases as $case)
                                            <option value="{{ $case->id }}">
                                                {{ $case->ticket_number }} · {{ \Illuminate\Support\Str::limit($case->subject, 40) }}
                                                @if ($case->assigned_to) (owned) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-pg" type="submit">Assign</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canAssign ? 5 : 4 }}" class="text-muted">
                            No one else is online right now. Presence updates as people use PlantGrid.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
