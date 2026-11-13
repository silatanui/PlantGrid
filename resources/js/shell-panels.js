const WIDTH_KEY = (side) => `plantgrid.shell.width.${side}`;
const ORDER_KEY = (side) => `plantgrid.shell.order.${side}`;
const COLLAPSE_KEY = (side) => `plantgrid.shell.${side}`;

const WIDTH_LIMITS = {
    left: { min: 180, max: 380, fallback: 232 },
    right: { min: 240, max: 440, fallback: 288 },
};

function readWidth(side) {
    const raw = Number(localStorage.getItem(WIDTH_KEY(side)));
    const limits = WIDTH_LIMITS[side];
    if (!Number.isFinite(raw)) {
        return limits.fallback;
    }
    return Math.min(limits.max, Math.max(limits.min, raw));
}

function writeWidth(side, value) {
    const limits = WIDTH_LIMITS[side];
    const next = Math.min(limits.max, Math.max(limits.min, Math.round(value)));
    localStorage.setItem(WIDTH_KEY(side), String(next));
    return next;
}

function applyRailWidths(shell) {
    if (!shell) {
        return;
    }
    const leftCollapsed = shell.classList.contains('pg-left-collapsed');
    const rightCollapsed = shell.classList.contains('pg-right-collapsed');
    shell.style.setProperty('--pg-left', leftCollapsed ? '0px' : `${readWidth('left')}px`);
    shell.style.setProperty('--pg-right', rightCollapsed ? '0px' : `${readWidth('right')}px`);
}

function initResizeHandles(shell) {
    document.querySelectorAll('[data-rail-resize]').forEach((handle) => {
        const side = handle.dataset.railResize;
        if (!side || !WIDTH_LIMITS[side]) {
            return;
        }

        let startX = 0;
        let startWidth = 0;

        const onMove = (event) => {
            const delta = event.clientX - startX;
            const next = side === 'left' ? startWidth + delta : startWidth - delta;
            const width = writeWidth(side, next);
            if (side === 'left' && !shell.classList.contains('pg-left-collapsed')) {
                shell.style.setProperty('--pg-left', `${width}px`);
            }
            if (side === 'right' && !shell.classList.contains('pg-right-collapsed')) {
                shell.style.setProperty('--pg-right', `${width}px`);
            }
        };

        const onUp = () => {
            document.body.classList.remove('is-rail-resizing');
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };

        handle.addEventListener('pointerdown', (event) => {
            if (shell.classList.contains(`pg-${side}-collapsed`)) {
                return;
            }
            event.preventDefault();
            startX = event.clientX;
            startWidth = readWidth(side);
            document.body.classList.add('is-rail-resizing');
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
        });
    });
}

function readOrder(side, defaults) {
    try {
        const parsed = JSON.parse(localStorage.getItem(ORDER_KEY(side)) || 'null');
        if (!Array.isArray(parsed) || parsed.length === 0) {
            return defaults;
        }
        const known = new Set(defaults);
        const ordered = parsed.filter((id) => known.has(id));
        defaults.forEach((id) => {
            if (!ordered.includes(id)) {
                ordered.push(id);
            }
        });
        return ordered;
    } catch {
        return defaults;
    }
}

function applySectionOrder(container, side) {
    const sections = [...container.querySelectorAll(':scope > [data-rail-section]')];
    if (!sections.length) {
        return;
    }
    const defaults = sections.map((node) => node.dataset.railSection);
    const order = readOrder(side, defaults);
    order.forEach((id) => {
        const node = sections.find((section) => section.dataset.railSection === id);
        if (node) {
            container.appendChild(node);
        }
    });
}

function initSectionReorder(container, side) {
    if (!container) {
        return;
    }

    applySectionOrder(container, side);

    let dragging = null;

    container.querySelectorAll(':scope > [data-rail-section]').forEach((section) => {
        const handle = section.querySelector('[data-rail-handle]') || section.querySelector('.pg-rail-label');
        if (!handle) {
            return;
        }
        handle.setAttribute('draggable', 'true');
        handle.title = handle.title || 'Drag to reorder';

        handle.addEventListener('dragstart', (event) => {
            dragging = section;
            section.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', section.dataset.railSection || '');
        });

        handle.addEventListener('dragend', () => {
            section.classList.remove('is-dragging');
            container.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
            dragging = null;
            const order = [...container.querySelectorAll(':scope > [data-rail-section]')]
                .map((node) => node.dataset.railSection)
                .filter(Boolean);
            localStorage.setItem(ORDER_KEY(side), JSON.stringify(order));
        });

        section.addEventListener('dragover', (event) => {
            if (!dragging || dragging === section) {
                return;
            }
            event.preventDefault();
            section.classList.add('is-drop-target');
            const bounds = section.getBoundingClientRect();
            const before = event.clientY < bounds.top + bounds.height / 2;
            if (before) {
                container.insertBefore(dragging, section);
            } else {
                container.insertBefore(dragging, section.nextSibling);
            }
        });

        section.addEventListener('dragleave', () => {
            section.classList.remove('is-drop-target');
        });

        section.addEventListener('drop', (event) => {
            event.preventDefault();
            section.classList.remove('is-drop-target');
        });
    });
}

export function initShellPanels(refreshIcons = () => {}) {
    const shell = document.body.classList.contains('pg-shell') ? document.body : null;
    if (!shell) {
        return {
            applyRailWidths: () => {},
        };
    }

    applyRailWidths(shell);
    initResizeHandles(shell);
    initSectionReorder(document.querySelector('[data-rail-sections="left"]'), 'left');
    initSectionReorder(document.querySelector('[data-rail-sections="right"]'), 'right');

    return {
        applyRailWidths: () => applyRailWidths(shell),
        isCollapsedStored: (side) => localStorage.getItem(COLLAPSE_KEY(side)) === '1',
    };
}

export { applyRailWidths, readWidth, COLLAPSE_KEY };
