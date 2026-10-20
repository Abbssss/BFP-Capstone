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
                    <span class="app-tool-icon" aria-hidden="true">⌕</span>
                    <span class="app-tool-icon app-notification-icon" aria-hidden="true">♧</span>
                    <span class="app-station-date"><small>Station date</small><strong>{{ now()->format('d M Y') }}</strong></span>
                </div>
            </header>
            <main class="app-main-content">
                @if(auth()->user()->role !== 'super_admin' && blank(auth()->user()->office_code))
                    <div class="app-office-warning" role="status">
                        No Office Code is assigned to your account. Ask a Super Admin to assign your office before using personnel, equipment, or operational records.
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
