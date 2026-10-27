<x-app-layout>
    <x-slot name="header"><h2>Dashboard</h2></x-slot>

    <style>
        :root { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #122744; background: #f1f3f7; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }
        a { color: inherit; text-decoration: none; }
        .admin-shell { min-height: 100vh; }
        .admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 10; display: flex; width: 190px; flex-direction: column; background: #0c2749; color: #d9e4f2; }
        .brand { display: flex; min-height: 98px; align-items: flex-start; gap: 11px; border-bottom: 1px solid rgba(210,226,245,.12); padding: 22px 18px 14px; }
        .brand-mark { display: grid; width: 27px; height: 32px; flex: 0 0 auto; place-items: center; clip-path: polygon(50% 0,100% 18%,100% 73%,50% 100%,0 73%,0 18%); background: #c51f25; color: white; font-size: 14px; font-weight: 800; }
        .brand strong { display: block; color: white; font-size: 14px; }
        .brand small { display: block; margin-top: 4px; color: #a7b7cb; font-size: 8px; font-weight: 700; letter-spacing: .1em; line-height: 1.4; text-transform: uppercase; }
        .station-name { border-bottom: 1px solid rgba(210,226,245,.12); padding: 0 18px 15px; color: #8da5c2; font-size: 8px; font-weight: 700; letter-spacing: .13em; }
        .nav-caption { padding: 19px 18px 9px; color: #7089a8; font-size: 8px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        .side-nav { display: grid; gap: 3px; padding: 0 8px; }
        .side-link { display: flex; min-height: 34px; align-items: center; gap: 10px; border-radius: 5px; padding: 0 10px; color: #c1d0e2; font-size: 10px; font-weight: 600; }
        .side-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .side-link.active { background: #bd1f24; color: #fff; }
        .side-icon { width: 14px; color: #a9bdd4; font-size: 13px; text-align: center; }
        .side-link.active .side-icon { color: #fff; }
        .sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(210,226,245,.12); padding: 14px 14px 17px; }
        .profile-link { display: flex; align-items: center; gap: 8px; }
        .avatar { display: grid; width: 25px; height: 25px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: #e2ac13; color: #14243a; font-size: 9px; font-weight: 800; }
        .profile-link strong { display: block; color: #fff; font-size: 9px; }
        .profile-link small { display: block; margin-top: 3px; color: #9cb0c9; font-size: 8px; }
        .signout { display: block; margin-top: 15px; color: #9fb3cb; font-size: 10px; }
        .main-area { min-height: 100vh; margin-left: 190px; }
        .topbar { display: flex; height: 58px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e0e5ec; padding: 0 22px; background: #fff; }
        .crumb { color: #8693a4; font-size: 8px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .page-title { margin-top: 4px; color: #102541; font-size: 16px; font-weight: 600; }
        .top-actions { display: flex; align-items: center; gap: 9px; }
        .top-icon { display: grid; width: 28px; height: 28px; place-items: center; border: 0; border-radius: 6px; background: #f2f4f8; color: #536780; font-size: 14px; }
        .station-date { margin-left: 3px; border-left: 1px solid #e3e7ed; padding-left: 12px; text-align: right; }
        .station-date small { display: block; color: #8996a7; font-size: 7px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .station-date strong { display: block; margin-top: 3px; color: #18304f; font-size: 9px; }
        .dashboard-content { max-width: 1280px; margin: 0 auto; padding: 30px 28px 36px; }
        .welcome-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 17px; }
        .sample-label { display: inline-block; border-radius: 3px; padding: 3px 6px; background: #fff0ef; color: #b62025; font-size: 7px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .welcome-row h1 { margin: 9px 0 5px; color: #183252; font-size: 20px; font-weight: 500; letter-spacing: -.025em; }
        .welcome-row p { margin: 0; color: #78879a; font-size: 10px; }
        .primary-button { display: inline-flex; min-height: 35px; align-items: center; gap: 8px; border-radius: 5px; padding: 0 15px; background: #bd1f24; box-shadow: 0 4px 9px rgba(128,22,27,.15); color: #fff; font-size: 10px; font-weight: 700; }
        .primary-button:hover { background: #a9191e; }
        .metrics { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; margin-bottom: 16px; }
        .metric { display: grid; grid-template-columns: 42px minmax(0,1fr); min-height: 108px; align-items: center; gap: 12px; border: 1px solid #dfe4eb; border-left: 3px solid var(--accent); border-radius: 8px; padding: 16px; background: #fff; box-shadow: 0 3px 10px rgba(20,43,76,.04); color: inherit; font-family: inherit; text-align: left; cursor: pointer; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
        .metric:hover { border-color: var(--accent); box-shadow: 0 8px 18px rgba(20,43,76,.09); transform: translateY(-2px); }
        .metric:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 24%, transparent); outline-offset: 3px; }
        .metric-icon { display: grid; width: 42px; height: 42px; flex: 0 0 auto; place-items: center; border-radius: 8px; background: var(--tint); color: var(--accent); font-size: 18px; }
        .metric-label { display: block; color: #667991; font-size: 12px; font-weight: 700; line-height: 1.2; }
        .metric-value { display: block; margin-top: 5px; color: #122b4c; font-size: 30px; font-weight: 800; line-height: 1; }
        .metric-note { display: block; margin-top: 7px; color: #8795a7; font-size: 10px; line-height: 1.25; }
        .panel-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; margin-bottom: 12px; }
        .panel { min-width: 0; overflow: hidden; border: 1px solid #dfe4eb; border-radius: 7px; background: #fff; }
        .panel-heading { display: flex; min-height: 38px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e9ef; padding: 0 13px; color: #1b3555; font-size: 10px; font-weight: 500; }
        .panel-body { padding: 10px; }
        .record-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 6px; }
        .record-box { min-height: 49px; border-radius: 4px; padding: 8px; background: #f5f6f8; }
        .record-box span { display: block; color: #8794a6; font-size: 7px; }
        .record-box strong { display: block; margin-top: 4px; color: #153151; font-size: 14px; }
        .service-status { display: inline-flex; border-radius: 12px; padding: 4px 7px; background: #e4f4ee; color: #19815e; font-size: 7px; font-weight: 700; }
        .service-status.inactive { background: #fff2e0; color: #98620c; }
        .service-name { margin-top: 9px; color: #183252; font-size: 9px; font-weight: 700; }
        .service-detail { margin-top: 5px; color: #8996a7; font-size: 7px; }
        .service-stats { margin-top: 10px; border-top: 1px solid #e7ebf0; }
        .service-stats div { display: flex; justify-content: space-between; border-bottom: 1px solid #edf0f3; padding: 7px 0; color: #607188; font-size: 7px; }
        .service-stats strong { color: #16304f; }
        .panel-link { display: inline-block; margin-top: 7px; color: #b81f24; font-size: 8px; font-weight: 700; }
        .lower-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .priority-level-summary { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; padding: 10px; }
        .priority-level-count { display: flex; align-items: center; justify-content: space-between; border-radius: 4px; padding: 9px 10px; background: #f5f6f8; color: #607188; font-size: 8px; }
        .priority-level-count strong { color: #153151; font-size: 13px; }
        .status-pill.warning { background: #fff2e0; color: #98620c; }
        .status-pill.danger { background: #fff0ef; color: #b62025; }
        .text-link { color: #bd1f24; font-size: 7px; font-weight: 700; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th { background: #f7f8fa; color: #8693a5; font-size: 7px; font-weight: 700; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e9edf2; padding: 9px 10px; }
        td { color: #536780; font-size: 8px; }
        td:first-child { color: #435973; }
        .status-pill { display: inline-block; border-radius: 12px; padding: 4px 7px; background: #e4f4ee; color: #19815e; font-size: 7px; font-weight: 700; text-transform: capitalize; }
        .status-pill.inactive { background: #edf0f4; color: #69788b; }
        .activity-row { display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #e9edf2; padding: 8px 11px; }
        .activity-icon { display: grid; width: 23px; height: 23px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: #f0f3f7; color: #38536f; font-size: 10px; }
        .activity-row strong { display: block; color: #29415e; font-size: 8px; }
        .activity-row small { display: block; overflow: hidden; max-width: 380px; margin-top: 3px; color: #8b98a9; font-size: 7px; text-overflow: ellipsis; }
        .empty-state { padding: 18px 12px; color: #8794a6; font-size: 8px; text-align: center; }
        @media (max-width: 1100px) { .metrics { grid-template-columns: repeat(3,minmax(0,1fr)); } }
        @media (max-width: 700px) {
            .admin-sidebar { position: static; width: auto; min-height: 0; }
            .brand { min-height: 68px; }
            .station-name, .nav-caption, .sidebar-bottom { display: none; }
            .side-nav { display: flex; overflow-x: auto; padding: 7px; }
            .side-link { flex: 0 0 auto; padding: 0 9px; }
            .main-area { margin-left: 0; }
            .topbar { padding: 0 15px; }
            .dashboard-content { padding: 22px 14px; }
            .metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .priority-level-summary { grid-template-columns: 1fr; }
            .panel-grid, .lower-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 390px) { .welcome-row { align-items: flex-start; flex-direction: column; } .station-date { display: none; } }
        .admin-sidebar { width: 270px; }
        .brand { min-height: 124px; gap: 15px; padding: 28px 24px 19px; }
        .brand-mark { width: 40px; height: 47px; font-size: 20px; }
        .brand strong { font-size: 19px; }
        .brand small { font-size: 11px; }
        .station-name { padding: 0 24px 20px; font-size: 11px; }
        .nav-caption { padding: 26px 24px 12px; font-size: 11px; }
        .side-nav { gap: 5px; padding: 0 10px; }
        .side-link { min-height: 49px; gap: 13px; padding: 0 14px; font-size: 15px; }
        .side-icon { width: 19px; font-size: 18px; }
        .sidebar-bottom { padding: 17px 17px 20px; }
        .avatar { width: 32px; height: 32px; font-size: 11px; }
        .profile-link { gap: 10px; }
        .profile-link strong { font-size: 12px; }
        .profile-link small, .signout { font-size: 11px; }
        .main-area { margin-left: 270px; }
        .topbar { height: 82px; padding: 0 34px; }
        .crumb { font-size: 11px; }
        .page-title { font-size: 23px; }
        .top-actions { gap: 11px; }
        .top-icon { width: 42px; height: 42px; font-size: 21px; }
        .station-date { padding-left: 18px; }
        .station-date small { font-size: 10px; }
        .station-date strong { font-size: 14px; }
        .dashboard-content { max-width: 1700px; padding: 48px 46px 56px; }
        .welcome-row { margin-bottom: 30px; }
        .sample-label { padding: 5px 9px; font-size: 10px; }
        .welcome-row h1 { margin: 13px 0 8px; font-size: 34px; }
        .welcome-row p { font-size: 16px; }
        .primary-button { min-height: 50px; flex: 0 0 auto; gap: 10px; padding: 0 21px; font-size: 15px; white-space: nowrap; }
        .metrics { gap: 15px; margin-bottom: 21px; }
        .metric { min-height: 112px; grid-template-columns: 44px minmax(0,1fr); gap: 14px; border-radius: 9px; padding: 16px 18px; }
        .metric-icon { width: 44px; height: 44px; border-radius: 8px; font-size: 20px; }
        .metric-label { font-size: 13px; }
        .metric-value { margin-top: 5px; font-size: 31px; }
        .metric-note { margin-top: 7px; font-size: 11px; }
        .panel-grid { gap: 19px; margin-bottom: 19px; }
        .panel { border-radius: 9px; }
        .panel-heading { min-height: 57px; padding: 0 20px; font-size: 15px; }
        .panel-body { padding: 17px; }
        .record-grid { gap: 10px; }
        .record-box { min-height: 73px; padding: 12px; }
        .record-box span { font-size: 11px; }
        .record-box strong { margin-top: 6px; font-size: 21px; }
        .service-status { padding: 6px 9px; font-size: 10px; }
        .service-name { margin-top: 13px; font-size: 14px; }
        .service-detail { margin-top: 7px; font-size: 12px; }
        .service-stats { margin-top: 16px; }
        .service-stats div { padding: 11px 0; font-size: 12px; }
        .panel-link { margin-top: 11px; font-size: 13px; }
        .lower-grid { gap: 19px; }
        .priority-level-summary { gap: 12px; padding: 15px; }
        .priority-level-count { padding: 13px 15px; font-size: 12px; }
        .priority-level-count strong { font-size: 18px; }
        .text-link { font-size: 12px; }
        th { font-size: 10px; }
        th, td { padding: 13px 14px; }
        td { font-size: 13px; }
        .status-pill { padding: 6px 9px; font-size: 10px; }
        .activity-row { gap: 12px; padding: 13px 15px; }
        .activity-icon { width: 35px; height: 35px; font-size: 15px; }
        .activity-row strong { font-size: 13px; }
        .activity-row small { margin-top: 5px; font-size: 11px; }
        .empty-state { padding: 26px 16px; font-size: 13px; }
        .primary-button { border: 0; cursor: pointer; }
        @media (max-width: 700px) {
            .admin-sidebar { width: auto; }
            .brand { min-height: 68px; padding: 14px 18px; }
            .brand-mark { width: 30px; height: 35px; font-size: 15px; }
            .brand strong { font-size: 15px; }
            .brand small { font-size: 9px; }
            .side-link { min-height: 40px; font-size: 12px; }
            .main-area { margin-left: 0; }
            .topbar { height: 64px; padding: 0 16px; }
            .dashboard-content { padding: 26px 16px 32px; }
            .welcome-row h1 { font-size: 26px; }
            .welcome-row p { font-size: 14px; }
            .metrics { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px; }
            .metric { min-height: 102px; grid-template-columns: 38px minmax(0,1fr); gap: 10px; padding: 12px; }
            .metric-icon { width: 38px; height: 38px; font-size: 18px; }
            .metric-label { font-size: 11px; }
            .metric-value { font-size: 26px; }
            .metric-note { font-size: 9px; }
            .panel-heading { font-size: 14px; }
            .record-box span, .service-detail { font-size: 10px; }
            .record-box strong { font-size: 19px; }
            td { font-size: 12px; }
        }
    </style>
    <div class="admin-shell">
            <div class="dashboard-content">
                <section class="welcome-row">
                    <div>
                        <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ Auth::user()->name }}.</h1>
                        <p>System-wide status and governance overview for BFP</p>
                    </div>
                </section>

                <section class="metrics" aria-label="System metrics">
                    <button type="button" class="metric" data-modal-open="dashboard-users-modal" style="--accent:#18385e;--tint:#edf2f8"><span class="metric-icon">♙</span><span><span class="metric-label">Total Users</span><span class="metric-value">{{ $userCount }}</span><span class="metric-note">Across all roles</span></span></button>
                    <button type="button" class="metric" data-modal-open="dashboard-equipment-modal" style="--accent:#3679cb;--tint:#edf4fc"><span class="metric-icon">⬡</span><span><span class="metric-label">Total Equipment</span><span class="metric-value">{{ $equipmentCount }}</span><span class="metric-note">Registered assets</span></span></button>
                    <button type="button" class="metric" data-modal-open="dashboard-active-users-modal" style="--accent:#1c9b76;--tint:#eaf7f2"><span class="metric-icon">✓</span><span><span class="metric-label">Active Users</span><span class="metric-value">{{ $activeUserCount }}</span><span class="metric-note">{{ $userCount - $activeUserCount }} inactive accounts</span></span></button>
                    <button type="button" class="metric" data-modal-open="dashboard-low-modal" style="--accent:#1c9b76;--tint:#eaf7f2"><span class="metric-icon">✓</span><span><span class="metric-label">Low Priority</span><span class="metric-value">{{ $priorityCounts['Low'] }}</span><span class="metric-note">Normal maintenance interval</span></span></button>
                    <button type="button" class="metric" data-modal-open="dashboard-medium-modal" style="--accent:#d4960b;--tint:#fff7df"><span class="metric-icon">◷</span><span><span class="metric-label">Medium Priority</span><span class="metric-value">{{ $priorityCounts['Medium'] }}</span><span class="metric-note">Approaching due dates</span></span></button>
                    <button type="button" class="metric" data-modal-open="dashboard-high-modal" style="--accent:#7549d8;--tint:#f2effc"><span class="metric-icon">△</span><span><span class="metric-label">High Priority</span><span class="metric-value">{{ $priorityCounts['High'] }}</span><span class="metric-note">Review required</span></span></button>
                </section>

                <section class="panel" style="margin-bottom: 12px">
                    <div class="panel-heading">Equipment priorities <button type="button" class="text-link" style="border:0;background:transparent;cursor:pointer" data-modal-open="dashboard-equipment-list-modal">View equipment →</button></div>
                    <div class="priority-level-summary" aria-label="Equipment priority levels">
                        @foreach($priorityCounts as $label => $total)
                            <div class="priority-level-count">
                                <span class="status-pill {{ $label === 'High' ? 'danger' : ($label === 'Medium' ? 'warning' : '') }}">{{ $label }} priority</span>
                                <strong>{{ number_format($total) }}</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Equipment</th><th>Priority</th><th>Reason</th><th>Next due date</th></tr></thead>
                            <tbody>
                                @forelse($priorityRecords->take(3) as $record)
                                    @php
                                        $priorityEquipment = $record['equipment'];
                                    @endphp
                                    <tr>
                                        <td>{{ $priorityEquipment->equipment_name }}</td>
                                        <td><span class="status-pill {{ $record['priority'] === 'High' ? 'danger' : ($record['priority'] === 'Medium' ? 'warning' : '') }}">{{ $record['priority'] }}</span></td>
                                        <td>{{ $record['reason'] }}</td>
                                        <td>{{ $record['due_date']?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="empty-state">No equipment records are available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="lower-grid">
                    <article class="panel">
                        <div class="panel-heading">User management overview</div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->role === 'pending' ? 'Pending approval' : ucfirst(str_replace('_', ' ', $user->role ?? '')) }}</td>
                                            <td><span class="status-pill {{ $user->status === 'active' ? '' : 'inactive' }}">{{ $user->status ?: 'unknown' }}</span></td>
                                            <td>{{ $user->created_at?->format('M, d, Y') ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="empty-state">No user accounts found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                    <article class="panel">
                        <div class="panel-heading">Recent records</div>
                        @forelse($recentActivity as $activity)
                            <div class="activity-row">
                                <span class="activity-icon">•</span>
                                <span><strong>{{ $activity['type'] }}</strong><small>{{ $activity['detail'] ?: 'Record' }} · {{ $activity['date']?->diffForHumans() ?? 'Date unavailable' }}</small></span>
                            </div>
                        @empty
                            <div class="empty-state">No inspection, maintenance, or equipment records yet.</div>
                        @endforelse
                    </article>
                </section>
            </div>
    </div>

    <dialog id="dashboard-users-modal" class="app-modal equipment-view-modal" aria-labelledby="dashboard-users-title">
        <div class="app-modal-panel" style="max-width:700px">
            <header class="app-modal-header"><div><h2 id="dashboard-users-title">Users</h2><p>{{ number_format($userCount) }} user accounts</p></div><button type="button" class="app-modal-close" aria-label="Close users" data-modal-close>&times;</button></header>
            <div class="app-modal-body"><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody>@forelse($metricUsers as $user)<tr><td>{{ $user->name }}</td><td>{{ ucfirst(str_replace('_', ' ', $user->role ?: '')) }}</td><td>{{ $user->status ?: 'Unknown' }}</td></tr>@empty<tr><td colspan="3" class="empty-state">No users found.</td></tr>@endforelse</tbody></table></div></div>
            <footer class="app-modal-footer"><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    <dialog id="dashboard-active-users-modal" class="app-modal equipment-view-modal" aria-labelledby="dashboard-active-users-title">
        <div class="app-modal-panel" style="max-width:700px">
            <header class="app-modal-header"><div><h2 id="dashboard-active-users-title">Active users</h2><p>{{ number_format($activeUserCount) }} active accounts</p></div><button type="button" class="app-modal-close" aria-label="Close active users" data-modal-close>&times;</button></header>
            <div class="app-modal-body"><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody>@forelse($metricActiveUsers as $user)<tr><td>{{ $user->name }}</td><td>{{ ucfirst(str_replace('_', ' ', $user->role ?: '')) }}</td><td>{{ $user->status }}</td></tr>@empty<tr><td colspan="3" class="empty-state">No active users found.</td></tr>@endforelse</tbody></table></div></div>
            <footer class="app-modal-footer"><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    <dialog id="dashboard-equipment-modal" class="app-modal equipment-view-modal" aria-labelledby="dashboard-equipment-title">
        <div class="app-modal-panel" style="max-width:900px">
            <header class="app-modal-header"><div><h2 id="dashboard-equipment-title">Equipment list</h2><p>{{ number_format($equipmentCount) }} registered equipment records</p></div><button type="button" class="app-modal-close" aria-label="Close equipment list" data-modal-close>&times;</button></header>
            <div class="app-modal-body"><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Equipment</th><th>Property no.</th><th>Status</th><th>Location</th><th>Assigned to</th></tr></thead><tbody>@forelse($metricEquipment as $equipmentItem)<tr><td>{{ $equipmentItem->equipment_name }}</td><td>{{ $equipmentItem->asset_code }}</td><td>{{ $equipmentItem->status }}</td><td>{{ $equipmentItem->location ?: '—' }}</td><td>{{ $equipmentItem->assignedUser?->name ?: 'Unassigned' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">No equipment found.</td></tr>@endforelse</tbody></table></div></div>
            <footer class="app-modal-footer"><a class="app-modal-button" href="{{ route('equipment.index') }}">Open equipment page</a><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    @foreach(['Low', 'Medium', 'High'] as $metricPriority)
        <dialog id="dashboard-{{ strtolower($metricPriority) }}-modal" class="app-modal equipment-view-modal" aria-labelledby="dashboard-{{ strtolower($metricPriority) }}-title">
            <div class="app-modal-panel" style="max-width:900px">
                <header class="app-modal-header"><div><h2 id="dashboard-{{ strtolower($metricPriority) }}-title">{{ $metricPriority }} priority equipment</h2><p>{{ number_format($priorityCounts[$metricPriority]) }} matching records</p></div><button type="button" class="app-modal-close" aria-label="Close {{ $metricPriority }} priority list" data-modal-close>&times;</button></header>
                <div class="app-modal-body"><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Equipment</th><th>Property no.</th><th>Priority</th><th>Reason</th><th>Next due date</th></tr></thead><tbody>@forelse(($allPriorityRecords ?? $priorityRecords)->where('priority', $metricPriority) as $record)@php($equipmentItem = $record['equipment'])<tr><td><strong>{{ $equipmentItem->equipment_name }}</strong></td><td>{{ $equipmentItem->asset_code }}</td><td><span class="status-pill {{ $metricPriority === 'High' ? 'danger' : ($metricPriority === 'Medium' ? 'warning' : '') }}">{{ $metricPriority }}</span></td><td>{{ $record['reason'] }}</td><td>{{ $record['due_date']?->format('M d, Y') ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">No {{ strtolower($metricPriority) }} priority equipment found.</td></tr>@endforelse</tbody></table></div></div>
                <footer class="app-modal-footer"><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
            </div>
        </dialog>
    @endforeach

    <dialog id="dashboard-equipment-list-modal" class="app-modal equipment-view-modal" aria-labelledby="dashboard-equipment-list-title">
        <div class="app-modal-panel" style="max-width:900px">
            <header class="app-modal-header">
                <div>
                    <h2 id="dashboard-equipment-list-title">Equipment list</h2>
                    <p>{{ number_format($equipmentCount) }} registered equipment records</p>
                </div>
                <button type="button" class="app-modal-close" aria-label="Close equipment list" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <div class="ops-table-scroll">
                    <table class="ops-table report-table">
                        <thead>
                            <tr><th>Equipment</th><th>Property no.</th><th>Priority</th><th>Reason</th><th>Next due date</th></tr>
                        </thead>
                        <tbody>
                            @forelse(($allPriorityRecords ?? $priorityRecords) as $record)
                                @php($equipmentItem = $record['equipment'])
                                <tr>
                                    <td><strong>{{ $equipmentItem->equipment_name }}</strong></td>
                                    <td>{{ $equipmentItem->asset_code }}</td>
                                    <td><span class="status-pill {{ $record['priority'] === 'High' ? 'danger' : ($record['priority'] === 'Medium' ? 'warning' : '') }}">{{ $record['priority'] }}</span></td>
                                    <td>{{ $record['reason'] }}</td>
                                    <td>{{ $record['due_date']?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">No equipment records are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="app-modal-footer">
                <a class="app-modal-button" href="{{ route('equipment.index') }}">Open equipment page</a>
                <button type="button" class="app-modal-button" data-modal-close>Close</button>
            </footer>
        </div>
    </dialog>
</x-app-layout>
