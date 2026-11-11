function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function initGlobalSearch() {
    const root = document.querySelector('[data-global-search]');
    const input = root?.querySelector('[data-global-search-input]');
    const panel = root?.querySelector('[data-global-search-panel]');
    if (!root || !input || !panel) {
        return;
    }

    const suggestUrl = root.dataset.suggestUrl;
    let timer = null;
    let requestId = 0;
    let activeIndex = -1;
    let open = false;
    let latestTerm = '';

    const setOpen = (next) => {
        open = next;
        panel.hidden = !next;
        root.classList.toggle('is-open', next);
        input.setAttribute('aria-expanded', next ? 'true' : 'false');
        if (!next) {
            activeIndex = -1;
        }
    };

    const optionNodes = () => Array.from(panel.querySelectorAll('[data-search-option]'));

    const syncActive = () => {
        optionNodes().forEach((node, index) => {
            const active = index === activeIndex;
            node.classList.toggle('is-active', active);
            node.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                node.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const renderLoading = (term) => {
        panel.innerHTML = `
            <div class="pg-search-status">Searching for “${escapeHtml(term)}”…</div>
        `;
        setOpen(true);
    };

    const renderEmpty = (term) => {
        panel.innerHTML = `
            <div class="pg-search-status">No matches for “${escapeHtml(term)}”.</div>
            <a class="pg-search-footer" href="${escapeHtml(`${root.action}?q=${encodeURIComponent(term)}`)}">
                Open full search
            </a>
        `;
        setOpen(true);
        activeIndex = -1;
    };

    const renderResults = (payload) => {
        if (!payload.groups?.length) {
            renderEmpty(payload.term || latestTerm);
            return;
        }

        const groupsMarkup = payload.groups.map((group) => {
            const items = group.items.map((item) => `
                <a
                    class="pg-search-option"
                    href="${escapeHtml(item.url)}"
                    role="option"
                    aria-selected="false"
                    data-search-option
                >
                    <span class="pg-search-option-main">
                        <strong>${escapeHtml(item.title)}</strong>
                        <span>${escapeHtml(item.subtitle || '')}</span>
                    </span>
                    <span class="pg-search-option-meta">${escapeHtml(item.meta || '')}</span>
                </a>
            `).join('');

            return `
                <div class="pg-search-group">
                    <div class="pg-search-group-label">${escapeHtml(group.label)}</div>
                    ${items}
                </div>
            `;
        }).join('');

        panel.innerHTML = `
            ${groupsMarkup}
            <a class="pg-search-footer" href="${escapeHtml(payload.search_url)}">
                Show more results for “${escapeHtml(payload.term)}”
            </a>
        `;
        setOpen(true);
        activeIndex = -1;
        syncActive();
    };

    const fetchSuggestions = async (term) => {
        latestTerm = term;
        if (term.length < 1) {
            setOpen(false);
            panel.innerHTML = '';
            return;
        }

        const current = ++requestId;
        renderLoading(term);

        try {
            const url = new URL(suggestUrl, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (current !== requestId) {
                return;
            }
            if (!response.ok || !payload?.ok) {
                panel.innerHTML = `<div class="pg-search-status is-error">Search is temporarily unavailable.</div>`;
                setOpen(true);
                return;
            }
            renderResults(payload);
        } catch {
            if (current !== requestId) {
                return;
            }
            panel.innerHTML = `<div class="pg-search-status is-error">Search is temporarily unavailable.</div>`;
            setOpen(true);
        }
    };

    const queueSearch = (term) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fetchSuggestions(term.trim()), 180);
    };

    input.addEventListener('input', () => {
        queueSearch(input.value);
    });

    input.addEventListener('focus', () => {
        root.classList.add('is-focused');
        if (input.value.trim().length >= 1) {
            queueSearch(input.value);
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (!root.contains(document.activeElement)) {
                root.classList.remove('is-focused');
                setOpen(false);
            }
        }, 120);
    });

    input.addEventListener('keydown', (event) => {
        const options = optionNodes();

        if (event.key === 'ArrowDown') {
            if (!open && input.value.trim()) {
                queueSearch(input.value);
                return;
            }
            if (!options.length) {
                return;
            }
            event.preventDefault();
            activeIndex = (activeIndex + 1) % options.length;
            syncActive();
            return;
        }

        if (event.key === 'ArrowUp') {
            if (!options.length) {
                return;
            }
            event.preventDefault();
            activeIndex = activeIndex <= 0 ? options.length - 1 : activeIndex - 1;
            syncActive();
            return;
        }

        if (event.key === 'Enter' && open && activeIndex >= 0 && options[activeIndex]) {
            event.preventDefault();
            window.location.href = options[activeIndex].href;
            return;
        }

        if (event.key === 'Escape') {
            if (open) {
                event.preventDefault();
                setOpen(false);
                return;
            }
            input.blur();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
            root.classList.remove('is-focused');
        }
    });
}
