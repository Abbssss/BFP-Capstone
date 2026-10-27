<dialog id="{{ $modalId }}" class="app-modal" aria-labelledby="{{ $modalId }}-title">
    <div class="app-modal-panel">
        <header class="app-modal-header">
            <div>
                <h2 id="{{ $modalId }}-title">Update {{ strtolower($recordType) }} status</h2>
                <p>{{ $recordType === 'Inspection' ? $record->reference_number : $record->request_number }}</p>
            </div>
            <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
        </header>
        <div class="app-modal-body">
            <form method="POST" action="{{ $statusUrl }}">
                @csrf
                @method('PATCH')
                <div class="app-modal-grid">
                    <div class="app-modal-field full-width">
                        <label for="{{ $modalId }}-status">Status</label>
                        <select id="{{ $modalId }}-status" name="status" required data-pending-status-select>
                            @foreach(($recordType === 'Inspection'
                                ? ['In Progress', 'Completed', 'Cancelled']
                                : ['Approved', 'Scheduled', 'In Progress', 'Completed', 'Cancelled']) as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="app-modal-field full-width">
                        <label for="{{ $modalId }}-notes">Update comment</label>
                        <textarea id="{{ $modalId }}-notes" name="notes" rows="3">{{ $record->notes ?? '' }}</textarea>
                    </div>
                    @if($recordType !== 'Inspection')
                        <div class="app-modal-field full-width" data-completion-fields hidden>
                            <label for="{{ $modalId }}-completion-date">Completion date</label>
                            <input id="{{ $modalId }}-completion-date" type="date" name="completion_date">
                        </div>
                        <div class="app-modal-field full-width" data-completion-fields hidden>
                            <label for="{{ $modalId }}-technician">Technician</label>
                            <input id="{{ $modalId }}-technician" name="technician_name" value="{{ $record->technician_name }}">
                        </div>
                        <div class="app-modal-field full-width" data-completion-fields hidden>
                            <label for="{{ $modalId }}-action">Action taken</label>
                            <textarea id="{{ $modalId }}-action" name="action_taken">{{ $record->action_taken }}</textarea>
                        </div>
                    @endif
                </div>
                <footer class="app-modal-footer">
                    <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
                    <button type="submit" class="app-modal-button primary">Update status</button>
                </footer>
            </form>
        </div>
    </div>
</dialog>
