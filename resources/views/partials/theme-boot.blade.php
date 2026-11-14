<script>
    (function () {
        try {
            var allowed = ['violet', 'rose', 'petrol', 'tide', 'lagoon', 'ocean', 'copper', 'sand', 'slate', 'graphite', 'ember', 'plum', 'custom'];
            var bgAllowed = ['mist', 'white', 'cloud', 'cool', 'warm', 'lilac', 'mint', 'blush', 'sandstone', 'ink', 'custom'];
            var theme = localStorage.getItem('plantgrid.theme') || 'violet';
            var custom = localStorage.getItem('plantgrid.theme.custom') || '#7d259d';
            var bg = localStorage.getItem('plantgrid.theme.bg') || 'mist';
            var customBg = localStorage.getItem('plantgrid.theme.bg.custom') || '#eef1f4';
            // Move previous default Rose sessions onto Violet once.
            if (theme === 'rose' && !localStorage.getItem('plantgrid.theme.moved-to-violet')) {
                theme = 'violet';
                localStorage.setItem('plantgrid.theme', 'violet');
                localStorage.setItem('plantgrid.theme.moved-to-violet', '1');
            }
            if (allowed.indexOf(theme) === -1) {
                theme = 'violet';
            }
            if (bgAllowed.indexOf(bg) === -1) {
                bg = 'mist';
            }
            document.documentElement.setAttribute('data-pg-theme', theme);
            document.documentElement.setAttribute('data-pg-bg', bg);

            function hexToRgb(hex) {
                var clean = hex.replace('#', '');
                if (clean.length === 3) {
                    clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
                }
                var n = parseInt(clean, 16);
                return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
            }
            function channel(v) {
                v = v / 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            }
            function isLight(hex) {
                var c = hexToRgb(hex);
                var L = 0.2126 * channel(c.r) + 0.7152 * channel(c.g) + 0.0722 * channel(c.b);
                return L > 0.58;
            }
            function mix(hex, target, amount) {
                var a = hexToRgb(hex), b = hexToRgb(target);
                return '#' + [a.r + (b.r - a.r) * amount, a.g + (b.g - a.g) * amount, a.b + (b.b - a.b) * amount].map(function (v) {
                    return Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0');
                }).join('');
            }
            function normalizeHex(value, fallback) {
                if (!value || !/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
                    return fallback;
                }
                return value.length === 4
                    ? '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3]
                    : value;
            }
            function applyContrast(hex, forceColors) {
                var light = isLight(hex);
                var root = document.documentElement.style;
                var onAccent = light ? '#152028' : '#ffffff';
                var onMuted = light ? 'rgba(21, 32, 40, 0.72)' : 'rgba(255, 255, 255, 0.78)';
                root.setProperty('--pg-on-accent', onAccent);
                root.setProperty('--pg-on-accent-muted', onMuted);
                root.setProperty('--pg-logo-fill', hex);
                root.setProperty('--pg-logo-ink', light ? '#152028' : '#ffffff');
                root.setProperty('--pg-auth-logo-fill', light ? '#152028' : '#ffffff');
                root.setProperty('--pg-auth-logo-ink', hex);
                document.documentElement.classList.toggle('pg-theme-light-accent', light);
                if (forceColors) {
                    root.setProperty('--pg-navy', hex);
                    root.setProperty('--pg-navy-deep', mix(hex, '#000000', light ? 0.35 : 0.28));
                    root.setProperty('--pg-teal', hex);
                    root.setProperty('--pg-teal-soft', mix(hex, '#ffffff', light ? 0.78 : 0.88));
                    root.setProperty('--pg-success', hex);
                    root.setProperty('--pg-info', hex);
                    root.setProperty('--pg-home-blue', hex);
                    root.setProperty('--pg-home-blue-deep', mix(hex, '#000000', light ? 0.35 : 0.28));
                }
            }
            function applyBackground(hex) {
                var root = document.documentElement.style;
                var light = isLight(hex);
                root.setProperty('--pg-bg', hex);
                document.documentElement.classList.toggle('pg-theme-dark-bg', !light);
                // Keep content cards/tables on a solid light surface so text stays readable.
                root.setProperty('--pg-card', '#ffffff');
                root.setProperty('--pg-border', '#c2cad2');
                root.removeProperty('--pg-text');
                root.removeProperty('--pg-muted');
            }

            var presets = {
                violet: '#7d259d', rose: '#b30068', petrol: '#0b8f9c', tide: '#0f5c6b', lagoon: '#148f77', ocean: '#1a6b7a',
                copper: '#c46a2b', sand: '#a67c52', slate: '#3d5a80', graphite: '#3a4550', ember: '#b54708', plum: '#6b4c7a'
            };
            var bgPresets = {
                mist: '#eef1f4', white: '#ffffff', cloud: '#e6ebf0', cool: '#e6eef2', warm: '#f0ebe4',
                lilac: '#f3eef6', mint: '#e8f2f0', blush: '#f6eef2', sandstone: '#f3efe8', ink: '#1c2430'
            };

            if (theme === 'custom' && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(custom)) {
                applyContrast(normalizeHex(custom, '#7d259d'), true);
            } else {
                applyContrast(presets[theme] || '#7d259d', false);
            }

            if (bg === 'custom') {
                applyBackground(normalizeHex(customBg, '#eef1f4'));
            } else {
                applyBackground(bgPresets[bg] || '#eef1f4');
            }
        } catch (e) {
            document.documentElement.setAttribute('data-pg-theme', 'violet');
            document.documentElement.setAttribute('data-pg-bg', 'mist');
        }
    })();
</script>
