@if (($rightPanel['stats'] ?? []) !== [])
    <div class="pg-rail-utility-block">
        <div class="pg-rail-sublabel">At a glance</div>
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

<div class="pg-rail-utility-block">
    <div class="pg-rail-sublabel">{{ $rightPanel['guidance']['title'] }}</div>
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
    <div class="pg-rail-utility-block">
        <div class="pg-rail-sublabel">Quick actions</div>
        <div class="pg-rail-actions">
            @foreach ($rightPanel['actions'] as $action)
                <a class="{{ $action['tone'] === 'primary' ? 'btn btn-pg btn-sm' : 'btn btn-pg-line btn-sm' }}" href="{{ route($action['route']) }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </div>
@endif

@if (($rightPanel['alerts'] ?? collect())->isNotEmpty())
    <div class="pg-rail-utility-block">
        <div class="pg-rail-sublabel">Simulated alerts</div>
        @foreach ($rightPanel['alerts'] as $alert)
            <a class="pg-rail-alert" href="{{ route('monitoring.index') }}">
                <strong>{{ $alert->message }}</strong>
                <span>{{ ucfirst($alert->severity) }} · {{ ucfirst($alert->status) }}</span>
            </a>
        @endforeach
    </div>
@endif
