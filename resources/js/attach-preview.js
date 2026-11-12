export function initAttachPreview(refreshIcons = () => {}) {
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
