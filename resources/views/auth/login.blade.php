@extends('layouts.guest')

@section('content')
    @php
        $defaultEmail = old('email', 'amina.okello@northridge.example');
        $accounts = [
            [
                'key' => 'admin',
                'name' => 'Amina Okello',
                'label' => 'System Administrator',
                'email' => 'amina.okello@northridge.example',
                'blurb' => 'Best starting point: users, settings, cases, assets and plant-wide controls.',
                'access' => 'Preferred',
                'featured' => true,
                'initials' => 'AO',
            ],
            [
                'key' => 'manager',
                'name' => 'Daniel Mwangi',
                'label' => 'ICT Manager',
                'email' => 'daniel.mwangi@northridge.example',
                'blurb' => 'Full operations console: assets, network, cases and monitoring.',
                'access' => 'Full console',
                'featured' => true,
                'initials' => 'DM',
            ],
            [
                'key' => 'tech',
                'name' => 'Faith Cheruiyot',
                'label' => 'ICT Technician',
                'email' => 'faith.cheruiyot@northridge.example',
                'blurb' => 'Work assigned cases, assets and floor support tasks.',
                'access' => 'Operations',
                'featured' => false,
                'initials' => 'FC',
            ],
            [
                'key' => 'dept',
                'name' => 'Peter Kamau',
                'label' => 'Department Manager',
                'email' => 'peter.kamau@northridge.example',
                'blurb' => 'Department visibility for people, requests and assets.',
                'access' => 'Department',
                'featured' => false,
                'initials' => 'PK',
            ],
            [
                'key' => 'employee',
                'name' => 'Grace Wanjiku',
                'label' => 'General Employee',
                'email' => 'grace.wanjiku@northridge.example',
                'blurb' => 'Open and track your own cases. No ICT admin tools.',
                'access' => 'Self-service',
                'featured' => false,
                'initials' => 'GW',
            ],
            [
                'key' => 'auditor',
                'name' => 'Helen Achieng',
                'label' => 'Auditor',
                'email' => 'helen.achieng@northridge.example',
                'blurb' => 'Read-focused reviews across controls, backups and reports.',
                'access' => 'Read review',
                'featured' => false,
                'initials' => 'HA',
            ],
        ];
        $featured = array_values(array_filter($accounts, fn ($a) => $a['featured']));
        $more = array_values(array_filter($accounts, fn ($a) => ! $a['featured']));
    @endphp
    <div class="pg-auth-shell">
        <aside class="pg-auth-visual" aria-hidden="false">
            <div class="pg-auth-visual-top">
                <a class="pg-auth-brand" href="{{ route('home') }}" aria-label="PlantGrid home">
                    @include('partials.brand-logo', ['class' => 'pg-logo pg-logo-auth'])
                </a>
                <p class="pg-auth-plant">{{ $plantName ?? 'Northridge Processing' }} · {{ $plantChipMeta ?? 'demo plant' }}</p>
            </div>

            <div class="pg-auth-visual-copy">
                <p class="pg-auth-eyebrow">Portfolio demonstration</p>
                <h1>Sign in and walk the plant operations story</h1>
                <p>Pick a role to see how permissions change the console across assets, cases, network and monitoring.</p>
                <ul class="pg-auth-points">
                    <li>
                        <i data-lucide="shield-check"></i>
                        <span>Role-based access for ICT, production and audit work</span>
                    </li>
                    <li>
                        <i data-lucide="git-branch"></i>
                        <span>Case workflow from new request through closure</span>
                    </li>
                    <li>
                        <i data-lucide="database"></i>
                        <span>Seeded Northridge records ready to explore</span>
                    </li>
                </ul>
            </div>

            <div class="pg-auth-visual-foot">
                <span>Shared demo password</span>
                <code>PlantGrid-Demo</code>
            </div>
        </aside>

        <main class="pg-auth-panel">
            <section class="pg-auth-card" aria-labelledby="signin-title">
                <header class="pg-auth-card-head">
                    <p class="pg-object">Demonstration access</p>
                    <h1 class="pg-page-title" id="signin-title">Sign in</h1>
                    <p class="pg-page-lead">Choose a demo account. The email fills in automatically; every account uses the same password.</p>
                </header>

                @include('partials.flash')

                <div class="pg-auth-password-chip" role="note">
                    <i data-lucide="key-round"></i>
                    <div>
                        <strong>Password for every account</strong>
                        <span class="pg-auth-password-value">PlantGrid-Demo</span>
                    </div>
                </div>

                <div class="pg-auth-section">
                    <div class="pg-auth-section-label">
                        <span>Quick start</span>
                        <span>Preferred · Admin first</span>
                    </div>
                    <div class="pg-persona-grid" role="group" aria-label="Recommended demonstration roles">
                        @foreach ($featured as $persona)
                            <button
                                class="pg-persona {{ $defaultEmail === $persona['email'] ? 'active' : '' }}"
                                type="button"
                                data-demo-email="{{ $persona['email'] }}"
                            >
                                <span class="pg-persona-avatar" aria-hidden="true">{{ $persona['initials'] }}</span>
                                <span class="pg-persona-body">
                                    <span class="pg-persona-role">{{ $persona['label'] }}</span>
                                    <span class="pg-persona-name">{{ $persona['name'] }}</span>
                                    <span class="pg-persona-blurb">{{ $persona['blurb'] }}</span>
                                </span>
                                <span class="pg-persona-access">{{ $persona['access'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <form class="pg-auth-form" method="post" action="{{ route('login.store') }}">
                    @csrf
                    <div class="pg-auth-fields">
                        <div>
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ $defaultEmail }}" required autofocus autocomplete="username">
                        </div>
                        <div>
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" name="password" type="password" value="PlantGrid-Demo" required autocomplete="current-password">
                        </div>
                    </div>
                    <div class="form-check pg-auth-remember">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <label class="form-check-label" for="remember">Keep me signed in on this browser</label>
                    </div>
                    <button class="btn btn-pg w-100" type="submit">Sign in to PlantGrid</button>
                </form>

                <div class="pg-auth-sso mt-3">
                    @if (! empty($entraEnabled))
                        <a class="btn btn-pg-line w-100" href="{{ route('auth.entra.redirect') }}">Sign in with Microsoft</a>
                    @else
                        <p class="text-muted small mb-1">Microsoft Entra ID SSO is ready to configure in Administration → Identity.</p>
                        <button class="btn btn-pg-line w-100" type="button" disabled>Sign in with Microsoft</button>
                    @endif
                    <p class="mt-2 mb-0 text-center">
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    </p>
                </div>

                <div class="pg-auth-section pg-auth-section-more">
                    <div class="pg-auth-section-label">
                        <span>More demonstration roles</span>
                        <span>{{ count($more) }} accounts</span>
                    </div>
                    <div class="pg-account-list" role="group" aria-label="More demonstration roles">
                        @foreach ($more as $persona)
                            <button
                                class="pg-account-row {{ $defaultEmail === $persona['email'] ? 'active' : '' }}"
                                type="button"
                                data-demo-email="{{ $persona['email'] }}"
                            >
                                <span class="pg-persona-avatar" aria-hidden="true">{{ $persona['initials'] }}</span>
                                <span class="pg-account-meta">
                                    <span class="pg-account-title">
                                        <strong>{{ $persona['label'] }}</strong>
                                        <em>{{ $persona['name'] }}</em>
                                    </span>
                                    <span class="pg-account-email">{{ $persona['email'] }}</span>
                                </span>
                                <span class="pg-account-tag">{{ $persona['access'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="pg-auth-foot-row">
                    <a href="{{ route('home') }}">
                        <i data-lucide="arrow-left"></i>
                        Back to PlantGrid home
                    </a>
                    @include('partials.theme-menu', [
                        'align' => 'end',
                        'buttonClass' => 'pg-theme-menu-toggle pg-theme-menu-toggle--auth',
                    ])
                </div>
            </section>
        </main>
    </div>
    @include('partials.footer')
@endsection
