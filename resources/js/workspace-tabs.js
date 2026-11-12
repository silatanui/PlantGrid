export function initWorkspaceTabs(refreshIcons = () => {}) {
    const root = document.querySelector('[data-workspace-tabs]');
    if (!root) {
        return;
    }

    const track = root.querySelector('[data-workspace-tab-track]');
    const more = root.querySelector('[data-workspace-more]');
    const moreButton = root.querySelector('[data-workspace-more-toggle]');
    const moreMenu = root.querySelector('[data-workspace-more-menu]');
    const moreLabel = root.querySelector('[data-workspace-more-label]');
    const reorderUrl = root.dataset.reorderUrl;
    const csrf = root.dataset.csrf
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    if (!track) {
        return;
    }

    let dragKey = null;

    const tabNodes = () => Array.from(track.querySelectorAll('[data-tab-key]'));

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

    track.addEventListener('dragstart', (event) => {
        const tab = event.target.closest('[data-tab-key]');
        if (!tab || event.target.closest('.pg-workspace-tab-close')) {
            return;
        }
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

    document.addEventListener('click', (event) => {
        if (!more?.contains(event.target)) {
            closeMore();
        }
    });

    // Stop the tab link from stealing the close click.
    root.addEventListener('click', (event) => {
        const closeButton = event.target.closest('.pg-workspace-tab-close button');
        if (closeButton) {
            event.stopPropagation();
        }
    });

    window.addEventListener('resize', () => {
        window.requestAnimationFrame(syncOverflow);
    });

    syncOverflow();
}
