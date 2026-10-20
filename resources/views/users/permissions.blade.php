<x-app-layout>
    <x-slot name="header"><h2>Roles &amp; Permissions</h2></x-slot>

    <style>
        :root { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #122744; background: #f1f3f7; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; }
        a { color: inherit; text-decoration: none; }
        .admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 10; display: flex; width: 240px; flex-direction: column; background: #0c2749; color: #d9e4f2; }
        .brand { display: flex; min-height: 112px; align-items: flex-start; gap: 13px; border-bottom: 1px solid rgba(210,226,245,.12); padding: 25px 21px 17px; }
        .brand-mark { display: grid; width: 34px; height: 40px; flex: 0 0 auto; place-items: center; clip-path: polygon(50% 0,100% 18%,100% 73%,50% 100%,0 73%,0 18%); background: #c51f25; color: #fff; font-size: 17px; font-weight: 800; }
        .brand strong { display: block; color: #fff; font-size: 17px; }
        .brand small { display: block; margin-top: 4px; color: #a7b7cb; font-size: 10px; font-weight: 700; letter-spacing: .1em; line-height: 1.4; text-transform: uppercase; }
        .station-name { border-bottom: 1px solid rgba(210,226,245,.12); padding: 0 21px 18px; color: #8da5c2; font-size: 10px; font-weight: 700; letter-spacing: .13em; }
        .nav-caption { padding: 23px 21px 11px; color: #7089a8; font-size: 10px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        .side-nav { display: grid; gap: 5px; padding: 0 10px; }
        .side-link { display: flex; min-height: 43px; align-items: center; gap: 12px; border-radius: 5px; padding: 0 12px; color: #c1d0e2; font-size: 13px; font-weight: 600; }
        .side-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .side-link.active { background: #bd1f24; color: #fff; }
        .side-icon { width: 17px; color: #a9bdd4; font-size: 16px; text-align: center; }
        .side-link.active .side-icon { color: #fff; }
        .sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(210,226,245,.12); padding: 17px 17px 20px; }
        .profile-link { display: flex; align-items: center; gap: 10px; }
        .avatar { display: grid; width: 32px; height: 32px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: #e2ac13; color: #14243a; font-size: 11px; font-weight: 800; }
        .profile-link strong { display: block; color: #fff; font-size: 12px; }
        .profile-link small { display: block; margin-top: 3px; color: #9cb0c9; font-size: 11px; }
        .signout { display: block; margin-top: 15px; border: 0; padding: 0; background: transparent; color: #9fb3cb; cursor: pointer; font-size: 11px; }
        .main-area { min-height: 100vh; margin-left: 240px; }
        .topbar { display: flex; height: 72px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e0e5ec; padding: 0 28px; background: #fff; }
        .crumb { color: #8693a4; font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .page-title { margin-top: 4px; color: #102541; font-size: 20px; font-weight: 600; }
        .top-actions { display: flex; align-items: center; gap: 11px; }
        .top-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 6px; background: #f2f4f8; color: #536780; font-size: 18px; }
        .station-date { margin-left: 3px; border-left: 1px solid #e3e7ed; padding-left: 15px; text-align: right; }
        .station-date small { display: block; color: #8996a7; font-size: 9px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .station-date strong { display: block; margin-top: 3px; color: #18304f; font-size: 12px; }
        .content { max-width: 1600px; margin: 0 auto; padding: 30px clamp(18px, 3.2vw, 48px) 48px; }
        .intro { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
        h1 { margin: 0 0 6px; color: #183252; font-size: 29px; font-weight: 500; letter-spacing: -.025em; }
        .intro p { margin: 0; color: #78879a; font-size: 14px; }
        .button-primary { display: inline-flex; min-height: 44px; flex: 0 0 auto; align-items: center; justify-content: center; border: 0; border-radius: 5px; padding: 0 18px; background: #bd1f24; box-shadow: 0 4px 9px rgba(128,22,27,.15); color: #fff; font-size: 13px; font-weight: 700; }
        .notice { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; border: 1px solid #cfe0f3; border-radius: 6px; padding: 12px 14px; background: #edf5fc; color: #365976; font-size: 12px; line-height: 1.55; }
        .flash { margin-bottom: 14px; border: 1px solid #b8e3cf; border-radius: 6px; padding: 11px 14px; background: #effaf4; color: #17613e; font-size: 12px; }
        .flash.error { border-color: #efc5c5; background: #fff1f1; color: #8d2428; }
        .role-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; margin-bottom: 14px; }
        .role-card { min-height: 122px; border: 1px solid #dfe4eb; border-radius: 7px; padding: 15px; background: #fff; }
        .role-icon { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 5px; background: #edf2f8; color: #23476f; font-size: 16px; }
        .role-card h2 { margin: 10px 0 4px; color: #173251; font-size: 13px; }
        .role-count { color: #b82025; font-size: 10px; font-weight: 700; }
        .role-card p { margin: 8px 0 0; color: #8996a7; font-size: 10px; }
        .matrix { overflow: hidden; border: 1px solid #dfe4eb; border-radius: 7px; background: #fff; }
        .matrix-title { border-bottom: 1px solid #e2e7ee; padding: 15px 16px; color: #1b3555; font-size: 14px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f7f8fa; }
        th { color: #718198; font-size: 10px; font-weight: 700; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e7ebf0; padding: 13px 16px; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #fbfcfe; }
        td { color: #344c69; font-size: 12px; }
        td:first-child { min-width: 210px; color: #243f5e; font-weight: 600; }
        td:first-child small { display: block; margin-top: 3px; color: #8996a7; font-size: 10px; font-weight: 400; }
        .access { display: inline-flex; min-width: 78px; min-height: 25px; align-items: center; justify-content: center; border-radius: 14px; padding: 5px 10px; background: #e4f4ee; color: #19815e; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .access.none { background: #edf0f4; color: #69788b; }
        .access.unconfigured { background: #fff2d3; color: #8b6415; }
        .permission-toggle { display: inline-flex; align-items: center; gap: 8px; color: #536780; cursor: pointer; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .permission-toggle input { width: 17px; height: 17px; accent-color: #16845f; cursor: pointer; }
        .permission-toggle input:focus-visible { outline: 3px solid rgba(45,93,145,.25); outline-offset: 2px; }
        .matrix-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #e7ebf0; padding: 13px 16px; }
        .matrix-footnote { color: #718198; font-size: 11px; line-height: 1.5; }
        .save-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; border: 0; border-radius: 5px; padding: 0 16px; background: #bd1f24; box-shadow: 0 4px 9px rgba(128,22,27,.15); color: #fff; cursor: pointer; font: inherit; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .save-button:hover { background: #a9191e; }
        .save-button:focus-visible { outline: 3px solid rgba(189,31,36,.25); outline-offset: 2px; }
        @media (max-width: 850px) {
            .admin-sidebar { position: static; width: auto; min-height: 0; }
            .brand { min-height: 70px; padding: 15px 18px; }
            .station-name, .nav-caption, .sidebar-bottom { display: none; }
            .side-nav { display: flex; overflow-x: auto; padding: 7px; }
            .side-link { min-height: 40px; flex: 0 0 auto; padding: 0 10px; font-size: 12px; }
            .main-area { margin-left: 0; }
            .topbar { height: 64px; padding: 0 16px; }
            .content { padding: 26px 16px 36px; }
        }
        @media (max-width: 650px) {
            .intro { align-items: flex-start; flex-direction: column; }
            .role-grid { grid-template-columns: 1fr; }
            .station-date { display: none; }
            th, td { padding: 11px 10px; }
            .matrix-actions { align-items: flex-start; flex-direction: column; }
            .save-button { align-self: stretch; }
        }
    </style>
    <div class="permissions-page">
        <section class="content">
            <div class="intro">
                <div>
                    <h1>Roles &amp; permissions</h1>
                    <p>Choose which application modules Admin and Maintenance Personnel can open.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="flash" role="status">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="flash error" role="alert">Some permission values were invalid. Review the form and save again.</div>
            @endif

            <div class="notice" role="note">
                <span aria-hidden="true">ⓘ</span>
                <span>Super Admin always has full access. User management, role permissions, and audit logs stay restricted to Super Admin. Changes to other roles are saved immediately and enforced on the next request.</span>
            </div>

            <section class="role-grid" aria-label="Application roles">
                @foreach($roles as $key => $role)
                    <article class="role-card">
                        <span class="role-icon" aria-hidden="true">{{ $role['icon'] }}</span>
                        <h2>{{ $role['name'] }}</h2>
                        <span class="role-count">{{ $role['user_count'] }} {{ \Illuminate\Support\Str::plural('user', $role['user_count']) }}</span>
                        <p>{{ $role['description'] }}</p>
                    </article>
                @endforeach
            </section>

            <form method="POST" action="{{ route('roles.permissions.update') }}">
                @csrf
                <section class="matrix" aria-label="Module access matrix">
                    <div class="matrix-title">Module access matrix</div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Module</th>
                                    @foreach($roles as $role)
                                        <th scope="col">{{ $role['name'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modules as $key => $module)
                                    <tr>
                                        <td>
                                            {{ $module['name'] }}
                                            @if(!empty($module['note']))<small>{{ $module['note'] }}</small>@endif
                                        </td>
                                        <td>
                                            <span class="access">{{ !empty($module['note']) ? 'Not configured' : ($key === 'user_management' ? 'Full access' : 'Always allowed') }}</span>
                                        </td>
                                        @foreach(['admin', 'maintenance'] as $role)
                                            <td>
                                                @if(!empty($module['locked']))
                                                    <span class="access {{ !empty($module['note']) ? 'unconfigured' : 'none' }}">{{ !empty($module['note']) ? 'Not configured' : 'Super Admin only' }}</span>
                                                @else
                                                    <label class="permission-toggle">
                                                        <input type="checkbox" name="permissions[{{ $role }}][{{ $key }}]" value="1" @checked($permissions[$role][$key])>
                                                        <span>Allow access</span>
                                                    </label>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="matrix-actions">
                        <span class="matrix-footnote">Unchecked modules are blocked by the server. Super Admin access cannot be removed.</span>
                        <button type="submit" class="save-button"><span aria-hidden="true">✓</span>Save permissions</button>
                    </div>
                </section>
            </form>
        </section>
    </div>
</x-app-layout>
