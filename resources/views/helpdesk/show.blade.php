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
            <div><dt>Status</dt><dd data-case-status-badge>{!! \App\Support\Labels::badge($ticket->status) !!}@if ($ticket->close_reason === 'duplicate') <span class="pg-badge pg-badge-muted">Duplicate</span>@endif</dd></div>
            <div><dt>SLA</dt><dd>{!! app(\App\Services\SlaService::class)->badge($ticket) !!}</dd></div>
            <div><dt>Requester</dt><dd>{{ $ticket->requester->name }}</dd></div>
            <div><dt>Owner</dt><dd data-case-owner>{{ $ticket->assignee->name ?? 'Unassigned' }}</dd></div>
            <div>
                <dt>Partners</dt>
                <dd class="pg-partners-summary" data-case-partners>
                    @php($casePartners = ($ticket->partners ?? collect())->values())
                    @if ($casePartners->isEmpty())
                        -
                    @else
                        <span class="pg-partners-visible">
                            @foreach ($casePartners->take(2) as $partner)
                                <a href="{{ route('people.show', $partner) }}">{{ $partner->name }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </span>
                        @if ($casePartners->count() > 2)
                            <button
                                type="button"
                                class="pg-partners-more"
                                data-partners-more
                                aria-expanded="false"
                                title="Show all {{ $casePartners->count() }} partners"
                            >+{{ $casePartners->count() - 2 }}</button>
                            <div class="pg-partners-popover" data-partners-popover hidden>
                                <ul>
                                    @foreach ($casePartners as $partner)
                                        <li><a href="{{ route('people.show', $partner) }}">{{ $partner->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </dd>
            </div>
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
                $canAdvance = auth()->user()->hasPermission('tickets.update')
                    && in_array($stage, $transitions, true)
                    && ! in_array($stage, ['assigned', 'resolved', 'closed'], true);
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
                @if ($ticket->asset)
                    @include('partials.device-media', [
                        'media' => \App\Support\DeviceMedia::forAsset($ticket->asset->loadMissing('category')),
                        'compact' => true,
                    ])
                @endif
            </section>
            <section
                class="pg-card pg-card-pad mb-3 pg-notes-panel"
                data-live-notes
                data-notes-store="{{ route('helpdesk.comment', $ticket) }}"
                data-can-internal="{{ $canSeeInternal ? '1' : '0' }}"
            >
                @php
                    $openComposer = filled(old('comment')) || $errors->has('comment') || $errors->has('attachment') || $errors->has('visibility');
                @endphp
                <header class="pg-notes-heading">
                    <h2 class="pg-section-title">
                        <i data-lucide="message-square-text" aria-hidden="true"></i>
                        <span>Notes</span>
                    </h2>
                    <span class="pg-notes-count" data-notes-count>
                        {{ $comments->count() }} {{ \Illuminate\Support\Str::plural('Note', $comments->count()) }}
                    </span>
                </header>
                <p class="pg-live-notes-status" data-notes-status hidden></p>

                <button
                    class="pg-note-compose-trigger"
                    type="button"
                    data-notes-open
                    @if ($openComposer) hidden @endif
                >
                    <i data-lucide="pencil" aria-hidden="true"></i>
                    <span>Add a note</span>
                </button>

                <form
                    class="pg-note-composer"
                    method="post"
                    action="{{ route('helpdesk.comment', $ticket) }}"
                    enctype="multipart/form-data"
                    data-notes-form
                    @unless ($openComposer) hidden @endunless
                >
                    @csrf
                    <div class="pg-note-composer-head">
                        <i data-lucide="pencil" aria-hidden="true"></i>
                        <label class="pg-note-composer-title" for="comment">Add a note</label>
                    </div>
                    @if ($canSeeInternal)
                        <div class="pg-note-visibility" role="group" aria-label="Note visibility">
                            <label class="pg-note-visibility-option">
                                <input type="radio" name="visibility" value="internal" @checked(old('visibility', 'internal') === 'internal')>
                                <span class="pg-note-visibility-copy">
                                    <strong>Internal ICT note</strong>
                                    <small>Visible to ICT team and authorized staff only</small>
                                </span>
                            </label>
                            <label class="pg-note-visibility-option">
                                <input type="radio" name="visibility" value="requester_visible" @checked(old('visibility') === 'requester_visible')>
                                <span class="pg-note-visibility-copy">
                                    <strong>Visible to requester</strong>
                                    <small>Visible to the requester (e.g. user, client)</small>
                                </span>
                            </label>
                        </div>
                    @else
                        <input type="hidden" name="visibility" value="requester_visible">
                    @endif
                    <div class="pg-ai-field-actions" data-ai-field-actions hidden>
                        <button class="btn btn-sm btn-pg-line pg-ai-chip" type="button" data-ai-action="refine" data-ai-target="comment">
                            <i data-lucide="sparkles" aria-hidden="true"></i>
                            Refine with AI
                        </button>
                        <button class="btn btn-sm btn-pg-line pg-ai-chip" type="button" data-ai-action="paraphrase" data-ai-target="comment">
                            <i data-lucide="copy" aria-hidden="true"></i>
                            Paraphrase
                        </button>
                    </div>
                    <textarea class="form-control pg-note-textarea" id="comment" name="comment" rows="4" required data-ai-assist data-mentions data-format-toolbar placeholder="Write a note. Use @ for people, # for cases, assets, hosts, or plant locations…">{{ old('comment') }}</textarea>
                    <div class="pg-attach-composer" data-attach-composer>
                        <label class="pg-attach-drop" for="attachment">
                            <input class="pg-attach-input" id="attachment" name="attachment" type="file" accept=".pdf,.png,.jpg,.jpeg,.txt,.doc,.docx,image/*" data-attach-input>
                            <span class="pg-attach-drop-copy">
                                <i data-lucide="image-plus"></i>
                                <strong>Add a photo or file</strong>
                                <span>PNG, JPG, PDF or Word · up to 5 MB</span>
                            </span>
                        </label>
                        <div class="pg-attach-pending" data-attach-pending hidden>
                            <div class="pg-attach-pending-media" data-attach-pending-media></div>
                            <div class="pg-attach-pending-meta">
                                <strong data-attach-pending-name></strong>
                                <span data-attach-pending-size></span>
                            </div>
                            <button class="btn btn-sm btn-pg-line" type="button" data-attach-clear>Remove</button>
                        </div>
                    </div>
                    <div class="pg-note-composer-actions">
                        <button class="btn btn-pg-line" type="button" data-notes-cancel>Cancel</button>
                        <button class="btn btn-pg" type="submit" data-notes-submit>
                            <span>Post</span>
                            <i data-lucide="send" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>

                <div class="pg-notes-list" data-notes-list>
                    @forelse ($comments as $comment)
                        @php
                            $canEdit = auth()->user()->canEditTicketComment($comment);
                            $canDelete = auth()->user()->canDeleteTicketComment($comment);
                            $edited = $comment->updated_at && $comment->created_at && $comment->updated_at->gt($comment->created_at->copy()->addSecond());
                            $author = $comment->author;
                            $isInternal = $comment->visibility === 'internal';
                        @endphp
                        <article
                            class="pg-note {{ $isInternal ? 'is-internal' : 'is-public' }}"
                            data-note-id="{{ $comment->id }}"
                            data-note-body="{{ json_encode($comment->comment) }}"
                            data-note-visibility="{{ $comment->visibility }}"
                            data-note-update="{{ route('helpdesk.comment.update', [$ticket, $comment]) }}"
                            data-note-delete-url="{{ route('helpdesk.comment.destroy', [$ticket, $comment]) }}"
                            data-can-edit="{{ $canEdit ? '1' : '0' }}"
                            data-can-delete="{{ $canDelete ? '1' : '0' }}"
                        >
                            <header class="pg-note-head">
                                <span class="pg-note-avatar" aria-hidden="true">{{ $author?->initials() ?? 'U' }}</span>
                                <div class="pg-note-meta">
                                    <div class="pg-note-meta-top">
                                        <strong data-note-author>{{ $author?->name ?? 'User' }}</strong>
                                        @if ($author)
                                            <span class="pg-note-role" data-note-role>
                                                <i data-lucide="user-round" aria-hidden="true"></i>
                                                {{ $author->shortRoleLabel() }}
                                            </span>
                                        @endif
                                        @if ($isInternal)
                                            <span class="pg-note-visibility-badge" data-note-internal-badge>Internal</span>
                                        @endif
                                        @if ($canEdit || $canDelete)
                                            <span class="pg-note-actions" data-note-actions>
                                                @if ($canEdit)
                                                    <button class="btn btn-sm btn-pg-line" type="button" data-note-edit>Edit</button>
                                                @endif
                                                @if ($canDelete)
                                                    <button class="btn btn-sm btn-pg-line" type="button" data-note-delete>Delete</button>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    <div class="pg-note-time-row">
                                        <time class="pg-note-time" data-note-time>{{ \App\Support\Format::date($comment->created_at) }}</time>
                                        @if ($edited)
                                            <span class="pg-note-edited" data-note-edited>· edited {{ \App\Support\Format::date($comment->updated_at) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </header>
                            <div class="pg-rich-note" data-note-html>{!! \App\Support\RichText::render($comment->comment) !!}</div>
                            <div class="pg-note-editor" data-note-editor hidden></div>
                        </article>
                    @empty
                        <p class="pg-notes-empty" data-notes-empty>No notes yet. Use Add a note to post the first update.</p>
                    @endforelse
                </div>

                <section class="pg-media-feed" aria-label="Attachments" data-media-feed @if ($ticket->attachments->isEmpty()) hidden @endif>
                    <h3 class="pg-section-title">Shared media</h3>
                    <div class="pg-media-grid" data-media-grid>
                        @foreach ($ticket->attachments as $attachment)
                            @php $canDeleteAttachment = auth()->user()->canDeleteTicketAttachment($attachment); @endphp
                            <article class="pg-media-post" data-attachment-id="{{ $attachment->id }}">
                                <header class="pg-media-post-head">
                                    <span class="pg-media-avatar" aria-hidden="true">{{ strtoupper(substr($attachment->uploader->name ?? 'U', 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $attachment->uploader->name ?? 'PlantGrid user' }}</strong>
                                        <div class="text-muted small">
                                            Shared {{ $attachment->kindLabel() }}
                                            · {{ \App\Support\Format::date($attachment->created_at) }}
                                        </div>
                                    </div>
                                </header>
                                @if ($attachment->isImage())
                                    <a
                                        class="pg-media-frame"
                                        href="{{ route('helpdesk.attachment.preview', [$ticket, $attachment]) }}"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <img
                                            src="{{ route('helpdesk.attachment.preview', [$ticket, $attachment]) }}"
                                            alt="{{ $attachment->original_name }}"
                                            loading="lazy"
                                        >
                                    </a>
                                @else
                                    <div class="pg-media-file">
                                        <i data-lucide="file-text"></i>
                                        <div>
                                            <strong>{{ $attachment->original_name }}</strong>
                                            <span>{{ $attachment->humanSize() }}</span>
                                        </div>
                                    </div>
                                @endif
                                <footer class="pg-media-post-foot">
                                    <span class="pg-mono">{{ $attachment->original_name }}</span>
                                    <span>{{ $attachment->humanSize() }}</span>
                                    <a href="{{ route('helpdesk.attachment', [$ticket, $attachment]) }}">Download</a>
                                    @if ($canDeleteAttachment)
                                        <button
                                            class="btn btn-sm btn-pg-line"
                                            type="button"
                                            data-delete-attachment
                                            data-delete-url="{{ route('helpdesk.attachment.destroy', [$ticket, $attachment]) }}"
                                            data-attachment-name="{{ $attachment->original_name }}"
                                        >Delete</button>
                                    @endif
                                </footer>
                            </article>
                        @endforeach
                    </div>
                    <p class="text-muted small mb-0 mt-2" data-media-status hidden></p>
                </section>
            </section>
        </div>
        <div class="col-lg-5">
            @php
                $ownerOptionLabel = static function ($person) use ($presence) {
                    $parts = [$person->name];
                    $role = $person->roleLabel();
                    if ($role) {
                        $parts[] = $role;
                    }
                    if ($person->department?->code) {
                        $parts[] = $person->department->code;
                    }
                    if (isset($presence) && $presence->isOnline($person)) {
                        $parts[] = 'Online';
                    }

                    return implode(' · ', $parts);
                };
            @endphp
            @if (! empty($canTakeOwnership) || ! empty($canTransferOwnership))
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Ownership</h2>
                    <p class="text-muted small">
                        One owner at a time. Partners can claim an unassigned case; the owner can transfer it.
                    </p>
                    @if (! empty($canTakeOwnership))
                        <form class="mb-2" method="post" action="{{ route('helpdesk.take-ownership', $ticket) }}" data-live-case-form="ownership">
                            @csrf
                            <button class="btn btn-pg" type="submit">Take ownership</button>
                        </form>
                    @endif
                    @if (! empty($canTransferOwnership))
                        <form method="post" action="{{ route('helpdesk.transfer', $ticket) }}" data-live-case-form="transfer">
                            @csrf
                            <label class="form-label" for="transfer_to">Transfer ownership</label>
                            <select class="form-select mb-2" id="transfer_to" name="assigned_to" required>
                                <option value="">Select person…</option>
                                @foreach ($technicians as $technician)
                                    @if ((int) $technician->id !== (int) auth()->id())
                                        <option value="{{ $technician->id }}">
                                            {{ $ownerOptionLabel($technician) }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-pg" type="submit">Transfer case</button>
                        </form>
                    @endif
                    <p class="text-muted small mb-0 mt-2" data-live-case-status hidden></p>
                </section>
            @endif
            @php
                $canManagePartners = auth()->user()->hasPermission('tickets.assign')
                    || ! empty($isCasePartner)
                    || ((int) $ticket->requester_id === (int) auth()->id())
                    || ((int) $ticket->assigned_to === (int) auth()->id());
            @endphp
            @if ($canManagePartners)
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Partners</h2>
                    <p class="text-muted small mb-2">
                        Partners can open the case, get notes and @mentions, and claim ownership when it is unassigned.
                    </p>
                    <ul
                        class="mb-2 pg-partner-list"
                        data-case-partner-list
                        data-can-remove-partners="{{ ! empty($canRemovePartners) ? '1' : '0' }}"
                        data-partner-remove-url-template="{{ route('helpdesk.partners.destroy', [$ticket, '__PARTNER__']) }}"
                        data-case-owner-id="{{ $ticket->assigned_to }}"
                    >
                        @forelse ($ticket->partners as $partner)
                            <li data-partner-id="{{ $partner->id }}">
                                <span class="pg-partner-row">
                                    <span>
                                        <a href="{{ route('people.show', $partner) }}">{{ $partner->name }}</a>
                                        <span class="text-muted small">· {{ $partner->pivot->source }}</span>
                                    </span>
                                    @if (! empty($canRemovePartners) && (int) $ticket->assigned_to !== (int) $partner->id)
                                        <button
                                            class="btn btn-sm btn-pg-line"
                                            type="button"
                                            data-remove-partner
                                            data-partner-id="{{ $partner->id }}"
                                            data-partner-name="{{ $partner->name }}"
                                        >Remove</button>
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="text-muted" data-partners-empty>No partners yet.</li>
                        @endforelse
                    </ul>
                    <form method="post" action="{{ route('helpdesk.partners.store', $ticket) }}" data-live-case-form="partners">
                        @csrf
                        <label class="form-label" for="partner_user_id">Add partner</label>
                        <select class="form-select mb-2" id="partner_user_id" name="user_id" required>
                            <option value="">Select person…</option>
                            @foreach ($partnerCandidates as $person)
                                <option value="{{ $person->id }}">{{ $ownerOptionLabel($person) }} · {{ $person->email }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-pg" type="submit">Add partner</button>
                    </form>
                    <p class="text-muted small mb-0 mt-2" data-live-case-status hidden></p>
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
                        @if (! empty($canTransferOwnership) || auth()->user()->hasPermission('tickets.assign'))
                            <label class="form-label" for="assigned_to">Owner</label>
                            <select class="form-select mb-2" id="assigned_to" name="assigned_to">
                                <option value="">Unchanged</option>
                                @foreach ($technicians as $technician)
                                    <option value="{{ $technician->id }}" @selected($ticket->assigned_to == $technician->id)>
                                        {{ $ownerOptionLabel($technician) }}
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
                        <label class="form-label" for="resolution_notes">Resolution / next-step notes</label>
                        <p class="text-muted small mb-1">Required when moving the case forward. Explain what was done or why the next step is needed. Use <strong>Add a note</strong> above for ICT discussion posts.</p>
                        <textarea class="form-control mb-2" id="resolution_notes" name="resolution_notes" rows="3" data-ai-assist data-mentions data-format-toolbar placeholder="What was done, and why this next step is needed…" data-step-evidence>{{ old('resolution_notes', $ticket->resolution_notes) }}</textarea>
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
                <script>
                    (() => {
                        const form = document.querySelector('[data-status-form]');
                        const select = form?.querySelector('[data-status-select]');
                        const evidence = form ? [...form.querySelectorAll('[data-step-evidence]')] : [];
                        const fields = document.querySelector('[data-duplicate-fields]');
                        const input = document.getElementById('duplicate_of');
                        const current = @json($ticket->status);
                        if (!select) return;
                        const sync = () => {
                            const next = select.value;
                            const needsEvidence = next !== current && ! (current === 'resolved' && next === 'closed');
                            evidence.forEach((field) => { field.required = needsEvidence; });
                            if (fields) {
                                const show = next === 'closed' && current !== 'resolved';
                                fields.hidden = !show;
                                if (input) input.required = show;
                            }
                        };
                        select.addEventListener('change', sync);
                        sync();
                    })();
                </script>
            @endif
            @if (! empty($canTriage))
                <section
                    class="pg-card pg-card-pad mb-3 pg-triage is-collapsed"
                    data-case-triage
                    data-ticket-id="{{ $ticket->id }}"
                    data-triage-url="{{ route('ai.triage', $ticket) }}"
                    data-triage-apply-url="{{ route('ai.triage.apply', $ticket) }}"
                >
                    <div class="pg-triage-collapsed">
                        <div>
                            <h2 class="pg-section-title mb-0">AI triage</h2>
                            <p class="text-muted small mb-0">Optional help choosing an owner, partners, and next steps.</p>
                        </div>
                        <button class="btn btn-sm btn-pg-line" type="button" data-triage-run>Check AI suggestion</button>
                    </div>
                    <div class="pg-triage-panel" data-triage-panel hidden>
                        <div class="pg-triage-head">
                            <p class="text-muted small mb-0">
                                Review the suggestion, then apply it or fill the update form.
                            </p>
                            <button class="btn btn-sm btn-pg-line" type="button" data-triage-hide>Hide</button>
                        </div>
                        <p class="pg-triage-status text-muted small mb-0" data-triage-status hidden></p>
                        <div class="pg-triage-body" data-triage-body hidden></div>
                    </div>
                </section>
            @endif
            <section class="pg-card">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Status history</h2></div>
                <ul class="pg-timeline px-3" data-case-history>
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
