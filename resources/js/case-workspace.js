const WIDTH_KEY = 'plantgrid.case.workspace.workflow';
const LIMITS = { min: 280, max: 560, fallback: 380 };
const PRIMARY_MIN = 320;
const STACKED_MQ = '(max-width: 991.98px)';

function readWidth() {
    const raw = Number(localStorage.getItem(WIDTH_KEY));
    if (!Number.isFinite(raw)) {
        return LIMITS.fallback;
    }
    return Math.min(LIMITS.max, Math.max(LIMITS.min, raw));
}

function writeWidth(value) {
    const next = Math.min(LIMITS.max, Math.max(LIMITS.min, Math.round(value)));
    localStorage.setItem(WIDTH_KEY, String(next));
    return next;
}

function maxAllowed(root) {
    const available = root.clientWidth - PRIMARY_MIN - 6;
    return Math.max(LIMITS.min, Math.min(LIMITS.max, available));
}

function applyWidth(root) {
    if (!root || root.hasAttribute('data-case-workspace-single')) {
        return;
    }
    if (window.matchMedia(STACKED_MQ).matches) {
        root.style.removeProperty('--pg-case-workflow');
        return;
    }
    const width = Math.min(readWidth(), maxAllowed(root));
    root.style.setProperty('--pg-case-workflow', `${width}px`);
}

function bindHandle(root, handle) {
    let startX = 0;
    let startWidth = 0;

    const onMove = (event) => {
        const delta = event.clientX - startX;
        const next = Math.min(maxAllowed(root), writeWidth(startWidth - delta));
        root.style.setProperty('--pg-case-workflow', `${next}px`);
    };

    const onUp = () => {
        document.body.classList.remove('is-case-resizing', 'is-rail-resizing');
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
    };

    handle.addEventListener('pointerdown', (event) => {
        if (window.matchMedia(STACKED_MQ).matches) {
            return;
        }
        event.preventDefault();
        startX = event.clientX;
        startWidth = readWidth();
        document.body.classList.add('is-case-resizing', 'is-rail-resizing');
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    });
}

let resizeListenerBound = false;

export function initCaseWorkspace() {
    const root = document.querySelector('[data-case-workspace]');
    if (!root || root.hasAttribute('data-case-workspace-single')) {
        return;
    }

    const handle = root.querySelector('[data-case-resize]');
    if (!handle) {
        return;
    }

    applyWidth(root);

    if (root.dataset.caseWorkspaceReady !== '1') {
        root.dataset.caseWorkspaceReady = '1';
        bindHandle(root, handle);
    }

    if (!resizeListenerBound) {
        resizeListenerBound = true;
        window.addEventListener('resize', () => {
            const current = document.querySelector('[data-case-workspace]');
            if (current) {
                applyWidth(current);
            }
        });
    }
}
