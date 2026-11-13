/**
 * Place a mention autocomplete panel near a caret/anchor without covering typed text.
 * Prefers below the caret; flips above when there is not enough room.
 */
export function placeMentionPanel(panel, anchorRect, { preferBelow = true, gap = 6, maxWidth = 320 } = {}) {
    if (!panel || !anchorRect) {
        return;
    }

    panel.hidden = false;
    panel.style.position = 'fixed';
    panel.style.zIndex = '80';
    panel.style.width = `${Math.min(maxWidth, window.innerWidth - 24)}px`;
    panel.style.maxHeight = '';

    const panelHeight = Math.min(panel.scrollHeight || 180, Math.floor(window.innerHeight * 0.32), 200);
    panel.style.maxHeight = `${panelHeight}px`;

    const spaceBelow = window.innerHeight - anchorRect.bottom - 12;
    const spaceAbove = anchorRect.top - 12;
    const placeBelow = preferBelow
        ? spaceBelow >= Math.min(140, panelHeight) || spaceBelow >= spaceAbove
        : spaceAbove < Math.min(140, panelHeight) && spaceBelow > spaceAbove;

    let top;
    if (placeBelow) {
        top = anchorRect.bottom + gap;
        if (top + panelHeight > window.innerHeight - 8) {
            top = Math.max(8, window.innerHeight - panelHeight - 8);
        }
    } else {
        top = anchorRect.top - gap - panelHeight;
        if (top < 8) {
            top = 8;
        }
    }

    let left = anchorRect.left;
    const width = Math.min(maxWidth, window.innerWidth - 24);
    if (left + width > window.innerWidth - 12) {
        left = Math.max(12, window.innerWidth - width - 12);
    }
    if (left < 12) {
        left = 12;
    }

    panel.style.top = `${Math.round(top)}px`;
    panel.style.left = `${Math.round(left)}px`;
}

export function caretRectFromTextarea(textarea, position) {
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

    const rect = textarea.getBoundingClientRect();
    div.style.position = 'fixed';
    div.style.left = `${rect.left}px`;
    div.style.top = `${rect.top}px`;
    div.style.visibility = 'hidden';
    div.style.whiteSpace = 'pre-wrap';
    div.style.wordWrap = 'break-word';
    div.style.overflow = 'hidden';
    properties.forEach((prop) => {
        div.style[prop] = style[prop];
    });
    div.style.height = `${textarea.clientHeight}px`;
    div.style.width = `${textarea.clientWidth}px`;

    div.textContent = textarea.value.slice(0, position);
    const marker = document.createElement('span');
    marker.textContent = '|';
    div.appendChild(marker);
    document.body.appendChild(div);
    div.scrollTop = textarea.scrollTop;

    const markerRect = marker.getBoundingClientRect();
    const lineHeight = Number.parseFloat(style.lineHeight) || Number.parseFloat(style.fontSize) * 1.4 || 18;
    document.body.removeChild(div);

    return {
        left: markerRect.left,
        top: markerRect.top,
        bottom: markerRect.top + lineHeight,
        right: markerRect.left + 4,
        width: 4,
        height: lineHeight,
    };
}

export function caretRectFromContentEditable() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return null;
    }
    const range = selection.getRangeAt(0).cloneRange();
    if (range.collapsed) {
        const probe = document.createElement('span');
        probe.textContent = '\u200b';
        range.insertNode(probe);
        const rect = probe.getBoundingClientRect();
        probe.parentNode?.removeChild(probe);
        selection.removeAllRanges();
        selection.addRange(range);
        if (rect.width || rect.height || rect.top || rect.left) {
            return rect;
        }
    }
    const rects = range.getClientRects();
    return rects.length ? rects[rects.length - 1] : range.getBoundingClientRect();
}
