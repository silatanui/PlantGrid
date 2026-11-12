function wrapSelection(textarea, before, after = before, placeholder = 'text') {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? 0;
    const value = textarea.value;
    const selected = value.slice(start, end) || placeholder;
    const next = `${value.slice(0, start)}${before}${selected}${after}${value.slice(end)}`;
    textarea.value = next;
    textarea.focus();
    const selectStart = start + before.length;
    textarea.setSelectionRange(selectStart, selectStart + selected.length);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function prefixLines(textarea, prefix) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? 0;
    const value = textarea.value;
    const blockStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
    const blockEnd = value.indexOf('\n', end);
    const sliceEnd = blockEnd === -1 ? value.length : blockEnd;
    const block = value.slice(blockStart, sliceEnd) || 'Item';
    const formatted = block
        .split('\n')
        .map((line) => {
            const trimmed = line.trim();
            if (!trimmed) {
                return line;
            }
            return trimmed.startsWith(prefix) ? line : `${prefix}${trimmed}`;
        })
        .join('\n');

    textarea.value = `${value.slice(0, blockStart)}${formatted}${value.slice(sliceEnd)}`;
    textarea.focus();
    textarea.setSelectionRange(blockStart, blockStart + formatted.length);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function toolbarHtml() {
    return `
        <div class="pg-format-toolbar" role="toolbar" aria-label="Text formatting">
            <button type="button" class="pg-format-btn" data-format="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>
            <button type="button" class="pg-format-btn" data-format="italic" title="Italic" aria-label="Italic"><em>I</em></button>
            <button type="button" class="pg-format-btn" data-format="underline" title="Underline" aria-label="Underline"><span style="text-decoration:underline">U</span></button>
            <button type="button" class="pg-format-btn" data-format="strike" title="Strikethrough" aria-label="Strikethrough"><s>S</s></button>
            <button type="button" class="pg-format-btn" data-format="code" title="Code" aria-label="Code"><span class="pg-mono">&lt;/&gt;</span></button>
            <button type="button" class="pg-format-btn" data-format="bullet" title="Bullet list" aria-label="Bullet list">• List</button>
        </div>
    `;
}

function bindToolbar(toolbar, textarea) {
    toolbar.querySelectorAll('[data-format]').forEach((button) => {
        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const action = button.dataset.format;
            if (action === 'bold') {
                wrapSelection(textarea, '**', '**', 'bold text');
                return;
            }
            if (action === 'italic') {
                wrapSelection(textarea, '*', '*', 'italic text');
                return;
            }
            if (action === 'underline') {
                wrapSelection(textarea, '__', '__', 'underlined text');
                return;
            }
            if (action === 'strike') {
                wrapSelection(textarea, '~~', '~~', 'removed text');
                return;
            }
            if (action === 'code') {
                wrapSelection(textarea, '`', '`', 'code');
                return;
            }
            if (action === 'bullet') {
                prefixLines(textarea, '- ');
            }
        });
    });
}

export function attachFormatToolbar(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.formatToolbarReady === '1') {
        return;
    }

    textarea.dataset.formatToolbarReady = '1';
    const host = document.createElement('div');
    host.className = 'pg-format-host';
    host.innerHTML = toolbarHtml();
    textarea.parentNode?.insertBefore(host, textarea);
    bindToolbar(host, textarea);
}

export function initFormatToolbars(root = document) {
    root.querySelectorAll('textarea[data-format-toolbar]').forEach((textarea) => {
        attachFormatToolbar(textarea);
    });
}
