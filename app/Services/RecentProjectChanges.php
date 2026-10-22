<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Support\OfficeScope;
use Illuminate\Support\Collection;

class RecentProjectChanges
{
    public function latest(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        $permissions = app(RolePermissionService::class);
        $changes = collect();
        $assignedEquipmentOnly = $user->role === 'maintenance';

        if ($permissions->allows($user->role, 'equipment')) {
            $equipmentQuery = OfficeScope::equipment(Equipment::query());
            $statusHistoryQuery = OfficeScope::records(EquipmentStatusHistory::query());
            if ($assignedEquipmentOnly) {
                $equipmentQuery->where('assigned_personnel', $user->id);
                $statusHistoryQuery->whereHas('equipment', fn ($equipment) => $equipment->where('assigned_personnel', $user->id));
            }

            $changes = $changes
                ->concat($equipmentQuery
                    ->latest('updated_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (Equipment $equipment) => [
                        'title' => $equipment->created_at?->equalTo($equipment->updated_at) ? 'Equipment registered' : 'Equipment updated',
                        'detail' => $equipment->equipment_name.' · '.$equipment->asset_code,
                        'date' => $equipment->updated_at,
                    ]))
                ->concat($statusHistoryQuery
                    ->with('equipment')
                    ->latest('changed_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (EquipmentStatusHistory $history) => [
                        'title' => 'Equipment status changed',
                        'detail' => ($history->equipment?->equipment_name ?? 'Equipment').' · '.$history->old_status.' → '.$history->new_status,
                        'date' => $history->changed_at,
                    ]));
        }

        if ($permissions->allows($user->role, 'inspections')) {
            $inspectionQuery = OfficeScope::records(Inspection::query());
            if ($assignedEquipmentOnly) {
                $inspectionQuery->whereHas('equipment', fn ($equipment) => $equipment->where('assigned_personnel', $user->id));
            }

            $changes = $changes->concat($inspectionQuery
                ->with('equipment')
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Inspection $inspection) => [
                    'title' => $inspection->created_at?->equalTo($inspection->updated_at) ? 'Inspection recorded' : 'Inspection updated',
                    'detail' => $inspection->reference_number.' · '.$inspection->equipment?->equipment_name,
                    'date' => $inspection->updated_at,
                ]));
        }

        if ($permissions->allows($user->role, 'maintenance')) {
            $maintenanceQuery = OfficeScope::records(Maintenance::query());
            if ($assignedEquipmentOnly) {
                $maintenanceQuery->where(fn ($query) => $query
                    ->where('assigned_to_id', $user->id)
                    ->orWhere('technician_name', $user->name)
                    ->orWhereHas('equipment', fn ($equipment) => $equipment->where('assigned_personnel', $user->id)));
            }

            $changes = $changes->concat($maintenanceQuery
                ->with('equipment')
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Maintenance $maintenance) => [
                    'title' => $maintenance->created_at?->equalTo($maintenance->updated_at) ? 'Maintenance recorded' : 'Maintenance updated',
                    'detail' => $maintenance->request_number.' · '.$maintenance->equipment?->equipment_name,
                    'date' => $maintenance->updated_at,
                ]));
        }

        return $changes
            ->sortByDesc('date')
            ->take(5)
            ->values();
    }
}
