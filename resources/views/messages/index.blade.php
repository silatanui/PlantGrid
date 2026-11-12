@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--messages" aria-hidden="true">
            <i data-lucide="message-square"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Messages</div>
            <h1 class="pg-page-title">{{ $folder === 'sent' ? 'Sent' : 'Inbox' }}</h1>
            <p class="pg-page-lead">Direct messages between PlantGrid demo users.</p>
        </div>
        <div class="pg-object-home-actions">
            <a class="btn btn-sm {{ $folder === 'inbox' ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('messages.index') }}">Inbox</a>
            <a class="btn btn-sm {{ $folder === 'sent' ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('messages.index', ['folder' => 'sent']) }}">Sent</a>
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="pg-card pg-listview">
        <div class="table-responsive">
            <table class="pg-table pg-table-list">
                <thead>
                    <tr>
                        <th>{{ $folder === 'sent' ? 'To' : 'From' }}</th>
                        <th>Subject</th>
                        <th>When</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($messages as $message)
                    @php
                        $counterpart = $folder === 'sent' ? $message->recipient : $message->sender;
                        $unread = $folder === 'inbox' && $message->read_at === null;
                    @endphp
                    <tr class="{{ $unread ? 'pg-message-row-unread' : '' }}">
                        <td>
                            <a class="pg-person-chip" href="{{ route('people.show', $counterpart) }}">
                                <span class="pg-avatar-sm" aria-hidden="true">{{ strtoupper(substr($counterpart->name, 0, 1)) }}</span>
                                <span>
                                    <strong>{{ $counterpart->name }}</strong>
                                    <small>{{ $counterpart->email }}</small>
                                </span>
                            </a>
                        </td>
                        <td>
                            <a class="pg-list-subject" href="{{ route('messages.show', $message) }}">{{ $message->subject }}</a>
                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($message->body, 90) }}</div>
                        </td>
                        <td class="text-nowrap">{{ $message->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-end">
                            @if ($unread)
                                <span class="pg-badge pg-badge-info">Unread</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No messages in this folder.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $messages->links() }}</div>
@endsection
