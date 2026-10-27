<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentWorkRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_modal_links_to_a_page_with_its_inspections_maintenance_and_repairs(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $equipment = $this->equipment('History Pump');
        $unrelatedEquipment = $this->equipment('Other Pump');

        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'HIS-10-26-2026-00001',
            'inspection_date' => now(),
            'inspector_name' => $admin->name,
            'inspection_type' => 'Annual inspection',
            'outcome' => 'Passed',
        ]);
        $maintenance = $this->maintenance($equipment, 'HIS-10-26-2026-00002', 'Preventive service');
        $repair = $this->maintenance($equipment, 'HIS-10-26-2026-00003', 'Repair', 'Hydraulic leak');
        $this->maintenance($unrelatedEquipment, 'OTH-10-26-2026-00001', 'Preventive service');

        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('View all records')
            ->assertSee(route('equipment.records', $equipment));

        $this->get(route('inspections.index'))
            ->assertOk()
            ->assertSee('data-modal-open="inspection-record-'.$inspection->id.'"', false)
            ->assertSee('id="inspection-record-'.$inspection->id.'"', false);

        $this->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee('data-modal-open="maintenance-record-'.$maintenance->id.'"', false)
            ->assertSee('id="maintenance-record-'.$maintenance->id.'"', false);

        $this->get(route('repairs.index'))
            ->assertOk()
            ->assertSee('data-modal-open="repair-record-'.$repair->id.'"', false)
            ->assertSee('id="repair-record-'.$repair->id.'"', false);

        $this->get(route('equipment.records', $equipment))
            ->assertOk()
            ->assertSee($equipment->equipment_name)
            ->assertSee($inspection->reference_number)
            ->assertSee($maintenance->request_number)
            ->assertSee($repair->request_number)
            ->assertSee('data-modal-open="equipment-record-inspection-'.$inspection->id.'"', false)
            ->assertSee('data-modal-open="equipment-record-maintenance-'.$maintenance->id.'"', false)
            ->assertSee('data-modal-open="equipment-record-repair-'.$repair->id.'"', false)
            ->assertDontSee('OTH-10-26-2026-00001');

        $searchResponse = $this->get(route('search', ['q' => 'History Pump']));
        $searchResponse->assertOk()
            ->assertSee($inspection->reference_number)
            ->assertSee($maintenance->request_number)
            ->assertSee($repair->request_number);
        $this->assertSame(
            4,
            substr_count($searchResponse->getContent(), route('equipment.records', $equipment))
        );
    }

    public function test_pending_inspection_maintenance_and_repair_records_can_update_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $equipment = $this->equipment('Pending Work Pump');
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'PEN-10-26-2026-00001',
            'inspection_date' => now(),
            'inspector_name' => $admin->name,
            'inspection_type' => 'Operational check',
            'outcome' => 'Passed',
            'status' => 'Pending',
        ]);
        $maintenance = $this->maintenance($equipment, 'PEN-10-26-2026-00002', 'Preventive service', status: 'Pending');
        $repair = $this->maintenance($equipment, 'PEN-10-26-2026-00003', 'Pump repair', 'Leaking hose', 'Pending');

        $this->actingAs($admin)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertSee('inspection-status-'.$inspection->id)
            ->assertSee('data-modal-open="inspection-status-'.$inspection->id.'"', false);
        $this->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee('maintenance-status-'.$maintenance->id)
            ->assertSee('data-modal-open="maintenance-status-'.$maintenance->id.'"', false);
        $this->get(route('repairs.index'))
            ->assertOk()
            ->assertSee('repair-status-'.$repair->id)
            ->assertSee('data-modal-open="repair-status-'.$repair->id.'"', false);

        $this->patch(route('inspections.status', $inspection), ['status' => 'Completed'])
            ->assertRedirect(route('inspections.index'));
        $this->patch(route('maintenances.status', $maintenance), ['status' => 'In Progress'])
            ->assertRedirect(route('maintenances.index'));
        $this->patch(route('repairs.status', $repair), ['status' => 'Approved'])
            ->assertRedirect(route('repairs.index'));

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'status' => 'Completed']);
        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id, 'status' => 'In Progress']);
        $this->assertDatabaseHas('maintenances', ['id' => $repair->id, 'status' => 'Approved']);
    }

    private function equipment(string $name): Equipment
    {
        return Equipment::query()->create([
            'asset_code' => 'EQ-'.str_replace(' ', '-', strtoupper($name)),
            'equipment_name' => $name,
            'category' => 'Mechanical',
            'status' => 'Operational',
            'office_code' => 'TEST-OFFICE',
        ]);
    }

    private function maintenance(
        Equipment $equipment,
        string $reference,
        string $type,
        ?string $issue = null,
        string $status = 'Completed'
    ): Maintenance
    {
        return Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => $reference,
            'maintenance_type' => $type,
            'priority' => 'Medium',
            'status' => $status,
            'reported_issue' => $issue,
        ]);
    }
}
