<dialog id="{{ $modalId }}" class="app-modal" aria-labelledby="{{ $modalId }}-title">
    <div class="app-modal-panel">
        <header class="app-modal-header">
            <div>
                <h2 id="{{ $modalId }}-title">{{ $recordType }} record</h2>
                <p>{{ $recordType === 'Inspection' ? $record->reference_number : $record->request_number }}</p>
            </div>
            <button type="button" class="app-modal-close" aria-label="Close dialog" data-modal-close>&times;</button>
        </header>
        <div class="app-modal-body">
            <dl class="equipment-detail-grid">
                <div><dt>Equipment</dt><dd>{{ $record->equipment?->equipment_name ?? 'Unavailable' }}</dd></div>
                @if($recordType === 'Inspection')
                    <div><dt>Inspection type</dt><dd>{{ $record->inspection_type }}</dd></div>
                    <div><dt>Inspector</dt><dd>{{ $record->inspector_name }}</dd></div>
                    @if($record->assignedTo)
                        <div><dt>Assigned to</dt><dd>{{ $record->assignedTo->name }}</dd></div>
                    @endif
                    @if($record->priority)
                        <div><dt>Priority</dt><dd>{{ $record->priority }}</dd></div>
                    @endif
                    <div><dt>Inspection date</dt><dd>{{ $record->inspection_date?->format('M d, Y h:i A') ?? 'Not recorded' }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $record->status ?: 'Finalized' }}</dd></div>
                    <div><dt>Outcome</dt><dd>{{ $record->outcome }}</dd></div>
                    <div><dt>Condition observed</dt><dd>{{ $record->condition_observed ?: '—' }}</dd></div>
                    <div><dt>Functional test result</dt><dd>{{ $record->functional_test_result ?: '—' }}</dd></div>
                    <div><dt>Defects identified</dt><dd>{{ $record->defects_identified ?: '—' }}</dd></div>
                    <div><dt>Safety concerns</dt><dd>{{ $record->safety_concerns ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Recommended action</dt><dd>{{ $record->recommended_action ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Notes</dt><dd>{{ $record->notes ?: '—' }}</dd></div>
                @else
                    <div><dt>Work type</dt><dd>{{ $record->maintenance_type }}</dd></div>
                    <div><dt>Priority</dt><dd>{{ $record->display_priority ?? $record->priority }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $record->status }}</dd></div>
                    <div><dt>Assigned to</dt><dd>{{ $record->assignedTo?->name ?? $record->equipment?->assignedUser?->name ?? $record->technician_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Start date</dt><dd>{{ $record->start_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
                    <div><dt>Completion date</dt><dd>{{ $record->completion_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Reported issue</dt><dd>{{ $record->reported_issue ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Diagnosis</dt><dd>{{ $record->diagnosis ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Action taken</dt><dd>{{ $record->action_taken ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Parts replaced</dt><dd>{{ $record->parts_replaced ?: '—' }}</dd></div>
                    <div class="equipment-detail-full"><dt>Update comment</dt><dd>{{ $record->notes ?: '—' }}</dd></div>
                @endif
            </dl>
        </div>
        <footer class="app-modal-footer">
            <button type="button" class="app-modal-button" data-modal-close>Close</button>
        </footer>
    </div>
</dialog>
