<x-app-layout>
    <x-slot name="header"><h2>Inspections</h2></x-slot>

    <div class="ops-page">
        @if(session('success'))<div class="ops-flash" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="ops-flash" role="alert">Please review the highlighted fields and try again.</div>@endif
        <section class="ops-card">
            <form method="GET" action="{{ route('inspections.index') }}" class="ops-toolbar">
                <div class="ops-filters">
                    <label class="ops-search"><span aria-hidden="true">⌕</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Search records..." aria-label="Search inspections"></label>
                    <select name="status" class="ops-filter" aria-label="Filter by outcome">
                        <option value="">All status</option>
                        @foreach($outcomes as $outcome)<option value="{{ $outcome }}" @selected(request('status') === $outcome)>{{ $outcome }}</option>@endforeach
                    </select>
                    <select name="category" class="ops-filter" aria-label="Filter by inspection type">
                        <option value="">All categories</option>
                        @foreach($types as $type)<option value="{{ $type }}" @selected(request('category') === $type)>{{ $type }}</option>@endforeach
                    </select>
                    <button type="submit" class="ops-button secondary small">Filter</button>
                    @if(request()->filled('search'))
                        <a class="ops-button secondary small" href="{{ route('inspections.index', request()->except(['search', 'page'])) }}">Clear search</a>
                    @endif
                </div>
                <button type="button" class="ops-button small" data-modal-open="inspection-create-modal">＋ Add new</button>
            </form>
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead><tr><th>Record</th><th>Type</th><th>Equipment</th><th>Inspector</th><th>Date</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($inspections as $inspection)
                        <tr>
                            <td>{{ $inspection->reference_number }}</td><td>{{ $inspection->inspection_type }}</td><td>{{ $inspection->equipment?->equipment_name ?? '—' }}</td><td>{{ $inspection->inspector_name }}</td>
                            <td>{{ $inspection->inspection_date?->format('M d, Y') ?? $inspection->inspection_date }}</td>
                            <td><span class="ops-pill {{ $inspection->equipment_priority === 'High' ? 'danger' : ($inspection->equipment_priority === 'Medium' ? 'warning' : 'neutral') }}">{{ $inspection->equipment_priority ?? '—' }}</span></td>
                            <td><span class="ops-pill {{ $inspection->status === 'Pending' ? 'warning' : ($inspection->outcome === 'Failed' ? 'danger' : ($inspection->outcome === 'Passed' ? '' : 'warning')) }}">{{ $inspection->status === 'Pending' ? 'Pending' : $inspection->outcome }}</span></td>
                            <td>
                                <button type="button" class="equipment-action" data-modal-open="inspection-record-{{ $inspection->id }}">View</button>
                                @if($inspection->status === 'Pending')
                                    <button type="button" class="equipment-action" data-modal-open="inspection-status-{{ $inspection->id }}">Update</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ops-empty">No inspection records match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ops-table-footer"><span>Showing {{ $inspections->firstItem() ?? 0 }}–{{ $inspections->lastItem() ?? 0 }} of {{ $inspections->total() }} records</span><div class="ops-pagination">{{ $inspections->links() }}</div></div>
        </section>
        @if($priorityInspectionSchedule->isNotEmpty())
            <section class="ops-card equipment-status-section">
                <div class="ops-card-heading">High-priority inspections</div>
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Asset code</th><th>Equipment</th><th>Assigned to</th><th>Inspection date</th><th>Priority</th></tr></thead>
                        <tbody>
                            @foreach($priorityInspectionSchedule as $record)
                                <tr>
                                    <td>{{ $record['equipment']->asset_code }}</td>
                                    <td>{{ $record['equipment']->equipment_name }}</td>
                                    <td>{{ $record['equipment']->assignedUser?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $record['date']->format('M d, Y') }}</td>
                                    <td><span class="ops-pill danger">{{ $record['priority'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
        @if($equipmentUnderInspection->isNotEmpty())
            <section class="ops-card equipment-status-section">
                <div class="ops-card-heading">Equipment currently under inspection</div>
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Asset code</th><th>Equipment</th><th>Category</th><th>Location</th><th>Assigned to</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($equipmentUnderInspection as $item)
                                <tr>
                                    <td>{{ $item->asset_code }}</td><td>{{ $item->equipment_name }}</td><td>{{ $item->category }}</td>
                                    <td>{{ $item->location ?: '—' }}</td><td>{{ $item->assignedUser?->name ?? 'Unassigned' }}</td>
                                    <td><span class="ops-pill warning">{{ $item->status }}</span></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="equipment-action"
                                            data-modal-open="complete-equipment-inspection-modal"
                                            data-inspection-equipment-id="{{ $item->id }}"
                                            data-inspection-equipment-name="{{ $item->equipment_name }}"
                                        >Record inspection</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
    @foreach($inspections as $inspection)
        @include('operations.record-view-modal', [
            'modalId' => 'inspection-record-'.$inspection->id,
            'recordType' => 'Inspection',
            'record' => $inspection,
        ])
        @if($inspection->status === 'Pending')
            @include('operations.pending-status-modal', [
                'modalId' => 'inspection-status-'.$inspection->id,
                'recordType' => 'Inspection',
                'record' => $inspection,
                'statusUrl' => route('inspections.status', $inspection),
            ])
        @endif
    @endforeach
    <dialog id="complete-equipment-inspection-modal" class="app-modal equipment-view-modal"
        data-open-on-load="{{ $errors->any() && old('_form') === 'complete-equipment-inspection' ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div><h2>Record inspection and update status</h2><p id="complete-inspection-equipment-name">Equipment under inspection</p></div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <form method="POST" action="{{ route('inspections.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="complete-equipment-inspection">
                    <input type="hidden" name="_equipment_id" value="{{ old('_equipment_id') }}">
                    <input type="hidden" name="equipment_id" value="{{ old('equipment_id', old('_equipment_id')) }}">
                    <div class="app-modal-grid">
                        <div class="app-modal-field"><label>Reference number</label><p>Generated when saved: first 3 equipment-name letters-MM-DD-YYYY-sequence.</p></div>
                        <div class="app-modal-field">
                            <label for="complete-inspection-date">Inspection date</label>
                            <input id="complete-inspection-date" type="datetime-local" name="inspection_date" value="{{ old('inspection_date', now()->format('Y-m-d\\TH:i')) }}" required>
                            @error('inspection_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="complete-inspection-inspector">Inspector</label>
                            <input id="complete-inspection-inspector" name="inspector_name" value="{{ old('inspector_name', Auth::user()->name) }}" required>
                            @error('inspector_name')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="complete-inspection-type">Inspection type</label>
                            <input id="complete-inspection-type" name="inspection_type" value="{{ old('inspection_type') }}" required>
                            @error('inspection_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="complete-inspection-outcome">Inspection outcome</label>
                            <select id="complete-inspection-outcome" name="outcome" required>
                                @foreach(['Passed','Passed with Findings','Failed','Requires Further Assessment'] as $outcome)
                                    <option value="{{ $outcome }}" @selected(old('outcome', 'Passed') === $outcome)>{{ $outcome }}</option>
                                @endforeach
                            </select>
                            @error('outcome')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="complete-inspection-equipment-status">New equipment status</label>
                            <select id="complete-inspection-equipment-status" name="equipment_status" required>
                                @foreach(['Operational','Operational with Restrictions','Under Maintenance','Under Repair','Out of Service','Retired'] as $status)
                                    <option value="{{ $status }}" @selected(old('equipment_status', 'Operational') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('equipment_status')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="complete-inspection-condition">Observed condition</label>
                            <textarea id="complete-inspection-condition" name="condition_observed">{{ old('condition_observed') }}</textarea>
                            @error('condition_observed')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="complete-inspection-defects">Defects identified</label>
                            <textarea id="complete-inspection-defects" name="defects_identified">{{ old('defects_identified') }}</textarea>
                            @error('defects_identified')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="complete-inspection-action">Recommended action</label>
                            <textarea id="complete-inspection-action" name="recommended_action">{{ old('recommended_action') }}</textarea>
                            @error('recommended_action')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @error('equipment_id')<p class="app-modal-error">{{ $message }}</p>@enderror
                    <footer class="app-modal-footer">
                        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                        <button type="submit" class="app-modal-button primary">Save inspection and update status</button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
    <dialog id="inspection-create-modal" class="app-modal" data-open-on-load="{{ $errors->any() && old('_form') === 'create-inspection' ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div><h2>Create inspection</h2><p>Record an equipment inspection and its findings.</p></div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <form method="POST" action="{{ route('inspections.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="create-inspection">
                    <div class="app-modal-grid">
                        <div class="app-modal-field">
                            <label for="inspection-equipment">Equipment</label>
                            <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                <input type="search" aria-label="Search and select equipment" placeholder="Select or search equipment..." data-equipment-select-search hidden>
                                <select id="inspection-equipment" name="equipment_id" required data-searchable-equipment>
                                    <option value="">Select equipment</option>
                                    @foreach($equipmentOptions as $item)
                                        <option value="{{ $item->id }}" @selected(old('equipment_id') == $item->id)>{{ $item->equipment_name }}</option>
                                    @endforeach
                                </select>
                                <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                            </div>
                            @error('equipment_id')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field"><label>Reference number</label><p>Generated when saved: first 3 equipment-name letters-MM-DD-YYYY-sequence.</p></div>
                        <div class="app-modal-field">
                            <label for="inspection-date">Inspection date</label>
                            <input id="inspection-date" type="datetime-local" name="inspection_date" value="{{ old('inspection_date', now()->format('Y-m-d\\TH:i')) }}" required>
                            @error('inspection_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="inspection-inspector">Inspector</label>
                            <input id="inspection-inspector" name="inspector_name" value="{{ old('inspector_name', Auth::user()->name) }}" required>
                            @error('inspector_name')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="inspection-type">Inspection type</label>
                            <input id="inspection-type" name="inspection_type" value="{{ old('inspection_type') }}" required>
                            @error('inspection_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="inspection-outcome">Outcome</label>
                            <select id="inspection-outcome" name="outcome" required>
                                @foreach(['Passed','Passed with Findings','Failed','Requires Further Assessment'] as $outcome)
                                    <option value="{{ $outcome }}" @selected(old('outcome', 'Passed') === $outcome)>{{ $outcome }}</option>
                                @endforeach
                            </select>
                            @error('outcome')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="inspection-condition">Observed condition</label>
                            <textarea id="inspection-condition" name="condition_observed">{{ old('condition_observed') }}</textarea>
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="inspection-defects">Defects identified</label>
                            <textarea id="inspection-defects" name="defects_identified">{{ old('defects_identified') }}</textarea>
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="inspection-action">Recommended action</label>
                            <textarea id="inspection-action" name="recommended_action">{{ old('recommended_action') }}</textarea>
                        </div>
                    </div>
                    <div class="app-modal-footer">
                        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                        <button type="submit" class="app-modal-button primary">Save inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
</x-app-layout>
