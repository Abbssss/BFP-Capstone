@if($equipmentUnderMaintenance->isNotEmpty())
    <dialog id="equipment-work-record-modal" class="app-modal equipment-view-modal"
        data-open-on-load="{{ $errors->any() && in_array(old('_form'), ['complete-equipment-maintenance', 'complete-equipment-repair'], true) && old('_equipment_id') ? 'true' : 'false' }}">
        <div class="app-modal-panel">
            <header class="app-modal-header">
                <div>
                    <h2 id="equipment-work-record-title">{{ $workRecordKind }} record</h2>
                    <p id="equipment-work-record-equipment">Equipment status update</p>
                </div>
                <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
            </header>
            <div class="app-modal-body">
                <form method="POST" action="{{ route($workRecordKind === 'Repair' ? 'repairs.equipment-work.store' : 'maintenance.equipment-work.store', $equipmentUnderMaintenance->first()) }}" data-equipment-work-record-form>
                    @csrf
                    <input type="hidden" name="_form" value="{{ $workRecordKind === 'Repair' ? 'complete-equipment-repair' : 'complete-equipment-maintenance' }}">
                    <input type="hidden" name="_equipment_id" value="{{ old('_equipment_id') }}">
                    <div class="app-modal-grid">
                        <div class="app-modal-field">
                            <label for="equipment-work-request">Request number</label>
                            <input id="equipment-work-request" name="request_number" value="{{ old('request_number') }}" required>
                            @error('request_number')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-type">Work type</label>
                            <input id="equipment-work-type" name="maintenance_type" value="{{ old('maintenance_type', $workRecordKind === 'Repair' ? 'Repair' : 'Preventive maintenance') }}" required>
                            @error('maintenance_type')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-priority">Priority</label>
                            <select id="equipment-work-priority" name="priority" required>
                                @foreach(['Low','Medium','High','Critical'] as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                                @endforeach
                            </select>
                            @error('priority')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-task-status">Work status</label>
                            <select id="equipment-work-task-status" name="status" required>
                                @foreach(['Pending','Approved','Scheduled','In Progress','Completed','Cancelled'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'In Progress') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-resulting-status">New equipment status</label>
                            <select id="equipment-work-resulting-status" name="equipment_status" required>
                                @foreach(['Operational','Operational with Restrictions','Under Inspection','Under Maintenance','Under Repair','Out of Service','Retired'] as $status)
                                    <option value="{{ $status }}" @selected(old('equipment_status', $workRecordKind === 'Repair' ? 'Under Repair' : 'Under Maintenance') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('equipment_status')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-technician">Technician</label>
                            <input id="equipment-work-technician" name="technician_name" value="{{ old('technician_name', auth()->user()->name) }}">
                            @error('technician_name')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-start-date">Work date</label>
                            <input id="equipment-work-start-date" type="date" name="start_date" value="{{ old('start_date', today()->toDateString()) }}">
                            @error('start_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-completion-date">Completion date</label>
                            <input id="equipment-work-completion-date" type="date" name="completion_date" value="{{ old('completion_date') }}">
                            @error('completion_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-next-date">Next recommended maintenance date</label>
                            <input id="equipment-work-next-date" type="date" name="next_recommended_maintenance_date" value="{{ old('next_recommended_maintenance_date') }}">
                            @error('next_recommended_maintenance_date')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field">
                            <label for="equipment-work-cost">Cost</label>
                            <input id="equipment-work-cost" type="number" min="0" step="0.01" name="maintenance_cost" value="{{ old('maintenance_cost') }}">
                            @error('maintenance_cost')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="equipment-work-issue">Reported issue / work needed</label>
                            <textarea id="equipment-work-issue" name="reported_issue" @if($workRecordKind === 'Repair') required @endif>{{ old('reported_issue') }}</textarea>
                            @error('reported_issue')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="equipment-work-diagnosis">Diagnosis</label>
                            <textarea id="equipment-work-diagnosis" name="diagnosis">{{ old('diagnosis') }}</textarea>
                            @error('diagnosis')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="app-modal-field full-width">
                            <label for="equipment-work-action">Action taken</label>
                            <textarea id="equipment-work-action" name="action_taken">{{ old('action_taken') }}</textarea>
                            @error('action_taken')<span class="app-modal-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @error('_equipment_id')<p class="app-modal-error">{{ $message }}</p>@enderror
                    <footer class="app-modal-footer">
                        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                        <button type="submit" class="app-modal-button primary">Save {{ strtolower($workRecordKind) }} and update status</button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
@endif
