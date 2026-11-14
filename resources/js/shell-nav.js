const SCROLL_KEY = 'plantgrid.shell.left.scroll';

function sameOrigin(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.origin === window.location.origin;
    } catch {
        return false;
    }
}

function shouldSoftNavigate(anchor) {
    if (!(anchor instanceof HTMLAnchorElement)) {
        return false;
    }
    if (anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.dataset.fullNav === '1') {
        return false;
    }
    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
        return false;
    }
    if (!sameOrigin(href)) {
        return false;
    }
    const url = new URL(href, window.location.origin);
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
        return false;
    }
    return true;
}

function persistLeftScroll() {
    const rail = document.getElementById('shell-left');
    if (rail) {
        sessionStorage.setItem(SCROLL_KEY, String(rail.scrollTop || 0));
    }
}

function restoreLeftScroll() {
    const rail = document.getElementById('shell-left');
    if (!rail) {
        return;
    }
    const saved = Number(sessionStorage.getItem(SCROLL_KEY) || '0');
    if (Number.isFinite(saved)) {
        rail.scrollTop = saved;
    }
}

function updateLeftActive(url) {
    const next = new URL(url, window.location.origin);
    document.querySelectorAll('#shell-left a[href]').forEach((link) => {
        try {
            const href = new URL(link.href, window.location.origin);
            const match = href.pathname === next.pathname
                && href.search === next.search;
            link.classList.toggle('active', match && link.classList.contains('pg-rail-link'));
            if (link.classList.contains('pg-rail-link')) {
                link.classList.toggle('active', match);
            }
        } catch {
            // Ignore bad hrefs.
        }
    });
}

function runInlineScripts(root) {
    root.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');
        [...oldScript.attributes].forEach((attr) => script.setAttribute(attr.name, attr.value));
        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
}

function replaceNode(current, incoming) {
    if (!current || !incoming) {
        return null;
    }
    const clone = incoming.cloneNode(true);
    current.replaceWith(clone);
    return clone;
}

function syncWorkspaceChrome(doc) {
    const navrow = document.querySelector('.pg-navrow');
    const nextTabs = doc.querySelector('[data-workspace-tabs]');
    const currentTabs = document.querySelector('[data-workspace-tabs]');

    if (nextTabs && currentTabs) {
        replaceNode(currentTabs, nextTabs);
    } else if (nextTabs && !currentTabs && navrow) {
        navrow.appendChild(nextTabs.cloneNode(true));
    } else if (!nextTabs && currentTabs) {
        currentTabs.remove();
    }

    navrow?.classList.toggle('has-workspace-tabs', Boolean(document.querySelector('[data-workspace-tabs]')));

    const nextPrimary = doc.querySelector('.pg-navrow > nav.pg-tabs');
    const currentPrimary = document.querySelector('.pg-navrow > nav.pg-tabs');
    if (nextPrimary && currentPrimary) {
        replaceNode(currentPrimary, nextPrimary);
    }

    const nextBack = doc.querySelector('.pg-navrow > a.pg-nav-back:not(.pg-nav-back--fallback)');
    const currentBack = document.querySelector('.pg-navrow > a.pg-nav-back:not(.pg-nav-back--fallback)');
    const fallbackBack = document.querySelector('.pg-navrow > a.pg-nav-back--fallback');

    if (nextBack && currentBack) {
        replaceNode(currentBack, nextBack);
        navrow?.classList.add('has-nav-back');
        if (fallbackBack) {
            fallbackBack.hidden = true;
        }
    } else if (nextBack && !currentBack && navrow) {
        const primary = navrow.querySelector('nav.pg-tabs');
        const node = nextBack.cloneNode(true);
        if (primary) {
            primary.before(node);
        } else {
            navrow.prepend(node);
        }
        navrow.classList.add('has-nav-back');
        if (fallbackBack) {
            fallbackBack.hidden = true;
        }
    } else if (!nextBack && currentBack) {
        currentBack.remove();
        navrow?.classList.remove('has-nav-back');
        if (fallbackBack) {
            fallbackBack.hidden = false;
        }
    }
}

export async function softNavigate(url, { push = true } = {}) {
    const absolute = new URL(url, window.location.origin).toString();
    if (absolute === window.location.href) {
        return;
    }

    persistLeftScroll();
    document.body.classList.add('is-shell-navigating');

    try {
        const response = await fetch(absolute, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-PlantGrid-Shell-Nav': '1',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            window.location.assign(absolute);
            return;
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        const nextMain = doc.querySelector('main.pg-main, #content');
        const nextRight = doc.querySelector('#shell-right');
        const currentMain = document.querySelector('main.pg-main, #content');
        const currentRight = document.querySelector('#shell-right');

        if (!nextMain || !currentMain) {
            window.location.assign(absolute);
            return;
        }

        const main = replaceNode(currentMain, nextMain);
        runInlineScripts(main);

        if (nextRight && currentRight) {
            replaceNode(currentRight, nextRight);
        } else if (nextRight && !currentRight) {
            const workspace = document.querySelector('.pg-workspace');
            workspace?.appendChild(nextRight.cloneNode(true));
        } else if (!nextRight && currentRight) {
            currentRight.remove();
        }

        syncWorkspaceChrome(doc);

        const nextTitle = doc.querySelector('title')?.textContent;
        if (nextTitle) {
            document.title = nextTitle;
        }

        if (push) {
            window.history.pushState({ shellNav: true }, '', absolute);
        }

        updateLeftActive(absolute);
        restoreLeftScroll();

        document.body.classList.remove('pg-left-open', 'pg-right-open');
        const mask = document.querySelector('[data-shell-mask]');
        if (mask) {
            mask.hidden = true;
        }

        document.dispatchEvent(new CustomEvent('plantgrid:page', {
            detail: { url: absolute },
        }));

        window.scrollTo(0, 0);
        const mainEl = document.querySelector('main.pg-main');
        if (mainEl) {
            mainEl.scrollTop = 0;
        }
    } catch {
        window.location.assign(absolute);
    } finally {
        document.body.classList.remove('is-shell-navigating');
    }
}

export function initShellNav() {
    const left = document.getElementById('shell-left');
    if (!left || left.dataset.shellNavReady === '1') {
        return;
    }
    left.dataset.shellNavReady = '1';

    restoreLeftScroll();
    left.addEventListener('scroll', persistLeftScroll, { passive: true });

    left.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || !left.contains(anchor) || !shouldSoftNavigate(anchor)) {
            return;
        }
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        void softNavigate(anchor.href);
    });

    window.addEventListener('popstate', () => {
        void softNavigate(window.location.href, { push: false });
    });
}
