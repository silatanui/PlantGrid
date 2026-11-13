@php
    $timeline = $rightPanel['caseTimeline'] ?? null;
    $onCasePage = ! empty($timeline);
@endphp
<aside class="pg-rail pg-rail-right {{ $onCasePage ? 'is-case-context' : '' }}" id="shell-right" aria-label="Utility">
    <div class="pg-rail-resize" data-rail-resize="right" title="Drag to resize"></div>
    <div class="pg-rail-head">
        <div>
            <strong>{{ $onCasePage ? 'Case context' : 'Utility' }}</strong>
            <span>{{ $onCasePage ? $timeline['ticket']->ticket_number : $plantName }}</span>
        </div>
        <button class="pg-rail-toggle" type="button" data-shell-toggle="right" aria-label="Collapse utility panel">
            <i data-lucide="panel-right-close"></i>
        </button>
    </div>

    <div class="pg-rail-body" data-rail-sections="right">
        @if ($onCasePage)
            <div class="pg-rail-section pg-rail-case-activity" data-rail-section="case-activity" id="activity">
                <div class="pg-rail-label">
                    <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                        <i data-lucide="grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span>Activity</span>
                </div>
                <p class="pg-rail-section-note">Live updates · mentions open people, cases, and assets</p>
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
                            <div class="text-muted">
                                {{ \App\Support\Format::date($item['at']) }} ·
                                @if (! empty($item['actor_url']))
                                    <a class="pg-activity-actor" href="{{ $item['actor_url'] }}">{{ $item['actor'] }}</a>
                                @else
                                    {{ $item['actor'] }}
                                @endif
                            </div>
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

        <div class="pg-rail-section" data-rail-section="utilities">
            <div class="pg-rail-label">
                <button type="button" class="pg-rail-handle" data-rail-handle aria-label="Drag to reorder">
                    <i data-lucide="grip-vertical" aria-hidden="true"></i>
                </button>
                <span>{{ $onCasePage ? 'More utilities' : 'Utility' }}</span>
            </div>

            @if ($onCasePage)
                <details class="pg-rail-utilities">
                    <summary>Show guidance, actions{{ ($rightPanel['stats'] ?? []) !== [] ? ', and stats' : '' }}</summary>
                    <div class="pg-rail-utilities-body">
                        @include('partials.shell-right-utilities', ['rightPanel' => $rightPanel, 'compact' => true])
                    </div>
                </details>
            @else
                @include('partials.shell-right-utilities', ['rightPanel' => $rightPanel, 'compact' => false])
            @endif
        </div>
    </div>
</aside>
