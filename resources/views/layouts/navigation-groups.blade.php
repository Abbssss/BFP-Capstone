@php
    $navigationVariant = $navigationVariant ?? 'app';
    $isAppNavigation = $navigationVariant === 'app';
    $navLinkClass = $isAppNavigation ? 'app-side-link' : 'side-link';
    $navIconClass = $isAppNavigation ? 'app-side-icon' : 'side-icon';
    $navGroupClass = $isAppNavigation ? 'app-side-group' : 'side-group';
    $navChildClass = $isAppNavigation ? 'app-side-subnav' : 'side-subnav';
    $navGroupSummaryClass = $isAppNavigation ? 'app-side-group-summary' : 'side-group-summary';
    $navChevronClass = $isAppNavigation ? 'app-side-chevron' : 'side-chevron';
    $currentRole = auth()->user()->role;
    $permissionMatrix = app(\App\Services\RolePermissionService::class)->matrix();
    $canAccess = fn (string $module) => $permissionMatrix[$currentRole][$module] ?? false;
    $isSuperAdmin = $currentRole === 'super_admin';
    $equipmentRoutes = ['equipment.*', 'inspections.*', 'maintenances.*', 'repairs.*', 'schedule.*'];
    $userRoutes = ['users.*', 'personnel.*', 'roles.permissions'];
    $reportRoutes = ['reports.*', 'defects.*', 'audit.*'];
    $canReviewApprovals = $isSuperAdmin || ($currentRole === 'admin' && $canAccess('approvals'));
    $equipmentOpen = request()->routeIs(...$equipmentRoutes);
    $usersOpen = request()->routeIs(...$userRoutes);
    $reportsOpen = request()->routeIs(...$reportRoutes);
@endphp

<a class="{{ $navLinkClass }} {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
    <span class="{{ $navIconClass }}" aria-hidden="true">▦</span>Dashboard
</a>
@if($canReviewApprovals)
    <a class="{{ $navLinkClass }} {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">
        <span class="{{ $navIconClass }}" aria-hidden="true">✓</span>Approvals
    </a>
@endif

@if($canAccess('equipment') || $canAccess('inspections') || $canAccess('maintenance') || $canAccess('repairs') || $canAccess('schedule'))
    <details class="{{ $navGroupClass }}" @if($equipmentOpen) open @endif>
        <summary class="{{ $navGroupSummaryClass }}"><span class="{{ $navIconClass }}" aria-hidden="true">⬡</span>Equipment<span class="{{ $navChevronClass }}" aria-hidden="true">›</span></summary>
        <div class="{{ $navChildClass }}">
            @if($canAccess('equipment'))<a class="{{ $navLinkClass }} {{ request()->routeIs('equipment.*') ? 'active' : '' }}" href="{{ route('equipment.index') }}">Equipment records</a>@endif
            @if($canAccess('inspections'))<a class="{{ $navLinkClass }} {{ request()->routeIs('inspections.*') ? 'active' : '' }}" href="{{ route('inspections.index') }}">Inspections</a>@endif
            @if($canAccess('maintenance'))<a class="{{ $navLinkClass }} {{ request()->routeIs('maintenances.*') ? 'active' : '' }}" href="{{ route('maintenances.index') }}">Maintenance</a>@endif
            @if($canAccess('repairs'))<a class="{{ $navLinkClass }} {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}">Repairs</a>@endif
            @if($canAccess('schedule'))<a class="{{ $navLinkClass }} {{ request()->routeIs('schedule.*') ? 'active' : '' }}" href="{{ route('schedule.index') }}">Schedule</a>@endif
        </div>
    </details>
@endif

@if($isSuperAdmin || $canAccess('personnel') || $canAccess('roles_permissions'))
    <details class="{{ $navGroupClass }}" @if($usersOpen) open @endif>
        <summary class="{{ $navGroupSummaryClass }}"><span class="{{ $navIconClass }}" aria-hidden="true">♙</span>User Management<span class="{{ $navChevronClass }}" aria-hidden="true">›</span></summary>
        <div class="{{ $navChildClass }}">
            @if($isSuperAdmin)<a class="{{ $navLinkClass }} {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Accounts</a>@endif
            @if($canAccess('personnel'))<a class="{{ $navLinkClass }} {{ request()->routeIs('personnel.*') ? 'active' : '' }}" href="{{ route('personnel.index') }}">Personnel</a>@endif
            @if($isSuperAdmin)<a class="{{ $navLinkClass }} {{ request()->routeIs('roles.permissions') ? 'active' : '' }}" href="{{ route('roles.permissions') }}">Roles &amp; Permissions</a>@endif
        </div>
    </details>
@endif

@if($canAccess('reports') || $canAccess('defect_reports') || ($isSuperAdmin && $canAccess('audit_logs')) || $canReviewApprovals)
    <details class="{{ $navGroupClass }}" @if($reportsOpen) open @endif>
        <summary class="{{ $navGroupSummaryClass }}"><span class="{{ $navIconClass }}" aria-hidden="true">▤</span>Reports<span class="{{ $navChevronClass }}" aria-hidden="true">›</span></summary>
        <div class="{{ $navChildClass }}">
            @if($canAccess('reports'))<a class="{{ $navLinkClass }} {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">Reports</a>@endif
            @if($canAccess('defect_reports'))<a class="{{ $navLinkClass }} {{ request()->routeIs('defects.*') ? 'active' : '' }}" href="{{ route('defects.index') }}">Defect Reports</a>@endif
            @if($isSuperAdmin && $canAccess('audit_logs'))<a class="{{ $navLinkClass }} {{ request()->routeIs('audit.*') ? 'active' : '' }}" href="{{ route('audit.index') }}">Audit Log</a>@endif
        </div>
    </details>
@endif

@if($canAccess('settings'))
    <a class="{{ $navLinkClass }} {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
        <span class="{{ $navIconClass }}" aria-hidden="true">⚙</span>Settings
    </a>
@endif
