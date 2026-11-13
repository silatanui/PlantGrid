@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pg-page-title">{{ $message->subject }}</h1>
            <p class="pg-page-lead mb-0">
                Conversation with
                <a href="{{ route('people.show', $counterpart) }}">{{ $counterpart->name }}</a>
                · {{ $counterpart->roleLabel() }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-pg-line" href="{{ route('messages.index') }}">Back to inbox</a>
            <a class="btn btn-pg" href="{{ route('people.show', $counterpart) }}#contact">Open profile</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="pg-card">
                <div
                    class="pg-message-thread pg-message-thread-tall"
                    data-live-thread
                    data-live-thread-url="{{ route('inbox.thread', $counterpart) }}"
                    data-initial-count="{{ $thread->count() }}"
                >
                    @foreach ($thread as $item)
                        <article class="pg-message-bubble {{ $item->sender_id === auth()->id() ? 'is-mine' : '' }}" data-message-id="{{ $item->id }}">
                            <header>
                                <strong>{{ $item->sender_id === auth()->id() ? 'You' : $item->sender->name }}</strong>
                                <span>{{ $item->created_at?->format('d M Y H:i') }}</span>
                            </header>
                            <div class="pg-message-subject">{{ $item->subject }}</div>
                            <div class="pg-rich-note">{!! \App\Support\RichText::render($item->body) !!}</div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="pg-card pg-card-pad">
                <h2 class="pg-section-title">Reply</h2>
                <form method="post" action="{{ route('messages.store') }}">
                    @csrf
                    <input type="hidden" name="recipient_id" value="{{ $counterpart->id }}">
                    <label class="form-label" for="subject">Subject</label>
                    <input class="form-control mb-2" id="subject" name="subject" value="{{ old('subject', str_starts_with($message->subject, 'Re:') ? $message->subject : 'Re: '.$message->subject) }}" required maxlength="180">
                    <label class="form-label" for="body">Message</label>
                    <textarea class="form-control mb-2" id="body" name="body" rows="6" required maxlength="4000" data-ai-assist data-mentions placeholder="Write a reply. Use @people, @everyone, @it_department, or # for cases/assets…">{{ old('body') }}</textarea>
                    <div class="pg-ai-field-actions mb-2" data-ai-field-actions>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="body">Refine with AI</button>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="body">Paraphrase</button>
                    </div>
                    <button class="btn btn-pg" type="submit">Send reply</button>
                </form>
            </section>
        </div>
    </div>
@endsection
