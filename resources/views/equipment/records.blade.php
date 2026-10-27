<x-app-layout>
    <x-slot name="header"><h2>Equipment records</h2></x-slot>

    <div class="ops-page">
        <section class="ops-card">
            <div class="ops-card-heading">
                <div>
                    <strong>Equipment information</strong>
                </div>
                <a class="ops-button secondary small" href="{{ route('equipment.index') }}">Back to equipment</a>
            </div>
            <div class="app-modal-body">
                <dl class="equipment-detail-grid">
                    <div><dt>Name</dt><dd>{{ $equipment->equipment_name }}</dd></div>
                    <div><dt>Asset code</dt><dd>{{ $equipment->asset_code }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $equipment->category }}</dd></div>
                    <div><dt>Location</dt><dd>{{ $equipment->location ?: 'Not specified' }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $equipment->status }}</dd></div>
                    <div><dt>Date acquired</dt><dd>{{ $equipment->commission_date?->format('M d, Y') ?? 'Not specified' }}</dd></div>
                </dl>
            </div>
            <div class="ops-table-scroll">
                <table class="ops-table">
                    <thead>
                        <tr><th>Type</th><th>Reference</th><th>Date</th><th>Details</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>{{ $record['type'] }}</td>
                                <td>{{ $record['reference'] }}</td>
                                <td>{{ $record['date']?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $record['details'] }}</td>
                                <td>
                                    <button type="button" class="equipment-action" data-modal-open="equipment-record-{{ strtolower($record['type']) }}-{{ $record['model']->id }}">View record</button>
                                    @if($record['model']->status === 'Pending')
                                        <button type="button" class="equipment-action" data-modal-open="equipment-status-{{ strtolower($record['type']) }}-{{ $record['model']->id }}">Update</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty">No inspection, maintenance, or repair records are available for this equipment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @foreach($records as $record)
        @include('operations.record-view-modal', [
            'modalId' => 'equipment-record-'.strtolower($record['type']).'-'.$record['model']->id,
            'recordType' => $record['type'],
            'record' => $record['model'],
        ])
        @if($record['model']->status === 'Pending')
            @include('operations.pending-status-modal', [
                'modalId' => 'equipment-status-'.strtolower($record['type']).'-'.$record['model']->id,
                'recordType' => $record['type'],
                'record' => $record['model'],
                'statusUrl' => $record['status_url'],
            ])
        @endif
    @endforeach
</x-app-layout>
