@php
    $aiEnabled = filled(config('services.openai.key'));
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
>
    <button class="pg-ai-launcher" type="button" data-ai-open aria-expanded="false" aria-controls="pg-ai-panel" title="PlantGrid Assistant">
        <i data-lucide="sparkles"></i>
        <span>Assistant</span>
    </button>

    <section class="pg-ai-panel" id="pg-ai-panel" data-ai-panel hidden>
        <header class="pg-ai-head">
            <div>
                <strong>PlantGrid Assistant</strong>
                <span>Live answers · create cases · refine notes</span>
            </div>
            <div class="pg-ai-head-actions">
                <button class="pg-ai-close" type="button" data-ai-close aria-label="Close assistant">
                    <i data-lucide="x"></i>
                </button>
            </div>
        </header>

        <div class="pg-ai-tabs" role="tablist" aria-label="Assistant tools">
            <button class="active" type="button" data-ai-tab="chat" role="tab" aria-selected="true">Chat</button>
            <button type="button" data-ai-tab="notes" role="tab" aria-selected="false">Notes</button>
        </div>

        <div class="pg-ai-body" data-ai-view="chat">
            <div class="pg-ai-transcript" data-ai-transcript aria-live="polite">
                <div class="pg-ai-bubble pg-ai-bubble-bot" data-ai-welcome>
                    Ask about cases and assets, or tell me to open a case for a department. Actions wait for your confirm.
                </div>
            </div>
            <div class="pg-ai-suggestions" data-ai-suggestions>
                <button type="button" data-ai-prompt="Have we solved all the cases?">Open cases?</button>
                <button type="button" data-ai-prompt="Create a case for Packaging: barcode label printer is offline at the packaging bench, priority high, asset PG-LBL-0142">Create packaging case</button>
                <button type="button" data-ai-prompt="Take ownership of case PG-2026-0148">Take ownership</button>
                <button type="button" data-ai-prompt="Any critical monitoring alerts right now?">Critical alerts?</button>
                <button type="button" data-ai-prompt="What assets need attention?">Assets at risk</button>
            </div>
            <form class="pg-ai-compose" data-ai-chat-form>
                <label class="visually-hidden" for="pg-ai-message">Message</label>
                <textarea id="pg-ai-message" data-ai-input rows="2" placeholder="Ask about cases, alerts, assets…" required></textarea>
                <button class="btn btn-pg pg-ai-send" type="submit" data-ai-send aria-label="Send">
                    <i data-lucide="send"></i>
                    <span>Send</span>
                </button>
            </form>
            <div class="pg-ai-clear-zone" data-ai-clear-zone hidden>
                <button class="pg-ai-clear" type="button" data-ai-clear>
                    Clear chat history
                </button>
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
            <textarea class="form-control" id="pg-ai-note" data-ai-note rows="6" placeholder="Paste or write the note to improve…"></textarea>
            <div class="pg-ai-note-actions">
                <button class="btn btn-pg" type="button" data-ai-refine>Refine</button>
                <button class="btn btn-pg-line" type="button" data-ai-paraphrase>Paraphrase</button>
                <button class="btn btn-pg-line" type="button" data-ai-copy-note>Copy result</button>
            </div>
            <p class="pg-ai-status" data-ai-note-status hidden></p>
        </div>
    </section>
</div>
