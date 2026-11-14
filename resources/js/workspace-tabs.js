import { softNavigate } from './shell-nav';

let workspaceChromeBound = false;
let syncOverflowActive = null;
let activeCloseTabs = null;
let contextMenuEl = null;
let contextMenuKey = null;

function ensureContextMenu() {
    if (contextMenuEl) {
        return contextMenuEl;
    }
    const menu = document.createElement('div');
    menu.className = 'pg-workspace-context';
    menu.hidden = true;
    menu.setAttribute('role', 'menu');
    menu.innerHTML = `
        <button type="button" role="menuitem" data-tab-action="one">Close</button>
        <button type="button" role="menuitem" data-tab-action="others">Close all but this</button>
        <button type="button" role="menuitem" data-tab-action="all">Close all</button>
    `;
    document.body.appendChild(menu);
    contextMenuEl = menu;
    return menu;
}

function hideContextMenu() {
    if (!contextMenuEl) {
        return;
    }
    contextMenuEl.hidden = true;
    contextMenuKey = null;
}

function placeContextMenu(clientX, clientY) {
    const menu = ensureContextMenu();
    menu.hidden = false;
    const pad = 8;
    const width = menu.offsetWidth || 180;
    const height = menu.offsetHeight || 120;
    const left = Math.min(clientX, window.innerWidth - width - pad);
    const top = Math.min(clientY, window.innerHeight - height - pad);
    menu.style.left = `${Math.max(pad, left)}px`;
    menu.style.top = `${Math.max(pad, top)}px`;
}

export function initWorkspaceTabs(refreshIcons = () => {}) {
    const root = document.querySelector('[data-workspace-tabs]');
    if (!root) {
        syncOverflowActive = null;
        activeCloseTabs = null;
        hideContextMenu();
        return;
    }

    if (root.dataset.tabsReady === '1') {
        syncOverflowActive?.();
        return;
    }
    root.dataset.tabsReady = '1';

    const track = root.querySelector('[data-workspace-tab-track]');
    const more = root.querySelector('[data-workspace-more]');
    const moreButton = root.querySelector('[data-workspace-more-toggle]');
    const moreMenu = root.querySelector('[data-workspace-more-menu]');
    const moreLabel = root.querySelector('[data-workspace-more-label]');
    const reorderUrl = root.dataset.reorderUrl;
    const closeUrl = root.dataset.closeUrl;
    const csrf = root.dataset.csrf
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    if (!track) {
        return;
    }

    let dragKey = null;

    const tabNodes = () => Array.from(track.querySelectorAll('[data-tab-key]'));
    const allTabNodes = () => Array.from(root.querySelectorAll('[data-tab-key]'));

    const updateMoreLabel = (count) => {
        if (!moreLabel || !moreButton) {
            return;
        }
        if (count <= 0) {
            moreLabel.textContent = 'More tabs';
            moreButton.title = 'Show open tabs that do not fit here';
            moreButton.setAttribute('aria-label', 'Show open tabs that do not fit in this bar');
            return;
        }
        moreLabel.textContent = count === 1 ? '1 more tab' : `${count} more tabs`;
        moreButton.title = count === 1
            ? '1 open tab is hidden because it does not fit in this bar'
            : `${count} open tabs are hidden because they do not fit in this bar`;
        moreButton.setAttribute('aria-label', moreButton.title);
    };

    const persistOrder = () => {
        if (!reorderUrl) {
            return;
        }
        const keys = tabNodes().map((tab) => tab.dataset.tabKey).filter(Boolean);
        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ keys }),
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const closeMore = () => {
        if (!more || !moreMenu || !moreButton) {
            return;
        }
        more.hidden = moreMenu.children.length === 0;
        moreMenu.hidden = true;
        moreButton.setAttribute('aria-expanded', 'false');
    };

    const syncOverflow = () => {
        if (!more || !moreMenu || !moreButton) {
            return;
        }

        // Reset: put every tab back into the track before measuring.
        Array.from(moreMenu.querySelectorAll('[data-tab-key]')).forEach((tab) => {
            track.appendChild(tab);
            tab.classList.remove('is-overflow');
            tab.setAttribute('draggable', 'true');
        });
        moreMenu.innerHTML = '';
        more.hidden = true;
        moreMenu.hidden = true;
        moreButton.setAttribute('aria-expanded', 'false');
        updateMoreLabel(0);

        const tabs = tabNodes();
        if (tabs.length === 0) {
            return;
        }

        const available = Math.max(120, root.clientWidth - 52);
        let used = 0;
        const overflow = [];

        tabs.forEach((tab) => {
            const width = Math.ceil(tab.getBoundingClientRect().width) || 160;
            if (overflow.length === 0 && used + width <= available) {
                used += width + 2;
                return;
            }
            overflow.push(tab);
        });

        // Keep the active tab on the strip when possible.
        const active = tabs.find((tab) => tab.classList.contains('active'));
        if (active && overflow.includes(active)) {
            overflow.splice(overflow.indexOf(active), 1);
            const visible = tabs.filter((tab) => !overflow.includes(tab) && tab !== active);
            const swap = visible.at(-1);
            if (swap) {
                overflow.unshift(swap);
            }
            track.appendChild(active);
        }

        if (overflow.length > 0) {
            const hint = document.createElement('p');
            hint.className = 'pg-workspace-more-hint';
            hint.textContent = 'These open records did not fit in the tab bar:';
            moreMenu.appendChild(hint);
        }

        overflow.forEach((tab) => {
            tab.classList.add('is-overflow');
            tab.setAttribute('draggable', 'false');
            const item = document.createElement('div');
            item.className = 'pg-workspace-more-item';
            item.setAttribute('role', 'menuitem');
            item.appendChild(tab);
            moreMenu.appendChild(item);
        });

        more.hidden = overflow.length === 0;
        updateMoreLabel(overflow.length);
        refreshIcons();
    };

    syncOverflowActive = syncOverflow;

    const removeClosedTabs = (closedKeys) => {
        const closed = new Set(closedKeys || []);
        allTabNodes().forEach((tab) => {
            if (closed.has(tab.dataset.tabKey)) {
                tab.closest('.pg-workspace-more-item')?.remove();
                tab.remove();
            }
        });
        if (allTabNodes().length === 0) {
            root.remove();
            document.querySelector('.pg-navrow')?.classList.remove('has-workspace-tabs');
            return;
        }
        syncOverflow();
    };

    const runClose = async ({ mode, key, wasActive }) => {
        if (!closeUrl) {
            return;
        }
        hideContextMenu();
        try {
            const response = await fetch(closeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    mode,
                    key: key || null,
                    was_active: Boolean(wasActive),
                }),
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.message || 'Could not close tab.');
            }
            removeClosedTabs(payload.closed || []);
            if (payload.redirect) {
                await softNavigate(payload.redirect);
            }
        } catch {
            if (mode === 'one' && key) {
                const form = root.querySelector(`[data-tab-key="${CSS.escape(key)}"] .pg-workspace-tab-close`);
                form?.submit();
            }
        }
    };

    const showTabContextMenu = (tab, clientX, clientY) => {
        const key = tab.dataset.tabKey;
        if (!key) {
            return;
        }
        contextMenuKey = key;
        const menu = ensureContextMenu();
        const tabCount = allTabNodes().length;
        menu.querySelector('[data-tab-action="others"]').disabled = tabCount < 2;
        menu.querySelector('[data-tab-action="all"]').disabled = tabCount < 1;
        placeContextMenu(clientX, clientY);
        closeMore();
    };

    track.addEventListener('dragstart', (event) => {
        const tab = event.target.closest('[data-tab-key]');
        if (!tab || event.target.closest('.pg-workspace-tab-close')) {
            return;
        }
        hideContextMenu();
        dragKey = tab.dataset.tabKey;
        tab.classList.add('is-dragging');
        event.dataTransfer?.setData('text/plain', dragKey);
        event.dataTransfer.effectAllowed = 'move';
    });

    track.addEventListener('dragend', (event) => {
        const tab = event.target.closest('[data-tab-key]');
        tab?.classList.remove('is-dragging');
        dragKey = null;
        track.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
        persistOrder();
        syncOverflow();
    });

    track.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('[data-tab-key]');
        if (!target || !dragKey || target.dataset.tabKey === dragKey) {
            return;
        }
        track.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
        target.classList.add('is-drop-target');
        const dragging = track.querySelector(`[data-tab-key="${CSS.escape(dragKey)}"]`);
        if (!dragging || dragging === target) {
            return;
        }
        const tabs = tabNodes();
        const from = tabs.indexOf(dragging);
        const to = tabs.indexOf(target);
        if (from < to) {
            target.after(dragging);
        } else {
            target.before(dragging);
        }
    });

    moreButton?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        hideContextMenu();
        if (moreMenu.children.length === 0) {
            syncOverflow();
        }
        const open = moreMenu.hidden;
        moreMenu.hidden = !open;
        moreButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            moreMenu.scrollTop = 0;
        }
        refreshIcons();
    });

    // AJAX close + soft-switch tabs (no full page reload).
    root.addEventListener('click', (event) => {
        const closeButton = event.target.closest('.pg-workspace-tab-close button');
        if (closeButton) {
            event.preventDefault();
            event.stopPropagation();
            const tab = closeButton.closest('[data-tab-key]');
            void runClose({
                mode: 'one',
                key: tab?.dataset.tabKey,
                wasActive: Boolean(tab?.classList.contains('active')),
            });
            return;
        }

        const tabLink = event.target.closest('[data-tab-key] > a[href], .pg-workspace-more-item [data-tab-key] > a[href]');
        if (!tabLink || !root.contains(tabLink)) {
            return;
        }
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const tab = tabLink.closest('[data-tab-key]');
        if (tab?.classList.contains('active')) {
            event.preventDefault();
            closeMore();
            return;
        }

        event.preventDefault();
        closeMore();
        void softNavigate(tabLink.href);
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('pg-workspace-tab-close') || !root.contains(form)) {
            return;
        }
        event.preventDefault();
        const tab = form.closest('[data-tab-key]');
        void runClose({
            mode: 'one',
            key: tab?.dataset.tabKey,
            wasActive: Boolean(tab?.classList.contains('active') || form.querySelector('[name="was_active"]')),
        });
    });

    root.addEventListener('contextmenu', (event) => {
        const tab = event.target.closest('[data-tab-key]');
        if (!tab || !root.contains(tab)) {
            return;
        }
        event.preventDefault();
        showTabContextMenu(tab, event.clientX, event.clientY);
    });

    if (!workspaceChromeBound) {
        workspaceChromeBound = true;
        document.addEventListener('click', (event) => {
            const currentMore = document.querySelector('[data-workspace-more]');
            if (!currentMore?.contains(event.target)) {
                const menu = document.querySelector('[data-workspace-more-menu]');
                const button = document.querySelector('[data-workspace-more-toggle]');
                if (menu && button && currentMore) {
                    menu.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                    currentMore.hidden = menu.children.length === 0;
                }
            }
            if (!event.target.closest('.pg-workspace-context')) {
                hideContextMenu();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideContextMenu();
            }
        });
        window.addEventListener('blur', hideContextMenu);
        window.addEventListener('resize', () => {
            hideContextMenu();
            window.requestAnimationFrame(() => syncOverflowActive?.());
        });
        document.addEventListener('scroll', hideContextMenu, true);

        ensureContextMenu().addEventListener('click', (event) => {
            const button = event.target.closest('[data-tab-action]');
            if (!button || button.disabled) {
                return;
            }
            const mode = button.dataset.tabAction;
            const key = contextMenuKey;
            const strip = document.querySelector('[data-workspace-tabs]');
            const tab = key
                ? strip?.querySelector(`[data-tab-key="${CSS.escape(key)}"]`)
                : null;
            const wasActive = Boolean(tab?.classList.contains('active'));
            if (typeof activeCloseTabs === 'function') {
                void activeCloseTabs({ mode, key, wasActive });
                return;
            }
            hideContextMenu();
        });
    }

    activeCloseTabs = runClose;

    syncOverflow();
}
