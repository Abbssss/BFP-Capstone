<x-app-layout>
    <x-slot name="header"><h2>{{ $title }}</h2></x-slot>

    <div class="ops-page {{ $title === 'Repairs' ? 'repairs-page' : '' }}">
        <div class="ops-intro">
            <div>
                <span class="ops-eyebrow">Live data</span>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
            </div>
            @if($createUrl)
                <a class="ops-button" href="{{ $createUrl }}">＋ Create new</a>
            @endif
        </div>
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
                </div>
                @if($createUrl)
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
                                        $pillClass = in_array(strtolower($value), ['failed', 'high', 'critical', 'overdue', 'out of service'], true)
                                            ? 'danger'
                                            : (in_array(strtolower($value), ['pending', 'due', 'medium', 'in progress', 'scheduled'], true) ? 'warning' : 'neutral');
                                    @endphp
                                    <td>
                                        @if($key === 'action' && !empty($record['action_url']))
                                            <a href="{{ $record['action_url'] }}">View</a>
                                        @elseif($isStatus)
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
</x-app-layout>
