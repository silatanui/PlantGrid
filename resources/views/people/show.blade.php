@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="pg-person-avatar" aria-hidden="true">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
            <div>
                <h1 class="pg-page-title mb-1">{{ $person->name }}</h1>
                <p class="pg-page-lead mb-0">{{ $person->roleLabel() }} · {{ $person->department->name ?? 'No department' }}</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-pg-line" href="{{ route('messages.index') }}">Open inbox</a>
            @if ($person->id !== auth()->id())
                <a class="btn btn-pg" href="#contact">Message {{ strtok($person->name, ' ') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="pg-card pg-card-pad mb-3">
                <h2 class="pg-section-title">Contact</h2>
                <dl class="pg-kv">
                    <dt>Email</dt>
                    <dd><a href="mailto:{{ $person->email }}">{{ $person->email }}</a></dd>
                    <dt>Role</dt>
                    <dd>{{ $person->roleLabel() }}</dd>
                    <dt>Department</dt>
                    <dd>{{ $person->department->name ?? '-' }}</dd>
                    <dt>Status</dt>
                    <dd>{!! \App\Support\Labels::badge($person->status) !!}</dd>
                    <dt>Phone</dt>
                    <dd class="text-muted">Not stored in this demo</dd>
                </dl>
            </section>

            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Open assigned cases</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($openCases as $case)
                        <tr>
                            <td>
                                <a href="{{ route('helpdesk.show', $case) }}">{{ $case->ticket_number }}</a>
                                <div class="text-muted small">{{ $case->subject }}</div>
                            </td>
                            <td class="text-end">{!! \App\Support\Labels::badge($case->status) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No open cases assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="col-lg-7">
            @if ($person->id === auth()->id())
                <section class="pg-card pg-card-pad">
                    <h2 class="pg-section-title">Your profile</h2>
                    <p class="mb-0 text-muted">Search for another person to message them from PlantGrid.</p>
                </section>
            @else
                <section class="pg-card pg-card-pad mb-3" id="contact">
                    <h2 class="pg-section-title">Message {{ $person->name }}</h2>
                    <p class="text-muted mb-3">Send a direct message inside PlantGrid. The recipient will see it in Messages.</p>
                    <form method="post" action="{{ route('people.message', $person) }}">
                        @csrf
                        <label class="form-label" for="subject">Subject</label>
                        <input class="form-control mb-2" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="180">
                        @error('subject') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <label class="form-label" for="body">Message</label>
                        <textarea class="form-control mb-2" id="body" name="body" rows="5" required maxlength="4000" data-ai-assist data-mentions placeholder="Message text. Use @people and case/asset tags…">{{ old('body') }}</textarea>
                        @error('body') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="pg-ai-field-actions" data-ai-field-actions>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="body">Refine with AI</button>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="body">Paraphrase</button>
                            </div>
                            <button class="btn btn-pg" type="submit">Send message</button>
                        </div>
                    </form>
                </section>

                <section class="pg-card">
                    <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Recent conversation</h2></div>
                    <div class="pg-message-thread">
                        @forelse ($thread as $item)
                            <article class="pg-message-bubble {{ $item->sender_id === auth()->id() ? 'is-mine' : '' }}">
                                <header>
                                    <strong>{{ $item->sender_id === auth()->id() ? 'You' : $item->sender->name }}</strong>
                                    <span>{{ $item->created_at?->format('d M Y H:i') }}</span>
                                </header>
                                <div class="pg-message-subject">{{ $item->subject }}</div>
                                <div class="pg-rich-note">{!! \App\Support\RichText::render($item->body) !!}</div>
                            </article>
                        @empty
                            <div class="pg-card-pad text-muted">No messages yet with {{ $person->name }}.</div>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
