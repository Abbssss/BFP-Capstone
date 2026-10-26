<x-app-layout>
    <x-slot name="header"><h2>Maintenance dashboard</h2></x-slot>

    <div class="ops-page maintenance-dashboard">
        <section class="maintenance-summary-grid" aria-label="My maintenance summary">
            @foreach([
                ['Tasks Today', $tasksTodayCount, 'Assigned for today', '⚒', 'navy'],
                ['In Progress', $inProgressCount, 'Continue your work', '⌁', 'blue'],
                ['Completed This Month', $completedThisMonthCount, 'Work completed', '✓', 'green'],
                ['Overdue', $overdueCount, 'Needs attention', '△', 'red'],
            ] as [$label, $value, $note, $icon, $color])
                <article class="maintenance-summary-card {{ $color }}">
                    <span aria-hidden="true">{{ $icon }}</span>
                    <div>
                        <small>{{ $label }}</small>
                        <strong>{{ number_format($value) }}</strong>
                        <small>{{ $note }}</small>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="maintenance-focus">
            <div class="maintenance-focus-icon" aria-hidden="true">▤</div>
            <div class="maintenance-focus-copy">
                <span class="maintenance-focus-label">Daily equipment check</span>
                <h1>{{ $tasksTodayCount ? 'Your maintenance work is ready' : 'Start with an equipment check' }}</h1>
                <p>
                    @if($todayTasks->isNotEmpty())
                        {{ $todayTasks->first()->equipment?->equipment_name ?? 'Assigned equipment' }} is on your schedule today. Record its condition before work begins.
                    @else
                        Record the condition of equipment before operational deployment.
                    @endif
                </p>
            </div>
            <a class="maintenance-focus-button" href="{{ route('inspections.create') }}">Start equipment check <span aria-hidden="true">→</span></a>
        </section>

        <div class="maintenance-work-layout">
            <section class="ops-card maintenance-panel">
                <div class="maintenance-panel-heading">
                    <h2>My tasks today</h2>
                    <a href="{{ route('maintenances.index') }}">View all tasks <span aria-hidden="true">→</span></a>
                </div>
                <div class="ops-table-scroll">
                    <table class="ops-table maintenance-task-table">
                        <thead>
                            <tr><th>Equipment</th><th>Task</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($todayTasks as $task)
                                @php
                                    $priorityClass = strtolower($task->priority ?? 'medium');
                                    $statusClass = strtolower(str_replace(' ', '-', $task->status ?? 'pending'));
                                    $taskDate = $task->start_date ?? $task->next_recommended_maintenance_date;
                                @endphp
                                <tr>
                                    <td><strong>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</strong></td>
                                    <td>{{ $task->maintenance_type }}</td>
                                    <td><span class="maintenance-badge {{ $priorityClass }}">{{ $task->priority }}</span></td>
                                    <td>{{ $taskDate?->isToday() ? 'Today' : ($taskDate?->format('M d') ?? 'Not set') }}</td>
                                    <td><span class="maintenance-badge {{ $statusClass }}">{{ $task->status }}</span></td>
                                    <td><a class="maintenance-task-open" href="{{ route('maintenances.show', $task) }}">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="maintenance-empty">No maintenance tasks are scheduled for you today.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="ops-card maintenance-panel maintenance-quick-actions">
                <div class="maintenance-panel-heading"><h2>Quick actions</h2></div>
                <a href="{{ route('inspections.create') }}">
                    <span class="maintenance-action-icon">▤</span>
                    <span><strong>Record equipment check</strong><small>Document a condition inspection</small></span>
                    <span class="maintenance-action-arrow" aria-hidden="true">→</span>
                </a>
                <a href="{{ route('maintenances.create') }}">
                    <span class="maintenance-action-icon">✓</span>
                    <span><strong>Log maintenance</strong><small>Record completed work</small></span>
                    <span class="maintenance-action-arrow" aria-hidden="true">→</span>
                </a>
                <a href="{{ route('defects.index') }}">
                    <span class="maintenance-action-icon danger">△</span>
                    <span><strong>Report a defect</strong><small>Notify the operations team</small></span>
                    <span class="maintenance-action-arrow" aria-hidden="true">→</span>
                </a>
            </aside>
        </div>

        <div class="maintenance-lower-layout">
            <section class="ops-card maintenance-panel">
                <div class="maintenance-panel-heading">
                    <h2>Risk on assigned equipment</h2>
                    <a class="maintenance-model-label" href="{{ route('risk.index') }}"><span aria-hidden="true">✣</span> Model assisted</a>
                </div>
                <div class="maintenance-risk-list">
                    @forelse($assignedRisks as $prediction)
                        @php
                            $riskClass = match ($prediction->prediction_label) {
                                'High Risk' => 'high',
                                'Medium Risk' => 'medium',
                                default => 'low',
                            };
                        @endphp
                        <a class="maintenance-risk-row" href="{{ route('risk.index') }}">
                            <span class="maintenance-badge {{ $riskClass }}">{{ str_replace(' Risk', '', $prediction->prediction_label) }}</span>
                            <span><strong>{{ $prediction->equipment?->equipment_name ?? 'Equipment unavailable' }}</strong><small>{{ $prediction->prediction_timestamp?->format('M d, Y') ?? 'Latest prediction' }}</small></span>
                            <span class="maintenance-risk-link">View</span>
                        </a>
                    @empty
                        <p class="maintenance-empty-message">No risk predictions are available for your assigned equipment.</p>
                    @endforelse
                </div>
                <p class="maintenance-model-note"><span aria-hidden="true">✣</span> Recommendations are view-only. Confirm maintenance decisions with your administrator.</p>
            </section>

            <section class="ops-card maintenance-panel">
                <div class="maintenance-panel-heading">
                    <h2>My upcoming schedule</h2>
                    <a href="{{ route('schedule.index') }}">View schedule <span aria-hidden="true">→</span></a>
                </div>
                <div class="maintenance-schedule-list">
                    @forelse($upcomingTasks as $task)
                        @php($taskDate = $task->start_date ?? $task->next_recommended_maintenance_date)
                        <a class="maintenance-schedule-row" href="{{ route('maintenances.show', $task) }}">
                            <span class="maintenance-schedule-date">
                                <strong>{{ $taskDate?->format('d') }}</strong>
                                <small>{{ $taskDate?->format('M') }}</small>
                            </span>
                            <span><strong>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</strong><small>{{ $task->maintenance_type }}</small></span>
                            <span class="maintenance-schedule-priority">{{ $task->priority }}</span>
                        </a>
                    @empty
                        <p class="maintenance-empty-message">You have no upcoming assigned maintenance tasks.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
