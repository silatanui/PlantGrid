function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function actorHtml(item) {
    const name = escapeHtml(item.actor || 'System');
    if (item.actor_url) {
        return `<a class="pg-activity-actor" href="${escapeHtml(item.actor_url)}">${name}</a>`;
    }
    return name;
}

function activityItemHtml(item) {
    const noteAttr = item.note_id ? ` data-activity-note="${escapeHtml(item.note_id)}"` : '';
    const body = item.html
        ? `<div class="pg-rich-note">${item.html}</div>`
        : (item.body ? `<div class="text-muted">${escapeHtml(item.body)}</div>` : '');
    const noteLink = item.note_id
        ? `<a class="small" href="#note-${escapeHtml(item.note_id)}">View note</a>`
        : '';

    return `
        <li${noteAttr}>
            ${item.badge_html || ''}
            <strong class="pg-activity-label">${escapeHtml(item.label || 'Update')}</strong>
            ${body}
            <div class="text-muted">${escapeHtml(item.at || '')} · ${actorHtml(item)}</div>
            ${noteLink}
        </li>
    `;
}

export function renderCaseActivity(items = []) {
    const list = document.querySelector('[data-case-activity]');
    if (!list) {
        return;
    }

    if (!items.length) {
        list.innerHTML = '<li class="text-muted">No activity recorded on this case yet.</li>';
        return;
    }

    list.innerHTML = items.map((item) => activityItemHtml(item)).join('');
}

export function applyCaseActivity(payload) {
    if (payload?.activity) {
        renderCaseActivity(payload.activity);
    }
}

export function refreshCaseActivity() {
    const list = document.querySelector('[data-case-activity]');
    const url = list?.dataset.activityUrl;
    if (!list || !url) {
        return Promise.resolve();
    }

    return fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((response) => response.json())
        .then((payload) => {
            if (payload?.ok && payload.activity) {
                renderCaseActivity(payload.activity);
            }
        })
        .catch(() => {});
}

let caseActivityListenersBound = false;
let caseActivityPollTimer = null;

export function initCaseActivity() {
    const list = document.querySelector('[data-case-activity]');

    if (!caseActivityListenersBound) {
        caseActivityListenersBound = true;
        document.addEventListener('plantgrid:case-activity', (event) => {
            if (event.detail?.activity) {
                renderCaseActivity(event.detail.activity);
                return;
            }
            refreshCaseActivity();
        });
    }

    if (caseActivityPollTimer) {
        window.clearInterval(caseActivityPollTimer);
        caseActivityPollTimer = null;
    }

    if (!list) {
        return;
    }

    const pollMs = Number(list.dataset.activityPoll || 12000);
    if (pollMs > 0) {
        caseActivityPollTimer = window.setInterval(() => {
            if (document.hidden) {
                return;
            }
            refreshCaseActivity();
        }, pollMs);
    }
}
