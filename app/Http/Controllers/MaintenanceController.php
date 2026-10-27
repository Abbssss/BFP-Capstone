<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\EquipmentPriorityService;
use App\Services\RecordReferenceNumberService;
use App\Support\OfficeScope;
use App\Support\SearchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request, EquipmentPriorityService $priorities): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'due' => ['nullable', 'in:overdue,soon'],
        ]);
        $maintenanceQuery = OfficeScope::records(Maintenance::query())->with([
            'assignedTo',
            'equipment.assignedUser',
            'equipment.latestInspection',
            'equipment.maintenances',
        ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $type) => $query->where('maintenance_type', $type))
            ->when($filters['due'] ?? null, function ($query, $due) {
                $query->whereNotIn('status', ['Completed', 'Cancelled']);
                if ($due === 'overdue') {
                    $query->whereRaw('COALESCE(next_recommended_maintenance_date, start_date) < ?', [today()->toDateString()]);
                } else {
                    $query->whereRaw('COALESCE(next_recommended_maintenance_date, start_date) BETWEEN ? AND ?', [
                        today()->toDateString(),
                        today()->addDays((int) config('maintenance.priority_warning_days', 30))->toDateString(),
                    ]);
                }
            });
        SearchQuery::apply($maintenanceQuery, $filters['search'] ?? null, [
            'request_number', 'maintenance_type', 'technician_name', 'reported_issue', 'diagnosis',
            'action_taken', 'parts_replaced', 'maintenance_outcome', 'status', 'priority',
        ], [
            'assignedTo' => ['name'],
            'equipment' => ['equipment_name', 'asset_code', 'asset_tag', 'category', 'location'],
            'equipment.assignedUser' => ['name'],
        ]);
        $maintenances = $maintenanceQuery->latest()->paginate(10)->withQueryString();
        $maintenances->getCollection()->each(function (Maintenance $maintenance) use ($priorities): void {
            $equipmentPriority = $maintenance->equipment
                ? $priorities->assess($maintenance->equipment)['priority']
                : $maintenance->priority;
            $maintenance->setAttribute(
                'display_priority',
                $maintenance->priority === 'Critical' ? 'Critical' : $equipmentPriority
            );
        });
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->with('assignedUser')->orderBy('equipment_name')->get();
        $personnelOptions = OfficeScope::personnel(User::query())->where('status', 'active')->orderBy('name')->get();
        $equipmentUnderMaintenance = OfficeScope::equipment(Equipment::query())->with('assignedUser')
            ->whereIn('status', ['Under Maintenance', 'Under Repair'])
            ->orderBy('equipment_name')
            ->get();
        $statuses = OfficeScope::records(Maintenance::query())->select('status')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');
        $types = OfficeScope::records(Maintenance::query())->select('maintenance_type')->whereNotNull('maintenance_type')->distinct()->orderBy('maintenance_type')->pluck('maintenance_type');

        return view('maintenances.index', compact('maintenances', 'equipmentOptions', 'personnelOptions', 'equipmentUnderMaintenance', 'statuses', 'types'));
    }

    public function create(Request $request): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->with('assignedUser')->orderBy('equipment_name')->get();
        $personnelOptions = OfficeScope::personnel(User::query())->where('status', 'active')->orderBy('name')->get();
        $defaultMaintenanceType = $request->query('type') === 'Repair' ? 'Repair' : 'Preventive';

        return view('maintenances.create', compact('equipment', 'personnelOptions', 'defaultMaintenanceType'));
    }

    public function store(Request $request, RecordReferenceNumberService $references): RedirectResponse
    {
        if (in_array($request->input('_form'), ['complete-equipment-maintenance', 'complete-equipment-repair'], true)) {
            return $this->storeEquipmentWorkRecord($request, $references);
        }

        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'assigned_to_id' => ['nullable', OfficeScope::personnelExistsRule()->where('status', 'active')],
            'maintenance_type' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'status' => ['required', 'string'],
            'reported_issue' => ['required_if:_form,create-repair', 'nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'maintenance_cost' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['required_if:status,Completed', 'nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string'],
            'action_taken' => ['required_if:status,Completed', 'nullable', 'string'],
            'downtime_hours' => ['nullable', 'numeric'],
            'maintenance_outcome' => ['nullable', 'string'],
        ]);
        $this->ensureCompletionTechnician($validated);
        if (($validated['status'] ?? null) === 'Completed') {
            $validated['approval_status'] = 'Pending';
        }

        DB::transaction(function () use ($validated, $references): void {
            $equipment = OfficeScope::equipment(Equipment::query())
                ->lockForUpdate()
                ->findOrFail($validated['equipment_id']);
            $validated['request_number'] = $references->nextFor($equipment);
            $maintenance = Maintenance::create($validated);
            $this->applyCompletionSchedule($equipment, $maintenance);
        });

        $redirectRoute = $request->input('_form') === 'create-repair' ? 'repairs.index' : 'maintenances.index';

        return redirect()->route($redirectRoute)->with('success', $redirectRoute === 'repairs.index' ? 'Repair record saved.' : 'Maintenance record saved.');
    }

    public function storeEquipmentMaintenance(Request $request, Equipment $equipment, RecordReferenceNumberService $references): RedirectResponse
    {
        return $this->storeEquipmentWorkRecord($request, $references, $equipment, 'Under Maintenance', 'complete-equipment-maintenance', 'Maintenance');
    }

    public function storeEquipmentRepair(Request $request, Equipment $equipment, RecordReferenceNumberService $references): RedirectResponse
    {
        return $this->storeEquipmentWorkRecord($request, $references, $equipment, 'Under Repair', 'complete-equipment-repair', 'Repair');
    }

    private function storeEquipmentWorkRecord(
        Request $request,
        RecordReferenceNumberService $references,
        ?Equipment $equipment = null,
        ?string $expectedStatus = null,
        ?string $expectedForm = null,
        ?string $workType = null
    ): RedirectResponse {
        $form = $expectedForm ?? $request->input('_form');
        $workType ??= $form === 'complete-equipment-repair' ? 'Repair' : 'Maintenance';
        $validated = $request->validate([
            '_form' => ['required', 'in:complete-equipment-maintenance,complete-equipment-repair'],
            '_equipment_id' => ['required', 'integer'],
            'maintenance_type' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:Low,Medium,High,Critical'],
            'status' => ['required', 'in:Pending,Approved,Scheduled,In Progress,Completed,Cancelled'],
            'equipment_status' => ['required', 'in:Operational,Operational with Restrictions,Under Inspection,Under Maintenance,Under Repair,Out of Service,Retired'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['required_if:status,Completed', 'nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string', 'max:255'],
            'reported_issue' => [$workType === 'Repair' ? 'required' : 'nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'action_taken' => ['required_if:status,Completed', 'nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'maintenance_outcome' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validated['status'] === 'Completed' && blank($validated['technician_name'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'technician_name' => 'Record the technician who completed this work.',
            ]);
        }

        if ($equipment && (int) $validated['_equipment_id'] !== $equipment->id) {
            abort(404);
        }

        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail(
            $equipment?->id ?? $validated['_equipment_id']
        );
        if ($expectedStatus === null) {
            $expectedStatus = $form === 'complete-equipment-repair' ? 'Under Repair' : 'Under Maintenance';
        }
        if ($form !== $expectedForm && $expectedForm !== null) {
            abort(422);
        }

        DB::transaction(function () use ($equipment, $expectedStatus, $validated, $request, $workType, $references): void {
            $lockedEquipment = OfficeScope::equipment(Equipment::query())->lockForUpdate()->findOrFail($equipment->id);
            if ($lockedEquipment->status !== $expectedStatus) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    '_equipment_id' => 'This equipment is no longer '.$expectedStatus.'. Refresh and try again.',
                ]);
            }

            $maintenance = Maintenance::create([
                'equipment_id' => $lockedEquipment->id,
                'request_number' => $references->nextFor($lockedEquipment),
                'maintenance_type' => $validated['maintenance_type'],
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'approval_status' => $validated['status'] === 'Completed' ? 'Pending' : 'Not Required',
                'reported_issue' => $validated['reported_issue'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
                'parts_replaced' => $validated['parts_replaced'] ?? null,
                'maintenance_cost' => $validated['maintenance_cost'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'completion_date' => $validated['completion_date'] ?? null,
                'next_recommended_maintenance_date' => $validated['next_recommended_maintenance_date'] ?? null,
                'technician_name' => $validated['technician_name'] ?? null,
                'downtime_hours' => $validated['downtime_hours'] ?? null,
                'maintenance_outcome' => $validated['maintenance_outcome'] ?? null,
            ]);

            $this->applyCompletionSchedule($lockedEquipment, $maintenance);
            if ($lockedEquipment->status !== $validated['equipment_status']) {
                EquipmentStatusHistory::create([
                    'equipment_id' => $lockedEquipment->id,
                    'old_status' => $lockedEquipment->status,
                    'new_status' => $validated['equipment_status'],
                    'changed_by' => $request->user()->id,
                    'reason' => $workType.' record '.$maintenance->request_number,
                    'changed_at' => now(),
                ]);
                $lockedEquipment->status = $validated['equipment_status'];
            }
            $lockedEquipment->save();
        });

        return redirect()->route($workType === 'Repair' ? 'repairs.index' : 'maintenances.index')
            ->with('success', $workType.' record saved and equipment status updated successfully.');
    }

    public function show(string $id): View
    {
        $maintenance = OfficeScope::records(Maintenance::query())->with('equipment')->findOrFail($id);

        return view('maintenances.show', compact('maintenance'));
    }

    public function updatePendingStatus(Request $request, string $id): RedirectResponse
    {
        $repairRoute = $request->routeIs('repairs.status');
        $validated = $request->validate([
            'status' => ['required', 'in:Approved,Scheduled,In Progress,Completed,Cancelled'],
            'completion_date' => ['required_if:status,Completed', 'nullable', 'date'],
            'technician_name' => ['required_if:status,Completed', 'nullable', 'string', 'max:255'],
            'action_taken' => ['required_if:status,Completed', 'nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validated['status'] === 'Completed') {
            $validated['approval_status'] = 'Pending';
            $validated['reviewed_by_id'] = null;
            $validated['reviewed_at'] = null;
            $validated['approval_notes'] = null;
        }
        $isRepair = false;

        DB::transaction(function () use ($id, $validated, $repairRoute, &$isRepair): void {
            $maintenance = OfficeScope::records(Maintenance::query())
                ->lockForUpdate()
                ->whereNotIn('status', ['Completed', 'Cancelled'])
                ->findOrFail($id);
            $isRepair = str_contains(strtolower($maintenance->maintenance_type), 'repair')
                || filled($maintenance->reported_issue);
            if ($repairRoute && ! $isRepair) {
                abort(404);
            }
            $equipment = OfficeScope::equipment(Equipment::query())
                ->lockForUpdate()
                ->findOrFail($maintenance->equipment_id);

            $maintenance->update($validated);
            $this->applyCompletionSchedule($equipment, $maintenance);
        });

        return redirect()->route($isRepair ? 'repairs.index' : 'maintenances.index')
            ->with('success', ucfirst($isRepair ? 'Repair' : 'Maintenance').' status updated successfully.');
    }

    public function edit(string $id): View
    {
        $maintenance = OfficeScope::records(Maintenance::query())->findOrFail($id);
        $equipment = OfficeScope::equipment(Equipment::query())->with('assignedUser')->orderBy('equipment_name')->get();
        $personnelOptions = OfficeScope::personnel(User::query())->where('status', 'active')->orderBy('name')->get();

        return view('maintenances.edit', compact('maintenance', 'equipment', 'personnelOptions'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $maintenance = OfficeScope::records(Maintenance::query())->findOrFail($id);
        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'assigned_to_id' => ['nullable', OfficeScope::personnelExistsRule()->where('status', 'active')],
            'request_number' => ['required', 'string', 'unique:maintenances,request_number,'.$maintenance->id],
            'maintenance_type' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'status' => ['required', 'string'],
            'reported_issue' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'maintenance_cost' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['required_if:status,Completed', 'nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string'],
            'action_taken' => ['required_if:status,Completed', 'nullable', 'string'],
            'downtime_hours' => ['nullable', 'numeric'],
            'maintenance_outcome' => ['nullable', 'string'],
        ]);
        if ($validated['status'] === 'Completed') {
            $validated['approval_status'] = 'Pending';
            $validated['reviewed_by_id'] = null;
            $validated['reviewed_at'] = null;
            $validated['approval_notes'] = null;
        }
        $this->ensureCompletionTechnician($validated);

        DB::transaction(function () use ($maintenance, $validated): void {
            $equipment = OfficeScope::equipment(Equipment::query())
                ->lockForUpdate()
                ->findOrFail($validated['equipment_id']);
            $maintenance->update($validated);
            $this->applyCompletionSchedule($equipment, $maintenance);
        });

        return redirect()->route('maintenances.index')->with('success', 'Maintenance record updated.');
    }

    public function destroy(string $id): RedirectResponse
    {
        OfficeScope::records(Maintenance::query())->findOrFail($id)->delete();

        return redirect()->route('maintenances.index')->with('success', 'Maintenance record removed.');
    }

    private function applyCompletionSchedule(Equipment $equipment, Maintenance $maintenance): void
    {
        if ($maintenance->status !== 'Completed') {
            return;
        }

        $completionDate = $maintenance->completion_date;
        $equipment->last_maintenance_date = $completionDate;

        if (! $maintenance->next_recommended_maintenance_date && $equipment->maintenance_interval_days) {
            $maintenance->next_recommended_maintenance_date = $completionDate
                ->copy()
                ->addDays($equipment->maintenance_interval_days);
            $maintenance->save();
        }

        $equipment->save();
    }

    private function ensureCompletionTechnician(array $validated): void
    {
        if (
            ($validated['status'] ?? null) === 'Completed'
            && empty($validated['assigned_to_id'])
            && blank($validated['technician_name'] ?? null)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'technician_name' => 'Assign a technician or record the technician who completed this work.',
            ]);
        }
    }
}
