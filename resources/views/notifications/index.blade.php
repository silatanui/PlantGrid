@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Inbox</div>
            <h1 class="pg-page-title">Notifications</h1>
            <p class="pg-page-lead">Case assignments, notes, and SLA alerts.</p>
        </div>
        <div class="pg-page-intro-actions">
            <form method="post" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-pg-line" type="submit">Mark all read</button>
            </form>
        </div>
    </section>

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
