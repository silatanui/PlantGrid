import { caretRectFromContentEditable, placeMentionPanel } from './mention-panel';

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function wrapInline(markup, open, close) {
    return `${open}${markup}${close}`;
}

function serializeNode(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.nodeValue || '';
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return '';
    }

    const el = node;
    const tag = el.tagName.toLowerCase();

    if (tag === 'br') {
        return '\n';
    }

    if (el.matches?.('a.pg-mention, span.pg-mention') || el.dataset?.pgToken) {
        return el.dataset.pgToken || el.textContent || '';
    }

    if (tag === 'script' || tag === 'style') {
        return '';
    }

    let inner = '';
    el.childNodes.forEach((child) => {
        inner += serializeNode(child);
    });

    if (el.classList?.contains('pg-note-bullet')) {
        const text = inner.replace(/^•\s*/, '').trim();
        return `- ${text}`;
    }

    if (tag === 'strong' || tag === 'b') {
        return wrapInline(inner, '**', '**');
    }
    if (tag === 'em' || tag === 'i') {
        return wrapInline(inner, '*', '*');
    }
    if (tag === 'u') {
        return wrapInline(inner, '__', '__');
    }
    if (tag === 's' || tag === 'strike' || tag === 'del') {
        return wrapInline(inner, '~~', '~~');
    }
    if (tag === 'code') {
        return wrapInline(inner, '`', '`');
    }
    if (tag === 'div' || tag === 'p' || tag === 'li') {
        return `${inner}\n`;
    }
    if (tag === 'ul' || tag === 'ol') {
        return `${inner}${inner.endsWith('\n') ? '' : '\n'}`;
    }

    return inner;
}

export function htmlToMarkup(root) {
    if (!root) {
        return '';
    }
    let out = '';
    root.childNodes.forEach((child) => {
        out += serializeNode(child);
    });
    out = out
        .replace(/\u00a0/g, ' ')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

    // Fallback if the browser produced markup we could not serialize cleanly.
    if (out === '' && (root.textContent || '').trim() !== '') {
        return String(root.textContent || '')
            .replace(/\u00a0/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    return out;
}

function toolbarHtml() {
    return `
        <div class="pg-format-toolbar" role="toolbar" aria-label="Text formatting">
            <button type="button" class="pg-format-btn" data-wysiwyg-format="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>
            <button type="button" class="pg-format-btn" data-wysiwyg-format="italic" title="Italic" aria-label="Italic"><em>I</em></button>
            <button type="button" class="pg-format-btn" data-wysiwyg-format="underline" title="Underline" aria-label="Underline"><span style="text-decoration:underline">U</span></button>
            <button type="button" class="pg-format-btn" data-wysiwyg-format="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><s>S</s></button>
            <button type="button" class="pg-format-btn" data-wysiwyg-format="insertUnorderedList" title="Bullet list" aria-label="Bullet list">• List</button>
        </div>
    `;
}

function placeCaretAtEnd(el) {
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
}

function textBeforeCaret(editor) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return { text: '', range: null };
    }
    const range = selection.getRangeAt(0);
    if (!editor.contains(range.startContainer)) {
        return { text: '', range: null };
    }
    const pre = range.cloneRange();
    pre.selectNodeContents(editor);
    pre.setEnd(range.startContainer, range.startOffset);
    return { text: pre.toString(), range };
}

function insertChip(editor, item) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return;
    }
    const range = selection.getRangeAt(0);

    const { text } = textBeforeCaret(editor);
    const match = text.match(/(^|[\s(])([@#])([a-zA-Z0-9._-]*)$/);
    if (match) {
        const deleteCount = match[2].length + match[3].length;
        for (let i = 0; i < deleteCount; i += 1) {
            document.execCommand('delete', false);
        }
    }

    const token = item.token || item.insert;
    const chipType = item.type || 'case';
    const useSpan = chipType === 'everyone' || chipType === 'department' || !item.url;
    const chip = document.createElement(useSpan ? 'span' : 'a');
    chip.className = `pg-mention pg-mention-${chipType}`;
    if (!useSpan) {
        chip.href = item.url;
    }
    chip.dataset.pgToken = token;
    chip.contentEditable = 'false';
    if (chipType === 'people') {
        chip.textContent = `@${item.label}`;
    } else if (chipType === 'everyone') {
        chip.textContent = '@everyone';
    } else if (chipType === 'department') {
        chip.textContent = item.insert || `@${item.label}`;
    } else if (chipType === 'location') {
        chip.textContent = item.meta ? `${item.meta} (${item.label})` : item.label;
    } else {
        chip.textContent = item.label;
    }

    const liveRange = window.getSelection()?.getRangeAt(0);
    if (!liveRange) {
        return;
    }
    liveRange.deleteContents();
    liveRange.insertNode(document.createTextNode(' '));
    liveRange.insertNode(chip);
    liveRange.setStartAfter(chip);
    liveRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(liveRange);
    // trailing space after chip
    document.execCommand('insertText', false, ' ');
    editor.dispatchEvent(new Event('input', { bubbles: true }));
}

export function mountNoteWysiwyg(host, { html = '', suggestUrl = '' } = {}) {
    host.innerHTML = `
        <div class="pg-format-host">${toolbarHtml()}</div>
        <div
            class="pg-note-wysiwyg pg-rich-note"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            data-note-wysiwyg
            data-mentions-wysiwyg
        >${html}</div>
    `;

    const surface = host.querySelector('[data-note-wysiwyg]');
    const toolbar = host.querySelector('.pg-format-toolbar');
    if (!surface) {
        return { getMarkup: () => '', surface: null };
    }

    surface.querySelectorAll('a.pg-mention, span.pg-mention').forEach((link) => {
        link.contentEditable = 'false';
        link.addEventListener('click', (event) => event.preventDefault());
    });

    toolbar?.querySelectorAll('[data-wysiwyg-format]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', (event) => {
            event.preventDefault();
            surface.focus();
            document.execCommand(button.dataset.wysiwygFormat, false);
            surface.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // Mentions autocomplete for contenteditable — fixed near caret, not over the note.
    const panel = document.createElement('div');
    panel.className = 'pg-mention-panel';
    panel.hidden = true;
    document.body.appendChild(panel);

    let timer = null;
    let items = [];
    let activeIndex = -1;
    let mentionActive = false;

    const closePanel = () => {
        panel.hidden = true;
        panel.innerHTML = '';
        items = [];
        activeIndex = -1;
        mentionActive = false;
    };

    const syncActive = () => {
        panel.querySelectorAll('[data-mention-item]').forEach((node, index) => {
            node.classList.toggle('is-active', index === activeIndex);
        });
    };

    const renderPanel = (list) => {
        items = list;
        if (!items.length) {
            panel.innerHTML = '<div class="pg-mention-empty">No matches</div>';
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
        const anchor = caretRectFromContentEditable() || surface.getBoundingClientRect();
        placeMentionPanel(panel, anchor);
        mentionActive = true;
        activeIndex = items.length ? 0 : -1;
        syncActive();
        panel.querySelectorAll('[data-mention-item]').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                insertChip(surface, items[Number(button.dataset.index)]);
                closePanel();
            });
        });
    };

    const fetchItems = async (query, trigger) => {
        if (!suggestUrl) {
            return;
        }
        try {
            const url = new URL(suggestUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('trigger', trigger === '#' ? '#' : '@');
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!payload?.ok) {
                closePanel();
                return;
            }
            renderPanel(payload.items || []);
        } catch {
            closePanel();
        }
    };

    const detectMention = () => {
        const { text } = textBeforeCaret(surface);
        const match = text.match(/(^|[\s(])([@#])([a-zA-Z0-9._-]*)$/);
        if (!match) {
            closePanel();
            return;
        }
        const trigger = match[2];
        const query = match[3];
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fetchItems(query, trigger), 120);
    };

    surface.addEventListener('input', detectMention);
    surface.addEventListener('keyup', detectMention);
    surface.addEventListener('click', detectMention);
    surface.addEventListener('blur', () => window.setTimeout(closePanel, 150));
    surface.addEventListener('keydown', (event) => {
        if (!mentionActive || panel.hidden || !items.length) {
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
            insertChip(surface, items[Math.max(0, activeIndex)]);
            closePanel();
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closePanel();
        }
    });

    placeCaretAtEnd(surface);

    return {
        surface,
        getMarkup: () => htmlToMarkup(surface),
        focus: () => surface.focus(),
    };
}
