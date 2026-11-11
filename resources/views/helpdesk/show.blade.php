@extends('layouts.app')

@section('content')
    @php
        $stages = ['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'];
        $currentIndex = array_search($ticket->status, $stages, true);
    @endphp
    <section class="pg-record">
        <div class="pg-record-icon" aria-hidden="true"><i data-lucide="life-buoy"></i></div>
        <div class="pg-record-title">
            <div class="pg-object">Case</div>
            <h1>{{ $ticket->ticket_number }}</h1>
            <p>{{ $ticket->subject }}</p>
        </div>
        <dl class="pg-highlights">
            <div><dt>Priority</dt><dd>{!! \App\Support\Labels::badge($ticket->priority) !!}</dd></div>
            <div><dt>Status</dt><dd>{!! \App\Support\Labels::badge($ticket->status) !!}@if ($ticket->close_reason === 'duplicate') <span class="pg-badge pg-badge-muted">Duplicate</span>@endif</dd></div>
            <div><dt>SLA</dt><dd>{!! app(\App\Services\SlaService::class)->badge($ticket) !!}</dd></div>
            <div><dt>Requester</dt><dd>{{ $ticket->requester->name }}</dd></div>
            <div><dt>Owner</dt><dd>{{ $ticket->assignee->name ?? 'Unassigned' }}</dd></div>
            @if ($ticket->close_reason === 'duplicate' && $ticket->duplicateOf)
                <div><dt>Duplicate of</dt><dd><a href="{{ route('helpdesk.show', $ticket->duplicateOf) }}">{{ $ticket->duplicateOf->ticket_number }}</a></dd></div>
            @endif
        </dl>
    </section>
    <ol class="pg-path" aria-label="Case status path">
        @foreach ($stages as $index => $stage)
            @php
                $isCurrent = $ticket->status === $stage;
                $isComplete = is_int($currentIndex) && $index < $currentIndex;
                $canAdvance = in_array($stage, $transitions, true) && ! in_array($stage, ['assigned', 'resolved', 'closed'], true);
                $stepClass = trim(($isComplete ? 'is-complete' : '').' '.($isCurrent ? 'is-current' : '').' '.($canAdvance ? 'is-available' : ''));
            @endphp
            <li class="{{ $stepClass }}">
                @if ($canAdvance)
                    <form method="post" action="{{ route('helpdesk.transition', $ticket) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $stage }}">
                        <button class="pg-path-step" type="submit">
                            <span class="pg-path-index">{{ $index + 1 }}</span>
                            <span class="pg-path-label">{{ \App\Support\Labels::status($stage) }}</span>
                        </button>
                    </form>
                @else
                    <div class="pg-path-step" @if ($isCurrent) aria-current="step" @endif>
                        <span class="pg-path-index">{{ $index + 1 }}</span>
                        <span class="pg-path-label">{{ \App\Support\Labels::status($stage) }}</span>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
    <div class="row g-3">
        <div class="col-lg-7">
            <section class="pg-card pg-card-pad mb-3">
                <h2 class="pg-section-title">Request</h2>
                <div class="pg-rich-note">{{-- description may include tags --}}{!! \App\Support\RichText::render($ticket->description) !!}</div>
                <dl class="pg-kv">
                    <dt>Requester</dt><dd><a href="{{ route('people.show', $ticket->requester) }}">{{ $ticket->requester->name }}</a></dd>
                    <dt>Department</dt><dd>{{ $ticket->department->name }}</dd>
                    <dt>Category</dt><dd>{{ $ticket->category->name }}</dd>
                    <dt>Opened</dt><dd>{{ \App\Support\Format::date($ticket->reported_at) }}</dd>
                    <dt>Due</dt><dd>{{ \App\Support\Format::date($ticket->due_at) }}</dd>
                    <dt>SLA due</dt><dd>{{ \App\Support\Format::date($ticket->sla_due_at) }}</dd>
                    @if ($ticket->asset)
                        <dt>Asset</dt><dd><a href="{{ route('assets.show', $ticket->asset) }}">{{ $ticket->asset->asset_tag }}</a> · {{ $ticket->asset->name }}</dd>
                    @endif
                    @if ($ticket->resolution_notes)
                        <dt>Resolution</dt><dd class="pg-rich-note">{!! \App\Support\RichText::render($ticket->resolution_notes) !!}</dd>
                    @endif
                </dl>
            </section>
            <section
                class="pg-card pg-card-pad mb-3"
                data-live-notes
                data-notes-store="{{ route('helpdesk.comment', $ticket) }}"
                data-can-internal="{{ $canSeeInternal ? '1' : '0' }}"
            >
                <h2 class="pg-section-title">Notes</h2>
                <p class="pg-live-notes-status" data-notes-status hidden></p>
                <div class="pg-notes-list" data-notes-list>
                    @forelse ($comments as $comment)
                        @php
                            $canEdit = auth()->user()->canEditTicketComment($comment);
                            $canDelete = auth()->user()->canDeleteTicketComment($comment);
                            $edited = $comment->updated_at && $comment->created_at && $comment->updated_at->gt($comment->created_at->copy()->addSecond());
                        @endphp
                        <article
                            class="pg-note"
                            data-note-id="{{ $comment->id }}"
                            data-note-body="{{ json_encode($comment->comment) }}"
                            data-note-visibility="{{ $comment->visibility }}"
                            data-note-update="{{ route('helpdesk.comment.update', [$ticket, $comment]) }}"
                            data-note-delete="{{ route('helpdesk.comment.destroy', [$ticket, $comment]) }}"
                            data-can-edit="{{ $canEdit ? '1' : '0' }}"
                            data-can-delete="{{ $canDelete ? '1' : '0' }}"
                        >
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                <strong>{{ $comment->author->name }}</strong>
                                @if ($comment->visibility === 'internal')
                                    <span class="pg-badge pg-badge-muted" data-note-internal-badge>Internal</span>
                                @endif
                                <span class="text-muted small" data-note-time>{{ \App\Support\Format::date($comment->created_at) }}</span>
                                @if ($edited)
                                    <span class="text-muted small" data-note-edited>· edited {{ \App\Support\Format::date($comment->updated_at) }}</span>
                                @endif
                                @if ($canEdit || $canDelete)
                                    <span class="ms-auto d-inline-flex flex-wrap gap-1">
                                        @if ($canEdit)
                                            <button class="btn btn-sm btn-pg-line" type="button" data-note-edit>Edit</button>
                                        @endif
                                        @if ($canDelete)
                                            <button class="btn btn-sm btn-pg-line" type="button" data-note-delete>Delete</button>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <div class="pg-rich-note" data-note-html>{!! \App\Support\RichText::render($comment->comment) !!}</div>
                            <div class="pg-note-editor" data-note-editor hidden></div>
                        </article>
                    @empty
                        <p class="text-muted" data-notes-empty>No notes yet.</p>
                    @endforelse
                </div>
                <form class="mt-3" method="post" action="{{ route('helpdesk.comment', $ticket) }}" enctype="multipart/form-data" data-notes-form>
                    @csrf
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <label class="form-label mb-0" for="comment">Add a note</label>
                        <div class="pg-ai-field-actions" data-ai-field-actions>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="comment">Refine with AI</button>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="comment">Paraphrase</button>
                        </div>
                    </div>
                    <textarea class="form-control mb-1" id="comment" name="comment" rows="3" required data-ai-assist data-mentions placeholder="Write a note. Use @ for people and # for cases, assets or hosts…"></textarea>
                    <p class="pg-mention-hint">Tag people with <span class="pg-mono">@</span>, and cases/assets/hosts with <span class="pg-mono">#</span> or their IDs (for example <span class="pg-mono">@faith.cheruiyot</span>, <span class="pg-mono">PG-2026-0148</span>, <span class="pg-mono">PG-LBL-0142</span>).</p>
                    @if ($canSeeInternal)
                        <div class="pg-note-visibility" role="group" aria-label="Note visibility">
                            <label class="pg-note-visibility-option">
                                <input type="radio" name="visibility" value="internal" @checked(old('visibility', 'internal') === 'internal')>
                                <span>Internal ICT note</span>
                            </label>
                            <label class="pg-note-visibility-option">
                                <input type="radio" name="visibility" value="requester_visible" @checked(old('visibility') === 'requester_visible')>
                                <span>Visible to requester</span>
                            </label>
                        </div>
                    @else
                        <input type="hidden" name="visibility" value="requester_visible">
                    @endif
                    <label class="form-label" for="attachment">Attachment</label>
                    <input class="form-control mb-2" id="attachment" name="attachment" type="file">
                    <button class="btn btn-pg" type="submit" data-notes-submit>Post note</button>
                </form>
                @if ($ticket->attachments->isNotEmpty())
                    <ul class="mt-3 mb-0">
                        @foreach ($ticket->attachments as $attachment)
                            <li><a href="{{ route('helpdesk.attachment', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
        <div class="col-lg-5">
            @if (! empty($canTakeOwnership))
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Ownership</h2>
                    <p class="text-muted small">Assign this case to yourself in one step.</p>
                    <form method="post" action="{{ route('helpdesk.take-ownership', $ticket) }}">
                        @csrf
                        <button class="btn btn-pg" type="submit">Take ownership</button>
                    </form>
                </section>
            @endif
            @if ($transitions !== [])
                @php $canCloseDuplicate = in_array('closed', $transitions, true) && $ticket->status !== 'resolved'; @endphp
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Update status</h2>
                    <form method="post" action="{{ route('helpdesk.transition', $ticket) }}" data-status-form>
                        @csrf
                        <label class="form-label" for="status">Next status</label>
                        <select class="form-select mb-2" id="status" name="status" data-status-select>
                            @foreach ($transitions as $status)
                                <option value="{{ $status }}" @selected(old('status') === $status)>
                                    {{ $status === 'closed' && $canCloseDuplicate ? 'Closed (duplicate)' : \App\Support\Labels::status($status) }}
                                </option>
                            @endforeach
                        </select>
                        @if (auth()->user()->hasPermission('tickets.assign'))
                            <label class="form-label" for="assigned_to">Technician</label>
                            <select class="form-select mb-2" id="assigned_to" name="assigned_to">
                                <option value="">Unchanged</option>
                                @foreach ($technicians as $technician)
                                    <option value="{{ $technician->id }}" @selected($ticket->assigned_to == $technician->id)>
                                        {{ $technician->name }}{{ isset($presence) && $presence->isOnline($technician) ? ' · Online' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @if ($canCloseDuplicate)
                            <div class="pg-duplicate-close mb-2" data-duplicate-fields hidden>
                                <label class="form-label" for="duplicate_of">Original case</label>
                                <input
                                    class="form-control mb-1"
                                    id="duplicate_of"
                                    name="duplicate_of"
                                    value="{{ old('duplicate_of') }}"
                                    placeholder="e.g. PG-2026-0148"
                                    autocomplete="off"
                                    data-mentions
                                >
                                <p class="text-muted small mb-0">Required when closing as a duplicate. Enter the surviving case number.</p>
                            </div>
                        @endif
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <label class="form-label mb-0" for="resolution_notes">Resolution notes</label>
                            <div class="pg-ai-field-actions" data-ai-field-actions>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="resolution_notes">Refine with AI</button>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="resolution_notes">Paraphrase</button>
                            </div>
                        </div>
                        <textarea class="form-control mb-2" id="resolution_notes" name="resolution_notes" rows="3" data-ai-assist data-mentions placeholder="Resolution notes. Use @people and case/asset tags…">{{ old('resolution_notes', $ticket->resolution_notes) }}</textarea>
                        <label class="form-label" for="note">History note</label>
                        <input class="form-control mb-2" id="note" name="note">
                        @if ($ticket->asset_id)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="record_maintenance" id="record_maintenance" value="1">
                                <label class="form-check-label" for="record_maintenance">Add a completed maintenance record for the asset</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restore_asset" id="restore_asset" value="1">
                                <label class="form-check-label" for="restore_asset">Mark the asset active again</label>
                            </div>
                        @endif
                        <button class="btn btn-pg" type="submit">Save update</button>
                    </form>
                </section>
                @if ($canCloseDuplicate)
                    <script>
                        (() => {
                            const select = document.querySelector('[data-status-select]');
                            const fields = document.querySelector('[data-duplicate-fields]');
                            const input = document.getElementById('duplicate_of');
                            if (!select || !fields) return;
                            const sync = () => {
                                const show = select.value === 'closed';
                                fields.hidden = !show;
                                if (input) input.required = show;
                            };
                            select.addEventListener('change', sync);
                            sync();
                        })();
                    </script>
                @endif
            @endif
            <section class="pg-card">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Status history</h2></div>
                <ul class="pg-timeline px-3">
                    @foreach ($ticket->histories->sortByDesc('created_at') as $history)
                        <li>
                            {!! \App\Support\Labels::badge($history->new_status) !!}
                            <div class="pg-rich-note">{!! \App\Support\RichText::render($history->notes) !!}</div>
                            <div class="text-muted">{{ \App\Support\Format::date($history->created_at) }} · {{ $history->changer->name ?? 'System' }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
@endsection
