<x-app-layout>
    <x-slot name="header"><h2>{{ $title }}</h2></x-slot>

    <div class="ops-page {{ $title === 'Repairs' ? 'repairs-page' : '' }}">
        @if($notice)
            <div class="ops-note mb-3">{{ $notice }}</div>
        @endif
        @if($statusEquipment?->isNotEmpty())
            <section class="ops-card equipment-status-section">
                <div class="ops-card-heading">Equipment currently under repair</div>
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Asset code</th><th>Equipment</th><th>Category</th><th>Location</th><th>Assigned to</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($statusEquipment as $item)
                                <tr>
                                    <td>{{ $item->asset_code }}</td>
                                    <td>{{ $item->equipment_name }}</td>
                                    <td>{{ $item->category }}</td>
                                    <td>{{ $item->location ?: '—' }}</td>
                                    <td>{{ $item->assignedUser?->name ?? 'Unassigned' }}</td>
                                    <td><span class="ops-pill warning">{{ $item->status }}</span></td>
                                    <td>
                                        @if($title === 'Repairs')
                                            <button
                                                type="button"
                                                class="equipment-action"
                                                data-modal-open="equipment-work-record-modal"
                                                data-work-record-url="{{ route('repairs.equipment-work.store', $item) }}"
                                                data-work-equipment-id="{{ $item->id }}"
                                                data-work-equipment-name="{{ $item->equipment_name }}"
                                                data-work-kind="Repair"
                                                data-work-default-status="Under Repair"
                                            >Record repair</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @if($title === 'Repairs')
                @include('operations.equipment-work-modal', ['workRecordKind' => 'Repair', 'equipmentUnderMaintenance' => $statusEquipment])
            @endif
        @endif
        <section class="ops-card">
            <form method="GET" action="{{ request()->url() }}" class="ops-toolbar">
                <div class="ops-filters">
                    <label class="ops-search">
                        <span aria-hidden="true">⌕</span>
                        <input name="search" type="search" value="{{ request('search') }}" placeholder="Search records..." aria-label="Search {{ strtolower($title) }}">
                    </label>
                    @if($statuses->isNotEmpty())
                        <select name="status" class="ops-filter" aria-label="Filter by status">
                            <option value="">All status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="ops-button secondary small">Filter</button>
                    @if(request()->filled('search'))
                        <a class="ops-button secondary small" href="{{ request()->url().'?'.http_build_query(request()->except(['search', 'page'])) }}">Clear search</a>
                    @endif
                </div>
                @if($title === 'Repairs')
                    <button type="button" class="ops-button small" data-modal-open="repair-create-modal">＋ Add repair</button>
                @elseif($title === 'Schedule' && in_array(auth()->user()->role, ['super_admin', 'admin'], true))
                    <button type="button" class="ops-button small" data-modal-open="schedule-create-modal">＋ Set schedule</button>
                @elseif($createUrl)
                    <a class="ops-button small" href="{{ $createUrl }}">＋ Add new</a>
                @endif
            </form>
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead>
                        <tr>
                            @foreach($columns as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedRecords as $record)
                            <tr>
                                @foreach($columns as $key => $label)
                                    @php
                                        $value = $record[$key] ?? '—';
                                        $isStatus = $key === 'status';
                                        $isPriority = $key === 'priority';
                                        $pillClass = in_array(strtolower($value), ['failed', 'high', 'critical', 'overdue', 'out of service'], true)
                                            ? 'danger'
                                            : (in_array(strtolower($value), ['pending', 'due', 'medium', 'in progress', 'scheduled'], true) ? 'warning' : 'neutral');
                                    @endphp
                                    <td>
                                        @if($key === 'action' && !empty($record['record_model']))
                                            <button type="button" class="equipment-action" data-modal-open="repair-record-{{ $record['record_model']->id }}">View</button>
                                            @if($record['record_model']->status === 'Pending')
                                                <button type="button" class="equipment-action" data-modal-open="repair-status-{{ $record['record_model']->id }}">Update</button>
                                            @endif
                                        @elseif($isStatus)
                                            <span class="ops-pill {{ $pillClass }}">{{ $value }}</span>
                                        @elseif($isPriority)
                                            <span class="ops-pill {{ $pillClass }}">{{ $value }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="ops-empty">{{ $emptyMessage }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ops-table-footer">
                <span>Showing {{ $paginatedRecords->firstItem() ?? 0 }}–{{ $paginatedRecords->lastItem() ?? 0 }} of {{ $paginatedRecords->total() }} records</span>
                <div class="ops-pagination">{{ $paginatedRecords->links() }}</div>
            </div>
        </section>
    </div>
    @if($title === 'Schedule' && in_array(auth()->user()->role, ['super_admin', 'admin'], true))
        <dialog id="schedule-create-modal" class="app-modal" aria-labelledby="schedule-create-title"
            data-open-on-load="{{ $errors->any() && old('_form') === 'create-schedule' ? 'true' : 'false' }}">
            <div class="app-modal-panel" style="max-width:760px">
                <header class="app-modal-header">
                    <div><h2 id="schedule-create-title">Set equipment schedule</h2><p>Assign an inspection, maintenance, or repair to a maintenance user.</p></div>
                    <button type="button" class="app-modal-close" aria-label="Close schedule form" data-modal-close>&times;</button>
                </header>
                <div class="app-modal-body">
                    <form method="POST" action="{{ route('schedule.store') }}">
                        @csrf
                        <input type="hidden" name="_form" value="create-schedule">
                        <div class="app-modal-grid">
                            <div class="app-modal-field">
                                <label for="schedule-work-type">Schedule for</label>
                                <select id="schedule-work-type" name="work_type" required>
                                    @foreach(['Inspection', 'Maintenance', 'Repair'] as $workType)
                                        <option value="{{ $workType }}" @selected(old('work_type', 'Inspection') === $workType)>{{ $workType }}</option>
                                    @endforeach
                                </select>
                                @error('work_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="schedule-equipment">Equipment</label>
                                <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                    <input type="search" aria-label="Search equipment" placeholder="Search equipment by name or asset code..." data-equipment-select-search hidden>
                                    <select id="schedule-equipment" name="equipment_id" required data-searchable-equipment>
                                        <option value="">Select equipment</option>
                                        @foreach($equipmentOptions ?? collect() as $item)
                                            <option value="{{ $item->id }}" @selected(old('equipment_id') == $item->id)>{{ $item->equipment_name }} · {{ $item->asset_code }}</option>
                                        @endforeach
                                    </select>
                                    <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                                </div>
                                @error('equipment_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="schedule-priority">Priority level</label>
                                <select id="schedule-priority" name="priority" required>
                                    @foreach(['Low', 'Medium', 'High'] as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                                    @endforeach
                                </select>
                                @error('priority')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="schedule-assignee">Assigned maintenance user</label>
                                <select id="schedule-assignee" name="assigned_to_id" required>
                                    <option value="">Select maintenance user</option>
                                    @foreach($schedulePersonnelOptions ?? collect() as $personnel)
                                        <option value="{{ $personnel->id }}" @selected(old('assigned_to_id') == $personnel->id)>{{ $personnel->name }} · {{ $personnel->office_code ?: 'No office' }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="schedule-date">Scheduled date</label>
                                <input id="schedule-date" type="date" name="scheduled_date" value="{{ old('scheduled_date', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
                                @error('scheduled_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field full-width">
                                <label for="schedule-notes">Notes or reported issue</label>
                                <textarea id="schedule-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                                @error('notes')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <footer class="app-modal-footer">
                            <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                            <button type="submit" class="app-modal-button primary">Set schedule</button>
                        </footer>
                    </form>
                </div>
            </div>
        </dialog>
    @endif
    @if($title === 'Repairs')
        @foreach($paginatedRecords as $record)
            @if(!empty($record['record_model']))
                @include('operations.record-view-modal', [
                    'modalId' => 'repair-record-'.$record['record_model']->id,
                    'recordType' => 'Repair',
                    'record' => $record['record_model'],
                ])
                @if($record['record_model']->status === 'Pending')
                    @include('operations.pending-status-modal', [
                        'modalId' => 'repair-status-'.$record['record_model']->id,
                        'recordType' => 'Repair',
                        'record' => $record['record_model'],
                        'statusUrl' => $record['status_url'],
                    ])
                @endif
            @endif
        @endforeach
        <dialog id="repair-create-modal" class="app-modal" aria-labelledby="repair-create-title"
            data-open-on-load="{{ $errors->any() && old('_form') === 'create-repair' ? 'true' : 'false' }}">
            <div class="app-modal-panel">
                <header class="app-modal-header">
                    <div><h2 id="repair-create-title">Add repair</h2><p>Record a repair request for equipment.</p></div>
                    <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
                </header>
                <div class="app-modal-body">
                    <form method="POST" action="{{ route('maintenances.store') }}">
                        @csrf
                        <input type="hidden" name="_form" value="create-repair">
                        <div class="app-modal-grid">
                            <div class="app-modal-field">
                                <label for="repair-equipment">Equipment</label>
                                <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                    <input type="search" aria-label="Search and select equipment" placeholder="Select or search equipment..." data-equipment-select-search hidden>
                                    <select id="repair-equipment" name="equipment_id" required data-searchable-equipment>
                                        <option value="">Select equipment</option>
                                        @foreach($equipmentOptions as $item)
                                            <option value="{{ $item->id }}" @selected(old('equipment_id') == $item->id)>{{ $item->equipment_name }} · {{ $item->asset_code }}</option>
                                        @endforeach
                                    </select>
                                    <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                                </div>
                                @error('equipment_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field"><label>Request number</label><p>Generated when saved: first 3 equipment-name letters-MM-DD-YYYY-sequence.</p></div>
                            <div class="app-modal-field">
                                <label for="repair-type">Repair type</label>
                                <input id="repair-type" name="maintenance_type" value="{{ old('maintenance_type', 'Repair') }}" required>
                                @error('maintenance_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="repair-priority">Priority</label>
                                <select id="repair-priority" name="priority" required>
                                    @foreach(['Low', 'Medium', 'High', 'Critical'] as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                                    @endforeach
                                </select>
                                @error('priority')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="repair-status">Status</label>
                                <select id="repair-status" name="status" required>
                                    @foreach(['Pending', 'Approved', 'Scheduled', 'In Progress', 'Completed', 'Cancelled'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Pending') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="repair-start-date">Start date</label>
                                <input id="repair-start-date" type="date" name="start_date" value="{{ old('start_date') }}">
                                @error('start_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="repair-completion-date">Completion date</label>
                                <input id="repair-completion-date" type="date" name="completion_date" value="{{ old('completion_date') }}">
                                @error('completion_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="repair-technician">Technician</label>
                                <input id="repair-technician" name="technician_name" value="{{ old('technician_name') }}">
                                @error('technician_name')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field full-width">
                                <label for="repair-reported-issue">Reported issue</label>
                                <textarea id="repair-reported-issue" name="reported_issue" required>{{ old('reported_issue') }}</textarea>
                                @error('reported_issue')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field full-width">
                                <label for="repair-diagnosis">Diagnosis</label>
                                <textarea id="repair-diagnosis" name="diagnosis">{{ old('diagnosis') }}</textarea>
                            </div>
                            <div class="app-modal-field full-width">
                                <label for="repair-action-taken">Action taken</label>
                                <textarea id="repair-action-taken" name="action_taken">{{ old('action_taken') }}</textarea>
                                @error('action_taken')<span class="app-modal-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="app-modal-field full-width">
                                <label for="repair-parts-replaced">Parts replaced</label>
                                <textarea id="repair-parts-replaced" name="parts_replaced">{{ old('parts_replaced') }}</textarea>
                            </div>
                        </div>
                        <footer class="app-modal-footer">
                            <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                            <button type="submit" class="app-modal-button primary">Save repair</button>
                        </footer>
                    </form>
                </div>
            </div>
        </dialog>
    @endif
</x-app-layout>
