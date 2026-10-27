<x-app-layout>
    <x-slot name="header"><h2>Maintenance dashboard</h2></x-slot>

    <div class="ops-page maintenance-dashboard">
        <section class="maintenance-summary-grid" aria-label="My maintenance summary">
            @foreach([
                ['Tasks Today', $tasksTodayCount, 'Inspection and work assignments', '⚒', 'navy'],
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
                <span class="maintenance-focus-label">Maintenance overview</span>
                <h1>{{ $tasksTodayCount ? 'Your maintenance tasks are ready' : ($todayTasks->isNotEmpty() ? 'Review upcoming maintenance work' : 'Start with an equipment check') }}</h1>
                <p>
                    @if($tasksTodayCount)
                        You have {{ number_format($tasksTodayCount) }} task{{ $tasksTodayCount === 1 ? '' : 's' }} scheduled for today. The list below includes inspections, maintenance, and repairs by urgency.
                    @elseif($todayTasks->isNotEmpty())
                        No maintenance is scheduled today. Your upcoming and overdue work is listed by priority and due date.
                    @else
                        No active tasks are assigned to you. Record equipment condition before operational deployment.
                    @endif
                </p>
            </div>
            <a class="maintenance-focus-button" href="{{ route('inspections.create') }}">Start equipment check <span aria-hidden="true">→</span></a>
        </section>

        <div class="maintenance-work-layout">
            <section class="ops-card maintenance-panel">
                <div class="maintenance-panel-heading">
                    <h2>My tasks by urgency</h2>
                    <button type="button" class="maintenance-task-open" data-modal-open="dashboard-all-tasks-modal">View all tasks <span aria-hidden="true">→</span></button>
                </div>
                <div class="ops-table-scroll maintenance-task-scroll">
                    <table class="ops-table maintenance-task-table">
                        <thead>
                            <tr><th>Equipment</th><th>Task</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($todayTasks as $task)
                                @php
                                    $priorityClass = strtolower($task->dashboard_priority ?? 'low');
                                    $statusClass = strtolower(str_replace(' ', '-', $task->status ?? 'pending'));
                                    $taskDate = $task->dashboard_due_date;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</strong>
                                        <small>{{ $task->equipment?->asset_code ?? '—' }}</small>
                                    </td>
                                    <td>{{ $task->dashboard_task_name }}</td>
                                    <td><span class="maintenance-badge {{ $priorityClass }}">{{ $task->dashboard_priority }} priority</span></td>
                                    <td>{{ $taskDate?->isToday() ? 'Today' : ($taskDate?->format('M d') ?? 'Not set') }}</td>
                                    <td><span class="maintenance-badge {{ $statusClass }}">{{ $task->status }}</span></td>
                                    <td>
                                        <button type="button" class="maintenance-task-open" data-modal-open="dashboard-{{ strtolower($task->dashboard_type) }}-{{ $task->id }}">Open</button>
                                        @if(!in_array($task->status, ['Completed', 'Cancelled'], true))
                                            <button type="button" class="maintenance-task-open" data-modal-open="dashboard-status-{{ strtolower($task->dashboard_type) }}-{{ $task->id }}">Update</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="maintenance-empty">No active maintenance tasks are assigned to you.</td></tr>
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
                        <h2>Priority of assigned equipment</h2>
                        <button type="button" class="maintenance-task-open" data-modal-open="dashboard-assigned-equipment-modal">View equipment <span aria-hidden="true">→</span></button>
                    </div>
                    <div class="maintenance-priority-list">
                        @forelse($assignedPriorities as $record)
                            @php
                                $equipment = $record['equipment'];
                            @endphp
                            <a class="maintenance-priority-row" href="{{ route('equipment.show', $equipment) }}">
                                <span class="maintenance-badge {{ strtolower($record['priority']) }}">{{ $record['priority'] }} priority</span>
                                <span>
                                    <strong>{{ $equipment->equipment_name }}</strong>
                                    <small>{{ $record['reason'] }} · Due {{ $record['due_date']?->format('M d, Y') ?? 'Date not set' }}</small>
                                </span>
                                <span class="maintenance-priority-link">View</span>
                            </a>
                        @empty
                            <p class="maintenance-empty-message">No equipment is currently assigned to you.</p>
                        @endforelse
                    </div>
                    <p class="maintenance-model-note">Priority is based on recorded maintenance dates, inspection findings, and equipment status.</p>
                </section>

            <section class="ops-card maintenance-panel">
                <div class="maintenance-panel-heading">
                    <h2>My upcoming schedule</h2>
                    <button type="button" class="maintenance-task-open" data-modal-open="dashboard-assigned-schedule-modal">View schedule <span aria-hidden="true">→</span></button>
                </div>
                <div class="maintenance-schedule-list">
                    @forelse($upcomingTasks as $task)
                            @php($taskDate = $task->dashboard_due_date ?? $task->start_date ?? $task->next_recommended_maintenance_date)
                        <div class="maintenance-schedule-item">
                            <button type="button" class="maintenance-schedule-row" data-modal-open="dashboard-{{ strtolower($task->dashboard_type) }}-{{ $task->id }}">
                                <span class="maintenance-schedule-date">
                                    <strong>{{ $taskDate?->format('d') }}</strong>
                                    <small>{{ $taskDate?->format('M') }}</small>
                                </span>
                                <span><strong>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</strong><small>{{ $task->dashboard_task_name }}</small></span>
                                <span class="maintenance-schedule-priority">{{ $task->dashboard_priority ?? $task->priority }}</span>
                            </button>
                            @if(!in_array($task->status, ['Completed', 'Cancelled'], true))
                                <button type="button" class="maintenance-task-open" data-modal-open="dashboard-status-{{ strtolower($task->dashboard_type) }}-{{ $task->id }}">Update</button>
                            @endif
                        </div>
                    @empty
                        <p class="maintenance-empty-message">You have no upcoming assigned maintenance tasks.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    <dialog id="dashboard-all-tasks-modal" class="app-modal" aria-labelledby="dashboard-all-tasks-title">
        <div class="app-modal-panel" style="max-width:1100px">
            <header class="app-modal-header"><div><h2 id="dashboard-all-tasks-title">All assigned tasks</h2><p>{{ number_format($allAssignedTasks->count()) }} active tasks</p></div><button type="button" class="app-modal-close" aria-label="Close assigned tasks" data-modal-close>&times;</button></header>
            <div class="app-modal-body">
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Equipment</th><th>Task</th><th>Priority</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($allAssignedTasks as $task)
                                <tr><td>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</td><td>{{ $task->dashboard_type }} · {{ $task->dashboard_task_name }}</td><td>{{ $task->dashboard_priority }} priority</td><td>{{ $task->dashboard_due_date?->format('M d, Y') ?? 'Not set' }}</td><td>{{ $task->status }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="maintenance-empty">No active tasks are assigned to you.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="app-modal-footer"><a class="app-modal-button" href="{{ route('maintenances.index') }}">Open maintenance page</a><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    <dialog id="dashboard-assigned-equipment-modal" class="app-modal" aria-labelledby="dashboard-assigned-equipment-title">
        <div class="app-modal-panel" style="max-width:900px">
            <header class="app-modal-header"><div><h2 id="dashboard-assigned-equipment-title">Assigned equipment</h2><p>{{ number_format($allAssignedEquipment->count()) }} equipment records</p></div><button type="button" class="app-modal-close" aria-label="Close assigned equipment" data-modal-close>&times;</button></header>
            <div class="app-modal-body">
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Equipment</th><th>Property no.</th><th>Priority</th><th>Location</th><th>Next due date</th></tr></thead>
                        <tbody>
                            @forelse($allAssignedEquipment as $record)
                                @php($equipment = $record['equipment'])
                                <tr><td>{{ $equipment->equipment_name }}</td><td>{{ $equipment->asset_code }}</td><td>{{ $record['priority'] }} priority</td><td>{{ $equipment->location ?: 'Not set' }}</td><td>{{ $record['due_date']?->format('M d, Y') ?? 'Not set' }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="maintenance-empty">No equipment is assigned to you.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="app-modal-footer"><a class="app-modal-button" href="{{ route('equipment.index') }}">Open equipment page</a><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    <dialog id="dashboard-assigned-schedule-modal" class="app-modal" aria-labelledby="dashboard-assigned-schedule-title">
        <div class="app-modal-panel" style="max-width:900px">
            <header class="app-modal-header"><div><h2 id="dashboard-assigned-schedule-title">Assigned schedule</h2><p>{{ number_format($allAssignedTasks->count()) }} scheduled work records</p></div><button type="button" class="app-modal-close" aria-label="Close assigned schedule" data-modal-close>&times;</button></header>
            <div class="app-modal-body">
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead><tr><th>Date</th><th>Equipment</th><th>Task</th><th>Priority</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($allAssignedTasks->sortBy('dashboard_due_date') as $task)
                                <tr><td>{{ $task->dashboard_due_date?->format('M d, Y') ?? 'Not scheduled' }}</td><td>{{ $task->equipment?->equipment_name ?? 'Equipment unavailable' }}</td><td>{{ $task->dashboard_type }} · {{ $task->dashboard_task_name }}</td><td>{{ $task->dashboard_priority }} priority</td><td>{{ $task->status }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="maintenance-empty">You have no upcoming assigned work.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="app-modal-footer"><a class="app-modal-button" href="{{ route('schedule.index') }}">Open schedule page</a><button type="button" class="app-modal-button" data-modal-close>Close</button></footer>
        </div>
    </dialog>

    @foreach($todayTasks->concat($upcomingTasks)->unique(fn ($task) => $task->dashboard_type.':'.$task->id) as $task)
        @include('operations.record-view-modal', [
            'modalId' => 'dashboard-'.strtolower($task->dashboard_type).'-'.$task->id,
            'recordType' => $task->dashboard_type,
            'record' => $task,
        ])
        @if(!in_array($task->status, ['Completed', 'Cancelled'], true))
            @include('operations.pending-status-modal', [
                'modalId' => 'dashboard-status-'.strtolower($task->dashboard_type).'-'.$task->id,
                'recordType' => $task->dashboard_type,
                'record' => $task,
                'statusUrl' => $task->dashboard_type === 'Inspection'
                    ? route('inspections.status', $task)
                    : ($task->dashboard_type === 'Repair'
                        ? route('repairs.status', $task)
                        : route('maintenances.status', $task)),
            ])
        @endif
    @endforeach
</x-app-layout>
