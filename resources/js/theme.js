const STORAGE_KEY = 'plantgrid.theme';
const CUSTOM_KEY = 'plantgrid.theme.custom';
const BG_STORAGE_KEY = 'plantgrid.theme.bg';
const BG_CUSTOM_KEY = 'plantgrid.theme.bg.custom';
export const THEME_DEFAULT = 'violet';
export const DEFAULT_HEX = '#7d259d';
export const BG_DEFAULT = 'mist';
export const BG_DEFAULT_HEX = '#eef1f4';

export const THEMES = [
    { id: 'violet', label: 'Violet', swatch: '#7d259d' },
    { id: 'rose', label: 'Rose', swatch: '#b30068' },
    { id: 'petrol', label: 'Petrol', swatch: '#0b8f9c' },
    { id: 'tide', label: 'Tide', swatch: '#0f5c6b' },
    { id: 'lagoon', label: 'Lagoon', swatch: '#148f77' },
    { id: 'ocean', label: 'Ocean', swatch: '#1a6b7a' },
    { id: 'copper', label: 'Copper', swatch: '#c46a2b' },
    { id: 'sand', label: 'Sand', swatch: '#a67c52' },
    { id: 'slate', label: 'Slate', swatch: '#3d5a80' },
    { id: 'graphite', label: 'Graphite', swatch: '#3a4550' },
    { id: 'ember', label: 'Ember', swatch: '#b54708' },
    { id: 'plum', label: 'Plum', swatch: '#6b4c7a' },
];

export const BACKGROUNDS = [
    { id: 'mist', label: 'Mist', swatch: '#eef1f4' },
    { id: 'white', label: 'White', swatch: '#ffffff' },
    { id: 'cloud', label: 'Cloud', swatch: '#e6ebf0' },
    { id: 'cool', label: 'Cool', swatch: '#e6eef2' },
    { id: 'warm', label: 'Warm', swatch: '#f0ebe4' },
    { id: 'lilac', label: 'Lilac', swatch: '#f3eef6' },
    { id: 'mint', label: 'Mint', swatch: '#e8f2f0' },
    { id: 'blush', label: 'Blush', swatch: '#f6eef2' },
    { id: 'sandstone', label: 'Sandstone', swatch: '#f3efe8' },
    { id: 'ink', label: 'Ink', swatch: '#1c2430' },
];

const FAVICON_SVG = (color, ink) => `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <rect width="512" height="512" fill="${color}"/>
  <path fill="${ink}" fill-rule="evenodd" d="M132 404 L132 108 L318 108 C414 108 464 157 464 239 C464 322 412 369 318 369 L218 369 L218 404 Z M218 291 L310 291 C354 291 377 274 377 239 C377 204 354 186 310 186 L218 186 Z"/>
</svg>`;

function clamp(n) {
    return Math.max(0, Math.min(255, Math.round(n)));
}

function hexToRgb(hex) {
    const clean = hex.replace('#', '').trim();
    const full = clean.length === 3
        ? clean.split('').map((c) => c + c).join('')
        : clean;
    const int = Number.parseInt(full, 16);
    return {
        r: (int >> 16) & 255,
        g: (int >> 8) & 255,
        b: int & 255,
    };
}

function rgbToHex({ r, g, b }) {
    return `#${[r, g, b].map((v) => clamp(v).toString(16).padStart(2, '0')).join('')}`;
}

function mix(hex, target, amount) {
    const a = hexToRgb(hex);
    const b = hexToRgb(target);
    return rgbToHex({
        r: a.r + (b.r - a.r) * amount,
        g: a.g + (b.g - a.g) * amount,
        b: a.b + (b.b - a.b) * amount,
    });
}

function channelLuminance(value) {
    const channel = value / 255;
    return channel <= 0.03928
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4;
}

export function relativeLuminance(hex) {
    const { r, g, b } = hexToRgb(hex);
    return (
        0.2126 * channelLuminance(r)
        + 0.7152 * channelLuminance(g)
        + 0.0722 * channelLuminance(b)
    );
}

export function isLightColor(hex) {
    return relativeLuminance(hex) > 0.58;
}

export function themeTokens(hex) {
    const light = isLightColor(hex);
    return {
        navy: hex,
        deep: mix(hex, '#000000', light ? 0.35 : 0.28),
        soft: mix(hex, '#ffffff', light ? 0.78 : 0.88),
        onAccent: light ? '#152028' : '#ffffff',
        onAccentMuted: light ? 'rgba(21, 32, 40, 0.72)' : 'rgba(255, 255, 255, 0.78)',
        logoFill: hex,
        logoInk: light ? '#152028' : '#ffffff',
        authLogoFill: light ? '#152028' : '#ffffff',
        authLogoInk: hex,
    };
}

function normalizeHex(value, fallback) {
    if (value && /^#([0-9a-f]{6}|[0-9a-f]{3})$/i.test(value)) {
        return value.length === 4
            ? `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`
            : value;
    }
    return fallback;
}

export function readCustomHex() {
    try {
        return normalizeHex(localStorage.getItem(CUSTOM_KEY), DEFAULT_HEX);
    } catch (_error) {
        return DEFAULT_HEX;
    }
}

export function readTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'custom' || THEMES.some((theme) => theme.id === stored)) {
            return stored;
        }
    } catch (_error) {
        // ignore
    }
    return THEME_DEFAULT;
}

export function readCustomBgHex() {
    try {
        return normalizeHex(localStorage.getItem(BG_CUSTOM_KEY), BG_DEFAULT_HEX);
    } catch (_error) {
        return BG_DEFAULT_HEX;
    }
}

export function readBackground() {
    try {
        const stored = localStorage.getItem(BG_STORAGE_KEY);
        if (stored === 'custom' || BACKGROUNDS.some((bg) => bg.id === stored)) {
            return stored;
        }
    } catch (_error) {
        // ignore
    }
    return BG_DEFAULT;
}

function themeHex(themeId, customHex) {
    if (themeId === 'custom') {
        return customHex;
    }
    return THEMES.find((theme) => theme.id === themeId)?.swatch || DEFAULT_HEX;
}

const CONTRAST_KEYS = [
    '--pg-navy',
    '--pg-navy-deep',
    '--pg-teal',
    '--pg-teal-soft',
    '--pg-success',
    '--pg-info',
    '--pg-home-blue',
    '--pg-home-blue-deep',
    '--pg-on-accent',
    '--pg-on-accent-muted',
    '--pg-logo-fill',
    '--pg-logo-ink',
    '--pg-auth-logo-fill',
    '--pg-auth-logo-ink',
];

function clearInlineThemeVars(root) {
    CONTRAST_KEYS.forEach((key) => root.style.removeProperty(key));
}

function applyTokenVars(hex, { forceInline = false } = {}) {
    const root = document.documentElement;
    const tokens = themeTokens(hex);

    // Contrast + logo tokens always applied so light accents stay readable.
    root.style.setProperty('--pg-on-accent', tokens.onAccent);
    root.style.setProperty('--pg-on-accent-muted', tokens.onAccentMuted);
    root.style.setProperty('--pg-logo-fill', tokens.logoFill);
    root.style.setProperty('--pg-logo-ink', tokens.logoInk);
    root.style.setProperty('--pg-auth-logo-fill', tokens.authLogoFill);
    root.style.setProperty('--pg-auth-logo-ink', tokens.authLogoInk);
    root.classList.toggle('pg-theme-light-accent', isLightColor(hex));

    if (forceInline) {
        root.style.setProperty('--pg-navy', tokens.navy);
        root.style.setProperty('--pg-navy-deep', tokens.deep);
        root.style.setProperty('--pg-teal', tokens.navy);
        root.style.setProperty('--pg-teal-soft', tokens.soft);
        root.style.setProperty('--pg-success', tokens.navy);
        root.style.setProperty('--pg-info', tokens.navy);
        root.style.setProperty('--pg-home-blue', tokens.navy);
        root.style.setProperty('--pg-home-blue-deep', tokens.deep);
    }

    return tokens;
}

function activeAccentColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--pg-navy').trim() || DEFAULT_HEX;
}

export function updateBrandFavicon(color = activeAccentColor(), ink) {
    const tokens = themeTokens(color);
    const mark = ink || tokens.logoInk;
    const href = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(FAVICON_SVG(color, mark))}`;
    document.querySelectorAll('link[rel="icon"], link[rel="apple-touch-icon"]').forEach((link) => {
        link.setAttribute('href', href);
        link.setAttribute('type', 'image/svg+xml');
        link.removeAttribute('sizes');
    });
}

function backgroundHex(bgId, customHex) {
    if (bgId === 'custom') {
        return customHex;
    }
    return BACKGROUNDS.find((bg) => bg.id === bgId)?.swatch || BG_DEFAULT_HEX;
}

function syncPickerUi(themeId, customHex, bgId = readBackground(), customBgHex = readCustomBgHex()) {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        const active = button.dataset.themeOption === themeId;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    document.querySelectorAll('[data-theme-custom]').forEach((input) => {
        input.value = customHex;
        input.classList.toggle('is-active', themeId === 'custom');
    });

    document.querySelectorAll('[data-theme-custom-swatch]').forEach((swatch) => {
        swatch.style.setProperty('--pg-swatch', customHex);
        swatch.classList.toggle('is-active', themeId === 'custom');
        swatch.setAttribute('aria-pressed', themeId === 'custom' ? 'true' : 'false');
    });

    document.querySelectorAll('[data-bg-option]').forEach((button) => {
        const active = button.dataset.bgOption === bgId;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    document.querySelectorAll('[data-bg-custom]').forEach((input) => {
        input.value = customBgHex;
        input.classList.toggle('is-active', bgId === 'custom');
    });

    document.querySelectorAll('[data-bg-custom-swatch]').forEach((swatch) => {
        swatch.style.setProperty('--pg-swatch', customBgHex);
        swatch.classList.toggle('is-active', bgId === 'custom');
        swatch.setAttribute('aria-pressed', bgId === 'custom' ? 'true' : 'false');
    });
}

export function applyBackground(bgId, customHex = readCustomBgHex()) {
    const background = bgId === 'custom' || BACKGROUNDS.some((item) => item.id === bgId)
        ? bgId
        : BG_DEFAULT;
    const hex = backgroundHex(background, customHex);
    const root = document.documentElement;
    const light = isLightColor(hex);

    root.setAttribute('data-pg-bg', background);
    root.style.setProperty('--pg-bg', hex);
    root.classList.toggle('pg-theme-dark-bg', !light);

    if (!light) {
        root.style.setProperty('--pg-text', '#e8eef3');
        root.style.setProperty('--pg-muted', '#9aa7b4');
        root.style.setProperty('--pg-card', mix(hex, '#ffffff', 0.08));
        root.style.setProperty('--pg-border', mix(hex, '#ffffff', 0.16));
    } else {
        root.style.removeProperty('--pg-text');
        root.style.removeProperty('--pg-muted');
        root.style.removeProperty('--pg-card');
        root.style.removeProperty('--pg-border');
    }

    try {
        localStorage.setItem(BG_STORAGE_KEY, background);
        if (background === 'custom') {
            localStorage.setItem(BG_CUSTOM_KEY, customHex);
        }
    } catch (_error) {
        // ignore
    }

    syncPickerUi(readTheme(), readCustomHex(), background, customHex);

    return background;
}

export function applyTheme(themeId, customHex = readCustomHex()) {
    const theme = themeId === 'custom' || THEMES.some((item) => item.id === themeId)
        ? themeId
        : THEME_DEFAULT;
    const root = document.documentElement;
    const hex = themeHex(theme, customHex);
    root.setAttribute('data-pg-theme', theme);

    if (theme === 'custom') {
        applyTokenVars(hex, { forceInline: true });
    } else {
        clearInlineThemeVars(root);
        // Re-apply contrast tokens after CSS theme vars resolve.
        requestAnimationFrame(() => {
            const accent = activeAccentColor();
            applyTokenVars(accent);
            updateBrandFavicon(accent);
        });
        applyTokenVars(hex);
    }

    try {
        localStorage.setItem(STORAGE_KEY, theme);
        if (theme === 'custom') {
            localStorage.setItem(CUSTOM_KEY, customHex);
        }
    } catch (_error) {
        // ignore
    }

    syncPickerUi(theme, customHex);
    updateBrandFavicon(hex);

    return theme;
}

export function initTheme() {
    applyTheme(readTheme(), readCustomHex());
    applyBackground(readBackground(), readCustomBgHex());

    document.querySelectorAll('[data-theme-picker]').forEach((picker) => {
        picker.addEventListener('click', (event) => {
            const button = event.target.closest('[data-theme-option]');
            if (button) {
                event.preventDefault();
                applyTheme(button.dataset.themeOption);
                return;
            }

            const customButton = event.target.closest('[data-theme-custom-swatch]');
            if (customButton) {
                event.preventDefault();
                const input = picker.querySelector('[data-theme-custom]');
                input?.click();
                return;
            }

            const bgButton = event.target.closest('[data-bg-option]');
            if (bgButton) {
                event.preventDefault();
                applyBackground(bgButton.dataset.bgOption);
                return;
            }

            const bgCustomButton = event.target.closest('[data-bg-custom-swatch]');
            if (bgCustomButton) {
                event.preventDefault();
                const input = picker.querySelector('[data-bg-custom]');
                input?.click();
            }
        });

        const colorInput = picker.querySelector('[data-theme-custom]');
        colorInput?.addEventListener('input', (event) => {
            applyTheme('custom', event.target.value);
        });
        colorInput?.addEventListener('change', (event) => {
            applyTheme('custom', event.target.value);
        });

        const bgInput = picker.querySelector('[data-bg-custom]');
        bgInput?.addEventListener('input', (event) => {
            applyBackground('custom', event.target.value);
        });
        bgInput?.addEventListener('change', (event) => {
            applyBackground('custom', event.target.value);
        });
    });
}
