function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'AI request failed.');
    }

    return payload;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatReply(text) {
    const escaped = escapeHtml(text).trim();
    const blocks = escaped.split(/\n{2,}/);
    return blocks.map((block) => {
        const lines = block.split('\n');
        const isList = lines.every((line) => /^\s*([-*•]|\d+\.)\s+/.test(line));
        if (isList) {
            const items = lines
                .map((line) => line.replace(/^\s*([-*•]|\d+\.)\s+/, ''))
                .map((line) => `<li>${line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')}</li>`)
                .join('');
            return `<ul>${items}</ul>`;
        }
        return `<p>${lines.join('<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')}</p>`;
    }).join('');
}

function nowIso() {
    return new Date().toISOString();
}

function formatChatTime(value) {
    if (!value) {
        return '';
    }
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const day = days[date.getDay()];
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const dayNum = String(date.getDate()).padStart(2, '0');
    const year = date.getFullYear();
    let hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const hourLabel = String(hours).padStart(2, '0');
    return `${day}, ${month}/${dayNum}/${year}, ${hourLabel}:${minutes} ${ampm}`;
}

const ASSISTANT_MARK_SVG = `
    <svg viewBox="0 0 32 32" focusable="false" aria-hidden="true">
        <rect x="3" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.95"/>
        <rect x="17" y="3" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
        <rect x="3" y="17" width="12" height="12" rx="3" fill="currentColor" opacity="0.55"/>
        <path d="M20 18.5c0 4.2 2.4 7 6 8.2-1.1-2.1-1.6-4-1.6-6.2V18.5H20z" fill="currentColor" opacity="0.95"/>
        <circle cx="24.5" cy="14.5" r="2.2" fill="currentColor"/>
    </svg>
`;

function avatarHtml() {
    return `<span class="pg-ai-avatar" aria-hidden="true">${ASSISTANT_MARK_SVG}</span>`;
}

function timeMetaHtml(at, { role = 'bot', showName = false } = {}) {
    const label = formatChatTime(at);
    if (!label && !showName) {
        return '';
    }
    const name = showName ? '<span>PlantGrid Assistant</span>' : '';
    const time = label
        ? `<time datetime="${escapeHtml(typeof at === 'string' ? at : nowIso())}">${escapeHtml(label)}</time>`
        : '';
    return `<div class="pg-ai-meta">${name}${name && time ? '<span class="pg-ai-meta-dot" aria-hidden="true">·</span>' : ''}${time}</div>`;
}

function typingHtml() {
    return `
        <div class="pg-ai-typing" aria-hidden="true" aria-label="Assistant is typing">
            <span class="pg-ai-typing-pulse"></span>
            <span class="pg-ai-typing-bars">
                <i></i><i></i><i></i><i></i>
            </span>
            <span class="pg-ai-typing-dots"><i></i><i></i><i></i></span>
        </div>
    `;
}

function proposalCardHtml(proposal, status = 'pending') {
    if (!proposal) {
        return '';
    }
    const details = (proposal.details || [])
        .map((row) => `<div><dt>${escapeHtml(row.label)}</dt><dd>${escapeHtml(row.value)}</dd></div>`)
        .join('');
    if (status === 'confirmed') {
        return `<div class="pg-ai-proposal is-done"><strong>${escapeHtml(proposal.title || 'Action')}</strong><p>Confirmed.</p></div>`;
    }
    if (status === 'cancelled') {
        return `<div class="pg-ai-proposal is-cancelled"><strong>${escapeHtml(proposal.title || 'Action')}</strong><p>Cancelled.</p></div>`;
    }
    const confirmLabel = proposal.confirm_label || (
        proposal.action === 'take_ownership'
            ? 'Confirm take ownership'
            : proposal.action === 'close_duplicate'
                ? 'Confirm close duplicate'
                : 'Confirm create'
    );
    return `
        <div class="pg-ai-proposal" data-ai-proposal-id="${escapeHtml(proposal.id)}" data-ai-proposal-action="${escapeHtml(proposal.action || '')}">
            <strong>${escapeHtml(proposal.title || 'Proposed action')}</strong>
            <p>${escapeHtml(proposal.summary || '')}</p>
            <dl class="pg-ai-proposal-details">${details}</dl>
            <div class="pg-ai-proposal-actions">
                <button class="btn btn-sm btn-pg" type="button" data-ai-confirm>${escapeHtml(confirmLabel)}</button>
                <button class="btn btn-sm btn-pg-line" type="button" data-ai-cancel-action>Cancel</button>
            </div>
        </div>
    `;
}

function appendBubble(transcript, role, text, { pending = false, proposal = null, proposalStatus = 'pending', at = null } = {}) {
    const isUser = role === 'user';
    const row = document.createElement('div');
    row.className = `pg-ai-row ${isUser ? 'pg-ai-row-user' : 'pg-ai-row-bot'}`;
    const stamped = at || nowIso();
    row.dataset.at = stamped;

    if (pending) {
        row.classList.add('is-pending');
        row.innerHTML = `
            ${avatarHtml()}
            <div class="pg-ai-msg">
                <div class="pg-ai-bubble pg-ai-bubble-bot is-pending">${typingHtml()}</div>
            </div>
        `;
    } else if (isUser) {
        row.innerHTML = `
            <div class="pg-ai-msg">
                <div class="pg-ai-bubble pg-ai-bubble-user">
                    <div class="pg-ai-bubble-body">${escapeHtml(text)}</div>
                </div>
                ${timeMetaHtml(stamped, { role: 'user' })}
            </div>
        `;
    } else {
        row.innerHTML = `
            ${avatarHtml()}
            <div class="pg-ai-msg">
                <div class="pg-ai-bubble pg-ai-bubble-bot">
                    <div class="pg-ai-bubble-body">${formatReply(text)}${proposalCardHtml(proposal, proposalStatus)}</div>
                </div>
                ${timeMetaHtml(stamped, { role: 'bot', showName: true })}
            </div>
        `;
    }

    transcript.appendChild(row);
    scrollTranscriptToLatest(transcript);
    return row;
}

function scrollTranscriptToLatest(transcript) {
    if (!transcript) {
        return;
    }
    const snap = () => {
        transcript.scrollTop = transcript.scrollHeight;
    };
    snap();
    window.requestAnimationFrame(() => {
        snap();
        window.requestAnimationFrame(snap);
    });
    window.setTimeout(snap, 50);
    window.setTimeout(snap, 180);
}

function setBusy(root, busy) {
    root.classList.toggle('is-busy', busy);
    root.querySelectorAll('button, textarea').forEach((el) => {
        if (
            el.hasAttribute('data-ai-close')
            || el.hasAttribute('data-ai-open')
            || el.hasAttribute('data-ai-tab')
            || el.hasAttribute('data-ai-clear')
            || el.hasAttribute('data-ai-menu-toggle')
            || el.hasAttribute('data-ai-sound-toggle')
        ) {
            return;
        }
        el.disabled = busy;
    });
}

function storageKey(userId) {
    return `plantgrid.ai.chat.${userId || 'guest'}`;
}

function soundPrefKey(userId) {
    return `plantgrid.ai.sound.${userId || 'guest'}`;
}

function loadChatState(userId) {
    try {
        const raw = localStorage.getItem(storageKey(userId));
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.messages) || !Array.isArray(parsed.history)) {
            return null;
        }
        return {
            messages: parsed.messages
                .filter((item) => item && (item.role === 'user' || item.role === 'assistant') && typeof item.content === 'string')
                .map((item) => ({
                    role: item.role,
                    content: item.content,
                    at: typeof item.at === 'string' ? item.at : null,
                    proposal: item.proposal && typeof item.proposal === 'object' ? item.proposal : null,
                    proposalStatus: item.proposalStatus || (item.proposal ? 'pending' : null),
                }))
                .slice(-40),
            history: parsed.history
                .filter((item) => item && (item.role === 'user' || item.role === 'assistant') && typeof item.content === 'string')
                .slice(-12),
            open: Boolean(parsed.open),
        };
    } catch {
        return null;
    }
}

function saveChatState(userId, payload) {
    try {
        localStorage.setItem(storageKey(userId), JSON.stringify({
            messages: payload.messages.slice(-40),
            history: payload.history.slice(-12),
            open: Boolean(payload.open),
            savedAt: Date.now(),
        }));
    } catch {
        // Ignore quota / private-mode failures.
    }
}

function createSoundboard({ openUrl = '', replyUrl = '', volume = 0.92 } = {}) {
    const players = {
        open: openUrl ? new Audio(openUrl) : null,
        reply: replyUrl ? new Audio(replyUrl) : null,
    };

    Object.values(players).forEach((audio) => {
        if (!audio) {
            return;
        }
        audio.preload = 'auto';
        audio.volume = Math.min(1, Math.max(0, volume));
    });

    const play = (audio) => {
        if (!audio) {
            return;
        }
        try {
            audio.pause();
            audio.currentTime = 0;
            const result = audio.play();
            if (result && typeof result.catch === 'function') {
                result.catch(() => {});
            }
        } catch {
            // Ignore autoplay / decode failures.
        }
    };

    return {
        unlock() {
            // Warm the audio elements during the user gesture that opens chat.
            Object.values(players).forEach((audio) => {
                if (!audio) {
                    return;
                }
                try {
                    audio.load();
                } catch {
                    // Ignore.
                }
            });
        },
        playOpen(enabled) {
            if (!enabled) {
                return;
            }
            play(players.open);
        },
        playReply(enabled) {
            if (!enabled) {
                return;
            }
            play(players.reply);
        },
    };
}

export function initAiAssistant(refreshIcons) {
    const root = document.querySelector('[data-ai-root]');
    if (!root) {
        return;
    }

    const enabled = root.dataset.aiEnabled === '1';
    const userId = root.dataset.aiUserId || 'guest';
    const openBtn = root.querySelector('[data-ai-open]');
    const closeBtn = root.querySelector('[data-ai-close]');
    const clearZone = root.querySelector('[data-ai-clear-zone]');
    const clearBtn = root.querySelector('[data-ai-clear]');
    const clearConfirm = root.querySelector('[data-ai-clear-confirm]');
    const clearCancelBtn = root.querySelector('[data-ai-clear-cancel]');
    const clearConfirmYesBtn = root.querySelector('[data-ai-clear-confirm-yes]');
    const menuToggle = root.querySelector('[data-ai-menu-toggle]');
    const menuPop = root.querySelector('[data-ai-menu-pop]');
    const soundToggle = root.querySelector('[data-ai-sound-toggle]');
    const panel = root.querySelector('[data-ai-panel]');
    const transcript = root.querySelector('[data-ai-transcript]');
    const welcome = root.querySelector('[data-ai-welcome]');
    const chatForm = root.querySelector('[data-ai-chat-form]');
    const chatInput = root.querySelector('[data-ai-input]');
    const noteInput = root.querySelector('[data-ai-note]');
    const noteStatus = root.querySelector('[data-ai-note-status]');
    const suggestions = root.querySelector('[data-ai-suggestions]');
    const welcomeText = welcome?.querySelector('.pg-ai-bubble-body')?.textContent?.trim()
        || welcome?.textContent?.trim()
        || 'Ask about open cases, alerts, or demo roles. I answer from the live Northridge sample data when I can.';

    const stored = loadChatState(userId);
    const history = stored?.history ? [...stored.history] : [];
    const messages = stored?.messages ? [...stored.messages] : [];
    const sounds = createSoundboard({
        openUrl: root.dataset.aiOpenSound || '',
        replyUrl: root.dataset.aiReplySound || '',
        volume: 0.95,
    });
    let soundEnabled = true;
    try {
        const savedSound = localStorage.getItem(soundPrefKey(userId));
        if (savedSound === '0') {
            soundEnabled = false;
        }
    } catch {
        soundEnabled = true;
    }

    const syncSoundLabel = () => {
        if (soundToggle) {
            soundToggle.textContent = soundEnabled ? 'Sound on' : 'Sound off';
        }
        root.classList.toggle('is-muted', !soundEnabled);
    };
    syncSoundLabel();

    const setMenuOpen = (open) => {
        if (!menuPop || !menuToggle) {
            return;
        }
        menuPop.hidden = !open;
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const hideClearConfirm = () => {
        if (clearConfirm) {
            clearConfirm.hidden = true;
        }
        if (clearZone) {
            clearZone.hidden = true;
        }
    };

    const persist = () => {
        saveChatState(userId, {
            messages,
            history,
            open: root.classList.contains('is-open'),
        });
        if (suggestions) {
            suggestions.hidden = messages.length > 0;
        }
    };

    const renderTranscript = () => {
        if (!transcript) {
            return;
        }
        transcript.innerHTML = '';
        const intro = document.createElement('div');
        intro.className = 'pg-ai-row pg-ai-row-bot';
        intro.setAttribute('data-ai-welcome', '');
        intro.innerHTML = `
            ${avatarHtml()}
            <div class="pg-ai-msg">
                <div class="pg-ai-bubble pg-ai-bubble-bot">
                    <div class="pg-ai-bubble-body">${escapeHtml(welcomeText)}</div>
                </div>
                <div class="pg-ai-meta"><span>PlantGrid Assistant</span></div>
            </div>
        `;
        transcript.appendChild(intro);

        messages.forEach((item) => {
            appendBubble(transcript, item.role, item.content, {
                proposal: item.proposal || null,
                proposalStatus: item.proposalStatus || 'pending',
                at: item.at || null,
            });
        });
        scrollTranscriptToLatest(transcript);
        persist();
    };

    const setOpen = (open, { fromUser = false } = {}) => {
        if (!panel || !openBtn) {
            return;
        }
        const wasOpen = root.classList.contains('is-open');
        panel.hidden = !open;
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
        setMenuOpen(false);
        persist();
        if (open) {
            if (fromUser && !wasOpen) {
                sounds.unlock();
                sounds.playOpen(soundEnabled);
            }
            refreshIcons?.();
            scrollTranscriptToLatest(transcript);
            const activeView = root.querySelector('[data-ai-view]:not([hidden])');
            activeView?.querySelector('textarea')?.focus();
        }
    };

    const showStatus = (message, isError = false) => {
        if (!noteStatus) {
            return;
        }
        noteStatus.hidden = !message;
        noteStatus.textContent = message || '';
        noteStatus.classList.toggle('is-error', Boolean(isError));
    };

    const ensureEnabled = () => {
        if (enabled) {
            return true;
        }
        window.alert('AI is not configured. Add OPENAI_API_KEY to the server environment.');
        return false;
    };

    const clearChat = () => {
        messages.length = 0;
        history.length = 0;
        hideClearConfirm();
        setMenuOpen(false);
        renderTranscript();
        chatInput?.focus();
    };

    const requestClearChat = () => {
        if (messages.length === 0) {
            setMenuOpen(false);
            return;
        }
        setMenuOpen(false);
        if (clearZone) {
            clearZone.hidden = false;
        }
        if (clearConfirm) {
            clearConfirm.hidden = false;
        }
    };

    const sendChat = async (rawMessage) => {
        if (!ensureEnabled() || !chatInput || !transcript) {
            return;
        }

        const message = String(rawMessage || '').trim();
        if (message.length < 2) {
            return;
        }

        const userAt = nowIso();
        messages.push({ role: 'user', content: message, at: userAt });
        appendBubble(transcript, 'user', message, { at: userAt });
        chatInput.value = '';
        persist();

        const pending = appendBubble(transcript, 'assistant', '', { pending: true });
        setBusy(root, true);

        try {
            const payload = await postJson(root.dataset.aiChatUrl, {
                message,
                history,
            });
            const assistantAt = nowIso();
            pending.classList.remove('is-pending');
            pending.dataset.at = assistantAt;
            pending.innerHTML = `
                ${avatarHtml()}
                <div class="pg-ai-msg">
                    <div class="pg-ai-bubble pg-ai-bubble-bot">
                        <div class="pg-ai-bubble-body">${formatReply(payload.reply)}${proposalCardHtml(payload.proposal, 'pending')}</div>
                    </div>
                    ${timeMetaHtml(assistantAt, { role: 'bot', showName: true })}
                </div>
            `;
            const assistantContent = payload.proposal
                ? `${payload.reply}\n\n[Proposed: ${payload.proposal.title} — ${payload.proposal.summary}]`
                : payload.reply;
            messages.push({
                role: 'assistant',
                content: payload.reply,
                at: assistantAt,
                proposal: payload.proposal || null,
                proposalStatus: payload.proposal ? 'pending' : null,
            });
            history.push({ role: 'user', content: message });
            history.push({ role: 'assistant', content: assistantContent });
            while (history.length > 12) {
                history.shift();
            }
            while (messages.length > 40) {
                messages.shift();
            }
            persist();
            bindProposalActions(pending);
            sounds.playReply(soundEnabled);
        } catch (error) {
            pending.classList.remove('is-pending');
            pending.classList.add('is-error');
            pending.innerHTML = `
                ${avatarHtml()}
                <div class="pg-ai-msg">
                    <div class="pg-ai-bubble pg-ai-bubble-bot is-error">
                        <div class="pg-ai-bubble-body">${escapeHtml(error.message || 'AI request failed.')}</div>
                    </div>
                </div>
            `;
            messages.pop();
            persist();
        } finally {
            setBusy(root, false);
            chatInput.focus();
            refreshIcons?.();
        }
    };

    const findMessageForProposal = (proposalId) => messages.find(
        (item) => item.role === 'assistant' && item.proposal?.id === proposalId
    );

    const bindProposalActions = (scope = transcript) => {
        scope?.querySelectorAll('[data-ai-proposal-id]').forEach((card) => {
            if (card.dataset.bound === '1') {
                return;
            }
            card.dataset.bound = '1';
            const proposalId = card.dataset.aiProposalId;
            card.querySelector('[data-ai-confirm]')?.addEventListener('click', async () => {
                if (!proposalId) {
                    return;
                }
                setBusy(root, true);
                try {
                    const payload = await postJson(root.dataset.aiConfirmUrl, { proposal_id: proposalId });
                    const ticketLink = payload.ticket?.url
                        ? `<p><a class="pg-ai-ticket-link" href="${escapeHtml(payload.ticket.url)}">${escapeHtml(payload.ticket.ticket_number)}</a> · ${escapeHtml(payload.ticket.subject || '')}</p>`
                        : '';
                    const doneTitle = card.dataset.aiProposalAction === 'take_ownership'
                        ? 'Ownership updated'
                        : card.dataset.aiProposalAction === 'close_duplicate'
                            ? 'Case closed'
                            : 'Case created';
                    card.classList.add('is-done');
                    card.innerHTML = `<strong>${doneTitle}</strong>${ticketLink}<p>${escapeHtml(payload.reply || 'Done.')}</p>`;
                    const storedMsg = findMessageForProposal(proposalId);
                    if (storedMsg) {
                        storedMsg.proposalStatus = 'confirmed';
                        storedMsg.content = payload.reply || storedMsg.content;
                    }
                    const confirmAt = nowIso();
                    const confirmText = payload.reply || 'Case created.';
                    messages.push({ role: 'assistant', content: confirmText, at: confirmAt });
                    appendBubble(transcript, 'assistant', confirmText, { at: confirmAt });
                    history.push({ role: 'assistant', content: confirmText });
                    while (history.length > 12) {
                        history.shift();
                    }
                    persist();
                    sounds.playReply(soundEnabled);
                } catch (error) {
                    card.insertAdjacentHTML('beforeend', `<p class="pg-ai-proposal-error">${escapeHtml(error.message || 'Could not create the case.')}</p>`);
                } finally {
                    setBusy(root, false);
                    refreshIcons?.();
                }
            });
            card.querySelector('[data-ai-cancel-action]')?.addEventListener('click', async () => {
                if (!proposalId) {
                    return;
                }
                setBusy(root, true);
                try {
                    await postJson(root.dataset.aiCancelUrl, { proposal_id: proposalId });
                } catch {
                    // Local cancel still stands if cache already expired.
                } finally {
                    card.classList.add('is-cancelled');
                    card.innerHTML = '<strong>Cancelled</strong><p>Nothing was created.</p>';
                    const stored = findMessageForProposal(proposalId);
                    if (stored) {
                        stored.proposalStatus = 'cancelled';
                    }
                    persist();
                    setBusy(root, false);
                }
            });
        });
    };

    renderTranscript();
    bindProposalActions(transcript);
    if (stored?.open) {
        setOpen(true, { fromUser: false });
    }

    openBtn?.addEventListener('click', () => {
        const opening = panel?.hidden !== false;
        setOpen(opening, { fromUser: true });
    });
    closeBtn?.addEventListener('click', () => setOpen(false));
    clearBtn?.addEventListener('click', requestClearChat);
    clearCancelBtn?.addEventListener('click', hideClearConfirm);
    clearConfirmYesBtn?.addEventListener('click', clearChat);

    menuToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        setMenuOpen(menuPop?.hidden !== false);
    });
    soundToggle?.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        try {
            localStorage.setItem(soundPrefKey(userId), soundEnabled ? '1' : '0');
        } catch {
            // Ignore storage failures.
        }
        syncSoundLabel();
        if (soundEnabled) {
            sounds.unlock();
            sounds.playOpen(true);
        }
        setMenuOpen(false);
    });
    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setMenuOpen(false);
            return;
        }
        if (!event.target.closest('[data-ai-menu]')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            if (menuPop && !menuPop.hidden) {
                setMenuOpen(false);
                return;
            }
            setOpen(false);
        }
    });

    root.querySelectorAll('[data-ai-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const name = tab.dataset.aiTab;
            root.querySelectorAll('[data-ai-tab]').forEach((item) => {
                const active = item === tab;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            root.querySelectorAll('[data-ai-view]').forEach((view) => {
                view.hidden = view.dataset.aiView !== name;
            });
            if (name === 'chat') {
                scrollTranscriptToLatest(transcript);
            }
            refreshIcons?.();
        });
    });

    const composeStatus = root.querySelector('[data-ai-compose-status]');
    const composeUndoBtn = root.querySelector('[data-ai-compose-undo]');
    let composeUndoText = null;

    const showComposeStatus = (message, isError = false) => {
        if (!composeStatus) {
            return;
        }
        composeStatus.hidden = !message;
        composeStatus.textContent = message || '';
        composeStatus.classList.toggle('is-error', Boolean(isError));
    };

    const syncComposeUndo = () => {
        if (composeUndoBtn) {
            composeUndoBtn.hidden = composeUndoText == null;
        }
    };

    const runComposeRewrite = async (mode) => {
        if (!ensureEnabled() || !chatInput) {
            return;
        }
        const text = chatInput.value.trim();
        if (text.length < 2) {
            showComposeStatus('Type a short draft first.', true);
            return;
        }

        const rewriteUrl = root.dataset.aiRewriteUrl;
        if (!rewriteUrl) {
            showComposeStatus('Rewrite is not available.', true);
            return;
        }

        const labels = {
            refine: 'Refining…',
            paraphrase: 'Paraphrasing…',
            shorten: 'Shortening…',
            clarify: 'Clarifying…',
        };
        const doneLabels = {
            refine: 'Refined. Review before sending.',
            paraphrase: 'Paraphrased. Review before sending.',
            shorten: 'Shortened. Review before sending.',
            clarify: 'Clarified. Review before sending.',
        };

        showComposeStatus(labels[mode] || 'Rewriting…');
        setBusy(root, true);
        try {
            const payload = await postJson(rewriteUrl, { text, mode });
            composeUndoText = chatInput.value;
            chatInput.value = payload.text || text;
            chatInput.dispatchEvent(new Event('input', { bubbles: true }));
            syncComposeUndo();
            showComposeStatus(doneLabels[mode] || 'Updated. Review before sending.');
            chatInput.focus();
        } catch (error) {
            showComposeStatus(error.message || 'AI request failed.', true);
        } finally {
            setBusy(root, false);
            refreshIcons?.();
        }
    };

    root.querySelectorAll('[data-ai-compose-action]').forEach((button) => {
        button.addEventListener('click', () => runComposeRewrite(button.dataset.aiComposeAction || 'refine'));
    });
    composeUndoBtn?.addEventListener('click', () => {
        if (composeUndoText == null || !chatInput) {
            return;
        }
        chatInput.value = composeUndoText;
        composeUndoText = null;
        syncComposeUndo();
        showComposeStatus('Restored previous draft.');
        chatInput.focus();
    });

    chatForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        sendChat(chatInput?.value || '');
    });

    chatInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendChat(chatInput.value);
        }
    });

    root.querySelectorAll('[data-ai-prompt]').forEach((button) => {
        button.addEventListener('click', () => sendChat(button.dataset.aiPrompt || button.textContent));
    });

    const runNoteAction = async (action) => {
        if (!ensureEnabled() || !noteInput) {
            return;
        }
        const text = noteInput.value.trim();
        if (text.length < 2) {
            showStatus('Enter at least a couple of characters first.', true);
            return;
        }

        const rewriteUrl = root.dataset.aiRewriteUrl;
        const url = rewriteUrl
            || (action === 'paraphrase' ? root.dataset.aiParaphraseUrl : root.dataset.aiRefineUrl);
        const busyLabels = {
            refine: 'Refining…',
            paraphrase: 'Paraphrasing…',
            shorten: 'Shortening…',
            clarify: 'Clarifying…',
        };
        const doneLabels = {
            refine: 'Refined. Review before saving.',
            paraphrase: 'Paraphrased. Review before saving.',
            shorten: 'Shortened. Review before saving.',
            clarify: 'Clarified. Review before saving.',
        };
        showStatus(busyLabels[action] || 'Rewriting…');
        setBusy(root, true);
        try {
            const payload = rewriteUrl
                ? await postJson(rewriteUrl, { text, mode: action })
                : await postJson(url, { text });
            noteInput.value = payload.text;
            showStatus(doneLabels[action] || 'Updated. Review before saving.');
        } catch (error) {
            showStatus(error.message || 'AI request failed.', true);
        } finally {
            setBusy(root, false);
        }
    };

    root.querySelector('[data-ai-refine]')?.addEventListener('click', () => runNoteAction('refine'));
    root.querySelector('[data-ai-paraphrase]')?.addEventListener('click', () => runNoteAction('paraphrase'));
    root.querySelectorAll('[data-ai-note-rewrite]').forEach((button) => {
        button.addEventListener('click', () => runNoteAction(button.dataset.aiNoteRewrite || 'refine'));
    });
    root.querySelector('[data-ai-copy-note]')?.addEventListener('click', async () => {
        if (!noteInput?.value) {
            showStatus('Nothing to copy yet.', true);
            return;
        }
        try {
            await navigator.clipboard.writeText(noteInput.value);
            showStatus('Copied to clipboard.');
        } catch {
            noteInput.select();
            showStatus('Select the text and copy manually.', true);
        }
    });

    const runFieldAction = async (button) => {
        if (!ensureEnabled()) {
            return;
        }
        const targetId = button.dataset.aiTarget;
        const action = button.dataset.aiAction;
        const field = targetId
            ? document.getElementById(targetId)
            : button.closest('[data-ai-field-actions]')?.parentElement?.querySelector('[data-ai-assist]');
        if (!field || !action) {
            return;
        }

        const text = String(field.value || '').trim();
        if (text.length < 2) {
            window.alert('Enter some text first.');
            return;
        }

        const url = action === 'paraphrase' ? root.dataset.aiParaphraseUrl : root.dataset.aiRefineUrl;
        const label = button.textContent;
        button.disabled = true;
        button.textContent = action === 'paraphrase' ? 'Paraphrasing…' : 'Refining…';

        try {
            const payload = await postJson(url, { text });
            field.value = payload.text;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.focus();
        } catch (error) {
            window.alert(error.message || 'AI request failed.');
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    };

    document.querySelectorAll('[data-ai-action]').forEach((button) => {
        button.addEventListener('click', () => runFieldAction(button));
    });
}
