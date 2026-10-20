<x-app-layout>
    <x-slot name="header"><h2>User Management</h2></x-slot>

    <style>
        :root { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #122744; background: #f1f3f7; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; }
        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }
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
        .content { max-width: 1600px; margin: 0 auto; padding: 34px 38px 48px; }
        .intro { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 22px; }
        .eyebrow { display: inline-block; border-radius: 3px; padding: 5px 8px; background: #fff0ef; color: #b62025; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 11px 0 6px; color: #183252; font-size: 29px; font-weight: 500; letter-spacing: -.025em; }
        .intro p { margin: 0; color: #78879a; font-size: 14px; }
        .button-primary { display: inline-flex; min-height: 44px; flex: 0 0 auto; align-items: center; justify-content: center; gap: 9px; border: 0; border-radius: 5px; padding: 0 18px; background: #bd1f24; box-shadow: 0 4px 9px rgba(128,22,27,.15); color: #fff; cursor: pointer; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .button-primary:hover { background: #a9191e; }
        .table-card { overflow: visible; border: 1px solid #dfe4eb; border-radius: 8px; background: #fff; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; border-bottom: 1px solid #e3e8ef; padding: 13px 15px; }
        .filters { display: flex; flex: 1; flex-wrap: wrap; align-items: center; gap: 9px; }
        .search-wrap { position: relative; width: min(100%, 330px); }
        .search-icon { position: absolute; top: 50%; left: 12px; color: #8291a4; font-size: 15px; transform: translateY(-50%); pointer-events: none; }
        .search-input, .filter-select { height: 40px; border: 1px solid #cfd8e3; border-radius: 5px; background: #fff; color: #42556d; font-size: 12px; outline: none; }
        .search-input { width: 100%; padding: 0 12px 0 36px; }
        .filter-select { min-width: 132px; padding: 0 30px 0 12px; }
        .search-input:focus, .filter-select:focus { border-color: #7495bc; box-shadow: 0 0 0 3px rgba(45,93,145,.1); }
        .toolbar .button-primary { min-height: 38px; font-size: 12px; }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f7f8fa; }
        th { color: #8391a4; font-size: 9px; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e7ebf0; padding: 14px 13px; }
        td { color: #52677f; font-size: 12px; }
        tbody tr:last-child td { border-bottom: 0; }
        .user-name { color: #314a68; font-weight: 600; }
        .username { color: #728198; }
        .role-label { color: #566b84; }
        .status-pill { display: inline-block; border-radius: 15px; padding: 5px 9px; background: #e4f4ee; color: #19815e; font-size: 10px; font-weight: 700; text-transform: capitalize; }
        .status-pill.inactive { background: #edf0f4; color: #69788b; }
        .last-login { color: #78889c; font-size: 11px; }
        .manage-button { border: 0; padding: 0; background: transparent; color: #b81f24; cursor: pointer; font-size: 12px; font-weight: 700; }
        .flash { margin-bottom: 15px; border: 1px solid #b8e3cf; border-radius: 6px; padding: 11px 14px; background: #effaf4; color: #17613e; font-size: 13px; }
        .empty-state { padding: 30px 15px; color: #8291a4; font-size: 13px; text-align: center; }
        .table-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #e3e8ef; padding: 12px 15px; color: #8391a4; font-size: 10px; }
        .pagination { display: flex; align-items: center; gap: 5px; }
        .pagination a, .pagination span { display: grid; min-width: 29px; height: 29px; place-items: center; border: 1px solid #dfe4eb; border-radius: 4px; padding: 0 7px; color: #61758e; font-size: 11px; }
        .pagination .current { border-color: #0c2749; background: #0c2749; color: #fff; }
        .pagination .disabled { color: #b5bfcc; }
        @media (min-width: 1500px) { .content { padding: 42px 48px 56px; } }
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
        @media (max-width: 600px) {
            .intro { align-items: flex-start; flex-direction: column; }
            h1 { font-size: 25px; }
            .toolbar { align-items: stretch; flex-direction: column; }
            .filters { align-items: stretch; flex-direction: column; }
            .search-wrap { width: 100%; }
            .filter-select { width: 100%; }
            .toolbar > .button-primary { align-self: flex-start; }
            .table-footer { align-items: flex-start; flex-direction: column; }
            .station-date { display: none; }
        }
    </style>
    <div class="user-management-page">
        <section class="content">
            <div class="intro">
                <div>
                    <span class="eyebrow">Live data</span>
                    <h1>User Management</h1>
                    <p>Create accounts and control system access across all three authorized roles.</p>
                </div>
                <button type="button" class="button-primary" data-modal-open="user-create-modal"><span aria-hidden="true">＋</span>Create new</button>
            </div>

            @if (session('success'))
                <div class="flash" role="status">{{ session('success') }}</div>
            @endif

            <section class="table-card" aria-label="User accounts">
                <div class="toolbar">
                    <form method="GET" action="{{ route('users.index') }}" class="filters">
                        <label class="search-wrap">
                            <span class="search-icon" aria-hidden="true">⌕</span>
                            <input class="search-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search records..." aria-label="Search by name or email">
                        </label>
                        <select class="filter-select" name="status" aria-label="Filter by status">
                            <option value="">All status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                        <select class="filter-select" name="role" aria-label="Filter by role">
                            <option value="">All categories</option>
                            <option value="super_admin" @selected(request('role') === 'super_admin')>Super Admin</option>
                            <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                            <option value="maintenance" @selected(request('role') === 'maintenance')>Maintenance</option>
                        </select>
                        <button class="button-primary" type="submit">Filter</button>
                        @if(request()->hasAny(['search', 'status', 'role']))
                            <a href="{{ route('users.index') }}" class="filter-select" style="display:inline-flex;align-items:center;justify-content:center">Clear</a>
                        @endif
                    </form>
                    <button type="button" class="button-primary" data-modal-open="user-create-modal"><span aria-hidden="true">＋</span>Add new</button>
                </div>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Office Code</th>
                                <th>Status</th>
                                <th>Last login</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="user-name">{{ $user->name }}</td>
                                    <td class="username">{{ \Illuminate\Support\Str::before($user->email, '@') }}</td>
                                    <td class="role-label">{{ ucfirst(str_replace('_', ' ', $user->role ?? 'maintenance')) }}</td>
                                    <td>{{ $user->office_code ?: '—' }}</td>
                                    <td><span class="status-pill {{ $user->status === 'active' ? '' : 'inactive' }}">{{ $user->status ?: 'unknown' }}</span></td>
                                    <td class="last-login">Not recorded</td>
                                    <td>
                                        <button type="button" class="manage-button" data-modal-open="manage-user-{{ $user->id }}">Manage</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state">No user accounts match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <footer class="table-footer">
                    <span>
                        @if($users->total())
                            Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }} records
                        @else
                            Showing 0 records
                        @endif
                    </span>
                    @if($users->hasPages())
                        <nav class="pagination" aria-label="User table pagination">
                            @if($users->onFirstPage())
                                <span class="disabled" aria-disabled="true">←</span>
                            @else
                                <a href="{{ $users->previousPageUrl() }}" rel="prev" aria-label="Previous page">←</a>
                            @endif
                            @foreach($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                                @if($page === $users->currentPage())
                                    <span class="current" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}">{{ $page }}</a>
                                @endif
                            @endforeach
                            @if($users->hasMorePages())
                                <a href="{{ $users->nextPageUrl() }}" rel="next" aria-label="Next page">→</a>
                            @else
                                <span class="disabled" aria-disabled="true">→</span>
                            @endif
                        </nav>
                    @endif
                </footer>
            </section>
        </section>
    @foreach($users as $user)
        <dialog id="manage-user-{{ $user->id }}" class="app-modal" data-open-on-load="{{ $errors->any() && old('_form') === 'manage-user' && (string) old('user_id') === (string) $user->id ? 'true' : 'false' }}">
            <div class="app-modal-panel">
                <header class="app-modal-header">
                    <div>
                        <h2>Manage account</h2>
                        <p>Update the role, office assignment, or access status for {{ $user->name }}.</p>
                    </div>
                    <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
                </header>
                <div class="app-modal-body">
                    <form method="POST" action="{{ route('users.role', $user) }}">
                        @csrf
                        <input type="hidden" name="_form" value="manage-user">
                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                        <div class="app-modal-grid">
                            <div class="app-modal-field">
                                <label for="manage-role-{{ $user->id }}">Role</label>
                                <select id="manage-role-{{ $user->id }}" name="role" required data-office-role>
                                    @foreach(['super_admin' => 'Super Admin', 'admin' => 'Admin', 'maintenance' => 'Maintenance'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="manage-office-code-{{ $user->id }}">Office Code</label>
                                <input id="manage-office-code-{{ $user->id }}" name="office_code" value="{{ old('user_id') == $user->id ? old('office_code', $user->office_code) : $user->office_code }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" data-office-code>
                                <small>Super Admin accounts are not assigned to an office.</small>
                                @error('office_code') @if(old('user_id') == $user->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="manage-status-{{ $user->id }}">Account status</label>
                                <select id="manage-status-{{ $user->id }}" name="status" required>
                                    <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                                </select>
                                @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="app-modal-footer">
                            <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                            <button type="submit" class="app-modal-button primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    @endforeach
    <dialog id="user-create-modal" class="app-modal" data-open-on-load="{{ $errors->any() && old('_form') === 'create-user' ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div>
                    <h2>Create user account</h2>
                    <p>Add an authorized account and assign its role.</p>
                </div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                @include('users._create-form', ['formPrefix' => 'new-user'])
            </div>
        </div>
    </dialog>
    </div>
</x-app-layout>
