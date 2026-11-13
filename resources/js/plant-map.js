function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

export function initPlantMap() {
    const root = document.querySelector('[data-plant-map]');
    if (!root || root.dataset.ready === '1') {
        return;
    }
    root.dataset.ready = '1';

    const viewport = root.querySelector('[data-plant-viewport]');
    const world = root.querySelector('[data-plant-world]');
    const modal = root.querySelector('[data-plant-modal]');
    const jsonNode = root.querySelector('[data-plant-pins-json]');
    if (!viewport || !world) {
        return;
    }

    let pins = [];
    try {
        pins = JSON.parse(jsonNode?.textContent || '[]');
    } catch (_error) {
        pins = [];
    }

    let scale = 1;
    let x = 0;
    let y = 0;
    let dragging = false;
    let lastX = 0;
    let lastY = 0;

    const apply = () => {
        world.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
    };

    const zoomBy = (factor, originX, originY) => {
        const rect = viewport.getBoundingClientRect();
        const cx = (originX ?? rect.left + rect.width / 2) - rect.left;
        const cy = (originY ?? rect.top + rect.height / 2) - rect.top;
        const next = clamp(scale * factor, 0.7, 3.2);
        const ratio = next / scale;
        x = cx - (cx - x) * ratio;
        y = cy - (cy - y) * ratio;
        scale = next;
        apply();
    };

    root.querySelector('[data-plant-zoom-in]')?.addEventListener('click', () => zoomBy(1.2));
    root.querySelector('[data-plant-zoom-out]')?.addEventListener('click', () => zoomBy(1 / 1.2));
    root.querySelector('[data-plant-zoom-reset]')?.addEventListener('click', () => {
        scale = 1;
        x = 0;
        y = 0;
        apply();
    });

    viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        zoomBy(event.deltaY < 0 ? 1.12 : 1 / 1.12, event.clientX, event.clientY);
    }, { passive: false });

    viewport.addEventListener('pointerdown', (event) => {
        if (event.target.closest('[data-plant-pin], a, button')) {
            return;
        }
        dragging = true;
        lastX = event.clientX;
        lastY = event.clientY;
        viewport.setPointerCapture(event.pointerId);
        viewport.classList.add('is-dragging');
    });

    viewport.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }
        x += event.clientX - lastX;
        y += event.clientY - lastY;
        lastX = event.clientX;
        lastY = event.clientY;
        apply();
    });

    const endDrag = (event) => {
        if (!dragging) {
            return;
        }
        dragging = false;
        viewport.classList.remove('is-dragging');
        try {
            viewport.releasePointerCapture(event.pointerId);
        } catch (_error) {
            // ignore
        }
    };

    viewport.addEventListener('pointerup', endDrag);
    viewport.addEventListener('pointercancel', endDrag);

    const openModal = (pin) => {
        if (!modal || !pin) {
            return;
        }
        modal.querySelector('[data-plant-modal-kind]').textContent = String(pin.kind || 'item').replace(/_/g, ' ');
        modal.querySelector('[data-plant-modal-title]').textContent = pin.title || pin.label || 'Item';
        modal.querySelector('[data-plant-modal-sub]').textContent = pin.subtitle || '';
        modal.querySelector('[data-plant-modal-status]').textContent = pin.status ? `Status: ${pin.status}` : '';
        modal.querySelector('[data-plant-modal-body]').textContent = pin.description || '';
        modal.querySelector('[data-plant-modal-meta]').textContent = pin.meta || '';
        const link = modal.querySelector('[data-plant-modal-link]');
        if (link) {
            link.href = pin.url || '#';
            link.hidden = !pin.url;
        }
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', 'open');
        }
    };

    root.querySelectorAll('[data-plant-pin]').forEach((node) => {
        const open = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const index = Number(node.dataset.pinIndex);
            openModal(pins[index]);
        };
        node.addEventListener('click', open);
        node.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                open(event);
            }
        });
    });

    apply();
}
