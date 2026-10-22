<x-app-layout>
    <x-slot name="header"><h2>Equipment</h2></x-slot>

    @php($canManageEquipment = auth()->user()->role !== 'maintenance')

    <div class="ops-page">
        @if(session('success'))<div class="ops-flash" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="ops-flash" role="alert">Please review the highlighted fields and try again.</div>@endif
        @if($canManageEquipment)
            <div class="ops-grid-2" style="margin-bottom:12px">
                <section class="ops-card">
                    <div class="ops-card-heading">Import equipment from CSV</div>
                    <div class="ops-card-body">
                        <p style="margin:0 0 10px;color:#718197;font-size:11px">Upload up to 500 equipment records using the CSV template. Every row is checked before any records are saved.</p>
                        <form method="POST" action="{{ route('equipment.import') }}" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            @csrf
                            <input type="file" name="file" accept=".csv,text/csv" required aria-label="Equipment CSV file">
                            <button type="submit" class="ops-button small">Import CSV</button>
                            <a class="ops-button secondary small" href="{{ route('equipment.import-template') }}">Download template</a>
                        </form>
                        @foreach($errors->get('file') as $message)<p class="ops-error" role="alert">{{ $message }}</p>@endforeach
                        @foreach($errors->getMessages() as $key => $messages)
                            @if(str_starts_with($key, 'rows.'))
                                @foreach($messages as $message)<p class="ops-error" role="alert">{{ $message }}</p>@endforeach
                            @endif
                        @endforeach
                    </div>
                </section>
                @if(auth()->user()->role === 'super_admin')
                    <section class="ops-card">
                        <div class="ops-card-heading">Equipment categories</div>
                        <div class="ops-card-body">
                            <p style="margin:0 0 10px;color:#718197;font-size:11px">Add a category to make it available when registering or editing equipment.</p>
                            <form method="POST" action="{{ route('equipment.categories.store') }}" style="display:flex;align-items:center;gap:8px">
                                @csrf
                                <input type="text" name="name" maxlength="100" class="ops-input" placeholder="New category name" value="{{ old('name') }}" required>
                                <button type="submit" class="ops-button small">Add category</button>
                            </form>
                            @error('name')<p class="ops-error" role="alert">{{ $message }}</p>@enderror
                            <div style="margin-top:10px;color:#718197;font-size:10px">Available categories: {{ $categories->implode(', ') }}</div>
                        </div>
                    </section>
                @endif
            </div>
        @endif
        <section class="ops-card">
            <nav aria-label="Equipment sections" style="display:flex;gap:8px;padding:14px 16px 0">
                <a class="ops-button {{ $isArchive ? 'secondary' : '' }}" href="{{ route('equipment.index', request()->only(['search', 'category'])) }}" @if(!$isArchive) aria-current="page" @endif>All equipment</a>
                <a class="ops-button {{ $isArchive ? '' : 'secondary' }}" href="{{ route('equipment.index', array_merge(request()->only(['search', 'category']), ['section' => 'archive'])) }}" @if($isArchive) aria-current="page" @endif>Archive ({{ $archiveCount }})</a>
            </nav>
            <form method="GET" action="{{ route('equipment.index') }}" class="ops-toolbar">
                <input type="hidden" name="section" value="{{ $isArchive ? 'archive' : 'all' }}">
                <div class="ops-filters">
                    <label class="ops-search"><span aria-hidden="true">⌕</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Search records..." aria-label="Search equipment"></label>
                    <select name="status" class="ops-filter" aria-label="Filter by status">
                        <option value="">All status</option>
                        @foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
                    </select>
                    <select name="category" class="ops-filter" aria-label="Filter by category">
                        <option value="">All categories</option>
                        @foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach
                    </select>
                    <button type="submit" class="ops-button secondary small">Filter</button>
                </div>
                @if($canManageEquipment)
                    <button type="button" class="ops-button small" data-modal-open="equipment-create-modal">＋ Add new</button>
                @endif
            </form>
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead><tr><th>Property no.</th><th>Equipment</th><th>Office Code</th><th>Category</th><th>Risk level</th><th>Location</th><th>Status</th><th>Assigned to</th><th>Last maintenance</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($equipment as $item)
                        <tr>
                            <td>{{ $item->asset_code }}</td><td>{{ $item->equipment_name }}</td><td>{{ $item->office_code ?: '—' }}</td><td>{{ $item->category }}</td>
                            <td>
                                @if($item->latestRiskPrediction)
                                    <span class="ops-pill {{ $item->latestRiskPrediction->prediction_label === 'High Risk' ? 'danger' : ($item->latestRiskPrediction->prediction_label === 'Medium Risk' ? 'warning' : '') }}">{{ $item->latestRiskPrediction->prediction_label }}</span>
                                @else
                                    <span class="ops-pill neutral">Not assessed</span>
                                @endif
                            </td>
                            <td>{{ $item->location ?: '—' }}</td>
                            <td><span class="ops-pill {{ str_contains(strtolower($item->status), 'out') || str_contains(strtolower($item->status), 'maintenance') ? 'warning' : '' }}">{{ $item->status }}</span></td>
                            <td>{{ $item->assignedUser?->name ?? '—' }}</td>
                            <td>{{ $item->last_maintenance_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="equipment-actions">
                                <button type="button" class="equipment-action" data-modal-open="equipment-view-{{ $item->id }}">View</button>
                                <button type="button" class="equipment-action" data-modal-open="equipment-edit-{{ $item->id }}">Edit</button>
                                @if(auth()->user()->role === 'super_admin')
                                    <button type="button" class="equipment-action danger" data-modal-open="equipment-delete-{{ $item->id }}">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="ops-empty">No equipment records match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ops-table-footer"><span>Showing {{ $equipment->firstItem() ?? 0 }}–{{ $equipment->lastItem() ?? 0 }} of {{ $equipment->total() }} records</span><div class="ops-pagination">{{ $equipment->links() }}</div></div>
        </section>
    </div>
    @foreach($equipment as $item)
        <dialog id="equipment-view-{{ $item->id }}" class="app-modal equipment-view-modal" aria-labelledby="equipment-view-title-{{ $item->id }}">
            <div class="app-modal-panel">
                <header class="app-modal-header">
                    <div><h2 id="equipment-view-title-{{ $item->id }}">{{ $item->equipment_name }}</h2><p>Equipment record · {{ $item->asset_code }}</p></div>
                    <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
                </header>
                <div class="app-modal-body">
                    <dl class="equipment-detail-grid">
                        <div><dt>Category</dt><dd>{{ $item->category }}</dd></div>
                        <div><dt>Office Code</dt><dd>{{ $item->office_code ?: 'Not assigned' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $item->status }}</dd></div>
                        <div><dt>Location</dt><dd>{{ $item->location ?: 'Not specified' }}</dd></div>
                        <div><dt>Assigned personnel</dt><dd>{{ $item->assignedUser?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt>Asset tag</dt><dd>{{ $item->asset_tag ?: 'Not specified' }}</dd></div>
                        <div><dt>Serial number</dt><dd>{{ $item->serial_number ?: 'Not specified' }}</dd></div>
                        <div><dt>Manufacturer</dt><dd>{{ $item->manufacturer ?: 'Not specified' }}</dd></div>
                        <div><dt>Model</dt><dd>{{ $item->model ?: 'Not specified' }}</dd></div>
                        <div><dt>Commission date</dt><dd>{{ $item->commission_date?->format('M d, Y') ?? 'Not specified' }}</dd></div>
                        <div><dt>Service age</dt><dd>{{ $item->service_age_years ?? 0 }} years</dd></div>
                        <div><dt>Last inspection</dt><dd>{{ $item->last_inspection_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Next inspection</dt><dd>{{ $item->next_inspection_date?->format('M d, Y') ?? 'Not scheduled' }}</dd></div>
                        <div><dt>Last maintenance</dt><dd>{{ $item->last_maintenance_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Maintenance interval</dt><dd>{{ $item->maintenance_interval_days ? $item->maintenance_interval_days.' days' : 'Not specified' }}</dd></div>
                        <div><dt>Engine hours</dt><dd>{{ $item->engine_operating_hours ?? 'Not recorded' }}</dd></div>
                        <div><dt>Runtime cycles</dt><dd>{{ $item->runtime_cycles ?? 'Not recorded' }}</dd></div>
                        <div><dt>Mechanical failures</dt><dd>{{ $item->mechanical_failure_count ?? 0 }}</dd></div>
                        <div><dt>Last inspection outcome</dt><dd>{{ $item->last_inspection_outcome ?: 'Not recorded' }}</dd></div>
                        <div class="equipment-detail-full"><dt>Description</dt><dd>{{ $item->description ?: 'No description.' }}</dd></div>
                        <div class="equipment-detail-full"><dt>Remarks</dt><dd>{{ $item->remarks ?: 'No remarks.' }}</dd></div>
                    </dl>
                </div>
                <footer class="app-modal-footer"><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
            </div>
        </dialog>

        <dialog id="equipment-edit-{{ $item->id }}" class="app-modal equipment-edit-modal" aria-labelledby="equipment-edit-title-{{ $item->id }}"
            data-open-on-load="{{ $errors->any() && old('_form') === 'edit-equipment' && (int) old('_equipment_id') === $item->id ? 'true' : 'false' }}">
            <div class="app-modal-panel">
                <header class="app-modal-header">
                    <div><h2 id="equipment-edit-title-{{ $item->id }}">Edit equipment</h2><p>{{ $item->asset_code }} · Update asset details.</p></div>
                    <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
                </header>
                <div class="app-modal-body">
                    <form method="POST" action="{{ route('equipment.update', $item) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="edit-equipment">
                        <input type="hidden" name="_equipment_id" value="{{ $item->id }}">
                        <div class="app-modal-grid">
                            @if($canManageEquipment)
                            <div class="app-modal-field">
                                <label for="edit-name-{{ $item->id }}">Equipment name</label>
                                <input id="edit-name-{{ $item->id }}" name="equipment_name" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('equipment_name', $item->equipment_name) : $item->equipment_name }}" required>
                                @error('equipment_name') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-category-{{ $item->id }}">Category</label>
                                <select id="edit-category-{{ $item->id }}" name="category" required>
                                    @foreach($categories as $category)
                                        <option value="{{ $category }}" @selected((old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('category', $item->category) : $item->category) === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                                @error('category') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-office-code-{{ $item->id }}">Office Code</label>
                                @if(auth()->user()->role === 'super_admin')
                                    <input id="edit-office-code-{{ $item->id }}" name="office_code" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('office_code', $item->office_code) : $item->office_code }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" required data-equipment-office-code>
                                @else
                                    <input id="edit-office-code-{{ $item->id }}" value="{{ auth()->user()->office_code }}" readonly data-equipment-office-code>
                                @endif
                                @error('office_code') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-status-{{ $item->id }}">Status</label>
                                <select id="edit-status-{{ $item->id }}" name="status" required>
                                    @foreach(['Operational','Operational with Restrictions','Under Inspection','Under Maintenance','Under Repair','Out of Service','Retired'] as $status)
                                        <option value="{{ $status }}" @selected((old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('status', $item->status) : $item->status) === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @error('status') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-location-{{ $item->id }}">Location</label>
                                <input id="edit-location-{{ $item->id }}" name="location" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('location', $item->location) : $item->location }}">
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-asset-tag-{{ $item->id }}">Asset tag</label>
                                <input id="edit-asset-tag-{{ $item->id }}" name="asset_tag" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('asset_tag', $item->asset_tag) : $item->asset_tag }}">
                                @error('asset_tag') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-serial-{{ $item->id }}">Serial number</label>
                                <input id="edit-serial-{{ $item->id }}" name="serial_number" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('serial_number', $item->serial_number) : $item->serial_number }}">
                                @error('serial_number') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-manufacturer-{{ $item->id }}">Manufacturer</label>
                                <input id="edit-manufacturer-{{ $item->id }}" name="manufacturer" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('manufacturer', $item->manufacturer) : $item->manufacturer }}">
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-model-{{ $item->id }}">Model</label>
                                <input id="edit-model-{{ $item->id }}" name="model" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('model', $item->model) : $item->model }}">
                            </div>
                            <div class="app-modal-field">
                                <label for="edit-commission-date-{{ $item->id }}">Commission date</label>
                                <input id="edit-commission-date-{{ $item->id }}" type="date" name="commission_date" value="{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('commission_date', $item->commission_date?->format('Y-m-d')) : $item->commission_date?->format('Y-m-d') }}">
                            </div>
                            @endif
                            <div class="app-modal-field">
                                <label for="edit-assigned-{{ $item->id }}">Assigned personnel</label>
                                @if($canManageEquipment)
                                <select id="edit-assigned-{{ $item->id }}" name="assigned_personnel" data-equipment-personnel-select>
                                    <option value="">Unassigned</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" data-office-code="{{ $user->office_code }}" @selected((old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('assigned_personnel', $item->assigned_personnel) : $item->assigned_personnel) == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @else
                                    <input id="edit-assigned-{{ $item->id }}" value="{{ $item->assignedUser?->name ?? 'Unassigned' }}" disabled>
                                @endif
                                <small>{{ $canManageEquipment ? 'Select a person to automatically set the equipment office code.' : 'Personnel assignment can only be changed by an administrator.' }}</small>
                                @if($canManageEquipment)
                                    @error('assigned_personnel') @if(old('_equipment_id') == $item->id)<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                                @endif
                            </div>
                            <div class="app-modal-field"><label for="edit-service-age-{{ $item->id }}">Service age (years)</label><input id="edit-service-age-{{ $item->id }}" type="number" min="0" name="service_age_years" value="{{ $item->service_age_years }}"></div>
                            <div class="app-modal-field"><label for="edit-engine-hours-{{ $item->id }}">Engine operating hours</label><input id="edit-engine-hours-{{ $item->id }}" type="number" min="0" name="engine_operating_hours" value="{{ $item->engine_operating_hours }}"></div>
                            <div class="app-modal-field"><label for="edit-runtime-cycles-{{ $item->id }}">Runtime cycles</label><input id="edit-runtime-cycles-{{ $item->id }}" type="number" min="0" name="runtime_cycles" value="{{ $item->runtime_cycles }}"></div>
                            <div class="app-modal-field"><label for="edit-failures-{{ $item->id }}">Mechanical failure count</label><input id="edit-failures-{{ $item->id }}" type="number" min="0" name="mechanical_failure_count" value="{{ $item->mechanical_failure_count }}"></div>
                            <div class="app-modal-field"><label for="edit-last-inspection-{{ $item->id }}">Last inspection date</label><input id="edit-last-inspection-{{ $item->id }}" type="date" name="last_inspection_date" value="{{ $item->last_inspection_date?->format('Y-m-d') }}"></div>
                            <div class="app-modal-field"><label for="edit-next-inspection-{{ $item->id }}">Next inspection date</label><input id="edit-next-inspection-{{ $item->id }}" type="date" name="next_inspection_date" value="{{ $item->next_inspection_date?->format('Y-m-d') }}"></div>
                            <div class="app-modal-field"><label for="edit-last-maintenance-{{ $item->id }}">Last maintenance date</label><input id="edit-last-maintenance-{{ $item->id }}" type="date" name="last_maintenance_date" value="{{ $item->last_maintenance_date?->format('Y-m-d') }}"></div>
                            <div class="app-modal-field"><label for="edit-maintenance-interval-{{ $item->id }}">Maintenance interval (days)</label><input id="edit-maintenance-interval-{{ $item->id }}" type="number" min="0" name="maintenance_interval_days" value="{{ $item->maintenance_interval_days }}"></div>
                            <div class="app-modal-field"><label for="edit-inspection-outcome-{{ $item->id }}">Last inspection outcome</label><input id="edit-inspection-outcome-{{ $item->id }}" name="last_inspection_outcome" value="{{ $item->last_inspection_outcome }}"></div>
                            <div class="app-modal-field full-width"><label for="edit-description-{{ $item->id }}">Description</label><textarea id="edit-description-{{ $item->id }}" name="description">{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('description', $item->description) : $item->description }}</textarea></div>
                            <div class="app-modal-field full-width"><label for="edit-remarks-{{ $item->id }}">Remarks</label><textarea id="edit-remarks-{{ $item->id }}" name="remarks">{{ old('_equipment_id') == $item->id && old('_form') === 'edit-equipment' ? old('remarks', $item->remarks) : $item->remarks }}</textarea></div>
                        </div>
                        <footer class="app-modal-footer">
                            <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                            <button type="submit" class="app-modal-button primary">Save changes</button>
                        </footer>
                    </form>
                </div>
            </div>
        </dialog>

        @if($canManageEquipment)
        @if(auth()->user()->role === 'super_admin')
            <dialog id="equipment-delete-{{ $item->id }}" class="app-modal equipment-delete-modal" aria-labelledby="equipment-delete-title-{{ $item->id }}"
                data-open-on-load="{{ $errors->any() && old('_form') === 'delete-equipment' && (int) old('_equipment_id') === $item->id ? 'true' : 'false' }}">
                <div class="app-modal-panel">
                    <header class="app-modal-header">
                        <div><h2 id="equipment-delete-title-{{ $item->id }}">Delete equipment?</h2><p>This action permanently removes {{ $item->equipment_name }} ({{ $item->asset_code }}).</p></div>
                        <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
                    </header>
                    <div class="app-modal-body">
                        <div class="equipment-delete-warning">Deleting this equipment also removes its linked inspection, maintenance, and status-history records. This cannot be undone.</div>
                        <form method="POST" action="{{ route('equipment.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="_form" value="delete-equipment">
                            <input type="hidden" name="_equipment_id" value="{{ $item->id }}">
                            <div class="app-modal-field">
                                <label for="delete-password-{{ $item->id }}">Confirm your Super Admin password</label>
                                <input id="delete-password-{{ $item->id }}" type="password" name="password" autocomplete="current-password" required>
                                @error('password') @if(old('_equipment_id') == $item->id && old('_form') === 'delete-equipment')<span class="app-modal-error">{{ $message }}</span>@endif @enderror
                            </div>
                            <footer class="app-modal-footer">
                                <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                                <button type="submit" class="app-modal-button equipment-delete-button">Delete equipment</button>
                            </footer>
                        </form>
                    </div>
                </div>
            </dialog>
        @endif
        @endif
    @endforeach

    @if($canManageEquipment)
    <dialog id="equipment-create-modal" class="app-modal" data-open-on-load="{{ $errors->any() && old('_form') === 'create-equipment' ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div><h2>Register equipment</h2><p>Add an asset to the station equipment registry.</p></div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <form method="POST" action="{{ route('equipment.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="create-equipment">
                    <div class="app-modal-grid">
                        <div class="app-modal-field">
                            <label for="equipment-name">Equipment name</label>
                            <input id="equipment-name" name="equipment_name" value="{{ old('equipment_name') }}" required>
                            @error('equipment_name')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-category">Category</label>
                            <select id="equipment-category" name="category" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-office-code">Office Code</label>
                            @if(auth()->user()->role === 'super_admin')
                                <input id="equipment-office-code" name="office_code" value="{{ old('office_code') }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" required data-equipment-office-code>
                            @else
                                <input id="equipment-office-code" value="{{ auth()->user()->office_code }}" readonly data-equipment-office-code>
                            @endif
                            @error('office_code')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-asset-tag">Asset tag</label>
                            <input id="equipment-asset-tag" name="asset_tag" value="{{ old('asset_tag') }}">
                            @error('asset_tag')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-serial">Serial number</label>
                            <input id="equipment-serial" name="serial_number" value="{{ old('serial_number') }}">
                            @error('serial_number')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-manufacturer">Manufacturer</label>
                            <input id="equipment-manufacturer" name="manufacturer" value="{{ old('manufacturer') }}">
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-model">Model</label>
                            <input id="equipment-model" name="model" value="{{ old('model') }}">
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-status">Status</label>
                            <select id="equipment-status" name="status" required>
                                @foreach(['Operational','Operational with Restrictions','Under Inspection','Under Maintenance','Under Repair','Out of Service','Retired'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Operational') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-location">Location</label>
                            <input id="equipment-location" name="location" value="{{ old('location') }}">
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-commission-date">Commission date</label>
                            <input id="equipment-commission-date" type="date" name="commission_date" value="{{ old('commission_date') }}">
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-assigned">Assigned personnel</label>
                            <select id="equipment-assigned" name="assigned_personnel" data-equipment-personnel-select>
                                <option value="">Unassigned</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" data-office-code="{{ $user->office_code }}" @selected(old('assigned_personnel') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <small>Select a person to automatically set the equipment office code.</small>
                            @error('assigned_personnel')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="equipment-description">Description</label>
                            <textarea id="equipment-description" name="description">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <div class="app-modal-footer">
                        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                        <button type="submit" class="app-modal-button primary">Save equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
    @endif
</x-app-layout>
