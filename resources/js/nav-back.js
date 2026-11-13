export function initNavBack() {
    const fallback = document.querySelector('[data-nav-back-fallback]');
    if (!fallback) {
        return;
    }

    try {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        if (!referrer || referrer.origin !== window.location.origin) {
            return;
        }
        if (referrer.pathname === window.location.pathname && referrer.search === window.location.search) {
            return;
        }
    } catch {
        return;
    }

    fallback.hidden = false;
    fallback.addEventListener('click', (event) => {
        event.preventDefault();
        window.history.back();
    });

    document.querySelector('.pg-navrow')?.classList.add('has-nav-back');
}
