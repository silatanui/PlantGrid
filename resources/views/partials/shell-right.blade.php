<aside class="pg-rail pg-rail-right" id="shell-right" aria-label="Utility">
    <div class="pg-rail-head">
        <div>
            <strong>Utility</strong>
            <span>{{ $plantName }}</span>
        </div>
        <button class="pg-rail-toggle" type="button" data-shell-toggle="right" aria-label="Collapse utility panel">
            <i data-lucide="panel-right-close"></i>
        </button>
    </div>

    @if (($rightPanel['stats'] ?? []) !== [])
        <div class="pg-rail-section">
            <div class="pg-rail-label">At a glance</div>
            <div class="pg-rail-stats">
                @foreach ($rightPanel['stats'] as $stat)
                    <div>
                        <strong>{{ $stat['value'] }}</strong>
                        <span>{{ $stat['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pg-rail-section">
        <div class="pg-rail-label">{{ $rightPanel['guidance']['title'] }}</div>
        <div class="pg-rail-guidance">
            <strong>{{ $rightPanel['guidance']['headline'] }}</strong>
            <p>{{ $rightPanel['guidance']['body'] }}</p>
            <ol>
                @foreach ($rightPanel['guidance']['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    </div>

    @if (($rightPanel['actions'] ?? []) !== [])
        <div class="pg-rail-section">
            <div class="pg-rail-label">Quick actions</div>
            <div class="pg-rail-actions">
                @foreach ($rightPanel['actions'] as $action)
                    <a class="{{ $action['tone'] === 'primary' ? 'btn btn-pg btn-sm' : 'btn btn-pg-line btn-sm' }}" href="{{ route($action['route']) }}">{{ $action['label'] }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pg-rail-section">
        <div class="pg-rail-label">Recent cases</div>
        @forelse ($rightPanel['activity'] as $item)
            <a class="pg-rail-activity" href="{{ $item['href'] }}">
                <strong>{{ $item['title'] }}</strong>
                <span>{{ $item['meta'] }}</span>
                <em>{{ $item['when'] }}</em>
            </a>
        @empty
            <p class="pg-rail-empty">Cases you open, note, or update will appear here for quick jumps.</p>
        @endforelse
    </div>

    @if (($rightPanel['alerts'] ?? collect())->isNotEmpty())
        <div class="pg-rail-section">
            <div class="pg-rail-label">Simulated alerts</div>
            @foreach ($rightPanel['alerts'] as $alert)
                <a class="pg-rail-alert" href="{{ route('monitoring.index') }}">
                    <strong>{{ $alert->message }}</strong>
                    <span>{{ ucfirst($alert->severity) }} · {{ ucfirst($alert->status) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</aside>
