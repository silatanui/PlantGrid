const HEARTBEAT_MS = 15000;
const POLL_MS = 5000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function postHeartbeat(url) {
    if (!url) {
        return;
    }

    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
    } catch (error) {
        // Presence is best-effort; ignore transient network failures.
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function caseOptionsHtml(cases, selectedId) {
    const options = ['<option value="">Select open case…</option>'];
    (cases || []).forEach((item) => {
        const selected = String(item.id) === String(selectedId || '') ? ' selected' : '';
        const owned = item.owned ? ' (owned)' : '';
        options.push(
            `<option value="${escapeHtml(item.id)}"${selected}>${escapeHtml(item.label)}${owned}</option>`
        );
    });

    return options.join('');
}

function renderOnlineTable(tbody, users, { canAssign, cases, csrf }) {
    if (!tbody) {
        return;
    }

    if (!users.length) {
        const cols = canAssign ? 5 : 4;
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-muted">No one else is online right now. Presence updates live as people use PlantGrid.</td></tr>`;
        return;
    }

    tbody.innerHTML = users
        .map((person) => {
            const selected = tbody.querySelector(`tr[data-user-id="${person.id}"] select[name="ticket_id"]`)?.value || '';
            const assignCell = canAssign
                ? `<td style="min-width: 280px;">
                        <form class="d-flex flex-wrap gap-1 align-items-center" method="post" action="${escapeHtml(person.assign_url)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrf)}">
                            <select class="form-select form-select-sm" name="ticket_id" required>
                                ${caseOptionsHtml(cases, selected)}
                            </select>
                            <button class="btn btn-sm btn-pg" type="submit">Assign</button>
                        </form>
                   </td>`
                : '';

            return `<tr data-user-id="${escapeHtml(person.id)}">
                <td>
                    <span class="pg-online-dot" title="Online" aria-hidden="true"></span>
                    <a href="${escapeHtml(person.profile_url)}">${escapeHtml(person.name)}</a>
                    <div class="text-muted small pg-mono">${escapeHtml(person.email)}</div>
                </td>
                <td>${escapeHtml(person.role)}</td>
                <td>${escapeHtml(person.department)}</td>
                <td data-last-seen>${escapeHtml(person.last_seen_label)}</td>
                ${assignCell}
            </tr>`;
        })
        .join('');
}

function renderHeaderOnline(root, users, count) {
    if (!root) {
        return;
    }

    const button = root.querySelector('[data-presence-count-host]');
    let badge = button?.querySelector('.pg-count');
    if (button) {
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'pg-count';
                button.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
    }

    const list = root.querySelector('[data-presence-header-list]');
    if (!list) {
        return;
    }

    if (!users.length) {
        list.innerHTML = '<div class="dropdown-item-text small text-muted">No other users online.</div>';
        return;
    }

    list.innerHTML = users
        .slice(0, 6)
        .map(
            (person) => `<a class="dropdown-item small" href="${escapeHtml(person.profile_url)}">
                <span class="pg-online-dot" aria-hidden="true"></span>
                <strong>${escapeHtml(person.name)}</strong>
                <div class="text-muted">${escapeHtml(person.role_label)}</div>
            </a>`
        )
        .join('');
}

export function initPresence() {
    const body = document.body;
    const heartbeatUrl = body.dataset.presenceHeartbeat;
    const liveUrl = body.dataset.presenceLive;

    if (!heartbeatUrl && !liveUrl) {
        return;
    }

    let heartbeatTimer = null;
    let pollTimer = null;

    const startHeartbeat = () => {
        if (!heartbeatUrl || heartbeatTimer) {
            return;
        }
        postHeartbeat(heartbeatUrl);
        heartbeatTimer = window.setInterval(() => postHeartbeat(heartbeatUrl), HEARTBEAT_MS);
    };

    const stopHeartbeat = () => {
        if (heartbeatTimer) {
            window.clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    };

    const refreshLive = async () => {
        if (!liveUrl) {
            return;
        }

        try {
            const response = await fetch(liveUrl, {
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
            const users = payload.users || [];
            const count = payload.count ?? users.length;

            const table = document.querySelector('[data-presence-table]');
            if (table) {
                const tbody = table.querySelector('[data-presence-rows]');
                let cases = [];
                try {
                    cases = JSON.parse(table.dataset.assignCases || '[]');
                } catch (error) {
                    cases = [];
                }
                renderOnlineTable(tbody, users, {
                    canAssign: table.dataset.canAssign === '1',
                    cases,
                    csrf: csrfToken(),
                });
                const status = document.querySelector('[data-presence-status]');
                if (status) {
                    status.textContent = `${count} online · updates every few seconds`;
                }
            }

            renderHeaderOnline(document.querySelector('[data-presence-header]'), users, count);
        } catch (error) {
            // Ignore transient polling failures.
        }
    };

    const startPolling = () => {
        if (!liveUrl || pollTimer) {
            return;
        }
        refreshLive();
        pollTimer = window.setInterval(refreshLive, POLL_MS);
    };

    const stopPolling = () => {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const syncVisibility = () => {
        if (document.hidden) {
            stopHeartbeat();
            // Keep polling slowly for admins watching the board while in another tab.
            return;
        }
        startHeartbeat();
        startPolling();
    };

    syncVisibility();
    document.addEventListener('visibilitychange', syncVisibility);
    window.addEventListener('pagehide', stopHeartbeat);
}
