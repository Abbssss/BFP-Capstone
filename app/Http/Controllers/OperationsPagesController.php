<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\OfficeScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperationsPagesController extends Controller
{
    public function repairs(Request $request): View
    {
        $equipmentUnderRepair = OfficeScope::equipment(Equipment::query())->with('assignedUser')
            ->where('status', 'Under Repair')
            ->orderBy('equipment_name')
            ->get();
        $repairs = OfficeScope::records(Maintenance::query())->with('equipment')
            ->where(function ($query) {
                $query->where('maintenance_type', 'like', '%repair%')
                    ->orWhere(function ($query) {
                        $query->where('maintenance_type', 'not like', '%preventive%')
                            ->whereNotNull('reported_issue')
                            ->where('reported_issue', '<>', '');
                    });
            })
            ->latest()
            ->get();
        $records = $repairs->map(fn (Maintenance $item) => [
            'record' => $item->request_number,
            'type' => 'Repair · '.$item->maintenance_type,
            'owner' => $item->technician_name ?: 'Unassigned',
            'date' => ($item->completion_date ?? $item->start_date)?->format('M d, Y') ?? '—',
            'status' => $item->status,
            'action_url' => route('maintenances.show', $item),
        ]);

        return $this->recordsPage($request, 'Repairs', 'Review and manage repair work and reported equipment issues.', [
            'record' => 'Record', 'type' => 'Type', 'owner' => 'Owner',
            'date' => 'Date', 'status' => 'Status', 'action' => 'Action',
        ], $records, 'No repair records have been reported.', createUrl: route('maintenances.create', ['type' => 'Repair']), statusEquipment: $equipmentUnderRepair);
    }

    public function schedule(Request $request): View
    {
        $scheduledWork = OfficeScope::records(Maintenance::query())->with('equipment')
            ->whereNotIn('status', ['Cancelled'])
            ->get()
            ->flatMap(function (Maintenance $item) {
                $isRepair = str_contains(strtolower($item->maintenance_type), 'repair')
                    || filled($item->reported_issue);
                $date = $item->status === 'Completed'
                    ? ($item->completion_date ?? $item->start_date)
                    : ($item->start_date ?? $item->next_recommended_maintenance_date);

                $work = [
                    'record' => $item->request_number,
                    'type' => ($isRepair ? 'Repair · ' : 'Maintenance · ').$item->maintenance_type,
                    'owner' => $item->technician_name ?: 'Unassigned',
                    'date' => $date?->format('M d, Y') ?? 'Not scheduled',
                    'status' => $item->status,
                    '_sort_date' => $date?->format('Y-m-d') ?? '9999-12-31',
                ];

                if ($item->next_recommended_maintenance_date
                    && (! $date || ! $item->next_recommended_maintenance_date->isSameDay($date))) {
                    return [$work, [
                        'record' => $item->request_number,
                        'type' => ($isRepair ? 'Repair follow-up · ' : 'Maintenance follow-up · ').$item->maintenance_type,
                        'owner' => $item->technician_name ?: 'Unassigned',
                        'date' => $item->next_recommended_maintenance_date->format('M d, Y'),
                        'status' => 'Scheduled',
                        '_sort_date' => $item->next_recommended_maintenance_date->format('Y-m-d'),
                    ]];
                }

                return [$work];
            });
        $inspections = OfficeScope::records(Inspection::query())->with('equipment')
            ->orderBy('inspection_date')
            ->get()
            ->map(fn (Inspection $inspection) => [
                'record' => $inspection->reference_number,
                'type' => 'Inspection · '.($inspection->equipment?->equipment_name ?? 'Equipment unavailable'),
                'owner' => $inspection->inspector_name ?: 'Unassigned',
                'date' => $inspection->inspection_date?->format('M d, Y') ?? 'Not scheduled',
                'status' => $inspection->outcome,
                '_sort_date' => $inspection->inspection_date?->format('Y-m-d') ?? '9999-12-31',
            ]);
        $nextInspections = OfficeScope::equipment(Equipment::query())
            ->whereNotNull('next_inspection_date')
            ->whereNotIn('status', ['Retired'])
            ->get()
            ->map(fn (Equipment $item) => [
                'record' => $item->asset_code,
                'type' => 'Inspection · '.$item->equipment_name,
                'owner' => $item->assignedUser?->name ?? 'Unassigned',
                'date' => $item->next_inspection_date?->format('M d, Y') ?? 'Not scheduled',
                'status' => $item->next_inspection_date?->isPast() ? 'Due' : 'Scheduled',
                '_sort_date' => $item->next_inspection_date?->format('Y-m-d') ?? '9999-12-31',
            ]);
        $records = $scheduledWork->concat($inspections)->concat($nextInspections)
            ->sortBy('_sort_date')
            ->map(fn (array $record) => collect($record)->except('_sort_date')->all())
            ->values();

        return $this->recordsPage($request, 'Schedule', 'Review upcoming maintenance and equipment inspection dates.', [
            'record' => 'Record', 'type' => 'Scheduled work', 'owner' => 'Owner', 'date' => 'Date', 'status' => 'Status',
        ], $records, 'No scheduled work is available.');
    }

    public function personnel(Request $request): View
    {
        $records = OfficeScope::personnel(User::query())->orderBy('name')->get()->map(fn (User $user) => [
            'name' => $user->name,
            'username' => $user->email,
            'office_code' => $user->office_code ?: '—',
            'role' => Str::of($user->role)->replace('_', ' ')->title()->toString(),
            'status' => $user->status ?: 'Active',
        ]);

        return $this->recordsPage($request, 'Personnel', 'Review personnel accounts and their assigned system roles.', [
            'name' => 'Name', 'username' => 'Username', 'office_code' => 'Office Code', 'role' => 'Role', 'status' => 'Status',
        ], $records, 'No personnel records are available.');
    }

    public function defects(Request $request): View
    {
        $records = OfficeScope::records(Inspection::query())->with('equipment')
            ->whereNotNull('defects_identified')
            ->where('defects_identified', '<>', '')
            ->latest('inspection_date')
            ->get()
            ->map(fn (Inspection $item) => [
                'record' => $item->reference_number,
                'equipment' => $item->equipment?->equipment_name ?? '—',
                'defect' => $item->defects_identified,
                'action' => $item->recommended_action ?: 'Review required',
                'owner' => $item->inspector_name,
                'date' => $item->inspection_date?->format('M d, Y') ?? '—',
                'status' => $item->outcome,
            ]);

        return $this->recordsPage($request, 'Defect Reports', 'Review defects recorded during equipment inspections.', [
            'record' => 'Record', 'equipment' => 'Equipment', 'defect' => 'Reported defect',
            'action' => 'Recommended action', 'owner' => 'Reported by', 'date' => 'Date', 'status' => 'Outcome',
        ], $records, 'No defects have been reported.');
    }

    public function approvals(Request $request): View
    {
        return $this->recordsPage($request, 'Approvals', 'Review pending operational approvals for BFP Sta. Ana.', [
            'record' => 'Record', 'type' => 'Type', 'owner' => 'Owner', 'date' => 'Date', 'status' => 'Status',
        ], collect(), 'Approval records are not available because an approval workflow has not been configured.', 'Approvals are not yet configured in this system.');
    }

    private function recordsPage(
        Request $request,
        string $title,
        string $description,
        array $columns,
        Collection $records,
        string $emptyMessage,
        ?string $notice = null,
        ?string $createUrl = null,
        ?Collection $statusEquipment = null
    ): View {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);
        $statuses = $records->pluck('status')->filter()->unique()->sort()->values();
        $records = $records
            ->when($filters['search'] ?? null, fn (Collection $items, string $search) => $items->filter(
                fn (array $record) => Str::contains(Str::lower(implode(' ', $record)), Str::lower($search))
            ))
            ->when($filters['status'] ?? null, fn (Collection $items, string $status) => $items->filter(
                fn (array $record) => ($record['status'] ?? null) === $status
            ))
            ->values();
        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginatedRecords = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('operations.records', compact('title', 'description', 'columns', 'paginatedRecords', 'statuses', 'emptyMessage', 'notice', 'createUrl', 'statusEquipment'));
    }

    public function audit(): View
    {
        return view('audit.index');
    }

    public function settings(Request $request, RolePermissionService $permissions): View
    {
        $user = $request->user();
        $role = $user->role;

        return view('settings.index', [
            'user' => $user,
            'canAccessDashboard' => $permissions->allows($role, 'dashboard'),
            'canManageRoles' => $role === 'super_admin',
            'canViewPersonnel' => $permissions->allows($role, 'personnel'),
            'canViewRiskModel' => $permissions->allows($role, 'ai_predictions'),
            'canViewReports' => $permissions->allows($role, 'reports'),
        ]);
    }
}
