<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeCodeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_assigns_office_codes_and_super_admin_accounts_remain_unassigned(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'office_code' => null,
        ]);

        $this->actingAs($superAdmin)->post('/users', [
            'name' => 'Office Admin',
            'email' => 'office-admin@example.test',
            'role' => 'admin',
            'office_code' => 'ana-01',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'email' => 'office-admin@example.test',
            'role' => 'admin',
            'office_code' => 'ANA-01',
        ]);

        $this->actingAs($superAdmin)->post('/users', [
            'name' => 'Second Super Admin',
            'email' => 'second-super@example.test',
            'role' => 'super_admin',
            'office_code' => 'SHOULD-NOT-BE-USED',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'email' => 'second-super@example.test',
            'role' => 'super_admin',
            'office_code' => null,
        ]);
    }

    public function test_users_without_an_office_code_see_a_session_scoped_assignment_notice(): void
    {
        $userWithoutOffice = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => null,
        ]);
        $userWithOffice = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'office_code' => null,
        ]);

        $this->actingAs($userWithoutOffice)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dialog')
            ->assertSee('Office assignment pending')
            ->assertSee('Please wait for a Super Admin to assign your office')
            ->assertSee('data-office-warning="'.$userWithoutOffice->id.'" data-open-on-load="true"', false)
            ->assertSee('method="POST" action="'.route('logout').'"', false)
            ->assertSee('Okay, I&rsquo;ll wait', false);

        $this->actingAs($userWithOffice)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Office assignment pending');

        $this->actingAs($superAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Office assignment pending');
    }

    public function test_personnel_equipment_and_work_records_are_limited_to_the_signed_in_office(): void
    {
        $admin = User::factory()->create([
            'name' => 'Ana Admin',
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $otherAdmin = User::factory()->create([
            'name' => 'Other Office Admin',
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'OTHER-02',
        ]);
        $superAdmin = User::factory()->create([
            'name' => 'Global Super Admin',
            'role' => 'super_admin',
            'status' => 'active',
            'office_code' => null,
        ]);
        $ownEquipment = $this->equipment('ANA-PUMP-01', 'ANA-01');
        $otherEquipment = $this->equipment('OTHER-PUMP-01', 'OTHER-02');

        Inspection::create([
            'equipment_id' => $ownEquipment->id,
            'reference_number' => 'ANA-INSP-01',
            'inspection_date' => now(),
            'inspector_name' => 'Ana Admin',
            'inspection_type' => 'Routine',
            'defects_identified' => 'Hose coupling worn',
            'outcome' => 'Failed',
        ]);
        Inspection::create([
            'equipment_id' => $otherEquipment->id,
            'reference_number' => 'OTHER-INSP-01',
            'inspection_date' => now(),
            'inspector_name' => 'Other Office Admin',
            'inspection_type' => 'Routine',
            'defects_identified' => 'Pump leak',
            'outcome' => 'Failed',
        ]);
        Maintenance::create([
            'equipment_id' => $ownEquipment->id,
            'request_number' => 'ANA-MAINT-01',
            'maintenance_type' => 'ANA Preventive',
            'priority' => 'Medium',
            'status' => 'Pending',
        ]);
        Maintenance::create([
            'equipment_id' => $otherEquipment->id,
            'request_number' => 'OTHER-MAINT-01',
            'maintenance_type' => 'Other Preventive',
            'priority' => 'Medium',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->get('/personnel')
            ->assertOk()
            ->assertSee('Ana Admin')
            ->assertDontSee('Other Office Admin')
            ->assertDontSee('Global Super Admin');

        $this->get('/equipment')
            ->assertOk()
            ->assertSee('ANA-PUMP-01')
            ->assertDontSee('OTHER-PUMP-01');

        $this->get('/inspections')
            ->assertOk()
            ->assertSee('ANA-INSP-01')
            ->assertDontSee('OTHER-INSP-01');

        $this->get('/maintenances')
            ->assertOk()
            ->assertSee('ANA Preventive')
            ->assertDontSee('Other Preventive')
            ->assertSee('ANA-PUMP-01')
            ->assertDontSee('OTHER-PUMP-01');

        $this->get('/defect-reports')
            ->assertOk()
            ->assertSee('Hose coupling worn')
            ->assertDontSee('Pump leak');

        $this->get('/reports?report=equipment-registry&period=all')
            ->assertOk()
            ->assertSee('ANA-PUMP-01')
            ->assertDontSee('OTHER-PUMP-01');

        $this->get('/equipment/'.$otherEquipment->id)->assertNotFound();

        $this->post('/inspections', [
            'equipment_id' => $otherEquipment->id,
            'reference_number' => 'CROSS-OFFICE-INSPECTION',
            'inspection_date' => now()->format('Y-m-d\TH:i'),
            'inspector_name' => 'Ana Admin',
            'inspection_type' => 'Routine',
            'outcome' => 'Passed',
        ])->assertSessionHasErrors('equipment_id');
    }

    public function test_admin_cannot_assign_equipment_to_another_office_or_override_its_own_code(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $otherPersonnel = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'OTHER-02',
        ]);

        $this->actingAs($admin)->from('/equipment')->post('/equipment', [
            'equipment_name' => 'Invalid cross-office equipment',
            'category' => 'Portable Water Pump',
            'office_code' => 'OTHER-02',
            'status' => 'Operational',
            'assigned_personnel' => $otherPersonnel->id,
        ])->assertRedirect('/equipment')
            ->assertSessionHasErrors('assigned_personnel');

        $this->post('/equipment', [
            'equipment_name' => 'Office-owned equipment',
            'category' => 'Portable Water Pump',
            'office_code' => 'OTHER-02',
            'status' => 'Operational',
        ])->assertRedirect('/equipment');

        $this->assertDatabaseHas('equipment', [
            'equipment_name' => 'Office-owned equipment',
            'office_code' => 'ANA-01',
        ]);
    }

    public function test_admin_can_assign_equipment_to_personnel_in_the_same_office(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $personnel = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);

        $this->actingAs($admin)
            ->get('/equipment')
            ->assertOk()
            ->assertSee('data-equipment-office-code', false)
            ->assertSee('data-equipment-personnel-select', false)
            ->assertSee('data-office-code="ANA-01"', false);

        $this->post('/equipment', [
            'equipment_name' => 'Assigned office equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'assigned_personnel' => $personnel->id,
        ])->assertRedirect('/equipment')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipment', [
            'equipment_name' => 'Assigned office equipment',
            'office_code' => 'ANA-01',
            'assigned_personnel' => $personnel->id,
        ]);
    }

    public function test_super_admin_can_choose_personnel_before_entering_the_equipment_office_code(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'office_code' => null,
        ]);
        $personnel = User::factory()->create([
            'name' => 'Selectable Personnel',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);

        $this->actingAs($superAdmin)
            ->get('/equipment')
            ->assertOk()
            ->assertSee('Selectable Personnel')
            ->assertSee('Select a person to automatically set the equipment office code.');

        $this->post('/equipment', [
            'equipment_name' => 'Super Admin Assigned Equipment',
            'category' => 'Fire Engine',
            'office_code' => 'ANA-01',
            'status' => 'Operational',
            'assigned_personnel' => $personnel->id,
        ])->assertRedirect('/equipment')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipment', [
            'equipment_name' => 'Super Admin Assigned Equipment',
            'office_code' => 'ANA-01',
            'assigned_personnel' => $personnel->id,
        ]);
    }

    public function test_admin_cannot_assign_a_maintenance_task_to_personnel_from_another_office(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $otherPersonnel = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'OTHER-02',
        ]);
        $equipment = $this->equipment('ANA-ASSIGNMENT-PUMP', 'ANA-01');

        $this->actingAs($admin)->post('/maintenances', [
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $otherPersonnel->id,
            'request_number' => 'CROSS-OFFICE-MAINTENANCE',
            'maintenance_type' => 'Preventive',
            'priority' => 'Medium',
            'status' => 'Scheduled',
        ])->assertSessionHasErrors('assigned_to_id');

        $this->assertDatabaseMissing('maintenances', [
            'request_number' => 'CROSS-OFFICE-MAINTENANCE',
        ]);
    }

    public function test_changing_a_users_office_clears_equipment_assignment_from_the_previous_office(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'office_code' => null,
        ]);
        $personnel = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'ANA-01',
        ]);
        $equipment = $this->equipment('ASSIGNED-PUMP-01', 'ANA-01');
        $equipment->update(['assigned_personnel' => $personnel->id]);

        $this->actingAs($superAdmin)->post('/users/'.$personnel->id.'/role', [
            'role' => 'maintenance',
            'office_code' => 'other-02',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $personnel->id,
            'office_code' => 'OTHER-02',
        ]);
        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'office_code' => 'ANA-01',
            'assigned_personnel' => null,
        ]);
    }

    private function equipment(string $code, string $officeCode): Equipment
    {
        return Equipment::create([
            'asset_code' => $code,
            'equipment_name' => $code,
            'category' => 'Pump',
            'status' => 'Operational',
            'office_code' => $officeCode,
        ]);
    }
}
