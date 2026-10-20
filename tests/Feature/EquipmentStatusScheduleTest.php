<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentStatusScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_status_appears_in_inspection_maintenance_and_repair_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $inspectionEquipment = $this->createEquipment('Under Inspection');
        $maintenanceEquipment = $this->createEquipment('Under Maintenance');
        $repairEquipment = $this->createEquipment('Under Repair');

        $this->actingAs($admin)
            ->get('/inspections')
            ->assertOk()
            ->assertSee('Equipment currently under inspection')
            ->assertSee($inspectionEquipment->asset_code)
            ->assertSee('Record inspection')
            ->assertSee('data-inspection-equipment-id="'.$inspectionEquipment->id.'"', false);

        $this->get('/maintenances')
            ->assertOk()
            ->assertSee('Equipment currently under maintenance or repair')
            ->assertSee($maintenanceEquipment->asset_code)
            ->assertSee($repairEquipment->asset_code);

        $this->get('/repairs')
            ->assertOk()
            ->assertSee('Equipment currently under repair')
            ->assertSee($repairEquipment->asset_code);
    }

    public function test_recording_an_inspection_from_under_inspection_updates_equipment_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Inspection');

        $this->actingAs($admin)
            ->post('/inspections', [
                '_form' => 'complete-equipment-inspection',
                '_equipment_id' => $equipment->id,
                'equipment_id' => $equipment->id,
                'reference_number' => 'INSP-STATUS-UPDATE-01',
                'inspection_date' => now()->format('Y-m-d\TH:i'),
                'inspector_name' => $admin->name,
                'inspection_type' => 'Operational inspection',
                'outcome' => 'Passed with Findings',
                'equipment_status' => 'Operational with Restrictions',
                'condition_observed' => 'Minor fault observed',
                'defects_identified' => 'Loose connection',
                'recommended_action' => 'Repair at next service',
            ])
            ->assertRedirect(route('inspections.index'))
            ->assertSessionHas('success', 'Inspection saved and equipment status updated successfully.');

        $this->assertDatabaseHas('inspections', [
            'equipment_id' => $equipment->id,
            'reference_number' => 'INSP-STATUS-UPDATE-01',
            'outcome' => 'Passed with Findings',
        ]);
        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'status' => 'Operational with Restrictions',
            'last_inspection_outcome' => 'Passed with Findings',
            'inspection_defects_count' => 1,
        ]);
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'old_status' => 'Under Inspection',
            'new_status' => 'Operational with Restrictions',
            'changed_by' => $admin->id,
            'reason' => 'Inspection INSP-STATUS-UPDATE-01',
        ]);

        $this->get('/inspections')
            ->assertOk()
            ->assertSee('INSP-STATUS-UPDATE-01')
            ->assertDontSee('Equipment currently under inspection');
    }

    public function test_schedule_uses_updated_work_and_inspection_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Maintenance');
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'SCHED-UPDATE-01',
            'maintenance_type' => 'Pump service',
            'priority' => 'High',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(9),
        ]);
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'INSP-SCHED-01',
            'inspection_date' => now()->addDays(13)->setTime(9, 30),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
            'outcome' => 'Passed',
        ]);
        $repair = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REPAIR-SCHED-UPDATE-01',
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Scheduled',
            'reported_issue' => 'Pressure leak',
            'start_date' => today()->addDays(11),
        ]);

        $updatedMaintenanceDate = today()->addDays(20);
        $updatedFollowupDate = today()->addDays(35);
        $updatedInspectionDate = now()->addDays(25)->setTime(10, 0);
        $updatedRepairDate = today()->addDays(30);
        $this->actingAs($admin)
            ->put(route('maintenances.update', $maintenance), [
                'equipment_id' => $equipment->id,
                'request_number' => $maintenance->request_number,
                'maintenance_type' => 'Pump service',
                'priority' => 'High',
                'status' => 'Scheduled',
                'start_date' => $updatedMaintenanceDate->toDateString(),
                'next_recommended_maintenance_date' => $updatedFollowupDate->toDateString(),
            ])
            ->assertRedirect(route('maintenances.index'));

        $this->put(route('maintenances.update', $repair), [
            'equipment_id' => $equipment->id,
            'request_number' => $repair->request_number,
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Scheduled',
            'reported_issue' => 'Pressure leak',
            'start_date' => $updatedRepairDate->toDateString(),
        ])->assertRedirect(route('maintenances.index'));

        $this->put(route('inspections.update', $inspection), [
            'equipment_id' => $equipment->id,
            'reference_number' => $inspection->reference_number,
            'inspection_date' => $updatedInspectionDate->format('Y-m-d\TH:i'),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
            'outcome' => 'Passed',
        ])->assertRedirect(route('inspections.index'));

        $this->get('/schedule')
            ->assertOk()
            ->assertSee('SCHED-UPDATE-01')
            ->assertSee($updatedMaintenanceDate->format('M d, Y'))
            ->assertSee('Maintenance follow-up · Pump service')
            ->assertSee($updatedFollowupDate->format('M d, Y'))
            ->assertSee('REPAIR-SCHED-UPDATE-01')
            ->assertSee('Repair · Pump repair')
            ->assertSee($updatedRepairDate->format('M d, Y'))
            ->assertSee('INSP-SCHED-01')
            ->assertSee($updatedInspectionDate->format('M d, Y'));
    }

    public function test_under_maintenance_equipment_can_be_recorded_and_released_from_maintenance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Maintenance');

        $this->actingAs($admin)
            ->get('/maintenances')
            ->assertOk()
            ->assertSee('Record maintenance')
            ->assertSee('equipment-work-record-modal');

        $this->from('/maintenances')
            ->post(route('maintenance.equipment-work.store', $equipment), [
                '_form' => 'complete-equipment-maintenance',
                '_equipment_id' => $equipment->id,
                'request_number' => 'MAINT-STATUS-'.strtoupper(bin2hex(random_bytes(3))),
                'maintenance_type' => 'Pump service',
                'priority' => 'High',
                'status' => 'Completed',
                'equipment_status' => 'Operational',
                'start_date' => today()->toDateString(),
                'completion_date' => today()->toDateString(),
                'technician_name' => 'Maintenance Officer',
                'reported_issue' => 'Routine service',
                'action_taken' => 'Service completed',
            ])
            ->assertRedirect('/maintenances')
            ->assertSessionHas('success', 'Maintenance record saved and equipment status updated successfully.');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'status' => 'Operational']);
        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Pump service',
            'status' => 'Completed',
        ]);
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'old_status' => 'Under Maintenance',
            'new_status' => 'Operational',
        ]);
    }

    public function test_under_repair_equipment_can_be_recorded_and_released_from_repair(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Repair');

        $this->actingAs($admin)
            ->get('/repairs')
            ->assertOk()
            ->assertSee('Record repair')
            ->assertSee('equipment-work-record-modal');

        $this->from('/repairs')
            ->post(route('repairs.equipment-work.store', $equipment), [
                '_form' => 'complete-equipment-repair',
                '_equipment_id' => $equipment->id,
                'request_number' => 'REPAIR-STATUS-'.strtoupper(bin2hex(random_bytes(3))),
                'maintenance_type' => 'Pump repair',
                'priority' => 'Critical',
                'status' => 'Completed',
                'equipment_status' => 'Operational with Restrictions',
                'start_date' => today()->toDateString(),
                'completion_date' => today()->toDateString(),
                'technician_name' => 'Repair Technician',
                'reported_issue' => 'Pressure leak',
                'diagnosis' => 'Seal damaged',
                'action_taken' => 'Seal replaced',
            ])
            ->assertRedirect('/repairs')
            ->assertSessionHas('success', 'Repair record saved and equipment status updated successfully.');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'status' => 'Operational with Restrictions']);
        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Pump repair',
            'reported_issue' => 'Pressure leak',
            'status' => 'Completed',
        ]);
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'old_status' => 'Under Repair',
            'new_status' => 'Operational with Restrictions',
        ]);
    }

    private function createEquipment(string $status): Equipment
    {
        return Equipment::query()->create([
            'asset_code' => 'EQ-'.strtoupper(bin2hex(random_bytes(4))),
            'equipment_name' => 'Status test equipment',
            'category' => 'Fire Engine',
            'status' => $status,
            'office_code' => 'TEST-OFFICE',
        ]);
    }
}
