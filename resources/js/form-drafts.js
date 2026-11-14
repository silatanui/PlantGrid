const STORAGE_PREFIX = 'plantgrid.formDraft:';
const SKIP_NAMES = new Set(['_token', '_method']);
const SKIP_TYPES = new Set(['password', 'file', 'submit', 'button', 'image', 'reset']);

function pageScope() {
    return `${window.location.pathname}${window.location.search}`;
}

function formKey(form) {
    if (form.dataset.draftKey) {
        return `${STORAGE_PREFIX}${form.dataset.draftKey}`;
    }
    const identity = form.getAttribute('id')
        || form.getAttribute('action')
        || form.getAttribute('data-live-case-form')
        || [...form.querySelectorAll('[name]')].slice(0, 3).map((field) => field.name).join('|')
        || 'form';
    return `${STORAGE_PREFIX}${pageScope()}::${identity}`;
}

function shouldTrack(form) {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }
    if (form.matches('[data-no-draft], .pg-workspace-tab-close, [data-workspace-tab-close]')) {
        return false;
    }
    if (form.closest('.pg-workspace-tab-close')) {
        return false;
    }
    if (form.method?.toLowerCase() === 'get' && form.matches('.pg-filters, [data-global-search], .pg-global-search')) {
        return false;
    }
    // Only persist interactive edit forms in the main app shell.
    if (!form.closest('.pg-main, [data-live-notes], .pg-shell, main, #app')) {
        return false;
    }
    return form.querySelector('textarea, input:not([type="hidden"]), select') !== null;
}

function fieldSnapshot(form) {
    const values = {};
    const checks = {};

    form.querySelectorAll('input, textarea, select').forEach((field) => {
        const name = field.name;
        if (!name || SKIP_NAMES.has(name) || field.disabled || field.dataset.noDraft === '1') {
            return;
        }
        const type = (field.type || field.tagName).toLowerCase();
        if (SKIP_TYPES.has(type)) {
            return;
        }

        if (type === 'checkbox') {
            checks[name] = checks[name] || [];
            if (field.checked) {
                checks[name].push(field.value || 'on');
            }
            return;
        }

        if (type === 'radio') {
            if (field.checked) {
                values[name] = field.value;
            }
            return;
        }

        if (field.tagName === 'SELECT' && field.multiple) {
            values[name] = [...field.selectedOptions].map((option) => option.value);
            return;
        }

        values[name] = field.value;
    });

    return { values, checks, savedAt: Date.now() };
}

function applySnapshot(form, snapshot) {
    if (!snapshot?.values && !snapshot?.checks) {
        return false;
    }

    let applied = false;
    const values = snapshot.values || {};
    const checks = snapshot.checks || {};

    Object.entries(values).forEach(([name, value]) => {
        const fields = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
        if (fields.length === 0) {
            return;
        }

        fields.forEach((field) => {
            const type = (field.type || field.tagName).toLowerCase();
            if (type === 'radio') {
                const match = field.value === value;
                if (field.checked !== match) {
                    field.checked = match;
                    if (match) {
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    applied = true;
                }
                return;
            }
            if (field.tagName === 'SELECT' && field.multiple && Array.isArray(value)) {
                [...field.options].forEach((option) => {
                    const next = value.includes(option.value);
                    if (option.selected !== next) {
                        option.selected = next;
                        applied = true;
                    }
                });
                return;
            }
            if (String(field.value ?? '') !== String(value ?? '')) {
                field.value = value ?? '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
                applied = true;
            }
        });
    });

    Object.entries(checks).forEach(([name, selected]) => {
        const selectedSet = new Set(selected || []);
        form.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(name)}"]`).forEach((field) => {
            const next = selectedSet.has(field.value || 'on');
            if (field.checked !== next) {
                field.checked = next;
                applied = true;
            }
        });
    });

    if (applied) {
        form.dataset.draftRestored = '1';
        form.dispatchEvent(new CustomEvent('plantgrid:draft-restored', { bubbles: true }));
    }

    return applied;
}

function readDraft(form) {
    try {
        const raw = sessionStorage.getItem(formKey(form));
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeDraft(form) {
    if (!shouldTrack(form)) {
        return;
    }
    try {
        const snapshot = fieldSnapshot(form);
        const hasContent = Object.values(snapshot.values).some((value) => {
            if (Array.isArray(value)) {
                return value.length > 0;
            }
            return String(value ?? '').trim() !== '';
        }) || Object.values(snapshot.checks).some((list) => (list || []).length > 0);

        if (!hasContent) {
            sessionStorage.removeItem(formKey(form));
            return;
        }

        sessionStorage.setItem(formKey(form), JSON.stringify(snapshot));
    } catch {
        // Ignore private mode / quota errors.
    }
}

export function clearFormDraft(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    try {
        sessionStorage.removeItem(formKey(form));
    } catch {
        // Ignore.
    }
}

function persistAllTracked() {
    document.querySelectorAll('form').forEach((form) => {
        if (shouldTrack(form)) {
            writeDraft(form);
        }
    });
}

function restoreAllTracked() {
    document.querySelectorAll('form').forEach((form) => {
        if (!shouldTrack(form)) {
            return;
        }
        const draft = readDraft(form);
        if (draft) {
            applySnapshot(form, draft);
        }
    });
}

function bindForm(form) {
    if (!shouldTrack(form) || form.dataset.draftBound === '1') {
        return;
    }
    form.dataset.draftBound = '1';

    const schedule = (() => {
        let timer = null;
        return () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => writeDraft(form), 180);
        };
    })();

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('submit', () => {
        // Successful navigation submits clear the draft; AJAX handlers should also clear.
        clearFormDraft(form);
    });
}

export function initFormDrafts() {
    document.querySelectorAll('form').forEach(bindForm);
    restoreAllTracked();

    // Flush drafts before workspace tab soft-navigation.
    document.querySelectorAll('[data-workspace-tabs] a[href]').forEach((link) => {
        link.addEventListener('pointerdown', persistAllTracked);
        link.addEventListener('click', persistAllTracked);
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }
        persistAllTracked();
    });

    window.addEventListener('pagehide', persistAllTracked);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            persistAllTracked();
        }
    });

    // Allow other modules to clear a draft after AJAX save.
    document.addEventListener('plantgrid:draft-clear', (event) => {
        const form = event.target instanceof HTMLFormElement
            ? event.target
            : event.detail?.form;
        if (form) {
            clearFormDraft(form);
        }
    });

    // Observe late-injected forms (note editors, etc.).
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) {
                    return;
                }
                if (node.matches?.('form')) {
                    bindForm(node);
                }
                node.querySelectorAll?.('form').forEach(bindForm);
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
}
