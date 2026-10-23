<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ trim(strip_tags($header ?? 'FIRE-PMIS')) }} · FIRE-PMIS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased app-shell">
        @include('layouts.sidebar')

        <div class="app-main">
            @php
                $breadcrumb = match (true) {
                    request()->routeIs('equipment.*') => 'Equipment',
                    request()->routeIs('inspections.*') => 'Inspections',
                    request()->routeIs('maintenances.*') => 'Maintenance',
                    request()->routeIs('repairs.*') => 'Repairs',
                    request()->routeIs('risk.*') => 'Predictions',
                    request()->routeIs('schedule.*') => 'Schedule',
                    request()->routeIs('personnel.*') => 'Personnel',
                    request()->routeIs('defects.*') => 'Defect Reports',
                    request()->routeIs('approvals.*') => 'Approvals',
                    request()->routeIs('audit.*') => 'Audit Logs',
                    request()->routeIs('reports.*') => 'Reports',
                    request()->routeIs('settings.*') => 'Settings',
                    request()->routeIs('profile.*') => 'Profile',
                    request()->routeIs('search') => 'Search',
                    default => 'Dashboard',
                };
            @endphp
            <header class="app-topbar">
                <div class="app-topbar-heading">
                    <span class="app-breadcrumb">Operations / {{ $breadcrumb }}</span>
                    @isset($header)
                        <div class="app-topbar-title">{{ $header }}</div>
                    @endisset
                </div>
                <div class="app-topbar-tools">
                    <form method="GET" action="{{ route('search') }}" class="app-global-search" role="search">
                        <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search records..." aria-label="Search project records" required>
                        <button type="submit" class="app-tool-icon" aria-label="Search">⌕</button>
                    </form>
                    <details class="app-notifications">
                        <summary class="app-tool-icon app-notification-icon" aria-label="Recent project changes" title="Recent project changes">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                                <path d="M10 21h4" />
                            </svg>
                        </summary>
                        <section class="app-notification-panel" aria-label="Latest project changes">
                            <h2>Latest changes</h2>
                            @forelse($recentProjectChanges as $change)
                                <article class="app-notification-item">
                                    <strong>{{ $change['title'] }}</strong>
                                    <span>{{ $change['detail'] }}</span>
                                    <time datetime="{{ $change['date']?->toISOString() }}">{{ $change['date']?->diffForHumans() }}</time>
                                </article>
                            @empty
                                <p class="app-notification-empty">No recent changes for your office.</p>
                            @endforelse
                        </section>
                    </details>
                    <span class="app-station-date"><small>Station date</small><strong>{{ now()->format('d M Y') }}</strong></span>
                </div>
            </header>
            <main class="app-main-content">
                @if(auth()->user()->role !== 'super_admin' && blank(auth()->user()->office_code))
                    <dialog class="app-modal" data-office-warning="{{ auth()->id() }}" aria-labelledby="office-warning-title" aria-describedby="office-warning-message">
                        <div class="app-modal-panel">
                            <header class="app-modal-header">
                                <div>
                                    <h2 id="office-warning-title">Office assignment pending</h2>
                                    <p id="office-warning-message">Your account does not have an office code yet.</p>
                                </div>
                            </header>
                            <div class="app-modal-body">
                                Please wait for a Super Admin to assign your office, or contact your administrator to confirm your office assignment. Until then, office-specific records may not be available.
                            </div>
                            <div class="app-modal-footer">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="app-modal-button primary">Wait and sign out</button>
                                </form>
                            </div>
                        </div>
                    </dialog>
                @endif
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
