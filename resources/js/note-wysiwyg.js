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
    const atMatch = text.match(/(^|[\s(])@([a-zA-Z0-9._\s-]*)$/);
    const hashMatch = text.match(/(^|[\s(])#([a-zA-Z0-9._-]*)$/);
    const query = atMatch?.[2] ?? hashMatch?.[2];
    if (query !== undefined) {
        const deleteCount = 1 + query.length;
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
    let catalog = [];
    let items = [];
    let activeIndex = -1;
    let mentionActive = false;
    let requestId = 0;
    let latestQuery = '';
    let lastAnchor = null;
    let detecting = false;

    const filterItems = (list, query) => {
        const parts = String(query || '')
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(Boolean);
        if (!parts.length) {
            return list;
        }
        return list.filter((item) => {
            const hay = [item.label, item.meta, item.insert, item.token, item.type]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return parts.every((part) => hay.includes(part));
        });
    };

    const parseMention = (text) => {
        const atMatch = text.match(/(^|[\s(])@([a-zA-Z0-9._\s-]*)$/);
        if (atMatch) {
            return { trigger: '@', query: (atMatch[2] || '').trim() };
        }
        const hashMatch = text.match(/(^|[\s(])#([a-zA-Z0-9._-]*)$/);
        if (hashMatch) {
            return { trigger: '#', query: (hashMatch[2] || '').trim() };
        }
        return null;
    };

    const closePanel = () => {
        panel.hidden = true;
        panel.innerHTML = '';
        catalog = [];
        items = [];
        activeIndex = -1;
        mentionActive = false;
        latestQuery = '';
        lastAnchor = null;
    };

    const syncActive = () => {
        panel.querySelectorAll('[data-mention-item]').forEach((node, index) => {
            node.classList.toggle('is-active', index === activeIndex);
        });
    };

    const renderPanel = (list, { reposition = true } = {}) => {
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

        if (reposition || !lastAnchor) {
            lastAnchor = caretRectFromContentEditable(surface) || surface.getBoundingClientRect();
        }
        placeMentionPanel(panel, lastAnchor || surface.getBoundingClientRect());
        mentionActive = true;
        if (!items.length) {
            activeIndex = -1;
        } else if (activeIndex < 0 || activeIndex >= items.length) {
            activeIndex = 0;
        }
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
        const id = ++requestId;
        latestQuery = query;
        try {
            const url = new URL(suggestUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('trigger', trigger === '#' ? '#' : '@');
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (id !== requestId) {
                return;
            }
            if (!payload?.ok) {
                if (!mentionActive || !items.length) {
                    closePanel();
                }
                return;
            }
            catalog = payload.items || [];
            renderPanel(filterItems(catalog, latestQuery), { reposition: !mentionActive });
        } catch {
            if (id === requestId && (!mentionActive || !items.length)) {
                closePanel();
            }
        }
    };

    const detectMention = () => {
        if (detecting) {
            return;
        }
        detecting = true;
        try {
            const { text } = textBeforeCaret(surface);
            const parsed = parseMention(text);
            if (!parsed) {
                closePanel();
                return;
            }

            latestQuery = parsed.query;

            // Keep the menu open and filter the last catalog while @/# is still active.
            if (catalog.length) {
                renderPanel(filterItems(catalog, latestQuery), { reposition: !mentionActive });
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchItems(parsed.query, parsed.trigger), 120);
        } finally {
            detecting = false;
        }
    };

    // Keep focus in the editor when clicking suggestions.
    panel.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });

    surface.addEventListener('input', detectMention);
    surface.addEventListener('keyup', (event) => {
        // Arrow/Enter handled below; still refresh after normal typing.
        if (['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes(event.key)) {
            return;
        }
        detectMention();
    });
    surface.addEventListener('click', detectMention);
    surface.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (panel.contains(document.activeElement) || panel.matches(':hover')) {
                return;
            }
            const { text } = textBeforeCaret(surface);
            if (!parseMention(text)) {
                closePanel();
            }
        }, 180);
    });
    surface.addEventListener('keydown', (event) => {
        if (!mentionActive || panel.hidden) {
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
        setHtml: (nextHtml) => {
            surface.innerHTML = nextHtml || '';
            surface.querySelectorAll('a.pg-mention, span.pg-mention').forEach((link) => {
                link.contentEditable = 'false';
                link.addEventListener('click', (event) => event.preventDefault());
            });
            surface.dispatchEvent(new Event('input', { bubbles: true }));
        },
        setPlain: (text) => {
            surface.textContent = text || '';
            surface.dispatchEvent(new Event('input', { bubbles: true }));
        },
        focus: () => surface.focus(),
    };
}

/**
 * Upgrade plain textareas marked data-wysiwyg into contenteditable surfaces
 * with live formatting and mention chips (same as note edit).
 */
export function initWysiwygFields(root = document) {
    const scope = root.querySelectorAll ? root : document;
    scope.querySelectorAll('textarea[data-wysiwyg]').forEach((textarea) => {
        if (textarea.dataset.wysiwygBound === '1') {
            return;
        }
        const id = textarea.id;
        if (!id) {
            return;
        }
        const shell = scope.querySelector(`[data-wysiwyg-for="${CSS.escape(id)}"]`)
            || document.querySelector(`[data-wysiwyg-for="${CSS.escape(id)}"]`);
        if (!shell) {
            return;
        }

        const seed = document.querySelector(`template[data-wysiwyg-seed="${CSS.escape(id)}"]`);
        const html = seed ? seed.innerHTML.trim() : '';
        const suggestUrl = document.body.dataset.mentionsUrl || '';
        const api = mountNoteWysiwyg(shell, { html, suggestUrl });
        textarea.dataset.wysiwygBound = '1';
        textarea._pgWysiwyg = api;

        let ignoreValueMirror = false;
        const proto = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');

        const writeTextarea = (markup) => {
            ignoreValueMirror = true;
            if (proto?.set) {
                proto.set.call(textarea, markup);
            } else {
                textarea.value = markup;
            }
            ignoreValueMirror = false;
        };

        const syncFromSurface = () => {
            writeTextarea(api.getMarkup() || '');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        api.surface?.addEventListener('input', syncFromSurface);
        syncFromSurface();

        textarea.closest('form')?.addEventListener('submit', syncFromSurface);

        // AI / triage helpers that assign textarea.value update the surface too.
        if (proto?.get && proto?.set) {
            Object.defineProperty(textarea, 'value', {
                configurable: true,
                get() {
                    return proto.get.call(this);
                },
                set(next) {
                    proto.set.call(this, next);
                    if (!ignoreValueMirror) {
                        api.setPlain?.(String(next ?? ''));
                    }
                },
            });
        }
    });
}

export function getWysiwygApi(textareaOrId) {
    const el = typeof textareaOrId === 'string'
        ? document.getElementById(textareaOrId)
        : textareaOrId;
    return el?._pgWysiwyg || null;
}
