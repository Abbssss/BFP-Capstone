<x-app-layout>
    <x-slot name="header"><h2>Maintenance</h2></x-slot>

    <div class="ops-page">
        @if(session('success'))<div class="ops-flash" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="ops-flash" role="alert">Please review the highlighted fields and try again.</div>@endif
        <section class="ops-card">
            <form method="GET" action="{{ route('maintenances.index') }}" class="ops-toolbar">
                <div class="ops-filters">
                    <label class="ops-search"><span aria-hidden="true">⌕</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Search records..." aria-label="Search maintenance records"></label>
                    <select name="status" class="ops-filter" aria-label="Filter by status">
                        <option value="">All status</option>
                        @foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
                    </select>
                    <select name="category" class="ops-filter" aria-label="Filter by maintenance type">
                        <option value="">All categories</option>
                        @foreach($types as $type)<option value="{{ $type }}" @selected(request('category') === $type)>{{ $type }}</option>@endforeach
                    </select>
                    <button type="submit" class="ops-button secondary small">Filter</button>
                    @if(request()->filled('search'))
                        <a class="ops-button secondary small" href="{{ route('maintenances.index', request()->except(['search', 'page'])) }}">Clear search</a>
                    @endif
                </div>
                <button type="button" class="ops-button small" data-modal-open="maintenance-create-modal">＋ Add new</button>
            </form>
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead><tr><th>Equipment</th><th>Maintenance type</th><th>Assigned personnel</th><th>Due date</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($maintenances as $maintenance)
                        <tr>
                            <td>{{ $maintenance->equipment?->equipment_name ?? '—' }}</td><td>{{ $maintenance->maintenance_type }}</td><td>{{ $maintenance->assignedTo?->name ?? $maintenance->equipment?->assignedUser?->name ?? ($maintenance->technician_name ?: 'Unassigned') }}</td>
                            <td>{{ $maintenance->next_recommended_maintenance_date?->format('M d, Y') ?? $maintenance->start_date?->format('M d, Y') ?? '—' }}</td>
                            <td><span class="ops-pill {{ in_array(strtolower($maintenance->display_priority), ['high', 'critical'], true) ? 'danger' : (strtolower($maintenance->display_priority) === 'medium' ? 'warning' : 'neutral') }}">{{ $maintenance->display_priority }}</span></td>
                            <td><span class="ops-pill {{ strtolower($maintenance->status) === 'completed' ? '' : (in_array(strtolower($maintenance->status), ['in progress', 'scheduled'], true) ? 'warning' : 'neutral') }}">{{ $maintenance->status }}</span></td>
                            <td>
                                <button type="button" class="equipment-action" data-modal-open="maintenance-record-{{ $maintenance->id }}">View</button>
                                @if($maintenance->status === 'Pending')
                                    <button type="button" class="equipment-action" data-modal-open="maintenance-status-{{ $maintenance->id }}">Update</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ops-empty">No maintenance tasks match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ops-table-footer"><span>Showing {{ $maintenances->firstItem() ?? 0 }}–{{ $maintenances->lastItem() ?? 0 }} of {{ $maintenances->total() }} records</span><div class="ops-pagination">{{ $maintenances->links() }}</div></div>
        </section>
        @if($equipmentUnderMaintenance->isNotEmpty())
            <section class="ops-card equipment-status-section">
                <div class="ops-card-heading">Equipment currently under maintenance or repair</div>
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Asset code</th><th>Equipment</th><th>Category</th><th>Location</th><th>Assigned to</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($equipmentUnderMaintenance as $item)
                                <tr>
                                    <td>{{ $item->asset_code }}</td><td>{{ $item->equipment_name }}</td><td>{{ $item->category }}</td>
                                    <td>{{ $item->location ?: '—' }}</td><td>{{ $item->assignedUser?->name ?? 'Unassigned' }}</td>
                                    <td><span class="ops-pill warning">{{ $item->status }}</span></td>
                                    <td>
                                        @if($item->status === 'Under Maintenance')
                                            <button
                                                type="button"
                                                class="equipment-action"
                                                data-modal-open="equipment-work-record-modal"
                                                data-work-record-url="{{ route('maintenance.equipment-work.store', $item) }}"
                                                data-work-equipment-id="{{ $item->id }}"
                                                data-work-equipment-name="{{ $item->equipment_name }}"
                                                data-work-kind="Maintenance"
                                                data-work-default-status="Under Maintenance"
                                            >Record maintenance</button>
                                        @else
                                            <span class="equipment-status-hint">Record this in Repairs</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
    @foreach($maintenances as $maintenance)
        @include('operations.record-view-modal', [
            'modalId' => 'maintenance-record-'.$maintenance->id,
            'recordType' => str_contains(strtolower($maintenance->maintenance_type), 'repair')
                || filled($maintenance->reported_issue) ? 'Repair' : 'Maintenance',
            'record' => $maintenance,
        ])
        @if($maintenance->status === 'Pending')
            @include('operations.pending-status-modal', [
                'modalId' => 'maintenance-status-'.$maintenance->id,
                'recordType' => 'Maintenance',
                'record' => $maintenance,
                'statusUrl' => route('maintenances.status', $maintenance),
            ])
        @endif
    @endforeach
    @include('operations.equipment-work-modal', ['workRecordKind' => 'Maintenance'])
    <dialog id="maintenance-create-modal" class="app-modal" data-open-on-load="{{ $errors->any() && old('_form') === 'create-maintenance' ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div><h2>Create maintenance task</h2><p>Schedule or record equipment maintenance work.</p></div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <form method="POST" action="{{ route('maintenances.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="create-maintenance">
                    <div class="app-modal-grid">
                        <div class="app-modal-field">
                            <label for="maintenance-equipment">Equipment</label>
                            <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                <input type="search" aria-label="Search and select equipment" placeholder="Select or search equipment..." data-equipment-select-search hidden>
                                <select id="maintenance-equipment" name="equipment_id" required data-assignee-equipment-select data-searchable-equipment>
                                    <option value="">Select equipment</option>
                                    @foreach($equipmentOptions as $item)
                                        <option value="{{ $item->id }}"
                                            data-assigned-personnel-id="{{ $item->assigned_personnel }}"
                                            data-assigned-personnel-name="{{ $item->assignedUser?->name ?? '' }}"
                                            @selected(old('equipment_id') == $item->id)>{{ $item->equipment_name }}</option>
                                    @endforeach
                                </select>
                                <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                            </div>
                            @error('equipment_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field" data-equipment-assignee-field hidden>
                            <label for="maintenance-assigned-to">Who will do the job?</label>
                            <select id="maintenance-assigned-to" name="assigned_to_id" data-maintenance-assignee-select>
                                <option value="">Select personnel</option>
                                @foreach($personnelOptions as $person)
                                    <option value="{{ $person->id }}" @selected(old('assigned_to_id') == $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <small data-equipment-assigned-personnel></small>
                            @error('assigned_to_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field"><label>Request number</label><p>Generated when saved: first 3 equipment-name letters-MM-DD-YYYY-sequence.</p></div>
                        <div class="app-modal-field">
                            <label for="maintenance-type">Maintenance type</label>
                            <input id="maintenance-type" name="maintenance_type" value="{{ old('maintenance_type', 'Preventive') }}" required>
                            @error('maintenance_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="maintenance-priority">Priority</label>
                            <select id="maintenance-priority" name="priority" required>
                                @foreach(['Low','Medium','High','Critical'] as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                                @endforeach
                            </select>
                            @error('priority')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="maintenance-status">Status</label>
                            <select id="maintenance-status" name="status" required>
                                @foreach(['Pending','Approved','Scheduled','In Progress','Completed','Cancelled'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Pending') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="maintenance-start">Start date</label>
                            <input id="maintenance-start" type="date" name="start_date" value="{{ old('start_date') }}">
                        </div>
                        <div class="app-modal-field">
                            <label for="maintenance-next-date">Next recommended maintenance date</label>
                            <input id="maintenance-next-date" type="date" name="next_recommended_maintenance_date" value="{{ old('next_recommended_maintenance_date') }}">
                            @error('next_recommended_maintenance_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="maintenance-cost">Maintenance cost</label>
                            <input id="maintenance-cost" type="number" min="0" step="0.01" name="maintenance_cost" value="{{ old('maintenance_cost') }}">
                            @error('maintenance_cost')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="maintenance-issue">Reported issue</label>
                            <textarea id="maintenance-issue" name="reported_issue">{{ old('reported_issue') }}</textarea>
                        </div>
                    </div>
                    <div class="app-modal-footer">
                        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                        <button type="submit" class="app-modal-button primary">Save maintenance task</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
</x-app-layout>
