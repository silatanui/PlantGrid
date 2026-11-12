<aside class="pg-rail pg-rail-left" id="shell-left" aria-label="Navigation">
    <div class="pg-rail-head">
        <div>
            <strong>Navigation</strong>
            <span>{{ auth()->user()->roleLabel() }}</span>
        </div>
        <button class="pg-rail-toggle" type="button" data-shell-toggle="left" aria-label="Collapse navigation">
            <i data-lucide="panel-left-close"></i>
        </button>
    </div>

    @php
        $badgeForRoute = [
            'helpdesk.index' => 'cases',
            'helpdesk.create' => null,
            'messages.index' => 'messages',
            'monitoring.index' => 'alerts',
        ];
    @endphp

    @if (($leftPanel['shortcuts'] ?? []) !== [])
        <div class="pg-rail-section">
            <div class="pg-rail-label">Shortcuts</div>
            <div class="pg-rail-shortcuts">
                @foreach ($leftPanel['shortcuts'] as $item)
                    @php
                        $shortcutBadgeKey = ($item['route'] ?? '') === 'helpdesk.index' ? 'cases' : null;
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

    <div class="pg-rail-section pg-rail-nav">
        <div class="pg-rail-label">Modules</div>
        @foreach ($leftPanel['groups'] as $heading => $items)
            <div class="pg-rail-group">
                <div class="pg-rail-group-title">{{ $heading }}</div>
                @foreach ($items as $item)
                    @php
                        $badgeKey = $badgeForRoute[$item['route'] ?? ''] ?? null;
                        $badgeCount = $badgeKey ? (int) ($navBadges[$badgeKey] ?? 0) : 0;
                    @endphp
                    <a
                        class="pg-rail-link {{ request()->routeIs($item['pattern']) ? 'active' : '' }}"
                        href="{{ route($item['route']) }}"
                        @if ($badgeKey) data-nav-badge="{{ $badgeKey }}" @endif
                    >
                        <i data-lucide="{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if ($badgeCount > 0)
                            <em class="pg-nav-count">{{ $badgeCount }}</em>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="pg-rail-section">
        <div class="pg-rail-label">Recent cases</div>
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
</aside>
