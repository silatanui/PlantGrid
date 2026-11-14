@extends('layouts.app')

@section('content')
    @php
        $sortLink = function (string $column, string $label) use ($sort) {
            $active = $sort->is($column);
            $next = $active && $sort->direction === 'asc' ? 'desc' : 'asc';
            $href = route('audit.index', array_merge(request()->query(), [
                'sort' => $column,
                'direction' => $next,
            ]));
            $arrow = $active ? ($sort->direction === 'asc' ? '↑' : '↓') : '↕';

            return compact('active', 'href', 'label', 'arrow');
        };
        $sortLinks = [
            $sortLink('created_at', 'When'),
            $sortLink('action', 'Action'),
            $sortLink('entity_type', 'Record'),
        ];
    @endphp

    @include('partials.object-home', [
        'icon' => 'scroll-text',
        'tone' => 'default',
        'eyebrow' => 'Administration',
        'title' => 'Audit log',
        'lead' => 'Sign-in events and important record changes. Passwords are never written here.',
    ])

    <form class="pg-filters my-3" method="get" role="search">
        <div>
            <label class="form-label" for="q">Action or record</label>
            <input
                class="form-control"
                id="q"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="e.g. ticket.status, user, login"
                autocomplete="off"
            >
        </div>
        <input type="hidden" name="sort" value="{{ $sort->column }}">
        <input type="hidden" name="direction" value="{{ $sort->direction }}">
        <button class="btn btn-pg" type="submit">Search</button>
        @if (! empty($filters['q']))
            <a class="btn btn-pg-line" href="{{ route('audit.index', request()->only(['sort', 'direction'])) }}">Clear</a>
        @endif
    </form>

    <div class="pg-audit-toolbar">
        <div class="pg-audit-sort" aria-label="Sort audit events">
            <span class="pg-audit-sort-label">Sort</span>
            @foreach ($sortLinks as $link)
                <a
                    class="pg-audit-sort-link {{ $link['active'] ? 'is-active' : '' }}"
                    href="{{ $link['href'] }}"
                    aria-sort="{{ $link['active'] ? ($sort->direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                >
                    <span>{{ $link['label'] }}</span>
                    <span aria-hidden="true">{{ $link['arrow'] }}</span>
                </a>
            @endforeach
        </div>
        <p class="pg-audit-toolbar-hint text-muted small mb-0">
            {{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('event', $logs->total()) }}
            · pretty JSON with one-click copy
        </p>
    </div>

    <div class="pg-audit-feed" data-audit-feed>
        @forelse ($logs as $log)
            @php
                $oldJson = $log->old_values
                    ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null;
                $newJson = $log->new_values
                    ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null;
                $combined = trim(implode("\n\n", array_filter([
                    $oldJson ? "// before\n".$oldJson : null,
                    $newJson ? "// after\n".$newJson : null,
                ])));
                $actionLabel = str_replace(['.', '_'], [' · ', ' '], $log->action);
                $bothId = 'audit-both-'.$log->id;
            @endphp
            <article class="pg-audit-entry">
                <header class="pg-audit-entry-head">
                    <div class="pg-audit-entry-meta">
                        <time class="pg-audit-when" datetime="{{ $log->created_at?->toIso8601String() }}">
                            {{ \App\Support\Format::date($log->created_at) }}
                        </time>
                        @if ($log->user)
                            <a class="pg-person-chip" href="{{ route('people.show', $log->user) }}">
                                <span class="pg-avatar-sm" aria-hidden="true">{{ strtoupper(substr($log->user->name, 0, 1)) }}</span>
                                <span>{{ $log->user->name }}</span>
                            </a>
                        @else
                            <span class="pg-audit-system">System</span>
                        @endif
                    </div>
                    <div class="pg-audit-entry-tags">
                        <code class="pg-audit-action" title="{{ $log->action }}">{{ $log->action }}</code>
                        <span class="pg-audit-entity">
                            <span class="pg-audit-entity-type">{{ $log->entity_type ?: '—' }}</span>
                            @if ($log->entity_id)
                                <span class="pg-mono">#{{ $log->entity_id }}</span>
                            @endif
                        </span>
                    </div>
                    <p class="pg-audit-entry-summary">{{ ucfirst($actionLabel) }}</p>
                </header>

                @if ($oldJson || $newJson)
                    <div class="pg-audit-payloads">
                        @if ($oldJson)
                            <div class="pg-code-panel">
                                <div class="pg-code-panel-head">
                                    <span>Before</span>
                                    <button class="pg-code-copy" type="button" data-copy-code aria-label="Copy before values">
                                        <i data-lucide="copy" aria-hidden="true"></i>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <pre class="pg-code-block" tabindex="0"><code>{{ $oldJson }}</code></pre>
                            </div>
                        @endif
                        @if ($newJson)
                            <div class="pg-code-panel">
                                <div class="pg-code-panel-head">
                                    <span>{{ $oldJson ? 'After' : 'Change' }}</span>
                                    <button class="pg-code-copy" type="button" data-copy-code aria-label="Copy change values">
                                        <i data-lucide="copy" aria-hidden="true"></i>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <pre class="pg-code-block" tabindex="0"><code>{{ $newJson }}</code></pre>
                            </div>
                        @endif
                    </div>
                    @if ($oldJson && $newJson)
                        <div class="pg-audit-entry-actions">
                            <pre id="{{ $bothId }}" class="pg-audit-copy-source" hidden>{{ $combined }}</pre>
                            <button
                                class="btn btn-pg-line btn-sm"
                                type="button"
                                data-copy-from="{{ $bothId }}"
                            >
                                <i data-lucide="clipboard-copy" aria-hidden="true"></i>
                                <span>Copy before + after</span>
                            </button>
                        </div>
                    @endif
                @else
                    <p class="pg-audit-empty-payload text-muted small mb-0">No payload recorded for this event.</p>
                @endif
            </article>
        @empty
            <div class="pg-audit-empty">
                <i data-lucide="scroll-text" aria-hidden="true"></i>
                <p class="mb-0">No audit events match this search.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
