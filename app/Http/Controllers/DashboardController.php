<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Services\EquipmentPriorityService;
use App\Models\User;
use App\Support\OfficeScope;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        if ($request->user()->role === 'super_admin') {
            return $this->superAdminDashboard();
        }

        if ($request->user()->role === 'admin') {
            return $this->adminDashboard($request);
        }

        return $this->maintenanceDashboard($request);
    }

    protected function maintenanceDashboard(Request $request)
    {
        $user = $request->user();
        $assignedMaintenance = OfficeScope::records(Maintenance::query())
            ->where(function ($query) use ($user) {
                $query->where('assigned_to_id', $user->id)
                    ->orWhere('technician_name', $user->name)
                    ->orWhereHas('equipment', fn ($equipment) => $equipment->where('assigned_personnel', $user->id));
            });
        $activeMaintenance = fn () => (clone $assignedMaintenance)->whereNotIn('status', ['Completed', 'Cancelled']);
        $assignedInspections = OfficeScope::records(Inspection::query())
            ->where(function ($query) use ($user) {
                $query->where('assigned_to_id', $user->id)
                    ->orWhere('inspector_name', $user->name)
                    ->orWhereHas('equipment', fn ($equipment) => $equipment->where('assigned_personnel', $user->id));
            });
        $activeInspections = fn () => (clone $assignedInspections)->whereIn('status', ['Pending', 'In Progress']);
        $scheduledOn = fn ($date) => $activeMaintenance()->where(function ($query) use ($date) {
            $query->whereDate('start_date', $date)
                ->orWhere(function ($query) use ($date) {
                    $query->whereNull('start_date')->whereDate('next_recommended_maintenance_date', $date);
                });
        });

        $maintenanceTasks = $activeMaintenance()
            ->with(['equipment.latestInspection', 'equipment.maintenances'])
            ->get()
            ->map(function (Maintenance $task): Maintenance {
                $dueDate = $task->start_date ?? $task->next_recommended_maintenance_date;
                $assessment = $task->equipment
                    ? app(EquipmentPriorityService::class)->assess($task->equipment)
                    : ['priority' => 'Low', 'critical' => false];
                $priority = $task->priority === 'Critical' ? 'High' : $assessment['priority'];
                $daysUntilDue = $dueDate ? (int) today()->diffInDays($dueDate, false) : null;
                $urgent = $task->priority === 'Critical' || $assessment['critical'] || $priority === 'High';
                $urgency = $urgent ? 0 : ($daysUntilDue !== null && $daysUntilDue < 0
                    ? 1
                    : ($daysUntilDue === 0 ? 2
                        : ($daysUntilDue !== null && $daysUntilDue <= (int) config('maintenance.priority_warning_days', 30) ? 3 : 4)));

                $task->dashboard_due_date = $dueDate;
                $task->dashboard_priority = $priority;
                $task->dashboard_urgency = $urgency;
                $task->dashboard_due_sort = $dueDate?->timestamp ?? PHP_INT_MAX;
                $task->dashboard_type = str_contains(strtolower($task->maintenance_type), 'repair')
                    || filled($task->reported_issue) ? 'Repair' : 'Maintenance';
                $task->dashboard_task_name = $task->maintenance_type;

                return $task;
            });
        $inspectionTasks = $activeInspections()
            ->with(['equipment.latestInspection', 'equipment.maintenances', 'assignedTo'])
            ->get()
            ->map(function (Inspection $task): Inspection {
                $dueDate = $task->inspection_date;
                $priority = $task->priority ?: 'Medium';
                $daysUntilDue = $dueDate ? (int) today()->diffInDays($dueDate, false) : null;
                $urgency = in_array($priority, ['High', 'Critical'], true) ? 0
                    : ($daysUntilDue !== null && $daysUntilDue < 0 ? 1
                        : ($daysUntilDue === 0 ? 2
                            : ($daysUntilDue !== null && $daysUntilDue <= (int) config('maintenance.priority_warning_days', 30) ? 3 : 4)));

                $task->dashboard_due_date = $dueDate;
                $task->dashboard_priority = $priority;
                $task->dashboard_urgency = $urgency;
                $task->dashboard_due_sort = $dueDate?->timestamp ?? PHP_INT_MAX;
                $task->dashboard_type = 'Inspection';
                $task->dashboard_task_name = $task->inspection_type;

                return $task;
            });
        $allAssignedTasks = $maintenanceTasks->concat($inspectionTasks)
            ->sortBy([['dashboard_urgency', 'asc'], ['dashboard_due_sort', 'asc']])
            ->values();
        $todayTasks = $allAssignedTasks->take(5)->values();

        $tasksTodayCount = $scheduledOn(today())->count()
            + $activeInspections()->whereDate('inspection_date', today())->count();
        $inProgressCount = $activeMaintenance()->where('status', 'In Progress')->count()
            + $activeInspections()->where('status', 'In Progress')->count();
        $completedThisMonthCount = (clone $assignedMaintenance)
            ->where('status', 'Completed')
            ->whereBetween('completion_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count()
            + (clone $assignedInspections)
                ->where('status', 'Completed')
                ->whereDate('inspection_date', '>=', now()->startOfMonth()->toDateString())
                ->whereDate('inspection_date', '<=', now()->endOfMonth()->toDateString())
                ->count();
        $overdueCount = $activeMaintenance()->where(function ($query) {
            $query->whereDate('start_date', '<', today())
                ->orWhere(function ($query) {
                    $query->whereNull('start_date')->whereDate('next_recommended_maintenance_date', '<', today());
                });
        })->count()
            + $activeInspections()->whereDate('inspection_date', '<', today())->count();

        $upcomingTasks = $allAssignedTasks
            ->filter(fn ($task) => $task->dashboard_due_date && $task->dashboard_due_date->isAfter(today()))
            ->sortBy('dashboard_due_sort')
            ->take(5)
            ->values();

        $assignedEquipment = OfficeScope::equipment(Equipment::query())
            ->where(function ($query) use ($user) {
                $query->where('assigned_personnel', $user->id)
                    ->orWhereHas('maintenances', fn ($maintenance) => $maintenance
                        ->where('assigned_to_id', $user->id)
                        ->orWhere('technician_name', $user->name))
                    ->orWhereHas('inspections', fn ($inspection) => $inspection
                        ->where('assigned_to_id', $user->id)
                        ->orWhere('inspector_name', $user->name));
            });
        $allAssignedEquipment = $assignedEquipment
            ->with(['assignedUser', 'latestInspection', 'maintenances'])
            ->get()
            ->map(fn (Equipment $item) => [
                'equipment' => $item,
                ...app(EquipmentPriorityService::class)->assess($item),
            ])
            ->map(function (array $record): array {
                $record['priority_order'] = ['High' => 0, 'Medium' => 1, 'Low' => 2][$record['priority']];
                $record['due_order'] = $record['due_date']?->timestamp ?? PHP_INT_MAX;

                return $record;
            })
            ->sortBy([['priority_order', 'asc'], ['due_order', 'asc']])
            ->values();

        $assignedPriorities = $assignedEquipment
            ->with(['latestInspection', 'maintenances'])
            ->get()
            ->map(fn (Equipment $item) => [
                'equipment' => $item,
                ...app(EquipmentPriorityService::class)->assess($item),
            ])
            ->map(function (array $record): array {
                $record['priority_order'] = ['High' => 0, 'Medium' => 1, 'Low' => 2][$record['priority']];
                $record['due_order'] = $record['due_date']?->timestamp ?? PHP_INT_MAX;

                return $record;
            })
            ->sortBy([['priority_order', 'asc'], ['due_order', 'asc']])
            ->take(5)
            ->values();

        return view('dashboard.maintenance', compact(
            'todayTasks',
            'tasksTodayCount',
            'inProgressCount',
            'completedThisMonthCount',
            'overdueCount',
            'upcomingTasks',
            'assignedPriorities',
            'allAssignedTasks',
            'allAssignedEquipment'
        ));
    }

    protected function adminDashboard(Request $request)
    {
        $priorityFilter = $request->validate([
            'maintenance_tab' => ['nullable', 'in:active,completed'],
            'priority_filter' => ['nullable', 'in:High,Medium,Low,alerts'],
        ])['priority_filter'] ?? 'all';
        $maintenanceTab = $request->validate([
            'maintenance_tab' => ['nullable', 'in:active,completed'],
        ])['maintenance_tab'] ?? 'active';
        $equipment = OfficeScope::equipment(Equipment::query());
        $maintenance = OfficeScope::records(Maintenance::query());
        $inspections = OfficeScope::records(Inspection::query());
        $equipmentCount = (clone $equipment)->count();
        $operationalCount = (clone $equipment)->where('status', 'Operational')->count();
        $pendingMaintenanceCount = (clone $maintenance)->where('status', 'Pending')->count();
        $dueTodayCount = (clone $maintenance)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->where(function ($query) {
                $query->whereDate('next_recommended_maintenance_date', today())
                    ->orWhereDate('start_date', today());
            })->count();
        $overdueMaintenanceCount = (clone $maintenance)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->whereRaw('COALESCE(next_recommended_maintenance_date, start_date) < ?', [today()->toDateString()])
            ->count();
        $maintenanceDueSoonCount = (clone $maintenance)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->whereRaw('COALESCE(next_recommended_maintenance_date, start_date) BETWEEN ? AND ?', [
                today()->toDateString(),
                today()->addDays((int) config('maintenance.priority_warning_days', 30))->toDateString(),
            ])
            ->count();
        $inspectionDueCount = (clone $equipment)
            ->whereNotNull('next_inspection_date')
            ->whereDate('next_inspection_date', '<=', today())
            ->whereNotIn('status', ['Retired'])
            ->count();
        $maintenanceMonitoring = (clone $maintenance)->with('assignedTo', 'equipment.assignedUser')
            ->when(
                $maintenanceTab === 'completed',
                fn ($query) => $query->where('status', 'Completed')->orderByDesc('completion_date'),
                fn ($query) => $query
                    ->whereNotIn('status', ['Completed', 'Cancelled'])
                    ->orderByRaw('CASE WHEN next_recommended_maintenance_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('next_recommended_maintenance_date')
            )
            ->latest('updated_at')
            ->limit(3)
            ->get();
        $pendingApprovals = (clone $maintenance)->with('assignedTo', 'equipment.assignedUser')
            ->where('status', 'Pending')
            ->latest()
            ->limit(3)
            ->get();

        $priorityAnalysis = app(EquipmentPriorityService::class)->summarize(
            (clone $equipment)->with(['latestInspection', 'maintenances'])->get()
        );
        $priorityRecords = $priorityAnalysis['records']
            ->map(function (array $record): array {
                $record['priority_order'] = ['High' => 0, 'Medium' => 1, 'Low' => 2][$record['priority']];
                $record['due_order'] = $record['due_date']?->timestamp ?? PHP_INT_MAX;

                return $record;
            })
            ->sortBy([['priority_order', 'asc'], ['due_order', 'asc']])
            ->values();
        $priorityCounts = $priorityAnalysis['counts'];
        $highPriorityCount = $priorityCounts['High'];
        $allPriorityRecords = $priorityRecords;
        $priorityRecords = match ($priorityFilter) {
            'High', 'Medium', 'Low' => $priorityRecords->where('priority', $priorityFilter)->values(),
            'alerts' => $priorityRecords->filter(fn (array $record) => $record['priority'] === 'High'
                || ($record['due_date'] && $record['due_date']->lt(today())))->values(),
            default => $priorityRecords,
        };
        $featuredPriority = $priorityRecords->first();
        $systemAlertCount = $highPriorityCount + $overdueMaintenanceCount + $inspectionDueCount;

        $equipmentByStatus = (clone $equipment)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();
        $equipmentByCategory = (clone $equipment)
            ->selectRaw('category, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ready', ['Operational'])
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(4)
            ->get();
        $readinessPercent = $equipmentCount
            ? (int) round($operationalCount / $equipmentCount * 100)
            : 0;
        $statusColors = [
            'operational' => '#16845f',
            'under maintenance' => '#2f6bb2',
            'under inspection' => '#dda900',
            'operational with restrictions' => '#dda900',
            'out of service' => '#c51f25',
            'retired' => '#7c8798',
        ];
        $statusChart = collect();
        $statusChartEnd = 0;
        foreach ($equipmentByStatus as $index => $status) {
            $statusChartEnd = $index === $equipmentByStatus->count() - 1
                ? 100
                : $statusChartEnd + ($equipmentCount ? $status->total / $equipmentCount * 100 : 0);
            $statusChart->push([
                'status' => $status->status,
                'total' => $status->total,
                'color' => $statusColors[strtolower($status->status)] ?? '#7c8798',
                'start' => $index === 0 ? 0 : $statusChart->last()['end'],
                'end' => $statusChartEnd,
            ]);
        }
        $statusChartBackground = $statusChart->isEmpty()
            ? '#e8edf3'
            : $statusChart->map(fn (array $slice) => sprintf('%s %.2f%% %.2f%%', $slice['color'], $slice['start'], $slice['end']))->implode(', ');
        $defectCount = (clone $inspections)
            ->whereNotNull('defects_identified')
            ->where('defects_identified', '<>', '')
            ->count();
        $completionRate = (clone $maintenance)->count()
            ? (int) round((clone $maintenance)->where('status', 'Completed')->count() / (clone $maintenance)->count() * 100)
            : 0;

        $user = $request->user();
        $metricUsers = OfficeScope::personnel(User::query(), $user)->orderBy('name')->get();
        $metricActiveUsers = (clone $metricUsers)->where('status', 'active')->values();
        $userCount = $metricUsers->count();
        $activeUserCount = $metricActiveUsers->count();
        $metricEquipment = (clone $equipment)->with(['assignedUser', 'latestInspection', 'maintenances'])->orderBy('equipment_name')->get();
        $users = $metricUsers->take(4)->values();
        $inspectionCount = (clone $inspections)->count();
        $maintenanceCount = (clone $maintenance)->count();
        $repairCount = (clone $maintenance)
            ->where(function ($query) {
                $query->where('maintenance_type', 'like', '%repair%')
                    ->orWhere(function ($query) {
                        $query->where('maintenance_type', 'not like', '%preventive%')
                            ->whereNotNull('reported_issue')
                            ->where('reported_issue', '<>', '');
                    });
            })
            ->count();
        $recentActivity = collect()
            ->concat((clone $inspections)->with('equipment')->latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Inspection recorded',
                'detail' => $record->reference_number.' · '.$record->equipment?->equipment_name,
                'date' => $record->created_at,
            ]))
            ->concat((clone $maintenance)->with('equipment')->latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Maintenance '.$record->status,
                'detail' => $record->request_number.' · '.$record->equipment?->equipment_name,
                'date' => $record->created_at,
            ]))
            ->concat((clone $equipment)->latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Equipment registered',
                'detail' => $record->asset_code.' · '.$record->equipment_name,
                'date' => $record->created_at,
            ]))
            ->sortByDesc('date')
            ->take(4)
            ->values();

        return view('dashboard.super-admin', compact(
            'equipmentCount',
            'operationalCount',
            'pendingMaintenanceCount',
            'dueTodayCount',
            'overdueMaintenanceCount',
            'maintenanceDueSoonCount',
            'inspectionDueCount',
            'maintenanceTab',
            'priorityFilter',
            'systemAlertCount',
            'maintenanceMonitoring',
            'pendingApprovals',
            'highPriorityCount',
            'priorityCounts',
            'priorityRecords',
            'allPriorityRecords',
            'featuredPriority',
            'equipmentByStatus',
            'equipmentByCategory',
            'statusChart',
            'statusChartBackground',
            'readinessPercent',
            'defectCount',
            'completionRate',
            'userCount',
            'activeUserCount',
            'inspectionCount',
            'maintenanceCount',
            'repairCount',
            'metricUsers',
            'metricActiveUsers',
            'metricEquipment',
            'users',
            'recentActivity'
        ));
    }

    protected function superAdminDashboard()
    {
        $userCount = User::count();
        $equipmentCount = Equipment::count();
        $maintenanceCount = Maintenance::count();
        $inspectionCount = Inspection::count();
        $repairCount = Maintenance::query()
            ->where(function ($query) {
                $query->where('maintenance_type', 'like', '%repair%')
                    ->orWhere(function ($query) {
                        $query->where('maintenance_type', 'not like', '%preventive%')
                            ->whereNotNull('reported_issue')
                            ->where('reported_issue', '<>', '');
                    });
            })
            ->count();
        $activeUserCount = User::where('status', 'active')->count();
        $priorityAnalysis = app(EquipmentPriorityService::class)->summarize(
            Equipment::with(['latestInspection', 'maintenances'])->get()
        );
        $priorityRecords = $priorityAnalysis['records']
            ->map(function (array $record): array {
                $record['priority_order'] = ['High' => 0, 'Medium' => 1, 'Low' => 2][$record['priority']];
                $record['due_order'] = $record['due_date']?->timestamp ?? PHP_INT_MAX;

                return $record;
            })
            ->sortBy([['priority_order', 'asc'], ['due_order', 'asc']])
            ->values();
        $priorityCounts = $priorityAnalysis['counts'];
        $highPriorityCount = $priorityCounts['High'];
        $overdueMaintenanceCount = Maintenance::query()
            ->where(function ($query) {
                $query->whereDate('next_recommended_maintenance_date', '<', today())
                    ->orWhere(function ($query) {
                        $query->whereNull('next_recommended_maintenance_date')
                            ->whereDate('start_date', '<', today());
                    });
            })
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->count();
        $overdueInspectionCount = Equipment::query()
            ->whereDate('next_inspection_date', '<', today())
            ->whereNotIn('status', ['Retired'])
            ->count();
        $systemAlertCount = $highPriorityCount + $overdueMaintenanceCount + $overdueInspectionCount;
        $users = User::query()->orderByDesc('updated_at')->limit(4)->get();
        $metricUsers = User::query()->orderBy('name')->get();
        $metricActiveUsers = User::query()->where('status', 'active')->orderBy('name')->get();
        $metricEquipment = Equipment::with(['assignedUser', 'latestInspection', 'maintenances'])
            ->orderBy('equipment_name')
            ->get();

        $recentActivity = collect()
            ->concat(Inspection::with('equipment')->latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Inspection recorded',
                'detail' => $record->reference_number.' · '.$record->equipment?->equipment_name,
                'date' => $record->created_at,
            ]))
            ->concat(Maintenance::with('equipment')->latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Maintenance '.$record->status,
                'detail' => $record->request_number.' · '.$record->equipment?->equipment_name,
                'date' => $record->created_at,
            ]))
            ->concat(Equipment::latest()->limit(4)->get()->map(fn ($record) => [
                'type' => 'Equipment registered',
                'detail' => $record->asset_code.' · '.$record->equipment_name,
                'date' => $record->created_at,
            ]))
            ->sortByDesc('date')
            ->take(4)
            ->values();

        return view('dashboard.super-admin', compact(
            'userCount',
            'equipmentCount',
            'maintenanceCount',
            'inspectionCount',
            'repairCount',
            'activeUserCount',
            'highPriorityCount',
            'priorityCounts',
            'priorityRecords',
            'overdueMaintenanceCount',
            'systemAlertCount',
            'users',
            'metricUsers',
            'metricActiveUsers',
            'metricEquipment',
            'recentActivity'
        ));
    }
}
