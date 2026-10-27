<x-app-layout>
    <x-slot name="header"><h2>Settings</h2></x-slot>

    <div class="ops-page">
        @if(session('status') === 'profile-updated')<div class="ops-flash" role="status">Account information saved.</div>@endif
        <div class="ops-grid-sidebar" style="align-items:stretch">
            <nav class="ops-card ops-tabs" aria-label="Settings sections">
                <a class="ops-tab active" href="#account-settings-panel" aria-current="page"><span aria-hidden="true">♙</span>Account</a>
                @if($canAccessDashboard)
                    <a class="ops-tab" href="{{ route('dashboard') }}"><span aria-hidden="true">⚙</span>System overview</a>
                @else
                    <span class="ops-tab disabled" aria-disabled="true" title="Dashboard access is not enabled for your role"><span aria-hidden="true">⚙</span>System overview <small>Unavailable</small></span>
                @endif
                @if($canManageRoles)
                    <a class="ops-tab" href="{{ route('roles.permissions') }}"><span aria-hidden="true">♙</span>User &amp; Role</a>
                @elseif($canViewPersonnel)
                    <a class="ops-tab" href="{{ route('personnel.index') }}"><span aria-hidden="true">♙</span>Personnel</a>
                @else
                    <span class="ops-tab disabled" aria-disabled="true" title="User and role management is not enabled for your role"><span aria-hidden="true">♙</span>User &amp; Role <small>Unavailable</small></span>
                @endif
                <span class="ops-tab disabled" aria-disabled="true" title="Notification preferences are not available yet"><span aria-hidden="true">♧</span>Notifications <small>Not configured</small></span>
                @if($canViewReports)
                    <a class="ops-tab" href="{{ route('reports.index') }}"><span aria-hidden="true">▤</span>Reports &amp; exports</a>
                @else
                    <span class="ops-tab disabled" aria-disabled="true" title="Reports access is not enabled for your role"><span aria-hidden="true">▤</span>Reports &amp; exports <small>Unavailable</small></span>
                @endif
            </nav>
            <section class="ops-card" id="account-settings-panel">
                <div class="ops-card-heading">Account</div>
                <div class="ops-card-body">
                    <p style="margin:0 0 14px;color:#8996a7;font-size:10px">Update your personal profile information.</p>
                    <form id="account-settings" method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="return_to" value="settings.index">
                        <div class="ops-form-grid">
                            <div class="ops-form-field">
                                <label for="settings-name">Full name</label>
                                <input id="settings-name" class="ops-input" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')<span class="ops-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="ops-form-field">
                                <label for="settings-email">Official email</label>
                                <input id="settings-email" class="ops-input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')<span class="ops-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="ops-form-field">
                                <label for="settings-role">Role</label>
                                <input id="settings-role" class="ops-input" value="{{ str_replace('_', ' ', ucfirst($user->role)) }}" readonly>
                            </div>
                            <div class="ops-form-field">
                                <label>Password</label>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid #e7ebf0;padding-top:10px">
                                    <span style="color:#718197;font-size:10px">For password changes, open profile security.</span>
                                    <a class="ops-button secondary small" href="{{ route('profile.edit') }}">Change password</a>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="ops-button" style="margin-top:14px">Save changes</button>
                    </form>
                    @if($errors->any())<div class="ops-flash" style="margin-top:14px" role="alert">Please correct the account details and try again.</div>@endif
                </div>
            </section>
        </div>
        <p style="margin:10px 0;color:#8996a7;font-size:10px">Notification preferences and database backups are not available yet. Use Reports &amp; exports for permitted operational data exports.</p>
    </div>
</x-app-layout>
