@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="pg-page-title mb-1">Notifications</h1>
            <p class="text-muted mb-0">Case assignments, notes, and SLA alerts.</p>
        </div>
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-pg-line" type="submit">Mark all read</button>
        </form>
    </div>

    <div class="pg-card">
        <ul class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <li class="list-group-item {{ $notification->read_at ? '' : 'pg-notification-unread' }}">
                    <a class="stretched-link text-decoration-none text-body" href="{{ route('notifications.read', $notification->id) }}">
                        <strong>{{ $data['title'] ?? 'Notification' }}</strong>
                        <div class="small">{{ $data['body'] ?? '' }}</div>
                        <div class="text-muted small">{{ \App\Support\Format::date($notification->created_at) }}</div>
                    </a>
                </li>
            @empty
                <li class="list-group-item text-muted">No notifications yet.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
