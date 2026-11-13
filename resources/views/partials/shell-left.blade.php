<aside class="pg-rail pg-rail-left" id="shell-left" aria-label="Navigation">
    <div class="pg-rail-resize" data-rail-resize="left" title="Drag to resize"></div>
    <div class="pg-rail-head">
        <div>
            <strong>Navigation</strong>
            <span>{{ auth()->user()->roleLabel() }}</span>
        </div>
        <button class="pg-rail-toggle" type="button" data-shell-toggle="left" aria-label="Collapse navigation">
            <i data-lucide="panel-left-close"></i>
        </button>
    </div>

    <div class="pg-rail-body" data-rail-sections="left">
        @if (($leftPanel['shortcuts'] ?? []) !== [])
            <div class="pg-rail-section" data-rail-section="shortcuts">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>Shortcuts</span>
                </div>
                <div class="pg-rail-shortcuts">
                    @foreach ($leftPanel['shortcuts'] as $item)
                        @php
                            $shortcutBadgeKey = $item['badge'] ?? null;
                            $shortcutCount = $shortcutBadgeKey ? (int) ($navBadges[$shortcutBadgeKey] ?? 0) : 0;
                        @endphp
                        <a
                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                            @if ($shortcutBadgeKey) data-nav-badge="{{ $shortcutBadgeKey }}" @endif
                        >
                            <i data-lucide="{{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                            @if ($shortcutCount > 0)
                                <em class="pg-nav-count">{{ $shortcutCount }}</em>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="pg-rail-section pg-rail-nav" data-rail-section="modules">
            <div class="pg-rail-label">
                <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                    <i data-lucide="grip-vertical" aria-hidden="true"></i>
                </button>
                <span>Modules</span>
            </div>
            @foreach ($leftPanel['groups'] as $heading => $items)
                <div class="pg-rail-group">
                    <div class="pg-rail-group-title">{{ $heading }}</div>
                    @foreach ($items as $item)
                        @php
                            $badgeKey = $item['badge'] ?? null;
                            $badgeCount = $badgeKey ? (int) ($navBadges[$badgeKey] ?? 0) : 0;
                            $statusKey = ($item['status'] ?? null) === 'monitoring' ? ($monitoringStatus ?? $navBadges['monitoring_status'] ?? 'hidden') : null;
                            $isActive = \App\Support\Navigation::isActive($item);
                        @endphp
                        <a
                            class="pg-rail-link {{ $isActive ? 'active' : '' }}"
                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                            @if ($badgeKey) data-nav-badge="{{ $badgeKey }}" @endif
                            @if ($statusKey && $statusKey !== 'hidden') data-nav-status="monitoring" data-status="{{ $statusKey }}" @endif
                        >
                            <i data-lucide="{{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                            @if ($badgeCount > 0)
                                <em class="pg-nav-count">{{ $badgeCount }}</em>
                            @elseif ($statusKey && $statusKey !== 'hidden')
                                <i class="pg-nav-status pg-nav-status-{{ $statusKey }}" title="Simulated monitoring: {{ $statusKey }}" aria-label="Simulated monitoring {{ $statusKey }}"></i>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="pg-rail-section" data-rail-section="recents">
            <div class="pg-rail-label">
                <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                    <i data-lucide="grip-vertical" aria-hidden="true"></i>
                </button>
                <span>Recent cases</span>
            </div>
            @forelse ($leftPanel['recents'] as $ticket)
                <a class="pg-rail-recent" href="{{ route('helpdesk.show', $ticket) }}">
                    <span class="pg-mono">{{ $ticket->ticket_number }}</span>
                    <strong>{{ $ticket->subject }}</strong>
                    <span>{{ \App\Support\Labels::status($ticket->status) }}</span>
                </a>
            @empty
                <p class="pg-rail-empty">No recent cases in your view.</p>
            @endforelse
        </div>
    </div>
</aside>
