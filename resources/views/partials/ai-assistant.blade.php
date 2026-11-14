@php
    $aiEnabled = filled(config('services.openai.key'));
    $aiEntityIndex = \App\Support\AiEntityIndex::forUser(auth()->user());
@endphp
<div
    class="pg-ai"
    data-ai-root
    data-ai-enabled="{{ $aiEnabled ? '1' : '0' }}"
    data-ai-user-id="{{ auth()->id() }}"
    data-ai-chat-url="{{ route('ai.chat') }}"
    data-ai-confirm-url="{{ route('ai.actions.confirm') }}"
    data-ai-cancel-url="{{ route('ai.actions.cancel') }}"
    data-ai-refine-url="{{ route('ai.refine') }}"
    data-ai-paraphrase-url="{{ route('ai.paraphrase') }}"
    data-ai-open-sound="{{ asset('sounds/assistant-open.mp3') }}"
    data-ai-reply-sound="{{ asset('sounds/message.mp3') }}"
    data-ai-rewrite-url="{{ route('ai.rewrite') }}"
    data-ai-entities='@json($aiEntityIndex['entities'])'
    data-ai-urls='@json($aiEntityIndex['urls'])'
>
    <button class="pg-ai-launcher" type="button" data-ai-open aria-expanded="false" aria-controls="pg-ai-panel" title="PlantGrid Assistant">
        <span class="pg-ai-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" focusable="false">
                <rect x="3" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.95"/>
                <rect x="17" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                <rect x="3" y="17" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                <path d="M20 18.5c0 4.2 2.4 7 6 8.2-1.1-2.1-1.6-4-1.6-6.2V18.5H20z" fill="currentColor" opacity="0.95"/>
                <circle cx="24.5" cy="14.5" r="2.2" fill="currentColor"/>
            </svg>
        </span>
        <span>Assistant</span>
    </button>

    <section class="pg-ai-panel" id="pg-ai-panel" data-ai-panel hidden>
        <header class="pg-ai-head">
            <div class="pg-ai-head-brand">
                <span class="pg-ai-avatar" aria-hidden="true">
                    <svg viewBox="0 0 32 32" focusable="false">
                        <rect x="3" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.95"/>
                        <rect x="17" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                        <rect x="3" y="17" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                        <path d="M20 18.5c0 4.2 2.4 7 6 8.2-1.1-2.1-1.6-4-1.6-6.2V18.5H20z" fill="currentColor" opacity="0.95"/>
                        <circle cx="24.5" cy="14.5" r="2.2" fill="currentColor"/>
                    </svg>
                </span>
                <div>
                    <strong>PlantGrid Assistant</strong>
                    <span>Live demo answers · confirm before actions</span>
                </div>
            </div>
            <div class="pg-ai-head-actions">
                <div class="pg-ai-menu" data-ai-menu>
                    <button class="pg-ai-icon-btn" type="button" data-ai-menu-toggle aria-expanded="false" aria-haspopup="true" aria-label="Assistant options">
                        <i data-lucide="more-vertical"></i>
                    </button>
                    <div class="pg-ai-menu-pop" data-ai-menu-pop hidden>
                        <button type="button" data-ai-sound-toggle>Sound on</button>
                        <button type="button" data-ai-clear>Clear chat history</button>
                    </div>
                </div>
                <button class="pg-ai-icon-btn" type="button" data-ai-close aria-label="Minimize assistant">
                    <i data-lucide="chevron-down"></i>
                </button>
            </div>
        </header>

        <div class="pg-ai-tabs" role="tablist" aria-label="Assistant tools">
            <button class="active" type="button" data-ai-tab="chat" role="tab" aria-selected="true">Chat</button>
            <button type="button" data-ai-tab="notes" role="tab" aria-selected="false">Notes</button>
        </div>

        <div class="pg-ai-body" data-ai-view="chat">
            <div class="pg-ai-transcript" data-ai-transcript aria-live="polite">
                <div class="pg-ai-row pg-ai-row-bot" data-ai-welcome>
                    <span class="pg-ai-avatar" aria-hidden="true">
                        <svg viewBox="0 0 32 32" focusable="false">
                            <rect x="3" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.95"/>
                            <rect x="17" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                            <rect x="3" y="17" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
                            <path d="M20 18.5c0 4.2 2.4 7 6 8.2-1.1-2.1-1.6-4-1.6-6.2V18.5H20z" fill="currentColor" opacity="0.95"/>
                            <circle cx="24.5" cy="14.5" r="2.2" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="pg-ai-msg">
                        <div class="pg-ai-bubble pg-ai-bubble-bot">
                            <div class="pg-ai-bubble-body">Ask about cases, assets, or Documentation guides — or tell me to open a case. Tag with <strong>@</strong> people and <strong>#</strong> cases/assets/devices. Actions wait for your confirm.</div>
                        </div>
                        <div class="pg-ai-meta">
                            <span>PlantGrid Assistant</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pg-ai-suggestions" data-ai-suggestions>
                <button type="button" data-ai-prompt="Have we solved all the cases?">Open cases?</button>
                <button type="button" data-ai-prompt="Is there any documentation? Which guides should a new user read first?">Docs?</button>
                <button type="button" data-ai-prompt="Create a case for Packaging: barcode label printer is offline at the packaging bench, priority high, asset PG-LBL-0142">Create packaging case</button>
                <button type="button" data-ai-prompt="Take ownership of case PG-2026-0148">Take ownership</button>
                <button type="button" data-ai-prompt="Any critical monitoring alerts right now?">Critical alerts?</button>
                <button type="button" data-ai-prompt="What assets need attention?">Assets at risk</button>
            </div>
            <div class="pg-ai-compose-wrap">
                <div class="pg-ai-compose-tools" data-ai-compose-tools>
                    <button type="button" data-ai-compose-action="refine" title="Improve clarity and grammar">Refine</button>
                    <button type="button" data-ai-compose-action="paraphrase" title="Rewrite in different wording">Paraphrase</button>
                    <button type="button" data-ai-compose-action="shorten" title="Make it shorter">Shorten</button>
                    <button type="button" data-ai-compose-action="clarify" title="Make it clearer and more specific">Clarify</button>
                    <button type="button" data-ai-compose-undo hidden title="Undo last AI edit">Undo</button>
                </div>
                <form class="pg-ai-compose" data-ai-chat-form>
                    <label class="visually-hidden" for="pg-ai-message">Message</label>
                    <textarea
                        id="pg-ai-message"
                        data-ai-input
                        data-mentions
                        rows="1"
                        placeholder="Message… Use @people or # for cases, assets, devices"
                        required
                    ></textarea>
                    <button class="pg-ai-send" type="submit" data-ai-send aria-label="Send">
                        <i data-lucide="send"></i>
                    </button>
                </form>
                <p class="pg-ai-compose-status" data-ai-compose-status hidden></p>
            </div>
            <div class="pg-ai-clear-zone" data-ai-clear-zone hidden>
                <div class="pg-ai-clear-confirm" data-ai-clear-confirm hidden>
                    <p>Clear this chat? This removes the conversation from this browser.</p>
                    <div class="pg-ai-clear-confirm-actions">
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-clear-cancel>Cancel</button>
                        <button class="btn btn-sm btn-pg" type="button" data-ai-clear-confirm-yes>Yes, clear chat</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="pg-ai-body" data-ai-view="notes" hidden>
            <p class="pg-ai-hint">Paste a case note or description, then refine or paraphrase it. The result can be copied into any note field.</p>
            <label class="form-label" for="pg-ai-note">Note text</label>
            <textarea
                class="form-control"
                id="pg-ai-note"
                data-ai-note
                data-mentions
                rows="6"
                placeholder="Paste or write a note… Use @people or # for cases/assets"
            ></textarea>
            <div class="pg-ai-note-actions">
                <button class="btn btn-pg" type="button" data-ai-refine>Refine</button>
                <button class="btn btn-pg-line" type="button" data-ai-paraphrase>Paraphrase</button>
                <button class="btn btn-pg-line" type="button" data-ai-note-rewrite="shorten">Shorten</button>
                <button class="btn btn-pg-line" type="button" data-ai-note-rewrite="clarify">Clarify</button>
                <button class="btn btn-pg-line" type="button" data-ai-copy-note>Copy result</button>
            </div>
            <p class="pg-ai-status" data-ai-note-status hidden></p>
        </div>
    </section>
</div>
