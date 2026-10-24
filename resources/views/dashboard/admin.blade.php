<x-app-layout>
    <x-slot name="header"><h2>Dashboard</h2></x-slot>

    <div class="ops-page admin-dashboard">
        <div class="admin-dashboard-intro">
            <div>
                <span class="ops-eyebrow">Live data</span>
                <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}.</h1>
                <p>Here is your station maintenance overview for today.</p>
            </div>
        </div>

        <section class="admin-summary-grid" aria-label="Station summary">
            @foreach([
                ['Total Equipment', $equipmentCount, 'All registered assets', '⬡', 'navy'],
                ['Operational', $operationalCount, $readinessPercent.'% ready for service', '✓', 'green'],
                ['Pending Maintenance', $pendingMaintenanceCount, 'Tasks to review', '⚒', 'gold'],
                ['Due Today', $dueTodayCount, 'Maintenance tasks due today', '▣', 'blue'],
                ['Overdue', $overdueMaintenanceCount, 'Requires attention', '△', 'red'],
                ['High-Risk', $highRiskCount, 'Predicted risks', '✣', 'purple'],
            ] as [$label, $value, $note, $icon, $color])
                <article class="admin-summary-card {{ $color }}">
                    <span class="admin-summary-icon" aria-hidden="true">{{ $icon }}</span>
                    <div class="admin-summary-copy">
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($value) }}</strong>
                        <small>{{ $note }}</small>
                    </div>
                </article>
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

        <div class="admin-risk-layout">
            <section class="ops-card admin-panel admin-risk-panel">
                <div class="ops-card-heading">
                    <span>Risk overview</span>
                    <a href="{{ route('risk.index') }}">View all predictions →</a>
                </div>
                <div class="admin-risk-counts">
                    @foreach($riskCounts as $label => $total)
                        @php
                            $riskColor = $label === 'High Risk' ? 'danger' : ($label === 'Medium Risk' ? 'warning' : '');
                        @endphp
                        <div><strong>{{ number_format($total) }}</strong><span class="ops-pill {{ $riskColor }}">{{ str_replace(' Risk', '', $label) }}</span></div>
                    @endforeach
                    @if($latestModel)
                        <small>Decision Tree · {{ $latestModel->version_name }}</small>
                    @endif
                </div>
                <div class="ops-table-scroll">
                    <table class="ops-table admin-risk-table">
                        <thead><tr><th>Equipment</th><th>Risk</th><th>Confidence</th><th>Prediction date</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($latestPredictions->take(5) as $prediction)
                            <tr>
                                <td><strong>{{ $prediction->equipment?->equipment_name ?? 'Equipment record unavailable' }}</strong></td>
                                <td><span class="ops-pill {{ $prediction->prediction_label === 'High Risk' ? 'danger' : ($prediction->prediction_label === 'Medium Risk' ? 'warning' : '') }}">{{ $prediction->prediction_label }}</span></td>
                                <td>{{ number_format((float) $prediction->confidence * 100, 1) }}%</td>
                                <td>{{ $prediction->prediction_timestamp?->format('M d, Y') ?? '—' }}</td>
                                <td><a href="{{ route('risk.index') }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty">No predictions are available yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="ops-card admin-recommendation">
                <div class="admin-recommendation-heading"><span aria-hidden="true">✣</span> Risk follow-up</div>
                @if($featuredPrediction)
                    @php
                        $featuredLabel = $featuredPrediction->prediction_label;
                        $featuredAction = match ($featuredLabel) {
                            'High Risk' => 'Arrange an equipment inspection before the next operational deployment.',
                            'Medium Risk' => 'Schedule a condition check and monitor the next service interval.',
                            default => 'Continue routine inspections and scheduled preventive maintenance.',
                        };
                        $featuredConfidence = (float) $featuredPrediction->confidence;
                    @endphp
                    <div class="admin-recommendation-top">
                        <div>
                            <span class="ops-pill {{ $featuredLabel === 'High Risk' ? 'danger' : ($featuredLabel === 'Medium Risk' ? 'warning' : '') }}">{{ $featuredLabel }}</span>
                            <h2>{{ $featuredPrediction->equipment?->equipment_name ?? 'Equipment record unavailable' }}</h2>
                            <small>Property No. {{ $featuredPrediction->equipment?->asset_code ?? '—' }}</small>
                        </div>
                        <div class="admin-risk-score"><small>Model confidence</small><strong>{{ number_format($featuredConfidence * 100, 0) }}%</strong></div>
                    </div>
                    <div class="admin-recommendation-detail">
                        <small>Recommended follow-up</small>
                        <p>{{ $featuredAction }}</p>
                        <span>Prediction generated {{ $featuredPrediction->prediction_timestamp?->format('M d, Y · H:i') ?? 'date unavailable' }}</span>
                    </div>
                    <p class="admin-recommendation-note">For review by authorized personnel. Confirm equipment condition before taking action.</p>
                    <a class="ops-button small" href="{{ route('risk.index') }}">Review prediction</a>
                @else
                    <div class="admin-recommendation-empty">
                        <strong>No prediction to review</strong>
                        <span>Run a risk assessment to see equipment follow-up guidance here.</span>
                        <a class="ops-button small" href="{{ route('risk.index') }}">Open Predictions</a>
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
