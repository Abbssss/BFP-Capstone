<x-app-layout>
    <x-slot name="header"><h2>Audit Logs</h2></x-slot>

    <div class="ops-page">
        <div class="ops-intro">
            <div><span class="ops-eyebrow">Governance</span><h1>Audit logs</h1><p>Traceable history of access, record changes, approvals, and prediction activity.</p></div>
        </div>
        <div class="ops-note" role="status"><strong>Audit logging is not configured.</strong> The application does not currently persist access or change events, so there are no audit records to display or export.</div>
        <section class="ops-card" style="margin-top: 14px">
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead><tr><th>Date / time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Record</th><th>Details</th></tr></thead>
                    <tbody><tr><td colspan="7" class="ops-empty">No audit records are available because event logging has not been enabled.</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
