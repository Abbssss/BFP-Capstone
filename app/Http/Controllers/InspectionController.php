<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Inspection;
use App\Models\User;
use App\Services\EquipmentPriorityService;
use App\Services\RecordReferenceNumberService;
use App\Support\OfficeScope;
use App\Support\SearchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(Request $request, EquipmentPriorityService $priorities): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $inspectionQuery = OfficeScope::records(Inspection::query())->with([
            'equipment.latestInspection',
            'equipment.maintenances',
        ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('outcome', $status))
            ->when($filters['category'] ?? null, fn ($query, $type) => $query->where('inspection_type', $type));
        SearchQuery::apply($inspectionQuery, $filters['search'] ?? null, [
            'reference_number', 'inspector_name', 'inspection_type', 'outcome', 'condition_observed',
            'functional_test_result', 'defects_identified', 'safety_concerns', 'recommended_action', 'notes',
        ], ['equipment' => ['equipment_name', 'asset_code', 'asset_tag', 'category']]);
        $inspections = $inspectionQuery->latest()->paginate(10)->withQueryString();
        $inspections->getCollection()->each(function (Inspection $inspection) use ($priorities): void {
            $inspection->setAttribute(
                'equipment_priority',
                $inspection->assigned_to_id
                    ? $inspection->priority
                    : ($inspection->equipment ? $priorities->assess($inspection->equipment)['priority'] : null)
            );
        });
        $priorityInspectionSchedule = OfficeScope::equipment(Equipment::query())->with([
            'assignedUser',
            'latestInspection',
            'maintenances',
        ])
            ->notArchived()
            ->get()
            ->map(fn (Equipment $item): array => [
                'equipment' => $item,
                'priority' => $priorities->assess($item)['priority'],
                'date' => $priorities->nextInspectionDate($item),
            ])
            ->filter(fn (array $record): bool => $record['priority'] === 'High' && $record['date'] !== null)
            ->sortBy('date')
            ->take(10)
            ->values();
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->notArchived()->orderBy('equipment_name')->get();
        $equipmentUnderInspection = OfficeScope::equipment(Equipment::query())->with('assignedUser')
            ->where('status', 'Under Inspection')
            ->orderBy('equipment_name')
            ->limit(10)
            ->get();
        $inspectorOptions = $request->user()->role === 'maintenance'
            ? collect()
            : OfficeScope::personnel(User::query())
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        $outcomes = OfficeScope::records(Inspection::query())->select('outcome')->whereNotNull('outcome')->distinct()->orderBy('outcome')->pluck('outcome');
        $types = OfficeScope::records(Inspection::query())->select('inspection_type')->whereNotNull('inspection_type')->distinct()->orderBy('inspection_type')->pluck('inspection_type');

        return view('inspections.index', compact(
            'inspections',
            'equipmentOptions',
            'equipmentUnderInspection',
            'inspectorOptions',
            'priorityInspectionSchedule',
            'outcomes',
            'types'
        ));
    }

    public function create(): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->notArchived()->orderBy('equipment_name')->get();
        $inspectorOptions = request()->user()->role === 'maintenance'
            ? collect()
            : OfficeScope::personnel(User::query())
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

        return view('inspections.create', compact('equipment', 'inspectorOptions'));
    }

    public function store(Request $request, RecordReferenceNumberService $references): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'inspection_date' => ['required', 'date'],
            'inspector_name' => ['required_if:_form,complete-equipment-inspection', 'nullable', 'string'],
            'inspector_user_id' => [
                'required_unless:_form,complete-equipment-inspection',
                'nullable',
                OfficeScope::personnelExistsRule($request->user())->where('status', 'active'),
            ],
            'inspection_type' => ['required', 'string'],
            'condition_observed' => ['nullable', 'string'],
            'functional_test_result' => ['nullable', 'string'],
            'defects_identified' => ['nullable', 'string'],
            'safety_concerns' => ['nullable', 'string'],
            'recommended_action' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'outcome' => ['required', 'in:Passed,Passed with Findings,Failed,Requires Further Assessment'],
            '_form' => ['nullable', 'in:create-inspection,complete-equipment-inspection'],
            '_equipment_id' => ['nullable', 'integer'],
            'equipment_status' => ['required_if:_form,complete-equipment-inspection', 'nullable', 'in:Operational,Operational with Restrictions,Under Maintenance,Under Repair,Out of Service,Retired'],
        ]);

        DB::transaction(function () use ($validated, $request, $references): void {
            $equipment = OfficeScope::equipment(Equipment::query())
                ->lockForUpdate()
                ->findOrFail($validated['equipment_id']);

            if (($validated['_form'] ?? null) === 'complete-equipment-inspection'
                && $equipment->status !== 'Under Inspection') {
                throw ValidationException::withMessages([
                    'equipment_id' => 'This equipment is no longer marked Under Inspection. Refresh and try again.',
                ]);
            }

            $inspectionData = collect($validated)->except([
                '_form', '_equipment_id', 'equipment_status', 'inspector_user_id',
            ])->all();
            if ($request->user()->role === 'maintenance') {
                $inspectionData['inspector_name'] = $request->user()->name;
            } elseif (($validated['_form'] ?? null) !== 'complete-equipment-inspection') {
                $inspectionData['inspector_name'] = OfficeScope::personnel(User::query(), $request->user())
                    ->where('status', 'active')
                    ->findOrFail($validated['inspector_user_id'])
                    ->name;
            }
            $inspectionData['reference_number'] = $references->nextFor($equipment);
            $inspectionData['approval_status'] = $request->user()->role === 'maintenance'
                || $inspectionData['outcome'] === 'Requires Further Assessment'
                ? 'Pending'
                : 'Not Required';
            $inspection = Inspection::create($inspectionData);

            $equipment->last_inspection_date = $inspection->inspection_date;
            $equipment->last_inspection_outcome = $inspection->outcome;
            $equipment->inspection_defects_count = max(0, (int) preg_match('/\S+/', (string) $inspection->defects_identified));

            $newStatus = $validated['equipment_status'] ?? $equipment->status;
            if ($equipment->status !== $newStatus) {
                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'old_status' => $equipment->status,
                    'new_status' => $newStatus,
                    'changed_by' => $request->user()->id,
                    'reason' => 'Inspection '.$inspection->reference_number,
                    'changed_at' => now(),
                ]);
                $equipment->status = $newStatus;
            }

            $equipment->save();
        });

        $message = ($validated['_form'] ?? null) === 'complete-equipment-inspection'
            ? 'Inspection saved and equipment status updated successfully.'
            : 'Inspection saved successfully.';

        return redirect()->route('inspections.index')->with('success', $message);
    }

    public function show(string $id): View
    {
        $inspection = OfficeScope::records(Inspection::query())->with('equipment')->findOrFail($id);

        return view('inspections.show', compact('inspection'));
    }

    public function updatePendingStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:In Progress,Completed,Cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validated['status'] === 'Completed') {
            $validated['approval_status'] = 'Pending';
            $validated['reviewed_by_id'] = null;
            $validated['reviewed_at'] = null;
            $validated['approval_notes'] = null;
        }
        $inspection = OfficeScope::records(Inspection::query())
            ->whereIn('status', ['Pending', 'In Progress'])
            ->findOrFail($id);
        $inspection->update($validated);

        return redirect()->route('inspections.index')->with('success', 'Inspection status updated successfully.');
    }

    public function edit(string $id): View
    {
        $inspection = OfficeScope::records(Inspection::query())->findOrFail($id);
        $equipment = OfficeScope::equipment(Equipment::query())->notArchived()->orderBy('equipment_name')->get();

        return view('inspections.edit', compact('inspection', 'equipment'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $inspection = OfficeScope::records(Inspection::query())->findOrFail($id);
        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
            'reference_number' => ['required', 'string', 'unique:inspections,reference_number,'.$inspection->id],
            'inspection_date' => ['required', 'date'],
            'inspector_name' => ['required', 'string'],
            'inspection_type' => ['required', 'string'],
            'condition_observed' => ['nullable', 'string'],
            'functional_test_result' => ['nullable', 'string'],
            'defects_identified' => ['nullable', 'string'],
            'safety_concerns' => ['nullable', 'string'],
            'recommended_action' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'outcome' => ['required', 'in:Passed,Passed with Findings,Failed,Requires Further Assessment'],
        ]);

        if ($request->user()->role === 'maintenance' || $validated['outcome'] === 'Requires Further Assessment') {
            $validated['approval_status'] = 'Pending';
            $validated['reviewed_by_id'] = null;
            $validated['reviewed_at'] = null;
            $validated['approval_notes'] = null;
        }

        $previousEquipmentId = $inspection->equipment_id;
        $inspection->update($validated);

        foreach (array_unique([$previousEquipmentId, $inspection->equipment_id]) as $equipmentId) {
            $equipmentRecord = OfficeScope::equipment(Equipment::query())->with('inspections')->find($equipmentId);
            if (! $equipmentRecord) {
                continue;
            }

            $latestInspection = $equipmentRecord->inspections()->latest('inspection_date')->first();
            $equipmentRecord->last_inspection_date = $latestInspection?->inspection_date;
            $equipmentRecord->last_inspection_outcome = $latestInspection?->outcome;
            $equipmentRecord->inspection_defects_count = $equipmentRecord->inspections()
                ->whereNotNull('defects_identified')
                ->where('defects_identified', '<>', '')
                ->count();
            $equipmentRecord->save();
        }

        return redirect()->route('inspections.index')->with('success', 'Inspection updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        OfficeScope::records(Inspection::query())->findOrFail($id)->delete();

        return redirect()->route('inspections.index')->with('success', 'Inspection deleted successfully.');
    }
}
