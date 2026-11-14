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
        let catalog = []; // last full server payload for this trigger session
        let items = []; // currently displayed (filtered) rows
        let mentionStart = -1;
        let trigger = '@';
        let requestId = 0;
        let latestQuery = '';
        let open = false;
        let lastAnchor = null;

        const close = () => {
            open = false;
            panel.hidden = true;
            panel.innerHTML = '';
            catalog = [];
            items = [];
            activeIndex = -1;
            mentionStart = -1;
            latestQuery = '';
            lastAnchor = null;
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

        const render = (list, { reposition = true } = {}) => {
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

            open = true;
            if (reposition || !lastAnchor) {
                const caret = textarea.selectionStart ?? 0;
                lastAnchor = caretRectFromTextarea(textarea, caret);
            }
            placeMentionPanel(panel, lastAnchor || textarea.getBoundingClientRect());
            activeIndex = items.length ? Math.min(Math.max(activeIndex, 0), items.length - 1) : -1;
            if (activeIndex < 0 && items.length) {
                activeIndex = 0;
            }
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
                    // Keep the panel if we already have matches for this query.
                    if (!open || !items.length) {
                        close();
                    }
                    return;
                }
                catalog = payload.items || [];
                render(filterMentionItems(catalog, latestQuery), { reposition: !open });
            } catch {
                if (id === requestId && (!open || !items.length)) {
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

            // Keep filtering the last catalog while the query is still an active @/# mention.
            if (catalog.length) {
                render(filterMentionItems(catalog, latestQuery), { reposition: !open });
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchItems(latestQuery, trigger), 120);
        };

        // Prevent blur-close when clicking a suggestion.
        panel.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        textarea.addEventListener('input', detectMention);
        textarea.addEventListener('click', detectMention);
        textarea.addEventListener('scroll', () => {
            if (!open) {
                return;
            }
            const caret = textarea.selectionStart ?? 0;
            lastAnchor = caretRectFromTextarea(textarea, caret);
            placeMentionPanel(panel, lastAnchor);
        });
        textarea.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (panel.contains(document.activeElement) || panel.matches(':hover')) {
                    return;
                }
                // Only dismiss when the caret is no longer in an @/# mention.
                const caret = textarea.selectionStart ?? 0;
                const before = textarea.value.slice(0, caret);
                if (!parseMentionBeforeCaret(before)) {
                    close();
                }
            }, 180);
        });
        textarea.addEventListener('keydown', (event) => {
            if (!open) {
                return;
            }
            if (event.key === 'ArrowDown') {
                if (!items.length) {
                    return;
                }
                event.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                syncActive();
                return;
            }
            if (event.key === 'ArrowUp') {
                if (!items.length) {
                    return;
                }
                event.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                syncActive();
                return;
            }
            if ((event.key === 'Enter' || event.key === 'Tab') && items.length) {
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
