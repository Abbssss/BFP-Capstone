<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Services\RolePermissionService;
use App\Support\OfficeScope;
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
            $results = $results->concat(OfficeScope::equipment(Equipment::query())
                ->where(function ($query) use ($term) {
                    $query->where('equipment_name', 'like', '%'.$term.'%')
                        ->orWhere('asset_code', 'like', '%'.$term.'%')
                        ->orWhere('asset_tag', 'like', '%'.$term.'%')
                        ->orWhere('serial_number', 'like', '%'.$term.'%')
                        ->orWhere('category', 'like', '%'.$term.'%')
                        ->orWhere('location', 'like', '%'.$term.'%');
                })
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Equipment $equipment) => [
                    'type' => 'Equipment',
                    'title' => $equipment->equipment_name,
                    'detail' => $equipment->asset_code.' · '.$equipment->category.' · '.$equipment->status,
                    'url' => route('equipment.show', $equipment),
                    'date' => $equipment->updated_at,
                ]));
        }

        if ($term !== '' && $permissions->allows($user->role, 'inspections')) {
            $results = $results->concat(OfficeScope::records(Inspection::query())
                ->with('equipment')
                ->where(function ($query) use ($term) {
                    $query->where('reference_number', 'like', '%'.$term.'%')
                        ->orWhere('inspector_name', 'like', '%'.$term.'%')
                        ->orWhere('inspection_type', 'like', '%'.$term.'%')
                        ->orWhereHas('equipment', fn ($equipment) => $equipment->where('equipment_name', 'like', '%'.$term.'%'));
                })
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Inspection $inspection) => [
                    'type' => 'Inspection',
                    'title' => $inspection->reference_number,
                    'detail' => $inspection->equipment?->equipment_name.' · '.$inspection->inspection_type.' · '.$inspection->outcome,
                    'url' => route('inspections.show', $inspection),
                    'date' => $inspection->updated_at,
                ]));
        }

        if ($term !== '' && $permissions->allows($user->role, 'maintenance')) {
            $results = $results->concat(OfficeScope::records(Maintenance::query())
                ->with('equipment')
                ->where(function ($query) use ($term) {
                    $query->where('request_number', 'like', '%'.$term.'%')
                        ->orWhere('maintenance_type', 'like', '%'.$term.'%')
                        ->orWhere('technician_name', 'like', '%'.$term.'%')
                        ->orWhereHas('assignedTo', fn ($assigned) => $assigned->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('equipment', fn ($equipment) => $equipment->where('equipment_name', 'like', '%'.$term.'%'));
                })
                ->latest('updated_at')
                ->limit(10)
                ->get()
                ->map(fn (Maintenance $maintenance) => [
                    'type' => 'Maintenance',
                    'title' => $maintenance->request_number,
                    'detail' => $maintenance->equipment?->equipment_name.' · '.$maintenance->maintenance_type.' · '.$maintenance->status,
                    'url' => route('maintenances.show', $maintenance),
                    'date' => $maintenance->updated_at,
                ]));
        }

        $results = $results->sortByDesc('date')->take(30)->values();

        return view('search.index', compact('term', 'results'));
    }
}
