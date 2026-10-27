<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_links_to_features_available_to_the_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('id="account-settings"', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('personnel.index').'"', false)
            ->assertDontSee('Risk model')
            ->assertSee('href="'.route('reports.index').'"', false)
            ->assertSee('Notifications')
            ->assertSee('Not configured')
            ->assertDontSee('Clear operational data')
            ->assertSee('database backups are not available yet');
    }

    public function test_super_admin_settings_links_to_role_management(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('href="'.route('roles.permissions').'"', false)
            ->assertSee('Clear operational data')
            ->assertSee('User &amp; Role', false);
    }

    public function test_super_admin_can_clear_operational_data_with_password_without_deleting_users(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('safe-password'),
        ]);
        $otherUser = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = Equipment::query()->create([
            'asset_code' => 'CLEAR-TEST-01',
            'equipment_name' => 'Reset test equipment',
            'category' => 'Mechanical',
            'status' => 'Operational',
        ]);
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'CLEAR-INSP-01',
            'inspection_date' => now(),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
            'outcome' => 'Passed',
        ]);
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'CLEAR-MAINT-01',
            'maintenance_type' => 'Repair',
            'priority' => 'Medium',
            'status' => 'Scheduled',
        ]);
        DB::table('equipment_status_histories')->insert([
            'equipment_id' => $equipment->id,
            'old_status' => null,
            'new_status' => 'Operational',
            'changed_by' => $superAdmin->id,
            'changed_at' => now(),
        ]);
        DB::table('risk_predictions')->insert([
            'equipment_id' => $equipment->id,
            'prediction_label' => 'Low',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('record_reference_sequences')->insert([
            'prefix' => 'RES',
            'sequence_date' => today()->toDateString(),
            'last_number' => 1,
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('settings.clear-operational-data'), [
                '_form' => 'clear-operational-data',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
        $this->assertDatabaseHas('inspections', ['id' => $inspection->id]);
        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id]);

        $this->delete(route('settings.clear-operational-data'), [
            '_form' => 'clear-operational-data',
            'password' => 'safe-password',
        ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'operational-data-cleared');

        foreach ([
            'equipment',
            'inspections',
            'maintenances',
            'equipment_status_histories',
            'risk_predictions',
            'record_reference_sequences',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_non_super_admin_cannot_clear_operational_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->delete(route('settings.clear-operational-data'), [
                '_form' => 'clear-operational-data',
                'password' => 'password',
            ])
            ->assertForbidden();
    }

    public function test_account_settings_form_saves_and_returns_to_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->patch(route('profile.update'), [
                'name' => 'Updated Settings User',
                'email' => $admin->email,
                'return_to' => 'settings.index',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'profile-updated');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Updated Settings User',
        ]);
    }
}
