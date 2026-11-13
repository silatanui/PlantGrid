function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

const VISIBLE = 2;

export function partnersSummaryHtml(partners = []) {
    if (!partners.length) {
        return '-';
    }

    const visible = partners.slice(0, VISIBLE);
    const rest = partners.slice(VISIBLE);
    const visibleHtml = visible.map((partner, index) => {
        const link = partner.url
            ? `<a href="${escapeHtml(partner.url)}">${escapeHtml(partner.name)}</a>`
            : escapeHtml(partner.name);
        return `${link}${index < visible.length - 1 ? ', ' : ''}`;
    }).join('');

    if (!rest.length) {
        return `<span class="pg-partners-visible">${visibleHtml}</span>`;
    }

    const allRows = partners.map((partner) => {
        const link = partner.url
            ? `<a href="${escapeHtml(partner.url)}">${escapeHtml(partner.name)}</a>`
            : escapeHtml(partner.name);
        return `<li>${link}</li>`;
    }).join('');

    return `
        <span class="pg-partners-visible">${visibleHtml}</span>
        <button type="button" class="pg-partners-more" data-partners-more aria-expanded="false" title="Show all partners">+${rest.length}</button>
        <div class="pg-partners-popover" data-partners-popover hidden>
            <ul>${allRows}</ul>
        </div>
    `;
}

function closeAll(except = null) {
    document.querySelectorAll('[data-case-partners].is-open').forEach((node) => {
        if (except && node === except) {
            return;
        }
        node.classList.remove('is-open');
        const btn = node.querySelector('[data-partners-more]');
        const pop = node.querySelector('[data-partners-popover]');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
        if (pop) {
            pop.hidden = true;
        }
    });
}

let partnersSummaryReady = false;

export function initPartnersSummary(root = document) {
    if (partnersSummaryReady) {
        return;
    }
    partnersSummaryReady = true;

    root.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-partners-more]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            const host = toggle.closest('[data-case-partners]');
            const pop = host?.querySelector('[data-partners-popover]');
            if (!host || !pop) {
                return;
            }
            const open = !host.classList.contains('is-open');
            closeAll(open ? host : null);
            host.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            pop.hidden = !open;
            return;
        }

        if (!event.target.closest('[data-case-partners]')) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}
