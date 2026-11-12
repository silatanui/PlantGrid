import 'bootstrap';
import { Chart, registerables } from 'chart.js';
import { createIcons, icons } from 'lucide';
import { initAiAssistant } from './ai-assistant';
import { initGlobalSearch } from './global-search';
import { initMentions } from './mentions';
import { initLiveNotes } from './live-notes';
import { initPresence } from './presence';
import { initLiveInbox } from './live-inbox';
import { initFormatToolbars } from './format-toolbar';
import { initTheme } from './theme';
import { initWorkspaceTabs } from './workspace-tabs';
import { initAttachPreview } from './attach-preview';
import { initCaseTriage } from './case-triage';
import { initFormDrafts } from './form-drafts';

Chart.register(...registerables);
Chart.defaults.font.family = 'system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
Chart.defaults.color = '#706E6B';
Chart.defaults.borderColor = '#C9C9C9';

document.addEventListener('DOMContentLoaded', () => {
    const refreshIcons = () => createIcons({ icons });
    initTheme();
    refreshIcons();
    initAiAssistant(refreshIcons);
    initGlobalSearch();
    initMentions();
    initLiveNotes(refreshIcons);
    initPresence();
    initLiveInbox();
    initFormatToolbars();
    initWorkspaceTabs(refreshIcons);
    initAttachPreview(refreshIcons);
    initCaseTriage(refreshIcons);
    initFormDrafts();

    const emailInput = document.querySelector('#email');
    const demoButtons = () => document.querySelectorAll('[data-demo-email]');
    demoButtons().forEach((button) => {
        button.addEventListener('click', () => {
            const email = button.dataset.demoEmail;
            if (emailInput) {
                emailInput.value = email;
                emailInput.focus();
            }
            demoButtons().forEach((item) => {
                item.classList.toggle('active', item.dataset.demoEmail === email);
            });
        });
    });

    const shell = document.body.classList.contains('pg-shell') ? document.body : null;
    const shellMask = document.querySelector('[data-shell-mask]');
    const shellKey = (side) => `plantgrid.shell.${side}`;
    const desktopLeft = () => window.matchMedia('(min-width: 1200px)').matches;
    const desktopRight = () => window.matchMedia('(min-width: 1400px)').matches;

    if (shell) {
        const closeOverlays = () => {
            shell.classList.remove('pg-left-open', 'pg-right-open');
            if (shellMask) {
                shellMask.hidden = true;
            }
        };

        const storedCollapsed = (side) => localStorage.getItem(shellKey(side)) === '1';

        const syncShellForViewport = () => {
            // Narrow viewports force rails into overlay mode. Without a resize sync,
            // that temporary collapsed class sticks when Chrome grows past the breakpoint
            // and the desktop CSS then hides the sidebar with display:none.
            closeOverlays();

            if (desktopLeft()) {
                shell.classList.toggle('pg-left-collapsed', storedCollapsed('left'));
            } else {
                shell.classList.add('pg-left-collapsed');
            }

            if (desktopRight()) {
                shell.classList.toggle('pg-right-collapsed', storedCollapsed('right'));
            } else {
                shell.classList.add('pg-right-collapsed');
            }

            createIcons({ icons });
        };

        const applyShellSide = (side, collapsed) => {
            shell.classList.toggle(`pg-${side}-collapsed`, collapsed);
            localStorage.setItem(shellKey(side), collapsed ? '1' : '0');
            if (side === 'left' && !desktopLeft()) {
                shell.classList.toggle('pg-left-open', !collapsed);
                if (shellMask) {
                    shellMask.hidden = collapsed && !shell.classList.contains('pg-right-open');
                }
            }
            if (side === 'right' && !desktopRight()) {
                shell.classList.toggle('pg-right-open', !collapsed);
                if (shellMask) {
                    shellMask.hidden = collapsed && !shell.classList.contains('pg-left-open');
                }
            }
            createIcons({ icons });
        };

        syncShellForViewport();
        window.matchMedia('(min-width: 1200px)').addEventListener('change', syncShellForViewport);
        window.matchMedia('(min-width: 1400px)').addEventListener('change', syncShellForViewport);

        document.querySelectorAll('[data-shell-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const side = button.dataset.shellToggle;
                const isDesktop = side === 'left' ? desktopLeft() : desktopRight();
                if (isDesktop) {
                    applyShellSide(side, !shell.classList.contains(`pg-${side}-collapsed`));
                    return;
                }
                const openClass = `pg-${side}-open`;
                const opening = !shell.classList.contains(openClass);
                shell.classList.remove('pg-left-open', 'pg-right-open');
                if (opening) {
                    shell.classList.add(openClass);
                    shell.classList.remove(`pg-${side}-collapsed`);
                    if (shellMask) {
                        shellMask.hidden = false;
                    }
                } else {
                    shell.classList.add(`pg-${side}-collapsed`);
                    if (shellMask) {
                        shellMask.hidden = true;
                    }
                }
                createIcons({ icons });
            });
        });

        shellMask?.addEventListener('click', closeOverlays);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeOverlays();
            }
        });
    }

    document.querySelectorAll('[data-deadline-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.deadlineFilter;
            document.querySelectorAll('[data-deadline-filter]').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            document.querySelectorAll('[data-deadline]').forEach((card) => {
                card.hidden = kind !== 'all' && card.dataset.deadline !== kind;
            });
        });
    });

    const themeColor = (name, fallback) => {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    };

    const paintChartColors = (value, palette) => {
        if (Array.isArray(value)) {
            return value.map((item) => paintChartColors(item, palette));
        }
        if (typeof value !== 'string') {
            return value;
        }
        return palette[value] || value;
    };

    const bindChartTickCallbacks = (options) => {
        if (!options?.scales) {
            return;
        }
        Object.values(options.scales).forEach((scale) => {
            if (scale?.ticks?.callback === '__pct') {
                scale.ticks.callback = (tickValue) => `${tickValue}%`;
            }
        });
    };

    document.querySelectorAll('[data-chart]').forEach((node) => {
        const canvas = node.querySelector('canvas');
        if (!canvas || !node.dataset.chart) {
            return;
        }

        const accent = themeColor('--pg-navy', '#7d259d');
        const deep = themeColor('--pg-navy-deep', '#5a1a72');
        const soft = themeColor('--pg-teal-soft', '#f3e9f7');
        const muted = themeColor('--pg-muted', '#5a6773');
        const warning = themeColor('--pg-warning', '#b54708');
        const success = themeColor('--pg-success', '#0b8f9c');
        const critical = themeColor('--pg-critical', '#b42318');
        const info = themeColor('--pg-info', accent);
        const palette = {
            'pg-accent': accent,
            'pg-deep': deep,
            'pg-accent-soft': soft,
            'pg-muted': muted,
            'pg-warning': warning,
            'pg-success': success,
            'pg-critical': critical,
            'pg-info': info,
        };

        const config = JSON.parse(node.dataset.chart);
        config.options = {
            responsive: true,
            maintainAspectRatio: false,
            ...(config.options || {}),
        };
        bindChartTickCallbacks(config.options);
        config.data?.datasets?.forEach((dataset) => {
            if (dataset.backgroundColor !== undefined) {
                dataset.backgroundColor = paintChartColors(dataset.backgroundColor, palette);
            }
            if (dataset.borderColor !== undefined) {
                dataset.borderColor = paintChartColors(dataset.borderColor, palette);
            }
            if (dataset.borderRadius === undefined && config.type === 'bar') {
                dataset.borderRadius = 6;
                dataset.borderSkipped = false;
            }
            if (dataset.maxBarThickness === undefined && config.type === 'bar') {
                dataset.maxBarThickness = 44;
            }
        });

        const centerTotal = node.dataset.chartTotal;
        if (centerTotal !== undefined && centerTotal !== '' && config.type === 'doughnut') {
            const centerLabel = node.dataset.chartTotalLabel || '';
            const centerPlugin = {
                id: `pg-center-${Math.random().toString(36).slice(2, 8)}`,
                afterDraw(chart) {
                    const { ctx, chartArea } = chart;
                    if (!chartArea) {
                        return;
                    }
                    const x = (chartArea.left + chartArea.right) / 2;
                    const y = (chartArea.top + chartArea.bottom) / 2;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = accent;
                    ctx.font = '700 28px IBM Plex Sans, system-ui, sans-serif';
                    ctx.fillText(String(centerTotal), x, y - (centerLabel ? 8 : 0));
                    if (centerLabel) {
                        ctx.fillStyle = muted;
                        ctx.font = '700 10px IBM Plex Sans, system-ui, sans-serif';
                        ctx.fillText(centerLabel.toUpperCase(), x, y + 14);
                    }
                    ctx.restore();
                },
            };
            config.plugins = [...(config.plugins || []), centerPlugin];
        }

        Chart.defaults.color = muted;
        Chart.defaults.borderColor = 'rgba(18, 38, 63, 0.08)';
        Chart.defaults.font.family = 'IBM Plex Sans, system-ui, sans-serif';
        new Chart(canvas, config);
    });

    document.querySelectorAll('[data-layer-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.layerFilter;
            document.querySelectorAll('[data-layer-filter]').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            document.querySelectorAll('[data-layer-board]').forEach((board) => {
                board.hidden = board.dataset.layerBoard !== kind;
            });
        });
    });

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const packetFlows = new Map();

    const createPacketFlow = (svg) => {
        if (!svg || reduceMotion) {
            return null;
        }

        let layer = svg.querySelector('[data-packet-layer]');
        if (!layer) {
            layer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            layer.setAttribute('data-packet-layer', '');
            svg.appendChild(layer);
        }

        let running = true;
        let frame = null;
        let startedAt = performance.now();
        let packets = [];

        const rebuild = () => {
            layer.innerHTML = '';
            packets = [];
            svg.querySelectorAll('.pg-map-edge path').forEach((path, edgeIndex) => {
                const edge = path.closest('.pg-map-edge');
                const impacted = edge?.classList.contains('is-impacted');
                const length = path.getTotalLength();
                if (!length) {
                    return;
                }

                const specs = impacted
                    ? [{ offset: 0.35, reverse: false, blocked: true, size: 3.2, speed: 0 }]
                    : [
                        { offset: 0, reverse: false, blocked: false, size: 3.4, speed: 220 + (edgeIndex % 3) * 35 },
                        { offset: 0.38, reverse: false, blocked: false, size: 2.6, speed: 180 + (edgeIndex % 2) * 40 },
                        { offset: 0.18, reverse: true, blocked: false, size: 2.4, speed: 160 + (edgeIndex % 4) * 20 },
                    ];

                specs.forEach((spec) => {
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('r', String(spec.size));
                    circle.classList.add('pg-map-packet');
                    if (spec.reverse) {
                        circle.classList.add('is-return');
                    }
                    if (spec.blocked) {
                        circle.classList.add('is-blocked');
                    }
                    layer.appendChild(circle);
                    packets.push({
                        circle,
                        path,
                        length,
                        offset: spec.offset,
                        reverse: spec.reverse,
                        blocked: spec.blocked,
                        speed: spec.speed,
                    });
                });
            });
        };

        const place = (packet, progress) => {
            const distance = packet.reverse
                ? packet.length * (1 - progress)
                : packet.length * progress;
            const point = packet.path.getPointAtLength(Math.max(0, Math.min(packet.length, distance)));
            packet.circle.setAttribute('cx', String(point.x));
            packet.circle.setAttribute('cy', String(point.y));
        };

        const tick = (now) => {
            if (!running) {
                frame = null;
                return;
            }
            const elapsed = (now - startedAt) / 1000;
            packets.forEach((packet) => {
                if (packet.blocked) {
                    place(packet, packet.offset);
                    return;
                }
                const cycle = packet.length / Math.max(40, packet.speed);
                const progress = (elapsed / cycle + packet.offset) % 1;
                place(packet, progress);
            });
            frame = requestAnimationFrame(tick);
        };

        const start = () => {
            if (running && frame !== null) {
                return;
            }
            running = true;
            startedAt = performance.now();
            if (frame === null) {
                frame = requestAnimationFrame(tick);
            }
        };

        const stop = () => {
            running = false;
            if (frame !== null) {
                cancelAnimationFrame(frame);
                frame = null;
            }
        };

        rebuild();
        start();

        return {
            rebuild,
            start,
            stop,
            isRunning: () => running,
            setRunning: (value) => {
                if (value) {
                    start();
                } else {
                    stop();
                }
            },
        };
    };

    const syncPacketToggle = (button, flow) => {
        if (!button || !flow) {
            return;
        }
        const running = flow.isRunning();
        button.setAttribute('aria-pressed', running ? 'true' : 'false');
        button.textContent = running ? 'Pause packets' : 'Play packets';
        button.classList.toggle('btn-pg', running);
        button.classList.toggle('btn-pg-line', !running);
    };

    document.querySelectorAll('[data-packet-flow]').forEach((svg) => {
        const flow = createPacketFlow(svg);
        if (!flow) {
            return;
        }
        packetFlows.set(svg, flow);
        const section = svg.closest('section') || document;
        const toggle = section.querySelector('[data-packet-toggle]');
        syncPacketToggle(toggle, flow);
        toggle?.addEventListener('click', () => {
            flow.setRunning(!flow.isRunning());
            syncPacketToggle(toggle, flow);
        });
    });

    const topologyHost = document.querySelector('[data-topology-graph]');
    const topologyCanvas = document.querySelector('[data-topology-canvas]');
    if (topologyHost && topologyCanvas && topologyHost.dataset.topologyGraph) {
        const graph = JSON.parse(topologyHost.dataset.topologyGraph);
        const positions = {};
        const nodeWidth = 188;
        const nodeHeight = 72;
        const canvasWidth = Number(graph.physical?.width || 1080);
        const canvasHeight = Number(graph.physical?.height || 420);
        let packetFlow = packetFlows.get(topologyCanvas) || createPacketFlow(topologyCanvas);
        if (packetFlow) {
            packetFlows.set(topologyCanvas, packetFlow);
        }

        const seedPositions = () => {
            (graph.physical?.nodes || graph.nodes || []).forEach((node) => {
                positions[node.id] = {
                    x: node.x ?? 40 + (Object.keys(positions).length % 4) * 200,
                    y: node.y ?? 40 + Math.floor(Object.keys(positions).length / 4) * 110,
                };
            });
        };

        const edgePath = (source, target, edge = {}) => {
            const x1 = source.x + nodeWidth / 2;
            const y1 = source.y + nodeHeight;
            const x2 = target.x + nodeWidth / 2;
            const y2 = target.y;
            const midY = Math.round(y1 + Math.max(18, (y2 - y1) * 0.28));
            const labelType = edge.type_label || '';
            const labelPort = edge.port || '';
            const label = labelPort ? `${labelType} · ${labelPort}` : labelType;
            const labelW = Math.max(78, Math.min(126, 18 + label.length * 6.1));
            const labelH = labelPort ? 28 : 18;
            return {
                d: `M ${x1} ${y1} L ${x1} ${midY} L ${x2} ${midY} L ${x2} ${y2}`,
                labelType,
                labelPort,
                labelX: x2,
                labelY: Math.round(midY + ((y2 - midY) / 2)),
                labelW,
                labelH,
            };
        };

        const iconShapes = {
            firewall: [
                ['path', 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z'],
            ],
            router: [
                ['rect', { x: 2, y: 14, width: 20, height: 8, rx: 2 }],
                ['path', 'M6.01 18H6'],
                ['path', 'M10.01 18H10'],
                ['path', 'M15 10v4'],
                ['path', 'M17.84 7.17a4 4 0 0 0-5.66 0'],
                ['path', 'M20.66 4.34a8 8 0 0 0-11.31 0'],
            ],
            switch: [
                ['rect', { x: 16, y: 16, width: 6, height: 6, rx: 1 }],
                ['rect', { x: 2, y: 16, width: 6, height: 6, rx: 1 }],
                ['rect', { x: 9, y: 2, width: 6, height: 6, rx: 1 }],
                ['path', 'M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3'],
                ['path', 'M12 12V8'],
            ],
            wireless_access_point: [
                ['path', 'M12 20h.01'],
                ['path', 'M2 8.82a15 15 0 0 1 20 0'],
                ['path', 'M5 12.859a10 10 0 0 1 14 0'],
                ['path', 'M8.5 16.429a5 5 0 0 1 7 0'],
            ],
            default: [
                ['rect', { x: 2, y: 6, width: 20, height: 12, rx: 2 }],
                ['path', 'M6 12h.01'],
                ['path', 'M10 12h.01'],
            ],
        };

        const deviceIconMarkup = (type, cx, cy, accent, size = 20) => {
            const shapes = iconShapes[type] || iconShapes.default;
            const scale = size / 24;
            const parts = shapes.map((shape) => {
                if (shape[0] === 'path') {
                    return `<path d="${shape[1]}"></path>`;
                }
                const attrs = Object.entries(shape[1] || {})
                    .map(([name, value]) => `${name}="${value}"`)
                    .join(' ');
                return `<${shape[0]} ${attrs}></${shape[0]}>`;
            }).join('');

            return `<g class="pg-map-device-icon" transform="translate(${cx} ${cy})">`
                + `<circle class="pg-map-device-icon-bg" cx="0" cy="0" r="16" fill="${accent}"></circle>`
                + `<g fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" transform="translate(${-size / 2} ${-size / 2}) scale(${scale})">`
                + `${parts}</g></g>`;
        };

        const draw = () => {
            const wasRunning = packetFlow?.isRunning() ?? true;
            const nodes = graph.nodes || [];
            const edges = graph.edges || [];
            const lanes = (graph.physical?.lanes || []).map((lane) => (
                `<rect class="pg-map-lane" x="0" y="${lane.y}" width="${canvasWidth}" height="${lane.height}"></rect>`
                + `<text class="pg-map-lane-label" x="14" y="${lane.y + 28}">${lane.label}</text>`
            )).join('');

            const lines = edges.map((edge) => {
                const source = positions[edge.source];
                const target = positions[edge.target];
                if (!source || !target) {
                    return '';
                }
                const path = edgePath(source, target, edge);
                const impacted = edge.impacted ? ' is-impacted' : '';
                return `<g class="pg-map-edge${impacted}"><path d="${path.d}"></path></g>`;
            }).join('');

            const labels = edges.map((edge) => {
                const source = positions[edge.source];
                const target = positions[edge.target];
                if (!source || !target) {
                    return '';
                }
                const path = edgePath(source, target, edge);
                const impacted = edge.impacted ? ' is-impacted' : '';
                const body = path.labelPort
                    ? `<text class="pg-map-edge-label-type" x="${path.labelX}" y="${path.labelY - 3}">${path.labelType}</text>`
                        + `<text class="pg-map-edge-label-port" x="${path.labelX}" y="${path.labelY + 9}">${path.labelPort}</text>`
                    : `<text class="pg-map-edge-label-type" x="${path.labelX}" y="${path.labelY + 3}">${path.labelType}</text>`;
                return `<g class="pg-map-edge-label${impacted}">`
                    + `<rect class="pg-map-edge-label-bg" x="${path.labelX - (path.labelW / 2)}" y="${path.labelY - (path.labelH / 2)}" width="${path.labelW}" height="${path.labelH}"></rect>`
                    + `${body}</g>`;
            }).join('');

            const boxes = nodes.map((node) => {
                const point = positions[node.id] || { x: 40, y: 40 };
                const accent = node.accent || '#005058';
                const alertClass = node.alert ? ' is-alert' : '';
                const location = node.location ? ` · ${node.location}` : '';
                const alertTag = node.alert
                    ? `<text class="pg-map-alert-tag" x="${point.x + nodeWidth - 8}" y="${point.y + 16}">OUTAGE</text>`
                    : '';
                return `<a class="pg-map-node-link" href="${node.url}" data-node-id="${node.id}">`
                    + `<rect class="pg-map-node${alertClass}" x="${point.x}" y="${point.y}" width="${nodeWidth}" height="${nodeHeight}"></rect>`
                    + deviceIconMarkup(node.type, point.x + 28, point.y + nodeHeight / 2, accent)
                    + `<text class="pg-map-host" x="${point.x + 52}" y="${point.y + 24}">${node.hostname}</text>`
                    + `<text class="pg-map-meta" x="${point.x + 52}" y="${point.y + 40}">${node.type_label}</text>`
                    + `<text class="pg-map-meta" x="${point.x + 52}" y="${point.y + 56}">${node.ip || '-'}${location}</text>`
                    + `${alertTag}</a>`;
            }).join('');

            topologyCanvas.setAttribute('viewBox', `0 0 ${canvasWidth} ${canvasHeight}`);
            topologyCanvas.innerHTML = `${lanes}${lines}${boxes}<g data-packet-layer></g><g class="pg-map-edge-labels">${labels}</g>`;
            if (!packetFlow) {
                packetFlow = createPacketFlow(topologyCanvas);
                if (packetFlow) {
                    packetFlows.set(topologyCanvas, packetFlow);
                }
            } else {
                packetFlow.rebuild();
                packetFlow.setRunning(wasRunning);
            }
            const section = topologyCanvas.closest('section') || document;
            syncPacketToggle(section.querySelector('[data-packet-toggle]'), packetFlow);
        };

        seedPositions();
        draw();

        let dragId = null;
        let offsetX = 0;
        let offsetY = 0;
        let moved = false;

        const scale = () => {
            const rect = topologyCanvas.getBoundingClientRect();
            return {
                x: canvasWidth / rect.width,
                y: canvasHeight / rect.height,
            };
        };

        topologyCanvas.addEventListener('pointerdown', (event) => {
            const link = event.target.closest('a[data-node-id]');
            if (!link) {
                return;
            }
            event.preventDefault();
            dragId = Number(link.dataset.nodeId);
            const point = positions[dragId];
            const factors = scale();
            offsetX = event.clientX * factors.x - point.x;
            offsetY = event.clientY * factors.y - point.y;
            moved = false;
            topologyHost.classList.add('is-dragging');
            topologyCanvas.setPointerCapture(event.pointerId);
        });

        topologyCanvas.addEventListener('pointermove', (event) => {
            if (dragId === null) {
                return;
            }
            const factors = scale();
            positions[dragId] = {
                x: Math.max(8, Math.min(canvasWidth - nodeWidth - 8, event.clientX * factors.x - offsetX)),
                y: Math.max(8, Math.min(canvasHeight - nodeHeight - 8, event.clientY * factors.y - offsetY)),
            };
            moved = true;
            draw();
        });

        topologyCanvas.addEventListener('pointerup', (event) => {
            if (dragId === null) {
                return;
            }
            topologyHost.classList.remove('is-dragging');
            topologyCanvas.releasePointerCapture(event.pointerId);
            if (moved) {
                event.preventDefault();
            }
            dragId = null;
        });

        topologyCanvas.addEventListener('click', (event) => {
            if (moved) {
                event.preventDefault();
                moved = false;
            }
        }, true);

        document.querySelector('[data-topology-reset]')?.addEventListener('click', () => {
            Object.keys(positions).forEach((key) => delete positions[key]);
            seedPositions();
            draw();
        });
    }

    if (document.body.classList.contains('pg-home')) {
        const homeBar = document.querySelector('[data-home-bar]');
        const parallax = document.querySelector('[data-home-parallax]');
        const syncHomeBar = () => {
            homeBar?.classList.toggle('is-scrolled', window.scrollY > 12);
        };
        syncHomeBar();
        window.addEventListener('scroll', syncHomeBar, { passive: true });

        if (parallax && !reduceMotion) {
            const syncParallax = () => {
                const offset = Math.min(80, window.scrollY * 0.18);
                parallax.style.transform = `scale(1.06) translate3d(0, ${offset}px, 0)`;
            };
            syncParallax();
            window.addEventListener('scroll', syncParallax, { passive: true });
        }

        const revealNodes = document.querySelectorAll('[data-home-reveal]');
        if (reduceMotion || !('IntersectionObserver' in window)) {
            revealNodes.forEach((node) => node.classList.add('is-in'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
            revealNodes.forEach((node) => observer.observe(node));
        }
    }
});
