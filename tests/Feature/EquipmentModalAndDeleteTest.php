<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EquipmentModalAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_list_uses_view_and_edit_modals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment();

        $this->actingAs($admin)
            ->get('/equipment')
            ->assertOk()
            ->assertSee('equipment-view-'.$equipment->id)
            ->assertSee('equipment-edit-'.$equipment->id)
            ->assertSee('data-modal-open="equipment-view-'.$equipment->id.'"', false)
            ->assertSee('data-modal-open="equipment-edit-'.$equipment->id.'"', false)
            ->assertSee('Date Acquired')
            ->assertDontSee('Commission date')
            ->assertDontSee('Engine operating hours')
            ->assertDontSee('Service age (years)')
            ->assertDontSee('Runtime cycles')
            ->assertDontSee('Mechanical failure count')
            ->assertDontSee('equipment-delete-'.$equipment->id);
    }

    public function test_create_and_edit_forms_use_the_same_equipment_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment();
        $fields = [
            'equipment_name',
            'category',
            'asset_tag',
            'serial_number',
            'manufacturer',
            'model',
            'status',
            'location',
            'commission_date',
            'description',
        ];

        $this->actingAs($admin);
        $createResponse = $this->get(route('equipment.create'))->assertOk();
        $editResponse = $this->get(route('equipment.edit', $equipment))->assertOk();

        foreach ($fields as $field) {
            $createResponse->assertSee('name="'.$field.'"', false);
            $editResponse->assertSee('name="'.$field.'"', false);
        }
        $createResponse->assertDontSee('name="assigned_personnel"', false);
        $editResponse->assertSee('name="assigned_personnel"', false);
        foreach (['last_inspection_date', 'next_inspection_date', 'last_maintenance_date', 'maintenance_interval_days', 'remarks'] as $field) {
            $createResponse->assertDontSee('name="'.$field.'"', false);
            $editResponse->assertDontSee('name="'.$field.'"', false);
        }
        $editResponse
            ->assertSee('Date Acquired')
            ->assertDontSee('Commission date')
            ->assertDontSee('Service age (years)')
            ->assertDontSee('Engine operating hours')
            ->assertDontSee('Runtime cycles')
            ->assertDontSee('Mechanical failure count');
    }

    public function test_out_of_service_and_retired_equipment_are_only_shown_in_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $activeEquipment = $this->createEquipment();
        $outOfServiceEquipment = Equipment::query()->create([
            'asset_code' => 'EQ-ARCHIVE-OOS',
            'equipment_name' => 'Out of service equipment',
            'category' => 'Mechanical',
            'status' => 'Out of Service',
            'office_code' => 'TEST-OFFICE',
        ]);
        $retiredEquipment = Equipment::query()->create([
            'asset_code' => 'EQ-ARCHIVE-RETIRED',
            'equipment_name' => 'Retired equipment',
            'category' => 'Mechanical',
            'status' => 'Retired',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('All equipment')
            ->assertSee('Archive (2)')
            ->assertSee($activeEquipment->equipment_name)
            ->assertDontSee('equipment-view-'.$outOfServiceEquipment->id)
            ->assertDontSee('equipment-view-'.$retiredEquipment->id);

        $this->get(route('equipment.index', ['section' => 'archive']))
            ->assertOk()
            ->assertSee($outOfServiceEquipment->equipment_name)
            ->assertSee($retiredEquipment->equipment_name)
            ->assertDontSee('equipment-view-'.$activeEquipment->id);
    }

    public function test_maintenance_users_can_edit_operational_equipment_fields_only(): void
    {
        $maintenanceUser = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $equipment = $this->createEquipment();
        $csv = UploadedFile::fake()->createWithContent(
            'equipment.csv',
            "equipment_name,category\nImported pump,Fire Engine\n"
        );

        $this->actingAs($maintenanceUser)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('equipment-view-'.$equipment->id)
            ->assertDontSee('Import equipment from CSV')
            ->assertDontSee('Download template')
            ->assertDontSee('equipment-create-modal')
            ->assertSee('equipment-edit-'.$equipment->id)
            ->assertSee('data-modal-open="equipment-edit-'.$equipment->id.'"', false)
            ->assertSee('Assigned personnel')
            ->assertDontSee('Engine operating hours')
            ->assertDontSee('Service age (years)')
            ->assertDontSee('Runtime cycles')
            ->assertDontSee('Mechanical failure count')
            ->assertSee('Date Acquired')
            ->assertDontSee('id="edit-name-'.$equipment->id.'"', false)
            ->assertDontSee('id="edit-category-'.$equipment->id.'"', false)
            ->assertDontSee('id="edit-status-'.$equipment->id.'"', false)
            ->assertDontSee('id="edit-location-'.$equipment->id.'"', false)
            ->assertDontSee('name="assigned_personnel"', false);

        $this->get(route('equipment.import-template'))->assertForbidden();
        $this->post(route('equipment.import'), ['file' => $csv])->assertForbidden();
        $this->get(route('equipment.create'))->assertForbidden();
        $this->post(route('equipment.store'), [])->assertForbidden();
        $this->get(route('equipment.edit', $equipment))->assertForbidden();
        $this->put(route('equipment.update', $equipment), [
            'assigned_personnel' => $maintenanceUser->id,
            'engine_operating_hours' => 999,
            'equipment_name' => 'Unauthorized name change',
            'category' => 'Unauthorized category change',
            'status' => 'Retired',
            'location' => 'Unauthorized location change',
        ])->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success', 'Equipment updated successfully.');

        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Imported pump']);
        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'equipment_name' => 'Test station equipment',
            'category' => 'Mechanical',
            'status' => 'Operational',
            'location' => null,
            'assigned_personnel' => null,
            'engine_operating_hours' => 125,
        ]);
    }

    public function test_only_super_admin_can_open_delete_controls_and_delete_equipment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment();

        $this->actingAs($admin)
            ->get('/equipment')
            ->assertDontSee('equipment-delete-'.$equipment->id);
        $this->delete(route('equipment.destroy', $equipment), [
            '_form' => 'delete-equipment',
            'password' => 'password',
        ])->assertForbidden();
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $this->actingAs($superAdmin)
            ->get('/equipment')
            ->assertSee('equipment-delete-'.$equipment->id);
    }

    public function test_super_admin_must_confirm_their_password_to_delete_equipment(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $equipment = $this->createEquipment();

        $this->actingAs($superAdmin)
            ->from('/equipment')
            ->delete(route('equipment.destroy', $equipment), [
                '_form' => 'delete-equipment',
                '_equipment_id' => $equipment->id,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/equipment')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
    }

    public function test_super_admin_can_delete_equipment_after_password_confirmation(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $equipment = $this->createEquipment();

        $this->actingAs($superAdmin)
            ->delete(route('equipment.destroy', $equipment), [
                '_form' => 'delete-equipment',
                '_equipment_id' => $equipment->id,
                'password' => 'super-secret',
            ])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success', 'Equipment removed successfully.');

        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
    }

    public function test_deleting_equipment_removes_its_inspections_maintenance_repairs_and_schedule_sources(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $equipment = $this->createEquipment();
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'DELETE-INSP-01',
            'inspection_date' => now(),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
        ]);
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'DELETE-MAINT-01',
            'maintenance_type' => 'Preventive service',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(5),
        ]);
        $repair = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'DELETE-REPAIR-01',
            'maintenance_type' => 'Pump repair',
            'reported_issue' => 'Pressure leak',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(7),
        ]);
        $history = EquipmentStatusHistory::query()->create([
            'equipment_id' => $equipment->id,
            'new_status' => 'Operational',
            'reason' => 'Test record',
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('equipment.destroy', $equipment), [
                '_form' => 'delete-equipment',
                'password' => 'super-secret',
            ])
            ->assertRedirect(route('equipment.index'));

        $this->assertDatabaseMissing('inspections', ['id' => $inspection->id]);
        $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id]);
        $this->assertDatabaseMissing('maintenances', ['id' => $repair->id]);
        $this->assertDatabaseMissing('equipment_status_histories', ['id' => $history->id]);

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('No scheduled work is available.')
            ->assertDontSee('DELETE-MAINT-01')
            ->assertDontSee('DELETE-REPAIR-01')
            ->assertDontSee('DELETE-INSP-01');
    }

    private function createEquipment(): Equipment
    {
        return Equipment::query()->create([
            'asset_code' => 'EQ-'.strtoupper(bin2hex(random_bytes(4))),
            'equipment_name' => 'Test station equipment',
            'category' => 'Mechanical',
            'status' => 'Operational',
            'office_code' => 'TEST-OFFICE',
            'engine_operating_hours' => 125,
        ]);
    }
}
