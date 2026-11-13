<aside class="pg-rail pg-rail-right" id="shell-right" aria-label="Utility">
    <div class="pg-rail-resize" data-rail-resize="right" title="Drag to resize"></div>
    <div class="pg-rail-head">
        <div>
            <strong>Utility</strong>
            <span>{{ $plantName }}</span>
        </div>
        <button class="pg-rail-toggle" type="button" data-shell-toggle="right" aria-label="Collapse utility panel">
            <i data-lucide="panel-right-close"></i>
        </button>
    </div>

    <div class="pg-rail-body" data-rail-sections="right">
        @if (($rightPanel['stats'] ?? []) !== [])
            <div class="pg-rail-section" data-rail-section="stats">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>At a glance</span>
                </div>
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

        <div class="pg-rail-section" data-rail-section="guidance">
            <div class="pg-rail-label">
                <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                    <i data-lucide="grip-vertical" aria-hidden="true"></i>
                </button>
                <span>{{ $rightPanel['guidance']['title'] }}</span>
            </div>
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
            <div class="pg-rail-section" data-rail-section="actions">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>Quick actions</span>
                </div>
                <div class="pg-rail-actions">
                    @foreach ($rightPanel['actions'] as $action)
                        <a class="{{ $action['tone'] === 'primary' ? 'btn btn-pg btn-sm' : 'btn btn-pg-line btn-sm' }}" href="{{ route($action['route']) }}">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! empty($rightPanel['caseTimeline']))
            @php
                $timeline = $rightPanel['caseTimeline'];
            @endphp
            <div class="pg-rail-section pg-rail-case-activity" data-rail-section="case-activity" id="activity">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>Activity</span>
                </div>
                <p class="pg-rail-section-note">Live updates for {{ $timeline['ticket']->ticket_number }}</p>
                <ul
                    class="pg-rail-timeline"
                    data-case-history
                    data-case-activity
                    data-activity-url="{{ $timeline['activity_url'] }}"
                    data-activity-poll="12000"
                >
                    @forelse ($timeline['items'] as $item)
                        <li @if (! empty($item['note_id'])) data-activity-note="{{ $item['note_id'] }}" @endif>
                            {!! \App\Support\Labels::badge((string) ($item['badge'] ?? 'update')) !!}
                            <strong class="pg-activity-label">{{ $item['label'] }}</strong>
                            @if (! empty($item['html']))
                                <div class="pg-rich-note">{!! $item['html'] !!}</div>
                            @elseif (! empty($item['body']))
                                <div class="text-muted">{{ $item['body'] }}</div>
                            @endif
                            <div class="text-muted">{{ \App\Support\Format::date($item['at']) }} · {{ $item['actor'] }}</div>
                            @if (! empty($item['note_id']))
                                <a class="small" href="#note-{{ $item['note_id'] }}">View note</a>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">No activity recorded on this case yet.</li>
                    @endforelse
                </ul>
            </div>
        @endif

        @if (($rightPanel['alerts'] ?? collect())->isNotEmpty())
            <div class="pg-rail-section" data-rail-section="alerts">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>Simulated alerts</span>
                </div>
                @foreach ($rightPanel['alerts'] as $alert)
                    <a class="pg-rail-alert" href="{{ route('monitoring.index') }}">
                        <strong>{{ $alert->message }}</strong>
                        <span>{{ ucfirst($alert->severity) }} · {{ ucfirst($alert->status) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</aside>
