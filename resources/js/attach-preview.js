function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function initSharedMediaDelete() {
    const feed = document.querySelector('[data-media-feed]');
    if (!feed) {
        return;
    }

    const status = feed.querySelector('[data-media-status]');
    const grid = feed.querySelector('[data-media-grid]');

    feed.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-attachment]');
        if (!button) {
            return;
        }

        const name = button.dataset.attachmentName || 'this file';
        const url = button.dataset.deleteUrl;
        const card = button.closest('[data-attachment-id]');
        if (!url || !card) {
            return;
        }

        if (!window.confirm(`Delete ${name} from shared media?`)) {
            return;
        }

        button.disabled = true;
        if (status) {
            status.hidden = false;
            status.textContent = `Deleting ${name}…`;
            status.classList.remove('is-error');
        }

        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.message || 'Could not delete media.');
            }

            card.remove();
            if (grid && !grid.querySelector('[data-attachment-id]')) {
                feed.hidden = true;
            }
            if (status) {
                status.hidden = false;
                status.textContent = payload.message || 'Media deleted.';
            }
        } catch (error) {
            button.disabled = false;
            if (status) {
                status.hidden = false;
                status.textContent = error.message || 'Could not delete media.';
                status.classList.add('is-error');
            }
        }
    });
}

export function initAttachPreview(refreshIcons = () => {}) {
    initSharedMediaDelete();

    document.querySelectorAll('[data-attach-composer]').forEach((composer) => {
        const input = composer.querySelector('[data-attach-input]');
        const pending = composer.querySelector('[data-attach-pending]');
        const media = composer.querySelector('[data-attach-pending-media]');
        const nameEl = composer.querySelector('[data-attach-pending-name]');
        const sizeEl = composer.querySelector('[data-attach-pending-size]');
        const clearBtn = composer.querySelector('[data-attach-clear]');
        const drop = composer.querySelector('.pg-attach-drop');
        if (!input || !pending || !media || !nameEl || !sizeEl) {
            return;
        }

        let objectUrl = null;

        const clear = () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            input.value = '';
            media.innerHTML = '';
            pending.hidden = true;
            if (drop) {
                drop.hidden = false;
            }
            refreshIcons();
        };

        const humanSize = (bytes) => {
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        };

        const showPending = (file) => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            nameEl.textContent = file.name;
            sizeEl.textContent = humanSize(file.size);
            media.innerHTML = '';

            if (file.type.startsWith('image/')) {
                objectUrl = URL.createObjectURL(file);
                const img = document.createElement('img');
                img.src = objectUrl;
                img.alt = file.name;
                media.appendChild(img);
            } else {
                media.innerHTML = '<i data-lucide="file-text"></i>';
            }

            pending.hidden = false;
            if (drop) {
                drop.hidden = true;
            }
            refreshIcons();
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) {
                clear();
                return;
            }
            showPending(file);
        });

        clearBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            clear();
        });
    });
}
