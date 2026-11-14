import { caretRectFromTextarea, placeMentionPanel } from './mention-panel';

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

/** Client-side filter so typing stays responsive even if a slower request finishes late. */
export function filterMentionItems(items, query) {
    const parts = String(query || '')
        .trim()
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean);
    if (!parts.length) {
        return items;
    }
    return items.filter((item) => {
        const hay = [
            item.label,
            item.meta,
            item.insert,
            item.token,
            item.type,
        ].filter(Boolean).join(' ').toLowerCase();
        return parts.every((part) => hay.includes(part));
    });
}

function parseMentionBeforeCaret(before) {
    // @ allows spaces so "@Faith Cher" keeps filtering by full name.
    // # stays token-like for case/asset/device/location tags.
    const atMatch = before.match(/(^|[\s(])@([a-zA-Z0-9._\s-]*)$/);
    if (atMatch) {
        return { trigger: '@', query: atMatch[2] || '', matchLen: 1 + (atMatch[2] || '').length };
    }
    const hashMatch = before.match(/(^|[\s(])#([a-zA-Z0-9._-]*)$/);
    if (hashMatch) {
        return { trigger: '#', query: hashMatch[2] || '', matchLen: 1 + (hashMatch[2] || '').length };
    }
    return null;
}

export function initMentions(root = document) {
    const fields = root.querySelectorAll('[data-mentions]');
    if (!fields.length) {
        return;
    }

    const suggestUrl = document.body.dataset.mentionsUrl || '/PlantGrid/mentions/suggest';

    fields.forEach((textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.mentionsReady === '1') {
            return;
        }
        textarea.dataset.mentionsReady = '1';
        const host = document.createElement('div');
        host.className = 'pg-mention-host';
        textarea.parentNode.insertBefore(host, textarea);
        host.appendChild(textarea);

        const panel = document.createElement('div');
        panel.className = 'pg-mention-panel';
        panel.hidden = true;
        document.body.appendChild(panel);

        let timer = null;
        let activeIndex = -1;
        let items = [];
        let mentionStart = -1;
        let trigger = '@';
        let requestId = 0;
        let latestQuery = '';

        const close = () => {
            panel.hidden = true;
            panel.innerHTML = '';
            items = [];
            activeIndex = -1;
            mentionStart = -1;
            latestQuery = '';
        };

        const syncActive = () => {
            panel.querySelectorAll('[data-mention-item]').forEach((node, index) => {
                node.classList.toggle('is-active', index === activeIndex);
            });
        };

        const insertItem = (item) => {
            if (mentionStart < 0 || !item) {
                return;
            }
            const value = textarea.value;
            const caret = textarea.selectionStart;
            const before = value.slice(0, mentionStart);
            const after = value.slice(caret);
            const inserted = String(item.token || item.insert || '').trim();
            const token = `${inserted} `;
            textarea.value = `${before}${token}${after}`;
            const next = before.length + token.length;
            textarea.focus();
            textarea.setSelectionRange(next, next);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            close();
        };

        const render = (list) => {
            items = list;
            if (!items.length) {
                panel.innerHTML = `<div class="pg-mention-empty">No matches</div>`;
            } else {
                panel.innerHTML = items.map((item, index) => `
                    <button type="button" class="pg-mention-item" data-mention-item data-index="${index}">
                        <span class="pg-mention-copy">
                            <strong>${escapeHtml(item.label)}</strong>
                            ${item.meta ? `<span class="pg-mention-meta">${escapeHtml(item.meta)}</span>` : ''}
                        </span>
                    </button>
                `).join('');
            }

            const caret = textarea.selectionStart ?? 0;
            placeMentionPanel(panel, caretRectFromTextarea(textarea, caret));
            activeIndex = items.length ? 0 : -1;
            syncActive();

            panel.querySelectorAll('[data-mention-item]').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    insertItem(items[Number(button.dataset.index)]);
                });
            });
        };

        const fetchItems = async (query, currentTrigger) => {
            const id = ++requestId;
            latestQuery = query;
            try {
                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('trigger', currentTrigger === '#' ? '#' : '@');
                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (id !== requestId) {
                    return;
                }
                if (!payload?.ok) {
                    close();
                    return;
                }
                // Re-apply client filter against the latest typed query.
                render(filterMentionItems(payload.items || [], latestQuery));
            } catch {
                if (id === requestId) {
                    close();
                }
            }
        };

        const detectMention = () => {
            const caret = textarea.selectionStart;
            const before = textarea.value.slice(0, caret);
            const parsed = parseMentionBeforeCaret(before);
            if (!parsed) {
                close();
                return;
            }

            trigger = parsed.trigger;
            const query = parsed.query;
            mentionStart = caret - parsed.matchLen;
            latestQuery = query.trim();

            // Instant local filter of whatever is already on screen while the request runs.
            if (!panel.hidden && items.length) {
                render(filterMentionItems(items, latestQuery));
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchItems(latestQuery, trigger), 90);
        };

        textarea.addEventListener('input', detectMention);
        textarea.addEventListener('click', detectMention);
        textarea.addEventListener('keyup', detectMention);
        textarea.addEventListener('scroll', () => {
            if (!panel.hidden) {
                placeMentionPanel(panel, caretRectFromTextarea(textarea, textarea.selectionStart ?? 0));
            }
        });
        textarea.addEventListener('blur', () => window.setTimeout(close, 120));
        textarea.addEventListener('keydown', (event) => {
            if (panel.hidden || !items.length) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                syncActive();
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                syncActive();
                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                insertItem(items[Math.max(0, activeIndex)]);
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            }
        });
    });
}
