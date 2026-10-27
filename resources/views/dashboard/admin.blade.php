<x-app-layout>
    <x-slot name="header"><h2>Dashboard</h2></x-slot>

    <div class="ops-page admin-dashboard">
        <div class="admin-dashboard-intro">
            <div>
                <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}.</h1>
                <p>Here is your station maintenance overview for today.</p>
            </div>
        </div>

        <section class="admin-summary-grid" aria-label="Station summary">
            @foreach([
                ['Overdue Maintenance', $overdueMaintenanceCount, 'Maintenance requires action', '△', 'red', route('maintenances.index', ['due' => 'overdue'])],
                ['Maintenance Due Soon', $maintenanceDueSoonCount, 'Due within the warning period', '▣', 'blue', route('maintenances.index', ['due' => 'soon'])],
                ['High Priority', $priorityCounts['High'], 'Schedule and inspection findings', '△', 'purple', route('dashboard', ['priority_filter' => 'High']).'#equipment-priority'],
                ['Medium Priority', $priorityCounts['Medium'], 'Approaching due dates or findings', '◷', 'gold', route('dashboard', ['priority_filter' => 'Medium']).'#equipment-priority'],
                ['Low Priority', $priorityCounts['Low'], 'Normal maintenance interval', '✓', 'green', route('dashboard', ['priority_filter' => 'Low']).'#equipment-priority'],
                ['System Alerts', $systemAlertCount, 'Priority, overdue, and inspection alerts', '♧', 'navy', route('dashboard', ['priority_filter' => 'alerts']).'#equipment-priority'],
            ] as [$label, $value, $note, $icon, $color, $target])
                <a class="admin-summary-card {{ $color }}" href="{{ $target }}">
                    <span class="admin-summary-icon" aria-hidden="true">{{ $icon }}</span>
                    <div class="admin-summary-copy">
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($value) }}</strong>
                        <small>{{ $note }}</small>
                    </div>
                </a>
            @endforeach
        </section>

        <div class="admin-dashboard-middle">
            <section class="ops-card admin-panel">
                <div class="ops-card-heading">
                    <span>Equipment readiness</span>
                    <a href="{{ route('equipment.index') }}">View equipment →</a>
                </div>
                <div class="admin-readiness-list">
                    @forelse($equipmentByCategory as $category)
                        @php
                            $categoryReady = $category->total ? (int) round($category->ready / $category->total * 100) : 0;
                        @endphp
                        <div class="admin-readiness-row">
                            <div class="admin-readiness-label">
                                <span>{{ $category->category }}</span>
                                <strong>{{ $category->ready }}/{{ $category->total }} ready</strong>
                            </div>
                            <div class="admin-readiness-track" role="img" aria-label="{{ $category->category }}: {{ $categoryReady }} percent operational">
                                <span style="width:{{ $categoryReady }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="ops-empty">No equipment records are available.</p>
                    @endforelse
                </div>
                <div class="admin-readiness-summary">
                    <div class="admin-readiness-gauge" style="--readiness:{{ $readinessPercent }}%" role="img" aria-label="{{ $readinessPercent }} percent of equipment operational">
                        <span><strong>{{ $readinessPercent }}%</strong><small>READY</small></span>
                    </div>
                    <div>
                        <small>Overall readiness</small>
                        <strong>{{ number_format($operationalCount) }} of {{ number_format($equipmentCount) }} equipment operational</strong>
                        <span>{{ number_format($defectCount) }} inspection records include defects</span>
                    </div>
                </div>
            </section>

            <section class="ops-card admin-panel">
                <div class="ops-card-heading">
                    <span>Equipment status</span>
                    <a class="admin-panel-menu" href="{{ route('equipment.index') }}" aria-label="Open equipment register">•••</a>
                </div>
                <div class="admin-status-content">
                    <div class="admin-status-donut" style="--status-chart:conic-gradient({{ $statusChartBackground }})" role="img" aria-label="Equipment status breakdown, {{ $equipmentCount }} total">
                        <span><strong>{{ number_format($equipmentCount) }}</strong><small>TOTAL</small></span>
                    </div>
                    <div class="admin-status-legend">
                        @forelse($statusChart as $slice)
                            <div>
                                <span class="admin-status-swatch" style="--swatch:{{ $slice['color'] }}"></span>
                                <span>{{ $slice['status'] }}</span>
                                <strong>{{ number_format($slice['total']) }}</strong>
                            </div>
                        @empty
                            <span class="admin-status-empty">No status data available.</span>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <div class="admin-priority-layout" id="equipment-priority">
            <section class="ops-card admin-panel admin-priority-panel">
                <div class="ops-card-heading">
                    <span>Equipment priority{{ $priorityFilter !== 'all' ? ' · '.ucfirst($priorityFilter) : '' }}</span>
                    <a href="{{ route('equipment.index') }}">View equipment →</a>
                </div>
                <div class="admin-priority-counts">
                    @foreach($priorityCounts as $label => $total)
                        @php
                            $priorityColor = $label === 'High' ? 'danger' : ($label === 'Medium' ? 'warning' : '');
                        @endphp
                        <div><strong>{{ number_format($total) }}</strong><span class="ops-pill {{ $priorityColor }}">{{ $label }} priority</span></div>
                    @endforeach
                </div>
                <div class="ops-table-scroll">
                    <table class="ops-table admin-priority-table">
                        <thead><tr><th>Equipment</th><th>Priority</th><th>Reason</th><th>Next due date</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($priorityRecords as $record)
                            @php
                                $priorityEquipment = $record['equipment'];
                            @endphp
                            <tr>
                                <td><strong>{{ $priorityEquipment->equipment_name }}</strong></td>
                                <td><span class="ops-pill {{ $record['priority'] === 'High' ? 'danger' : ($record['priority'] === 'Medium' ? 'warning' : '') }}">{{ $record['priority'] }}</span></td>
                                <td>{{ $record['reason'] }}</td>
                                <td>{{ $record['due_date']?->format('M d, Y') ?? '—' }}</td>
                                <td><a href="{{ route('equipment.show', $priorityEquipment) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty">{{ $priorityFilter === 'all' ? 'No equipment records are available.' : 'No equipment records match this filter.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="ops-card admin-recommendation">
                <div class="admin-recommendation-heading"><span aria-hidden="true">△</span> Priority follow-up</div>
                @if($featuredPriority)
                    @php
                        $featuredEquipment = $featuredPriority['equipment'];
                    @endphp
                    <div class="admin-recommendation-top">
                        <div>
                            <span class="ops-pill {{ $featuredPriority['priority'] === 'High' ? 'danger' : ($featuredPriority['priority'] === 'Medium' ? 'warning' : '') }}">{{ $featuredPriority['priority'] }} priority</span>
                            <h2>{{ $featuredEquipment->equipment_name }}</h2>
                            <small>Asset code {{ $featuredEquipment->asset_code }}</small>
                        </div>
                    </div>
                    <div class="admin-recommendation-detail">
                        <small>Reason and due date</small>
                        <p>{{ $featuredPriority['reason'] }}</p>
                        <span>Scheduled date: {{ $featuredPriority['due_date']?->format('M d, Y') ?? 'Not set' }}</span>
                    </div>
                    <p class="admin-recommendation-note">Priority is derived from recorded maintenance schedules, inspection findings, and equipment status.</p>
                    <a class="ops-button small" href="{{ route('equipment.show', $featuredEquipment) }}">Review equipment</a>
                @else
                    <div class="admin-recommendation-empty">
                        <strong>No equipment to review</strong>
                        <span>Equipment priorities will appear when equipment records are available.</span>
                        <a class="ops-button small" href="{{ route('equipment.index') }}">Open equipment</a>
                    </div>
                @endif
            </aside>
        </div>

        <div class="admin-bottom-grid">
            <section class="ops-card admin-panel">
                <div class="ops-card-heading admin-bottom-heading">
                    <span>Maintenance monitoring</span>
                    <nav class="admin-maintenance-tabs" aria-label="Maintenance status">
                        <a class="{{ $maintenanceTab === 'active' ? 'active' : '' }}" href="{{ route('dashboard', ['maintenance_tab' => 'active']) }}">Active</a>
                        <a class="{{ $maintenanceTab === 'completed' ? 'active' : '' }}" href="{{ route('dashboard', ['maintenance_tab' => 'completed']) }}">Completed</a>
                    </nav>
                </div>
                <div class="ops-table-scroll">
                    <table class="ops-table admin-bottom-table">
                        <thead><tr><th>Equipment</th><th>Maintenance</th><th>Assigned to</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($maintenanceMonitoring as $maintenance)
                            @php
                                $maintenanceDue = $maintenanceTab === 'completed'
                                    ? $maintenance->completion_date
                                    : ($maintenance->next_recommended_maintenance_date ?? $maintenance->start_date);
                                $isOverdue = $maintenanceTab === 'active' && $maintenanceDue && $maintenanceDue->isPast() && $maintenanceDue->isToday() === false;
                                $maintenanceStatus = $isOverdue ? 'Overdue' : $maintenance->status;
                                $maintenancePill = $isOverdue || strtolower($maintenanceStatus) === 'overdue'
                                    ? 'danger'
                                    : (in_array(strtolower($maintenanceStatus), ['pending', 'scheduled'], true) ? 'warning' : 'neutral');
                            @endphp
                            <tr>
                                <td><strong>{{ $maintenance->equipment?->equipment_name ?? 'Equipment record unavailable' }}</strong></td>
                                <td>{{ $maintenance->maintenance_type }}</td>
                                <td>{{ $maintenance->assignedTo?->name ?? $maintenance->equipment?->assignedUser?->name ?? ($maintenance->technician_name ?: 'Unassigned') }}</td>
                                <td>{{ $maintenanceDue?->isToday() ? 'Today' : ($maintenanceDue?->format('M d') ?? 'Not set') }}</td>
                                <td><span class="ops-pill {{ $maintenancePill }}">{{ $maintenanceStatus }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty">{{ $maintenanceTab === 'completed' ? 'No completed maintenance records yet.' : 'No active maintenance tasks.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ops-card admin-panel">
                <div class="ops-card-heading admin-bottom-heading">
                    <span>Pending approvals</span>
                    <span class="admin-approval-count">{{ $pendingMaintenanceCount }} pending</span>
                </div>
                <div class="ops-table-scroll">
                    <table class="ops-table admin-bottom-table">
                        <thead><tr><th>Request</th><th>Equipment</th><th>Requested by</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($pendingApprovals as $approval)
                            <tr>
                                <td><strong>{{ $approval->maintenance_type }}</strong><small class="admin-request-number">{{ $approval->request_number }}</small></td>
                                <td>{{ $approval->equipment?->equipment_name ?? 'Equipment record unavailable' }}</td>
                                <td>Not recorded</td>
                                <td><a href="{{ route('maintenances.show', $approval) }}">Review</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ops-empty">No maintenance requests are waiting for review.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
