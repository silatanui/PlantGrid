@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Administration</div>
            <h1 class="pg-page-title">Online users</h1>
            <p class="pg-page-lead">
                People active in the last {{ $onlineWindowSeconds }} seconds.
                @if ($canAssign)
                    Assign an open case directly from this list.
                @endif
            </p>
            <p class="text-muted small mb-0" data-presence-status>Live · updates every few seconds</p>
        </div>
        <div class="pg-page-intro-actions">
            <a class="btn btn-pg-line" href="{{ route('admin.index') }}">Back to administration</a>
        </div>
    </section>

    @include('partials.flash')

    @php
        $casePayload = $assignableCases->map(fn ($case) => [
            'id' => $case->id,
            'label' => $case->ticket_number.' · '.\Illuminate\Support\Str::limit($case->subject, 40),
            'owned' => (bool) $case->assigned_to,
        ])->values();
    @endphp

    <div
        class="pg-card table-responsive"
        data-presence-table
        data-can-assign="{{ $canAssign ? '1' : '0' }}"
        data-assign-cases='@json($casePayload)'
    >
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
            <tbody data-presence-rows>
                @forelse ($online as $person)
                    <tr data-user-id="{{ $person->id }}">
                        <td>
                            <span class="pg-online-dot" title="Online" aria-hidden="true"></span>
                            <a href="{{ route('people.show', $person) }}">{{ $person->name }}</a>
                            <div class="text-muted small pg-mono">{{ $person->email }}</div>
                        </td>
                        <td>{{ $person->roles->pluck('name')->join(', ') ?: '-' }}</td>
                        <td>{{ $person->department->name ?? '-' }}</td>
                        <td data-last-seen>{{ \App\Support\Format::date($person->last_seen_at) }}</td>
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
                            No one else is online right now. Presence updates live as people use PlantGrid.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
