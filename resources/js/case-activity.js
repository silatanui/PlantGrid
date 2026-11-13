function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
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
            <div class="text-muted">${escapeHtml(item.at || '')} · ${escapeHtml(item.actor || 'System')}</div>
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

export function initCaseActivity() {
    const list = document.querySelector('[data-case-activity]');
    if (!list) {
        return;
    }

    document.addEventListener('plantgrid:case-activity', (event) => {
        if (event.detail?.activity) {
            renderCaseActivity(event.detail.activity);
            return;
        }
        refreshCaseActivity();
    });

    const pollMs = Number(list.dataset.activityPoll || 12000);
    if (pollMs > 0) {
        window.setInterval(() => {
            if (document.hidden) {
                return;
            }
            refreshCaseActivity();
        }, pollMs);
    }
}
