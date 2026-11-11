@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pg-page-title">{{ $folder === 'sent' ? 'Sent' : 'Inbox' }}</h1>
            <p class="pg-page-lead">Direct messages between PlantGrid demo users.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm {{ $folder === 'inbox' ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('messages.index') }}">Inbox</a>
            <a class="btn btn-sm {{ $folder === 'sent' ? 'btn-pg' : 'btn-pg-line' }}" href="{{ route('messages.index', ['folder' => 'sent']) }}">Sent</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <section class="pg-card">
        <table class="pg-table">
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
                        <a href="{{ route('people.show', $counterpart) }}">{{ $counterpart->name }}</a>
                        <div class="text-muted small">{{ $counterpart->email }}</div>
                    </td>
                    <td>
                        <a href="{{ route('messages.show', $message) }}">{{ $message->subject }}</a>
                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($message->body, 90) }}</div>
                    </td>
                    <td>{{ $message->created_at?->format('d M Y H:i') }}</td>
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
    </section>

    <div class="mt-3">{{ $messages->links() }}</div>
@endsection
