async function copyText(text) {
    if (!text) {
        return false;
    }
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        try {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            return ok;
        } catch {
            return false;
        }
    }
}

function flashCopied(button, label = 'Copied') {
    if (!button) {
        return;
    }
    const span = button.querySelector('span');
    const previous = span?.textContent || button.textContent;
    button.classList.add('is-copied');
    if (span) {
        span.textContent = label;
    } else {
        button.textContent = label;
    }
    window.setTimeout(() => {
        button.classList.remove('is-copied');
        if (span) {
            span.textContent = previous;
        } else {
            button.textContent = previous;
        }
    }, 1400);
}

export function initAuditLog(refreshIcons = () => {}) {
    const feed = document.querySelector('[data-audit-feed]');
    if (!feed || feed.dataset.auditReady === '1') {
        return;
    }
    feed.dataset.auditReady = '1';

    feed.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-code], [data-copy-from]');
        if (!button || !feed.contains(button)) {
            return;
        }

        let text = '';
        if (button.hasAttribute('data-copy-from')) {
            const sourceId = button.getAttribute('data-copy-from');
            text = sourceId ? (document.getElementById(sourceId)?.textContent || '') : '';
        } else {
            const panel = button.closest('.pg-code-panel');
            text = panel?.querySelector('code')?.textContent || '';
        }

        const ok = await copyText(text);
        flashCopied(button, ok ? 'Copied' : 'Select & copy');
        refreshIcons();
    });
}
