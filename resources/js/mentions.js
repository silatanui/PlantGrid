function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function caretCoordinates(textarea, position) {
    const div = document.createElement('div');
    const style = window.getComputedStyle(textarea);
    const properties = [
        'boxSizing', 'width', 'height', 'overflowX', 'overflowY',
        'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'fontStyle', 'fontVariant', 'fontWeight', 'fontStretch', 'fontSize', 'fontSizeAdjust',
        'lineHeight', 'fontFamily', 'textAlign', 'textTransform', 'textIndent',
        'textDecoration', 'letterSpacing', 'wordSpacing', 'tabSize', 'MozTabSize',
    ];

    div.style.position = 'absolute';
    div.style.visibility = 'hidden';
    div.style.whiteSpace = 'pre-wrap';
    div.style.wordWrap = 'break-word';
    properties.forEach((prop) => {
        div.style[prop] = style[prop];
    });

    div.textContent = textarea.value.slice(0, position);
    const marker = document.createElement('span');
    marker.textContent = textarea.value.slice(position) || '.';
    div.appendChild(marker);
    document.body.appendChild(div);

    const coordinates = {
        top: marker.offsetTop - textarea.scrollTop + Number.parseFloat(style.borderTopWidth),
        left: marker.offsetLeft - textarea.scrollLeft + Number.parseFloat(style.borderLeftWidth),
    };
    document.body.removeChild(div);
    return coordinates;
}

export function initMentions(root = document) {
    const fields = root.querySelectorAll('[data-mentions]');
    if (!fields.length) {
        return;
    }

    const suggestUrl = document.body.dataset.mentionsUrl || '/PlantGrid/mentions/suggest';

    fields.forEach((textarea) => {
        if (textarea.closest('.pg-mention-host') || textarea.dataset.mentionsReady === '1') {
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
        host.appendChild(panel);

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
                panel.hidden = false;
                return;
            }

            panel.innerHTML = items.map((item, index) => `
                <button type="button" class="pg-mention-item" data-mention-item data-index="${index}">
                    <span class="pg-mention-copy">
                        <strong>${escapeHtml(item.label)}</strong>
                    </span>
                </button>
            `).join('');
            panel.hidden = false;
            activeIndex = 0;
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
            const coords = caretCoordinates(textarea, caret);
            panel.style.top = `${Math.min(coords.top + 22, textarea.offsetHeight - 8)}px`;
            panel.style.left = `${Math.min(coords.left, Math.max(8, textarea.clientWidth - 280))}px`;

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchItems(query, trigger), 120);
        };

        textarea.addEventListener('input', detectMention);
        textarea.addEventListener('click', detectMention);
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
