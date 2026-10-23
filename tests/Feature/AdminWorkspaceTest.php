<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Maintenance;
use App\Models\RiskPrediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_the_operations_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('class="app-sidebar"', false)
            ->assertSee('class="app-topbar"', false)
            ->assertSee('Equipment readiness')
            ->assertSee('Equipment records')
            ->assertSee('Inspections')
            ->assertSee('Maintenance')
            ->assertSee('Repairs')
            ->assertSee('Schedule')
            ->assertSee('Personnel')
            ->assertSee('Predictions')
            ->assertSee('Equipment status')
            ->assertSee('Risk overview')
            ->assertSee('Risk follow-up')
            ->assertSee('Maintenance monitoring')
            ->assertSee('Pending approvals')
            ->assertSee('Due Today')
            ->assertSee('Predictions')
            ->assertSee('Defect Reports')
            ->assertDontSee('Recommendation service')
            ->assertDontSee('External recommendations')
            ->assertDontSee('Accounts')
            ->assertDontSee('Roles &amp; Permissions', false);
    }

    public function test_super_admin_sidebar_groups_the_requested_sections(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Equipment records')
            ->assertSee('Inspections')
            ->assertSee('Maintenance')
            ->assertSee('Repairs')
            ->assertSee('Schedule')
            ->assertSee('Accounts')
            ->assertSee('Personnel')
            ->assertSee('Roles &amp; Permissions', false)
            ->assertSee('Reports')
            ->assertSee('Predictions')
            ->assertSee('Defect Reports')
            ->assertSee('Audit Log')
            ->assertDontSee('Recommendation service')
            ->assertDontSee('External recommendations')
            ->assertDontSee('Add user')
            ->assertDontSee('Create user account');
    }

    public function test_super_admin_dashboard_shows_latest_equipment_risk_levels(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-DASH-RISK',
            'equipment_name' => 'Dashboard risk equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
        ]);
        RiskPrediction::create([
            'equipment_id' => $equipment->id,
            'prediction_label' => 'Low Risk',
            'confidence' => 0.8,
            'prediction_timestamp' => now()->subDay(),
        ]);
        RiskPrediction::create([
            'equipment_id' => $equipment->id,
            'prediction_label' => 'High Risk',
            'confidence' => 0.9,
            'prediction_timestamp' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Equipment risk levels')
            ->assertSee('Dashboard risk equipment')
            ->assertSee('High Risk')
            ->assertSee('Low Risk');
        $response->assertViewHas('riskLevels', [
            'Low Risk' => 0,
            'Medium Risk' => 0,
            'High Risk' => 1,
        ]);
    }

    public function test_super_admin_dashboard_counts_repairs_and_overdue_maintenance_accurately(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-DASH-METRICS',
            'equipment_name' => 'Dashboard metrics equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
        ]);
        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-DASH-OLD-MAINT',
            'maintenance_type' => 'Preventive service',
            'status' => 'Pending',
            'start_date' => today()->subDay(),
        ]);
        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-DASH-REPAIR',
            'maintenance_type' => 'Pump repair',
            'status' => 'Completed',
            'completion_date' => today(),
        ]);
        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-DASH-COMPLETE-MAINT',
            'maintenance_type' => 'Preventive service',
            'status' => 'Completed',
            'completion_date' => today(),
        ]);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertOk()
            ->assertViewHas('repairCount', 1)
            ->assertViewHas('overdueMaintenanceCount', 1);
    }

    public function test_super_admin_pages_use_the_shared_application_shell(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        foreach ([
            '/dashboard' => 'System-wide status',
            '/users' => 'User Management',
            '/roles-permissions' => 'Roles &amp; permissions',
        ] as $path => $content) {
            $this->actingAs($superAdmin)
                ->get($path)
                ->assertOk()
                ->assertSee('class="app-sidebar"', false)
                ->assertSee('class="app-topbar"', false)
                ->assertSee($content, false);
        }
    }

    public function test_super_admin_can_assign_a_role_to_a_pending_registered_user(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $pendingUser = User::factory()->create([
            'role' => 'pending',
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin)
            ->get('/users')
            ->assertOk()
            ->assertSee('Pending approval')
            ->assertSee('Assign a role');

        $this->post(route('users.role', $pendingUser), [
            'role' => 'admin',
            'office_code' => 'STA-ANA',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $pendingUser->id,
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
    }

    public function test_super_admin_can_delete_another_account_after_password_confirmation(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $user = User::factory()->create(['role' => 'maintenance', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->get('/users')
            ->assertOk()
            ->assertSee('delete-user-'.$user->id)
            ->assertSee('Your Super Admin password');

        $this->delete(route('users.destroy', $user), [
            '_form' => 'delete-user',
            'user_id' => $user->id,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->delete(route('users.destroy', $user), [
            '_form' => 'delete-user',
            'user_id' => $user->id,
            'password' => 'super-secret',
        ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'User account deleted.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $superAdmin), [
                '_form' => 'delete-user',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_maintenance_dashboard_uses_the_shared_application_shell(): void
    {
        $maintenanceUser = User::factory()->create(['role' => 'maintenance', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $this->actingAs($maintenanceUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('class="app-sidebar"', false)
            ->assertSee('class="app-topbar"', false)
            ->assertSee('My tasks today')
            ->assertSee('Quick actions')
            ->assertSee('Risk on assigned equipment')
            ->assertSee('My upcoming schedule')
            ->assertDontSee('Recommendation service')
            ->assertDontSee('External recommendations');
    }

    public function test_maintenance_dashboard_lists_only_work_assigned_to_the_user(): void
    {
        $maintenanceUser = User::factory()->create([
            'name' => 'Taylor Technician',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Morgan Technician',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
        $assignedEquipment = Equipment::create([
            'asset_code' => 'FP-MAINT-001',
            'equipment_name' => 'Assigned Fire Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'assigned_personnel' => $maintenanceUser->id,
            'office_code' => 'STA-ANA',
        ]);
        $otherEquipment = Equipment::create([
            'asset_code' => 'FP-MAINT-002',
            'equipment_name' => 'Other Fire Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'assigned_personnel' => $otherUser->id,
            'office_code' => 'STA-ANA',
        ]);
        Maintenance::create([
            'equipment_id' => $assignedEquipment->id,
            'request_number' => 'REQ-MAINT-001',
            'maintenance_type' => 'Preventive service',
            'priority' => 'High',
            'status' => 'In Progress',
            'start_date' => today(),
            'technician_name' => $maintenanceUser->name,
        ]);
        Maintenance::create([
            'equipment_id' => $otherEquipment->id,
            'request_number' => 'REQ-MAINT-002',
            'maintenance_type' => 'Unassigned service',
            'priority' => 'Medium',
            'status' => 'Pending',
            'start_date' => today(),
            'technician_name' => $otherUser->name,
        ]);

        $this->actingAs($maintenanceUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Assigned Fire Pump')
            ->assertSee('Preventive service')
            ->assertDontSee('Other Fire Pump')
            ->assertDontSee('Unassigned service');
    }

    public function test_maintenance_tasks_can_be_assigned_to_a_personnel_user_and_show_on_their_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
        $maintenanceUser = User::factory()->create([
            'name' => 'Assigned Maintenance User',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
        $equipment = Equipment::create([
            'asset_code' => 'FP-ASSIGN-001',
            'equipment_name' => 'Task Assignment Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'assigned_personnel' => $maintenanceUser->id,
            'office_code' => 'STA-ANA',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.create'))
            ->assertOk()
            ->assertSee('Who will do the job?')
            ->assertSee('data-assigned-personnel-id="'.$maintenanceUser->id.'"', false);

        $this->post(route('maintenances.store'), [
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'request_number' => 'REQ-ASSIGNED-001',
            'maintenance_type' => 'Scheduled pump service',
            'priority' => 'High',
            'status' => 'Scheduled',
            'start_date' => today()->toDateString(),
        ])->assertRedirect(route('maintenances.index'));

        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'request_number' => 'REQ-ASSIGNED-001',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee('Assigned Maintenance User')
            ->assertSee('Scheduled pump service');

        $this->actingAs($maintenanceUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Task Assignment Pump')
            ->assertSee('Scheduled pump service');
    }

    public function test_dashboard_monitoring_lists_pending_work_and_completed_tab_loads(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $equipment = Equipment::create([
            'asset_code' => 'FP-TEST-001',
            'equipment_name' => 'Test Fire Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);
        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-TEST-001',
            'maintenance_type' => 'Preventive service',
            'priority' => 'High',
            'status' => 'Pending',
            'technician_name' => 'Test Technician',
            'start_date' => today(),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Test Fire Pump')
            ->assertSee('Preventive service')
            ->assertSee('Pending');

        $this->get('/dashboard?maintenance_tab=completed')
            ->assertOk()
            ->assertSee('No completed maintenance records yet.');
    }

    public function test_admin_can_open_all_screens_shown_in_the_operations_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        foreach (['/repairs', '/schedule', '/personnel', '/defect-reports', '/approvals'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_schedule_includes_maintenance_and_repairs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $equipment = Equipment::create([
            'asset_code' => 'FP-SCHEDULE-001',
            'equipment_name' => 'Schedule Test Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);

        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'MAINT-SCHEDULE-001',
            'maintenance_type' => 'Preventive service',
            'priority' => 'Medium',
            'status' => 'Scheduled',
            'start_date' => today()->addDay(),
        ]);
        Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REPAIR-SCHEDULE-001',
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Pending',
            'reported_issue' => 'Pressure leak',
            'start_date' => today()->addDays(2),
        ]);

        $this->actingAs($admin)
            ->get('/schedule')
            ->assertOk()
            ->assertSee('Maintenance · Preventive service')
            ->assertSee('Repair · Pump repair')
            ->assertSee('MAINT-SCHEDULE-001')
            ->assertSee('REPAIR-SCHEDULE-001');
    }

    public function test_repairs_page_matches_record_list_and_links_to_repair_creation_and_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $equipment = Equipment::create([
            'asset_code' => 'FP-REPAIR-001',
            'equipment_name' => 'Repair Test Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);
        $repair = Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REPAIR-LIST-001',
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Pending',
            'technician_name' => 'Test Technician',
            'start_date' => today(),
        ]);

        $this->actingAs($admin)
            ->get('/repairs')
            ->assertOk()
            ->assertSee('Add new')
            ->assertDontSee('Create new')
            ->assertSee('Record')
            ->assertSee('Type')
            ->assertSee('Owner')
            ->assertSee('Status')
            ->assertSee('Repair · Pump repair')
            ->assertSee('Test Technician')
            ->assertSee(route('maintenances.show', $repair));

        $this->get(route('maintenances.create', ['type' => 'Repair']))
            ->assertOk()
            ->assertSee('value="Repair"', false);
    }

    public function test_super_admin_can_change_role_module_access_and_changes_are_enforced(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $this->actingAs($superAdmin)
            ->get('/roles-permissions')
            ->assertOk()
            ->assertSee('Save permissions')
            ->assertSee('Allow access')
            ->assertSee('Always allowed')
            ->assertSee('Super Admin only');

        $this->post('/roles-permissions', [
            'permissions' => [
                'admin' => ['equipment' => '0', 'schedule' => '1'],
                'maintenance' => ['equipment' => '1'],
            ],
        ])->assertRedirect('/roles-permissions')
            ->assertSessionHas('success');

        $this->actingAs($admin)->get('/equipment')->assertForbidden();
        $this->get('/schedule')->assertOk();
    }

    public function test_admin_cannot_change_role_permissions_or_access_super_admin_modules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $this->actingAs($admin)
            ->get('/roles-permissions')
            ->assertForbidden();

        $this->post('/roles-permissions', ['permissions' => ['admin' => ['equipment' => '0']]])
            ->assertForbidden();

        $this->get('/users')->assertForbidden();
    }
}
