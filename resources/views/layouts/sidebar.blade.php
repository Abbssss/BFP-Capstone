<aside class="app-sidebar">
    <a href="{{ route('dashboard') }}" class="app-brand">
        <span class="app-brand-mark">F</span>
        <span><strong>FIRE-PMIS</strong><small>Bureau of Fire<br>Protection</small></span>
    </a>
    <div class="app-nav-caption">Workspace</div>
    <nav class="app-side-nav" aria-label="Main navigation">
        @include('layouts.navigation-groups')
    </nav>
    <div class="app-sidebar-bottom">
        <a href="{{ route('profile.edit') }}" class="app-profile-link">
            <span class="app-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
            <span><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_', ' ', auth()->user()->role) }}</small></span>
            <span class="app-profile-chevron">›</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="app-signout"><span aria-hidden="true">⇥</span> Sign out</button>
        </form>
    </div>
</aside>
