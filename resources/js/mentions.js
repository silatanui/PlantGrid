import { caretRectFromTextarea, placeMentionPanel } from './mention-panel';

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
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

        const close = () => {
            panel.hidden = true;
            panel.innerHTML = '';
            items = [];
            activeIndex = -1;
            mentionStart = -1;
        };

        const syncActive = () => {
            panel.querySelectorAll('[data-mention-item]').forEach((node, index) => {
                node.classList.toggle('is-active', index === activeIndex);
            });
        };

        const insertItem = (item) => {
            if (mentionStart < 0) {
                return;
            }
            const value = textarea.value;
            const caret = textarea.selectionStart;
            const before = value.slice(0, mentionStart);
            const after = value.slice(caret);
            const token = `${item.insert} `;
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
                if (!payload?.ok) {
                    close();
                    return;
                }
                render(payload.items || []);
            } catch {
                close();
            }
        };

        const detectMention = () => {
            const caret = textarea.selectionStart;
            const before = textarea.value.slice(0, caret);
            const match = before.match(/(^|[\s(])([@#])([a-zA-Z0-9._-]*)$/);
            if (!match) {
                close();
                return;
            }

            trigger = match[2];
            mentionStart = caret - match[2].length - match[3].length;
            const query = match[3];

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchItems(query, trigger), 120);
        };

        textarea.addEventListener('input', detectMention);
        textarea.addEventListener('click', detectMention);
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
