<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Maintenance;
use App\Models\User;
use App\Support\OfficeScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $maintenances = OfficeScope::records(Maintenance::query())->with('assignedTo', 'equipment.assignedUser')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('request_number', 'like', '%'.$search.'%')
                        ->orWhere('maintenance_type', 'like', '%'.$search.'%')
                        ->orWhere('technician_name', 'like', '%'.$search.'%')
                        ->orWhereHas('assignedTo', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('equipment', fn ($equipmentQuery) => $equipmentQuery->where('equipment_name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $type) => $query->where('maintenance_type', $type))
            ->latest()
            ->paginate(10)
            ->withQueryString();
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

    public function store(Request $request): RedirectResponse
    {
        if (in_array($request->input('_form'), ['complete-equipment-maintenance', 'complete-equipment-repair'], true)) {
            return $this->storeEquipmentWorkRecord($request);
        }

        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'assigned_to_id' => ['nullable', OfficeScope::personnelExistsRule()->where('status', 'active')],
            'request_number' => ['required', 'string', 'unique:maintenances,request_number'],
            'maintenance_type' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'status' => ['required', 'string'],
            'reported_issue' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'maintenance_cost' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string'],
            'downtime_hours' => ['nullable', 'numeric'],
            'maintenance_outcome' => ['nullable', 'string'],
        ]);

        Maintenance::create($validated);

        return redirect()->route('maintenances.index')->with('success', 'Maintenance record saved.');
    }

    public function storeEquipmentMaintenance(Request $request, Equipment $equipment): RedirectResponse
    {
        return $this->storeEquipmentWorkRecord($request, $equipment, 'Under Maintenance', 'complete-equipment-maintenance', 'Maintenance');
    }

    public function storeEquipmentRepair(Request $request, Equipment $equipment): RedirectResponse
    {
        return $this->storeEquipmentWorkRecord($request, $equipment, 'Under Repair', 'complete-equipment-repair', 'Repair');
    }

    private function storeEquipmentWorkRecord(
        Request $request,
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
            'request_number' => ['required', 'string', 'max:255', 'unique:maintenances,request_number'],
            'maintenance_type' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:Low,Medium,High,Critical'],
            'status' => ['required', 'in:Pending,Approved,Scheduled,In Progress,Completed,Cancelled'],
            'equipment_status' => ['required', 'in:Operational,Operational with Restrictions,Under Inspection,Under Maintenance,Under Repair,Out of Service,Retired'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string', 'max:255'],
            'reported_issue' => [$workType === 'Repair' ? 'required' : 'nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'maintenance_outcome' => ['nullable', 'string', 'max:255'],
        ]);

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

        DB::transaction(function () use ($equipment, $expectedStatus, $validated, $request, $workType): void {
            $lockedEquipment = OfficeScope::equipment(Equipment::query())->lockForUpdate()->findOrFail($equipment->id);
            if ($lockedEquipment->status !== $expectedStatus) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    '_equipment_id' => 'This equipment is no longer '.$expectedStatus.'. Refresh and try again.',
                ]);
            }

            $maintenance = Maintenance::create([
                'equipment_id' => $lockedEquipment->id,
                'request_number' => $validated['request_number'],
                'maintenance_type' => $validated['maintenance_type'],
                'priority' => $validated['priority'],
                'status' => $validated['status'],
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

            $lockedEquipment->last_maintenance_date = $maintenance->completion_date ?? $maintenance->start_date ?? $lockedEquipment->last_maintenance_date;
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
            'completion_date' => ['nullable', 'date'],
            'next_recommended_maintenance_date' => ['nullable', 'date'],
            'technician_name' => ['nullable', 'string'],
            'downtime_hours' => ['nullable', 'numeric'],
            'maintenance_outcome' => ['nullable', 'string'],
        ]);

        $maintenance->update($validated);

        return redirect()->route('maintenances.index')->with('success', 'Maintenance record updated.');
    }

    public function destroy(string $id): RedirectResponse
    {
        OfficeScope::records(Maintenance::query())->findOrFail($id)->delete();

        return redirect()->route('maintenances.index')->with('success', 'Maintenance record removed.');
    }
}
