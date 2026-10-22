<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\ModelVersion;
use App\Models\RiskPrediction;
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
        $scheduledOn = fn ($date) => $activeMaintenance()->where(function ($query) use ($date) {
            $query->whereDate('start_date', $date)
                ->orWhere(function ($query) use ($date) {
                    $query->whereNull('start_date')->whereDate('next_recommended_maintenance_date', $date);
                });
        });

        $todayTasks = $scheduledOn(today())
            ->with('equipment')
            ->orderByRaw("CASE priority WHEN 'Critical' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END")
            ->orderByRaw('COALESCE(start_date, next_recommended_maintenance_date)')
            ->limit(5)
            ->get();
        $tasksTodayCount = $scheduledOn(today())->count();
        $inProgressCount = $activeMaintenance()->where('status', 'In Progress')->count();
        $completedThisMonthCount = (clone $assignedMaintenance)
            ->where('status', 'Completed')
            ->whereBetween('completion_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();
        $overdueCount = $activeMaintenance()->where(function ($query) {
            $query->whereDate('start_date', '<', today())
                ->orWhere(function ($query) {
                    $query->whereNull('start_date')->whereDate('next_recommended_maintenance_date', '<', today());
                });
        })->count();

        $upcomingTasks = $activeMaintenance()
            ->with('equipment')
            ->where(function ($query) {
                $query->whereDate('start_date', '>', today())
                    ->orWhere(function ($query) {
                        $query->whereNull('start_date')->whereDate('next_recommended_maintenance_date', '>', today());
                    });
            })
            ->orderByRaw('COALESCE(start_date, next_recommended_maintenance_date)')
            ->limit(5)
            ->get();

        $assignedEquipment = OfficeScope::equipment(Equipment::query())
            ->where(function ($query) use ($user) {
                $query->where('assigned_personnel', $user->id)
                    ->orWhereHas('maintenances', fn ($maintenance) => $maintenance
                        ->where('assigned_to_id', $user->id)
                        ->orWhere('technician_name', $user->name));
            });
        $latestPredictionIds = OfficeScope::records(RiskPrediction::query())
            ->whereIn('equipment_id', (clone $assignedEquipment)->select('id'))
            ->selectRaw('MAX(id)')
            ->groupBy('equipment_id');
        $assignedRisks = OfficeScope::records(RiskPrediction::query())->with('equipment')
            ->whereIn('id', $latestPredictionIds)
            ->latest('prediction_timestamp')
            ->limit(5)
            ->get();

        return view('dashboard.maintenance', compact(
            'todayTasks',
            'tasksTodayCount',
            'inProgressCount',
            'completedThisMonthCount',
            'overdueCount',
            'upcomingTasks',
            'assignedRisks'
        ));
    }

    protected function adminDashboard(Request $request)
    {
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
            ->where(function ($query) {
                $query->whereDate('next_recommended_maintenance_date', '<', today())
                    ->orWhere(function ($query) {
                        $query->whereNull('next_recommended_maintenance_date')
                            ->whereDate('start_date', '<', today());
                    });
            })->count();
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

        $latestPredictionIds = OfficeScope::records(RiskPrediction::query())
            ->selectRaw('MAX(id)')
            ->groupBy('equipment_id');
        $latestPredictions = OfficeScope::records(RiskPrediction::query())->with('equipment')
            ->whereIn('id', $latestPredictionIds)
            ->latest('prediction_timestamp')
            ->get();
        $highRiskCount = $latestPredictions->where('prediction_label', 'High Risk')->count();
        $riskCounts = [
            'Low Risk' => $latestPredictions->where('prediction_label', 'Low Risk')->count(),
            'Medium Risk' => $latestPredictions->where('prediction_label', 'Medium Risk')->count(),
            'High Risk' => $highRiskCount,
        ];

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
        $latestModel = ModelVersion::latest('training_date')->first();
        $featuredPrediction = $latestPredictions->firstWhere('prediction_label', 'High Risk')
            ?? $latestPredictions->first();
        $completionRate = (clone $maintenance)->count()
            ? (int) round((clone $maintenance)->where('status', 'Completed')->count() / (clone $maintenance)->count() * 100)
            : 0;

        return view('dashboard.admin', compact(
            'equipmentCount',
            'operationalCount',
            'pendingMaintenanceCount',
            'dueTodayCount',
            'overdueMaintenanceCount',
            'inspectionDueCount',
            'maintenanceTab',
            'maintenanceMonitoring',
            'pendingApprovals',
            'highRiskCount',
            'riskCounts',
            'latestPredictions',
            'equipmentByStatus',
            'equipmentByCategory',
            'statusChart',
            'statusChartBackground',
            'readinessPercent',
            'defectCount',
            'latestModel',
            'featuredPrediction',
            'completionRate'
        ));
    }

    protected function superAdminDashboard()
    {
        $userCount = User::count();
        $equipmentCount = Equipment::count();
        $maintenanceCount = Maintenance::count();
        $inspectionCount = Inspection::count();
        $completedMaintenanceCount = Maintenance::whereNotNull('completion_date')->count();
        $activeUserCount = User::where('status', 'active')->count();
        $predictionCount = RiskPrediction::count();
        $latestRiskPredictions = RiskPrediction::query()
            ->with('equipment')
            ->whereIn('id', RiskPrediction::query()
                ->selectRaw('MAX(id)')
                ->groupBy('equipment_id'))
            ->latest('prediction_timestamp')
            ->get();
        $riskLevels = [
            'Low Risk' => $latestRiskPredictions->where('prediction_label', 'Low Risk')->count(),
            'Medium Risk' => $latestRiskPredictions->where('prediction_label', 'Medium Risk')->count(),
            'High Risk' => $latestRiskPredictions->where('prediction_label', 'High Risk')->count(),
        ];
        $highRiskCount = $riskLevels['High Risk'];
        $overdueMaintenanceCount = Maintenance::query()
            ->whereDate('next_recommended_maintenance_date', '<', today())
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->count();
        $overdueInspectionCount = Equipment::query()
            ->whereDate('next_inspection_date', '<', today())
            ->whereNotIn('status', ['Retired'])
            ->count();
        $systemAlertCount = $highRiskCount + $overdueMaintenanceCount + $overdueInspectionCount;
        $latestModel = ModelVersion::latest('training_date')->first();
        $users = User::query()->orderByDesc('updated_at')->limit(4)->get();

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
            'completedMaintenanceCount',
            'activeUserCount',
            'highRiskCount',
            'riskLevels',
            'latestRiskPredictions',
            'overdueMaintenanceCount',
            'systemAlertCount',
            'predictionCount',
            'latestModel',
            'users',
            'recentActivity'
        ));
    }
}
