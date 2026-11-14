import { brandFaviconDataUrl } from './theme';

const POLL_MS = 4000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function setBadge(button, count) {
    if (!button) {
        return;
    }

    let badge = button.querySelector('.pg-count');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'pg-count';
            button.appendChild(badge);
        }
        badge.textContent = String(count);
        button.classList.toggle('is-live-ping', true);
        window.setTimeout(() => button.classList.remove('is-live-ping'), 900);
    } else if (badge) {
        badge.remove();
    }
}

function setNavBadge(key, count) {
    if (!key) {
        return;
    }

    document.querySelectorAll(`[data-nav-badge="${key}"]`).forEach((host) => {
        let badge = host.querySelector('.pg-nav-count');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('em');
                badge.className = 'pg-nav-count';
                host.appendChild(badge);
            }
            badge.textContent = String(count);
            host.classList.add('has-nav-badge');
        } else if (badge) {
            badge.remove();
            host.classList.remove('has-nav-badge');
        }
    });
}

function syncMonitoringStatus(status) {
    const tone = ['normal', 'degraded', 'outage'].includes(status) ? status : 'normal';

    document.querySelectorAll('[data-nav-status="monitoring"]').forEach((host) => {
        host.dataset.status = tone;
        let dot = host.querySelector('.pg-nav-status');
        if (!dot) {
            dot = document.createElement('i');
            host.appendChild(dot);
        }
        dot.className = `pg-nav-status pg-nav-status-${tone}`;
        dot.title = `Simulated monitoring: ${tone}`;
        dot.setAttribute('aria-label', `Simulated monitoring ${tone}`);
    });
}

function syncNavBadges(badges = {}) {
    setNavBadge('my_cases', Number(badges.my_cases || 0));
    setNavBadge('new_cases', Number(badges.new_cases || 0));
    setNavBadge('messages', Number(badges.messages || 0));
    setNavBadge('notifications', Number(badges.notifications || 0));
    if (badges.monitoring_status && badges.monitoring_status !== 'hidden') {
        syncMonitoringStatus(badges.monitoring_status);
    }
}

function showToast(title, body, url) {
    let host = document.querySelector('[data-live-toasts]');
    if (!host) {
        host = document.createElement('div');
        host.className = 'pg-live-toasts';
        host.setAttribute('data-live-toasts', '');
        document.body.appendChild(host);
    }

    const toast = document.createElement('a');
    toast.className = 'pg-live-toast';
    toast.href = url || '#';
    toast.innerHTML = `<strong>${escapeHtml(title)}</strong><span>${escapeHtml(body || '')}</span>`;
    host.prepend(toast);
    window.setTimeout(() => toast.classList.add('is-in'), 20);
    window.setTimeout(() => {
        toast.classList.remove('is-in');
        window.setTimeout(() => toast.remove(), 280);
    }, 6000);
}

function maybeBrowserNotify(title, body) {
    if (!('Notification' in window) || document.visibilityState === 'visible') {
        return;
    }
    if (Notification.permission === 'granted') {
        try {
            // Use the themed P mark (same as the tab favicon), not the legacy static PNG.
            new Notification(title, { body: body || '', icon: brandFaviconDataUrl() });
        } catch (error) {
            // Ignore browser notification failures.
        }
    }
}

function playCue(kind) {
    const url = kind === 'notification'
        ? document.body?.dataset?.soundNotification
        : document.body?.dataset?.soundMessage;
    if (!url) {
        return;
    }
    try {
        const audio = new Audio(url);
        audio.volume = 0.95;
        const result = audio.play();
        if (result && typeof result.catch === 'function') {
            result.catch(() => {});
        }
    } catch {
        // Ignore autoplay / decode failures.
    }
}

function renderNotificationList(root, items) {
    const list = root?.querySelector('[data-live-notifications-list]');
    if (!list) {
        return;
    }

    if (!items.length) {
        list.innerHTML = '<div class="dropdown-item-text small text-muted">No unread notifications.</div>';
        return;
    }

    list.innerHTML = items
        .map(
            (item) => `<a class="dropdown-item small" href="${escapeHtml(item.url)}">
                <strong>${escapeHtml(item.title)}</strong><br>${escapeHtml(item.body)}
            </a>`
        )
        .join('');
}

function renderMessageList(root, items) {
    const list = root?.querySelector('[data-live-messages-list]');
    if (!list) {
        return;
    }

    if (!items.length) {
        list.innerHTML = '<div class="dropdown-item-text small text-muted">No unread messages.</div>';
        return;
    }

    list.innerHTML = items
        .map(
            (item) => `<a class="dropdown-item small" href="${escapeHtml(item.url)}">
                <strong>${escapeHtml(item.sender)}</strong><br>${escapeHtml(item.subject)}
            </a>`
        )
        .join('');
}

function renderThread(host, items) {
    if (!host) {
        return;
    }

    const existingIds = new Set(
        [...host.querySelectorAll('[data-message-id]')].map((node) => String(node.dataset.messageId))
    );

    host.innerHTML = items
        .map(
            (item) => `<article class="pg-message-bubble ${item.mine ? 'is-mine' : ''}" data-message-id="${escapeHtml(item.id)}">
                <header>
                    <strong>${escapeHtml(item.sender)}</strong>
                    <span>${escapeHtml(item.created_label)}</span>
                </header>
                <div class="pg-message-subject">${escapeHtml(item.subject)}</div>
                <div class="pg-rich-note">${item.body_html || ''}</div>
            </article>`
        )
        .join('');

    const newest = items[items.length - 1];
    if (newest && !existingIds.has(String(newest.id)) && existingIds.size > 0) {
        host.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
}

export function initLiveInbox() {
    const feedUrl = document.body.dataset.liveInboxUrl;
    if (!feedUrl) {
        return;
    }

    const notificationsRoot = document.querySelector('[data-live-notifications]');
    const messagesRoot = document.querySelector('[data-live-messages]');
    const threadHost = document.querySelector('[data-live-thread]');
    const threadUrl = threadHost?.dataset.liveThreadUrl || '';

    let lastNotificationCount = Number(notificationsRoot?.dataset.initialCount || 0);
    let lastMessageCount = Number(messagesRoot?.dataset.initialCount || 0);
    let lastThreadCount = Number(threadHost?.dataset.initialCount || 0);
    let timer = null;
    let knownNotificationIds = new Set();
    let knownMessageIds = new Set();

    const refreshFeed = async () => {
        try {
            const response = await fetch(feedUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const notes = payload.notifications || { count: 0, items: [] };
            const msgs = payload.messages || { count: 0, items: [] };

            setBadge(notificationsRoot?.querySelector('[data-live-count-host]'), notes.count);
            setBadge(messagesRoot?.querySelector('[data-live-count-host]'), msgs.count);
            syncNavBadges(payload.badges || {
                cases: 0,
                messages: msgs.count,
                notifications: notes.count,
                alerts: 0,
            });
            renderNotificationList(notificationsRoot, notes.items || []);
            renderMessageList(messagesRoot, msgs.items || []);

            (notes.items || []).forEach((item) => {
                if (!knownNotificationIds.has(item.id) && knownNotificationIds.size > 0) {
                    showToast(item.title, item.body, item.url);
                    maybeBrowserNotify(item.title, item.body);
                    playCue('notification');
                }
            });
            (msgs.items || []).forEach((item) => {
                if (!knownMessageIds.has(String(item.id)) && knownMessageIds.size > 0) {
                    showToast('New message', `${item.sender}: ${item.subject}`, item.url);
                    maybeBrowserNotify('New message', `${item.sender}: ${item.subject}`);
                    playCue('message');
                }
            });

            knownNotificationIds = new Set((notes.items || []).map((item) => item.id));
            knownMessageIds = new Set((msgs.items || []).map((item) => String(item.id)));

            if (notes.count > lastNotificationCount || msgs.count > lastMessageCount) {
                document.title = notes.count + msgs.count > 0
                    ? `(${notes.count + msgs.count}) PlantGrid`
                    : document.title.replace(/^\(\d+\)\s*/, '');
            }
            lastNotificationCount = notes.count;
            lastMessageCount = msgs.count;
        } catch (error) {
            // Ignore transient polling failures.
        }
    };

    const refreshThread = async () => {
        if (!threadUrl || !threadHost) {
            return;
        }

        try {
            const response = await fetch(threadUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            const items = payload.items || [];
            if (items.length !== lastThreadCount || items.length > 0) {
                renderThread(threadHost, items);
            }
            if (items.length > lastThreadCount && lastThreadCount > 0) {
                const newest = items[items.length - 1];
                if (newest && !newest.mine) {
                    showToast('New reply', newest.subject, window.location.href);
                }
            }
            lastThreadCount = items.length;
            threadHost.dataset.initialCount = String(items.length);
        } catch (error) {
            // Ignore transient polling failures.
        }
    };

    const tick = () => {
        refreshFeed();
        refreshThread();
    };

    const start = () => {
        if (timer) {
            return;
        }
        tick();
        timer = window.setInterval(tick, POLL_MS);
    };

    const stop = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Keep a slower poll while the tab is open but hidden.
            stop();
            timer = window.setInterval(tick, POLL_MS * 2);
            return;
        }
        stop();
        start();
    });

    const enableBtn = document.querySelector('[data-live-enable-browser]');
    if (enableBtn && 'Notification' in window && Notification.permission === 'default') {
        enableBtn.hidden = false;
        enableBtn.addEventListener('click', async () => {
            try {
                await Notification.requestPermission();
            } catch (error) {
                // Ignore.
            }
            enableBtn.hidden = true;
        });
    } else if (enableBtn) {
        enableBtn.hidden = true;
    }

    // Seed known IDs from first successful poll without toasting existing items.
    start();
}
