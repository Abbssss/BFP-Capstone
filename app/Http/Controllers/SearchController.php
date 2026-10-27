<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Services\RolePermissionService;
use App\Support\OfficeScope;
use App\Support\SearchQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, RolePermissionService $permissions): View
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);
        $term = trim($validated['q']);
        $user = $request->user();
        $results = collect();

        if ($term !== '' && $permissions->allows($user->role, 'equipment')) {
            $equipmentQuery = OfficeScope::equipment(Equipment::query())->notArchived();
            SearchQuery::apply($equipmentQuery, $term, [
                'equipment_name', 'asset_code', 'asset_tag', 'serial_number', 'category',
                'manufacturer', 'model', 'location', 'office_code', 'status',
            ], ['assignedUser' => ['name']]);
            $results = $results->concat($equipmentQuery
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Equipment $equipment) => [
                    'type' => 'Equipment',
                    'title' => $equipment->equipment_name,
                    'detail' => $equipment->asset_code.' · '.$equipment->category.' · '.$equipment->status,
                    'url' => route('equipment.records', $equipment),
                    'date' => $equipment->updated_at,
                ]));
        }

        if ($term !== '' && $permissions->allows($user->role, 'inspections')) {
            $inspectionQuery = OfficeScope::records(Inspection::query())->with('equipment');
            SearchQuery::apply($inspectionQuery, $term, [
                'reference_number', 'inspector_name', 'inspection_type', 'outcome', 'condition_observed',
                'functional_test_result', 'defects_identified', 'safety_concerns', 'recommended_action', 'notes',
            ], ['equipment' => ['equipment_name', 'asset_code', 'asset_tag', 'category']]);
            $results = $results->concat($inspectionQuery
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Inspection $inspection) => [
                    'type' => 'Inspection',
                    'title' => $inspection->reference_number,
                    'detail' => $inspection->equipment?->equipment_name.' · '.$inspection->inspection_type.' · '.$inspection->outcome,
                    'url' => $inspection->equipment
                        ? route('equipment.records', $inspection->equipment)
                        : route('inspections.show', $inspection),
                    'date' => $inspection->updated_at,
                ]));
        }

        if ($term !== '' && $permissions->allows($user->role, 'maintenance')) {
            $maintenanceQuery = OfficeScope::records(Maintenance::query())->with('equipment');
            SearchQuery::apply($maintenanceQuery, $term, [
                'request_number', 'maintenance_type', 'technician_name', 'reported_issue', 'diagnosis',
                'action_taken', 'parts_replaced', 'maintenance_outcome', 'status', 'priority',
            ], [
                'assignedTo' => ['name'],
                'equipment' => ['equipment_name', 'asset_code', 'asset_tag', 'category', 'location'],
                'equipment.assignedUser' => ['name'],
            ]);
            $results = $results->concat($maintenanceQuery
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Maintenance $maintenance) => [
                    'type' => 'Maintenance',
                    'title' => $maintenance->request_number,
                    'detail' => $maintenance->equipment?->equipment_name.' · '.$maintenance->maintenance_type.' · '.$maintenance->status,
                    'url' => $maintenance->equipment
                        ? route('equipment.records', $maintenance->equipment)
                        : route('maintenances.show', $maintenance),
                    'date' => $maintenance->updated_at,
                ]));
        }

        $results = $results->sortByDesc('date')->take(30)->values();

        return view('search.index', compact('term', 'results'));
    }
}
