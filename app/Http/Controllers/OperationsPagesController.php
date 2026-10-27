<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\EquipmentPriorityService;
use App\Services\RolePermissionService;
use App\Support\OfficeScope;
use App\Support\SearchQuery;
use App\Services\RecordReferenceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperationsPagesController extends Controller
{
    public function repairs(Request $request, EquipmentPriorityService $priorities): View
    {
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->notArchived()->orderBy('equipment_name')->get();
        $equipmentUnderRepair = OfficeScope::equipment(Equipment::query())->with('assignedUser')
            ->where('status', 'Under Repair')
            ->orderBy('equipment_name')
            ->get();
        $repairs = OfficeScope::records(Maintenance::query())->with([
            'assignedTo',
            'equipment.assignedUser',
            'equipment.latestInspection',
            'equipment.maintenances',
        ])
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
        $records = $repairs->map(function (Maintenance $item) use ($priorities): array {
            $item->setAttribute('display_priority', $this->maintenancePriority($item, $priorities));

            return [
            'record' => $item->request_number,
            'type' => 'Repair · '.$item->maintenance_type,
            'owner' => $item->assignedTo?->name ?? $item->equipment?->assignedUser?->name ?? ($item->technician_name ?: 'Unassigned'),
            'date' => ($item->completion_date ?? $item->start_date)?->format('M d, Y') ?? '—',
            'priority' => $item->display_priority,
            'status' => $item->status,
            'searchable' => implode(' ', array_filter([
                $item->equipment?->equipment_name,
                $item->equipment?->asset_code,
                $item->reported_issue,
                $item->diagnosis,
                $item->action_taken,
                $item->parts_replaced,
            ])),
            'record_model' => $item,
            'status_url' => route('repairs.status', $item),
            ];
        });

        return $this->recordsPage($request, 'Repairs', 'Review and manage repair work and reported equipment issues.', [
            'record' => 'Record', 'type' => 'Type', 'owner' => 'Owner', 'priority' => 'Priority',
            'date' => 'Date', 'status' => 'Status', 'action' => 'Action',
        ], $records, 'No repair records have been reported.', statusEquipment: $equipmentUnderRepair, equipmentOptions: $equipmentOptions);
    }

    public function schedule(Request $request, EquipmentPriorityService $priorities): View
    {
        $scheduledWork = OfficeScope::records(Maintenance::query())->with([
            'assignedTo',
            'equipment.assignedUser',
            'equipment.latestInspection',
            'equipment.maintenances',
        ])
            ->whereNotIn('status', ['Cancelled'])
            ->get()
            ->flatMap(function (Maintenance $item) use ($priorities) {
                $isRepair = str_contains(strtolower($item->maintenance_type), 'repair')
                    || filled($item->reported_issue);
                $date = $item->status === 'Completed'
                    ? ($item->completion_date ?? $item->start_date)
                    : ($item->start_date ?? $item->next_recommended_maintenance_date);

                $work = [
                    'record' => $item->request_number,
                    'type' => ($isRepair ? 'Repair · ' : 'Maintenance · ').$item->maintenance_type,
                    'owner' => $item->assignedTo?->name ?? $item->equipment?->assignedUser?->name ?? ($item->technician_name ?: 'Unassigned'),
                    'date' => $date?->format('M d, Y') ?? 'Not scheduled',
                    'priority' => $this->maintenancePriority($item, $priorities),
                    'status' => $item->status,
                    'searchable' => implode(' ', array_filter([
                        $item->equipment?->equipment_name,
                        $item->equipment?->asset_code,
                        $item->reported_issue,
                        $item->diagnosis,
                        $item->action_taken,
                        $item->parts_replaced,
                    ])),
                    '_sort_date' => $date?->format('Y-m-d') ?? '9999-12-31',
                ];

                if ($item->next_recommended_maintenance_date
                    && (! $date || ! $item->next_recommended_maintenance_date->isSameDay($date))) {
                    return [$work, [
                        'record' => $item->request_number,
                        'type' => ($isRepair ? 'Repair follow-up · ' : 'Maintenance follow-up · ').$item->maintenance_type,
                        'owner' => $item->assignedTo?->name ?? $item->equipment?->assignedUser?->name ?? ($item->technician_name ?: 'Unassigned'),
                        'date' => $item->next_recommended_maintenance_date->format('M d, Y'),
                        'priority' => $work['priority'],
                        'status' => 'Scheduled',
                        'searchable' => $work['searchable'],
                        '_sort_date' => $item->next_recommended_maintenance_date->format('Y-m-d'),
                    ]];
                }

                return [$work];
            });
        $inspections = OfficeScope::records(Inspection::query())->with([
            'assignedTo',
            'equipment.latestInspection',
            'equipment.maintenances',
        ])
            ->orderBy('inspection_date')
            ->get()
            ->map(fn (Inspection $inspection) => [
                'record' => $inspection->reference_number,
                'type' => 'Inspection · '.($inspection->equipment?->equipment_name ?? 'Equipment unavailable'),
                'owner' => $inspection->assignedTo?->name ?? $inspection->inspector_name ?: 'Unassigned',
                'date' => $inspection->inspection_date?->format('M d, Y') ?? 'Not scheduled',
                'priority' => $inspection->assigned_to_id
                    ? $inspection->priority
                    : ($inspection->equipment ? $priorities->assess($inspection->equipment)['priority'] : '—'),
                'status' => $inspection->status === 'Pending' ? 'Scheduled' : $inspection->outcome,
                '_sort_date' => $inspection->inspection_date?->format('Y-m-d') ?? '9999-12-31',
            ]);
        $nextInspections = OfficeScope::equipment(Equipment::query())->with([
            'latestInspection',
            'maintenances',
        ])
            ->notArchived()
            ->get()
            ->map(function (Equipment $item) use ($priorities): ?array {
                $priority = $priorities->assess($item)['priority'];
                $inspectionDate = $priorities->nextInspectionDate($item);

                if (! $inspectionDate) {
                    return null;
                }

                return [
                    'record' => $item->asset_code,
                    'type' => 'Inspection · '.$item->equipment_name,
                    'owner' => $item->assignedUser?->name ?? 'Unassigned',
                    'date' => $inspectionDate->format('M d, Y'),
                    'priority' => $priority,
                    'status' => $inspectionDate->isPast() ? 'Due' : 'Scheduled',
                    '_sort_date' => $inspectionDate->format('Y-m-d'),
                ];
            })
            ->filter()
            ->values();
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->notArchived()->orderBy('equipment_name')->get();
        $personnelOptions = OfficeScope::personnel(User::query())
            ->where('role', 'maintenance')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $records = $scheduledWork->concat($inspections)->concat($nextInspections)
            ->sortBy('_sort_date')
            ->map(fn (array $record) => collect($record)->except('_sort_date')->all())
            ->values();

        return $this->recordsPage($request, 'Schedule', 'Review upcoming maintenance and equipment inspection dates.', [
            'record' => 'Record', 'type' => 'Scheduled work', 'owner' => 'Owner', 'date' => 'Date',
            'priority' => 'Priority', 'status' => 'Status',
        ], $records, 'No scheduled work is available.', equipmentOptions: $equipmentOptions, schedulePersonnelOptions: $personnelOptions);
    }

    public function storeScheduledWork(Request $request, RecordReferenceNumberService $references): RedirectResponse
    {
        $validated = $request->validate([
            '_form' => ['required', 'in:create-schedule'],
            'work_type' => ['required', 'in:Inspection,Maintenance,Repair'],
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'assigned_to_id' => ['required', OfficeScope::personnelExistsRule()->where('role', 'maintenance')->where('status', 'active')],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', 'in:Low,Medium,High'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $request, $references): void {
            $equipment = OfficeScope::equipment(Equipment::query())->lockForUpdate()->findOrFail($validated['equipment_id']);
            $assignee = OfficeScope::personnel(User::query(), $request->user())
                ->where('role', 'maintenance')
                ->where('status', 'active')
                ->lockForUpdate()
                ->findOrFail($validated['assigned_to_id']);

            if ($validated['work_type'] === 'Inspection') {
                Inspection::create([
                    'equipment_id' => $equipment->id,
                    'assigned_to_id' => $assignee->id,
                    'priority' => $validated['priority'],
                    'reference_number' => $references->nextFor($equipment),
                    'inspection_date' => $validated['scheduled_date'].' 09:00:00',
                    'inspector_name' => $assignee->name,
                    'inspection_type' => 'Scheduled inspection',
                    'recommended_action' => $validated['notes'] ?: null,
                    'outcome' => 'Requires Further Assessment',
                    'status' => 'Pending',
                ]);
                $equipment->next_inspection_date = $validated['scheduled_date'];
                $equipment->save();

                return;
            }

            $maintenanceType = $validated['work_type'] === 'Repair' ? 'Repair' : 'Preventive maintenance';
            Maintenance::create([
                'equipment_id' => $equipment->id,
                'request_number' => $references->nextFor($equipment),
                'maintenance_type' => $maintenanceType,
                'priority' => $validated['priority'],
                'status' => 'Scheduled',
                'reported_issue' => $validated['work_type'] === 'Repair' ? ($validated['notes'] ?: 'Scheduled repair') : null,
                'technician_name' => $assignee->name,
                'assigned_to_id' => $assignee->id,
                'start_date' => $validated['scheduled_date'],
                'next_recommended_maintenance_date' => $validated['scheduled_date'],
                'diagnosis' => $validated['notes'] ?: null,
            ]);
        });

        return redirect()->route('schedule.index')->with('success', $validated['work_type'].' schedule assigned successfully.');
    }

    private function maintenancePriority(Maintenance $maintenance, EquipmentPriorityService $priorities): string
    {
        if ($maintenance->priority === 'Critical') {
            return 'Critical';
        }

        return $maintenance->equipment
            ? $priorities->assess($maintenance->equipment)['priority']
            : $maintenance->priority;
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
        $inspections = OfficeScope::records(Inspection::query())
            ->with(['equipment', 'assignedTo'])
            ->where('approval_status', 'Pending')
            ->where(function ($query) {
                $query->where('status', 'Completed')
                    ->orWhere('status', 'finalized');
            })
            ->get()
            ->map(fn (Inspection $inspection) => [
                'id' => $inspection->id,
                'kind' => 'inspection',
                'reference' => $inspection->reference_number,
                'type' => 'Inspection · '.$inspection->inspection_type,
                'equipment' => $inspection->equipment?->equipment_name ?? 'Equipment unavailable',
                'owner' => $inspection->assignedTo?->name ?? $inspection->inspector_name,
                'date' => $inspection->inspection_date?->format('M d, Y') ?? '—',
                'priority' => $inspection->priority ?: 'Medium',
                'status' => $inspection->outcome,
                'notes' => $inspection->notes,
                'submitted_at' => $inspection->updated_at,
            ]);
        $maintenance = OfficeScope::records(Maintenance::query())
            ->with(['equipment', 'assignedTo'])
            ->where('status', 'Completed')
            ->where('approval_status', 'Pending')
            ->get()
            ->map(fn (Maintenance $work) => [
                'id' => $work->id,
                'kind' => 'maintenance',
                'reference' => $work->request_number,
                'type' => (str_contains(strtolower($work->maintenance_type), 'repair') || filled($work->reported_issue) ? 'Repair · ' : 'Maintenance · ').$work->maintenance_type,
                'equipment' => $work->equipment?->equipment_name ?? 'Equipment unavailable',
                'owner' => $work->assignedTo?->name ?? $work->technician_name ?? 'Unassigned',
                'date' => $work->completion_date?->format('M d, Y') ?? $work->start_date?->format('M d, Y') ?? '—',
                'priority' => $work->priority,
                'status' => $work->maintenance_outcome ?: $work->status,
                'notes' => $work->notes,
                'submitted_at' => $work->updated_at,
            ]);
        $approvalRecords = $inspections->concat($maintenance)
            ->sortByDesc('submitted_at')
            ->values();

        return view('operations.approvals', compact('approvalRecords'));
    }

    public function updateApproval(Request $request, string $type, string $id): RedirectResponse
    {
        abort_unless(in_array($type, ['inspection', 'maintenance'], true), 404);
        $validated = $request->validate([
            'decision' => ['required', 'in:Passed,Requires Further Assessment'],
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $type, $id, $validated): void {
            $passed = $validated['decision'] === 'Passed';
            $review = [
                'approval_status' => $validated['decision'],
                'approval_notes' => $validated['approval_notes'] ?? null,
                'reviewed_by_id' => $request->user()->id,
                'reviewed_at' => now(),
                'status' => $passed ? 'Completed' : 'In Progress',
            ];

            if ($type === 'inspection') {
                $record = OfficeScope::records(Inspection::query())
                    ->lockForUpdate()
                    ->where('approval_status', 'Pending')
                    ->where(function ($query) {
                        $query->where('status', 'Completed')
                            ->orWhere('status', 'finalized');
                    })
                    ->findOrFail($id);
                $record->update([...$review, 'outcome' => $validated['decision']]);

                return;
            }

            $record = OfficeScope::records(Maintenance::query())
                ->lockForUpdate()
                ->where('status', 'Completed')
                ->where('approval_status', 'Pending')
                ->findOrFail($id);
            $record->update([...$review, 'maintenance_outcome' => $validated['decision']]);
        });

        return redirect()->route('approvals.index')->with(
            'success',
            $validated['decision'] === 'Passed'
                ? 'Work approved as passed.'
                : 'Work returned for further assessment.'
        );
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
        ?Collection $statusEquipment = null,
        ?Collection $equipmentOptions = null,
        ?Collection $schedulePersonnelOptions = null
    ): View {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);
        $statuses = $records->pluck('status')->filter()->unique()->sort()->values();
        $records = $records
            ->when($filters['search'] ?? null, fn (Collection $items, string $search) => $items->filter(
                fn (array $record) => SearchQuery::matches($search, $record)
            ))
            ->when($filters['status'] ?? null, fn (Collection $items, string $status) => $items->filter(
                fn (array $record) => ($record['status'] ?? null) === $status
            ))
            ->map(fn (array $record) => collect($record)->except('searchable')->all())
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

        return view('operations.records', compact('title', 'description', 'columns', 'paginatedRecords', 'statuses', 'emptyMessage', 'notice', 'createUrl', 'statusEquipment', 'equipmentOptions', 'schedulePersonnelOptions'));
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
            'canViewReports' => $permissions->allows($role, 'reports'),
        ]);
    }
}
