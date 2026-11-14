const STORAGE_PREFIX = 'plantgrid.alert.dismissed.';

function storageKey(key) {
    return `${STORAGE_PREFIX}${key}`;
}

function isDismissed(key) {
    if (!key) {
        return false;
    }

    try {
        return sessionStorage.getItem(storageKey(key)) === '1';
    } catch {
        return false;
    }
}

function markDismissed(key) {
    if (!key) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(key), '1');
    } catch {
        // Ignore quota / private-mode failures.
    }
}

function dismissAlert(alert) {
    const key = alert.dataset.alertKey || '';
    markDismissed(key);
    alert.remove();
}

export function initAlerts() {
    document.querySelectorAll('[data-pg-alert]').forEach((alert) => {
        const key = alert.dataset.alertKey || '';
        if (isDismissed(key)) {
            alert.remove();
            return;
        }

        if (alert.dataset.alertReady === '1') {
            return;
        }
        alert.dataset.alertReady = '1';

        alert.querySelector('[data-pg-alert-dismiss]')?.addEventListener('click', () => {
            dismissAlert(alert);
        });
    });
}

export function initConfirmActions() {
    if (document.body.dataset.confirmActionsReady === '1') {
        return;
    }
    document.body.dataset.confirmActionsReady = '1';

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const message = form.getAttribute('data-confirm');
        if (!message) {
            return;
        }

        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm-click]');
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const message = trigger.getAttribute('data-confirm-click');
        if (!message) {
            return;
        }

        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
}
