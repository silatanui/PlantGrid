@extends('layouts.app')

@section('content')
    <div class="pg-person-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="pg-person-avatar" aria-hidden="true">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 class="pg-page-title mb-1">{{ $person->name }}</h1>
                <p class="pg-page-lead mb-0">{{ $person->roleLabel() }} · {{ $person->department->name ?? 'No department' }}</p>
            </div>
        </div>
        <div class="pg-person-hero-actions d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-pg-line" href="{{ route('people.performance', $person) }}">View performance</a>
            <a class="btn btn-pg-line" href="{{ route('messages.index') }}">Open inbox</a>
            @if ($person->id !== auth()->id())
                <a class="btn btn-pg" href="#contact">Message {{ strtok($person->name, ' ') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="pg-card pg-card-pad mb-3" id="contact-details">
                <h2 class="pg-section-title">Contact</h2>
                <dl class="pg-kv">
                    <dt>Job title</dt>
                    <dd>{{ $person->job_title ?: '-' }}</dd>
                    <dt>Email</dt>
                    <dd><a href="mailto:{{ $person->email }}">{{ $person->email }}</a></dd>
                    <dt>Desk phone</dt>
                    <dd>@if ($person->phone)<a href="tel:{{ preg_replace('/\s+/', '', $person->phone) }}">{{ $person->phone }}</a>@else<span class="text-muted">-</span>@endif</dd>
                    <dt>Mobile</dt>
                    <dd>@if ($person->mobile)<a href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">{{ $person->mobile }}</a>@else<span class="text-muted">-</span>@endif</dd>
                    <dt>Extension</dt>
                    <dd class="pg-mono">{{ $person->extension ?: '-' }}</dd>
                    <dt>Address</dt>
                    <dd>{{ $person->address ?: '-' }}</dd>
                    <dt>City</dt>
                    <dd>{{ $person->city ?: '-' }}</dd>
                    <dt>Postal code</dt>
                    <dd class="pg-mono">{{ $person->postal_code ?: '-' }}</dd>
                    <dt>Country</dt>
                    <dd>{{ $person->country ?: '-' }}</dd>
                    <dt>Role</dt>
                    <dd>{{ $person->roleLabel() }}</dd>
                    <dt>Department</dt>
                    <dd>{{ $person->department->name ?? '-' }}</dd>
                    <dt>Status</dt>
                    <dd>{!! \App\Support\Labels::badge($person->status) !!}</dd>
                </dl>
            </section>

            <section class="pg-card mb-3">
                <div class="pg-card-pad d-flex justify-content-between align-items-center gap-2">
                    <h2 class="pg-section-title mb-0">Open assigned cases</h2>
                    <a class="btn btn-sm btn-pg-line" href="{{ route('people.performance', $person) }}">Full performance</a>
                </div>
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
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Your profile</h2>
                    <p class="mb-3 text-muted">Update the contact details shown on your PlantGrid profile. Search for another person to message them.</p>
                    <form method="post" action="{{ route('people.contact', $person) }}">
                        @csrf
                        @method('put')
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" for="job_title">Job title</label>
                                <input class="form-control" id="job_title" name="job_title" value="{{ old('job_title', $person->job_title) }}" maxlength="120">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="extension">Extension</label>
                                <input class="form-control pg-mono" id="extension" name="extension" value="{{ old('extension', $person->extension) }}" maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Desk phone</label>
                                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $person->phone) }}" maxlength="40">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile">Mobile</label>
                                <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $person->mobile) }}" maxlength="40">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address">Address</label>
                                <input class="form-control" id="address" name="address" value="{{ old('address', $person->address) }}" maxlength="180">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="city">City</label>
                                <input class="form-control" id="city" name="city" value="{{ old('city', $person->city) }}" maxlength="80">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="postal_code">Postal code</label>
                                <input class="form-control pg-mono" id="postal_code" name="postal_code" value="{{ old('postal_code', $person->postal_code) }}" maxlength="24">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="country">Country</label>
                                <input class="form-control" id="country" name="country" value="{{ old('country', $person->country) }}" maxlength="80">
                            </div>
                        </div>
                        <button class="btn btn-pg mt-3" type="submit">Save contact details</button>
                    </form>
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
