<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'PlantGrid' }} · PlantGrid</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}?v={{ @filemtime(public_path('images/favicon-32x32.png')) ?: 1 }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon-48x48.png') }}?v={{ @filemtime(public_path('images/favicon-48x48.png')) ?: 1 }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}?v={{ @filemtime(public_path('images/apple-touch-icon.png')) ?: 1 }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="pg-shell"
    data-mentions-url="{{ route('mentions.suggest') }}"
    data-presence-heartbeat="{{ route('presence.heartbeat') }}"
    @if (auth()->user()->hasPermission('users.view'))
        data-presence-live="{{ route('presence.live') }}"
    @endif
>
    <a class="pg-skip" href="#content">Skip to content</a>
    <header class="pg-appbar">
        <div class="pg-appbar-brand">
            <a class="pg-brand" href="{{ route('dashboard') }}" aria-label="PlantGrid home">
                <img src="{{ asset('images/logo-lockup.png') }}?v={{ @filemtime(public_path('images/logo-lockup.png')) ?: 1 }}" alt="PlantGrid, Manufacturing Technology Management">
            </a>
            <div class="pg-plant-chip" title="Demonstration plant">
                <span class="pg-plant-chip-name">{{ $plantName }}</span>
                <span class="pg-plant-chip-meta">Demo plant</span>
            </div>
        </div>

        <nav class="pg-tabs" aria-label="Primary">
            @foreach ($primaryNavigation as $item)
                <a class="{{ request()->routeIs($item['pattern']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    <i data-lucide="{{ $item['icon'] }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="pg-appbar-tools">
            <form
                class="pg-global-search"
                action="{{ route('search') }}"
                method="get"
                role="search"
                data-global-search
                data-suggest-url="{{ route('search.suggest') }}"
            >
                <label class="visually-hidden" for="global-search">Search PlantGrid</label>
                <i data-lucide="search"></i>
                <input
                    id="global-search"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search people, cases, assets…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-autocomplete="list"
                    aria-controls="pg-search-results"
                    aria-expanded="false"
                    data-global-search-input
                >
                <div
                    class="pg-search-panel"
                    id="pg-search-results"
                    data-global-search-panel
                    role="listbox"
                    hidden
                ></div>
            </form>

            <div class="pg-appbar-actions">
                @if (auth()->user()->hasPermission('users.view'))
                    <div class="dropdown" data-presence-header>
                        <button class="pg-icon-btn" type="button" data-bs-toggle="dropdown" data-presence-count-host aria-label="Online users" title="Online users">
                            <i data-lucide="radio"></i>
                            @if (($headerOnlineCount ?? 0) > 0)
                                <span class="pg-count">{{ $headerOnlineCount }}</span>
                            @endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px;">
                            <div class="px-2 py-1 text-muted small">Online now</div>
                            <div data-presence-header-list>
                                @forelse (($headerOnlineUsers ?? collect()) as $person)
                                    <a class="dropdown-item small" href="{{ route('people.show', $person) }}">
                                        <span class="pg-online-dot" aria-hidden="true"></span>
                                        <strong>{{ $person->name }}</strong>
                                        <div class="text-muted">{{ $person->roleLabel() }}</div>
                                    </a>
                                @empty
                                    <div class="dropdown-item-text small text-muted">No other users online.</div>
                                @endforelse
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item small" href="{{ route('admin.online') }}">Open online users</a>
                        </div>
                    </div>
                @endif

                <div class="dropdown">
                    <button class="pg-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications" title="Notifications">
                        <i data-lucide="bell"></i>
                        @if (($headerNotificationCount ?? 0) > 0)
                            <span class="pg-count">{{ $headerNotificationCount }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 300px;">
                        <div class="px-2 py-1 text-muted small">Notifications</div>
                        @forelse (($headerNotifications ?? collect()) as $note)
                            @php $data = $note->data; @endphp
                            <a class="dropdown-item small" href="{{ route('notifications.read', $note->id) }}">
                                <strong>{{ $data['title'] ?? 'Update' }}</strong><br>{{ $data['body'] ?? '' }}
                            </a>
                        @empty
                            <div class="dropdown-item-text small text-muted">No unread notifications.</div>
                        @endforelse
                        <div class="dropdown-divider"></div>
                        <form method="post" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button class="dropdown-item small" type="submit">Mark all read</button>
                        </form>
                        <a class="dropdown-item small" href="{{ route('notifications.index') }}">Open notifications</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="pg-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Messages" title="Messages">
                        <i data-lucide="message-square"></i>
                        @if (($headerMessageCount ?? 0) > 0)
                            <span class="pg-count">{{ $headerMessageCount }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 300px;">
                        <div class="px-2 py-1 text-muted small">Direct messages</div>
                        @forelse (($headerMessages ?? collect()) as $note)
                            <a class="dropdown-item small" href="{{ route('messages.show', $note) }}">
                                <strong>{{ $note->sender->name }}</strong><br>{{ $note->subject }}
                            </a>
                        @empty
                            <div class="dropdown-item-text small text-muted">No unread messages.</div>
                        @endforelse
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item small" href="{{ route('messages.index') }}">Open inbox</a>
                    </div>
                </div>

                @if (auth()->user()->hasPermission('monitoring.view'))
                    <div class="dropdown">
                        <button class="pg-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Simulated alerts" title="Alerts">
                            <i data-lucide="activity"></i>
                            @if ($headerAlertCount > 0)
                                <span class="pg-count">{{ $headerAlertCount }}</span>
                            @endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 300px;">
                            <div class="px-2 py-1 text-muted small">Simulated alerts</div>
                            @forelse ($headerAlerts as $alert)
                                <a class="dropdown-item small" href="{{ route('monitoring.index') }}">{{ $alert->message }}</a>
                            @empty
                                <div class="dropdown-item-text small text-muted">No open simulated alerts.</div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <div class="dropdown">
                    <button class="pg-user" type="button" data-bs-toggle="dropdown" aria-label="Account menu">
                        <span class="pg-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="pg-user-copy">
                            <span class="pg-user-name">{{ auth()->user()->name }}</span>
                            <span class="pg-user-role">{{ auth()->user()->roleLabel() }}</span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item" type="submit">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <div class="pg-workspace">
        @include('partials.shell-left')
        <main class="pg-main" id="content">
            @include('partials.flash')
            @yield('content')
        </main>
        @include('partials.shell-right')
    </div>
    @include('partials.footer')
    @include('partials.ai-assistant')
    <button class="pg-shell-expand pg-shell-expand-left" type="button" data-shell-toggle="left" aria-label="Show navigation" title="Show navigation">
        <i data-lucide="panel-left-open"></i>
    </button>
    <button class="pg-shell-expand pg-shell-expand-right" type="button" data-shell-toggle="right" aria-label="Show utility panel" title="Show utility panel">
        <i data-lucide="panel-right-open"></i>
    </button>
    <div class="pg-shell-mask" data-shell-mask hidden></div>
</body>
</html>
