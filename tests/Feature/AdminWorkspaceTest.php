<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
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
            ->assertSee(now()->format('d M Y'))
            ->assertDontSee('Station date')
            ->assertSee('Total Users')
            ->assertSee('Total Equipment')
            ->assertSee('Active Users')
            ->assertSee('Low Priority')
            ->assertSee('Medium Priority')
            ->assertSee('High Priority')
            ->assertSee('Equipment priorities')
            ->assertSee('User management overview')
            ->assertSee('Recent records')
            ->assertSee('Equipment records')
            ->assertSee('Inspections')
            ->assertSee('Maintenance')
            ->assertSee('Repairs')
            ->assertSee('Schedule')
            ->assertSee('Personnel')
            ->assertDontSee('Predictions')
            ->assertDontSee('Accounts')
            ->assertDontSee('Roles &amp; Permissions', false);
    }

    public function test_admin_dashboard_only_lists_records_from_the_admin_office(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $sameOfficeUser = User::factory()->create(['role' => 'maintenance', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $otherOfficeUser = User::factory()->create(['role' => 'maintenance', 'status' => 'active', 'office_code' => 'STA-BANAO']);
        $sameOfficeEquipment = Equipment::create([
            'asset_code' => 'EQ-ADMIN-ANA',
            'equipment_name' => 'Sta Ana Admin Equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);
        $otherOfficeEquipment = Equipment::create([
            'asset_code' => 'EQ-ADMIN-BANAO',
            'equipment_name' => 'Sta Banao Admin Equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'STA-BANAO',
        ]);
        Maintenance::create([
            'equipment_id' => $sameOfficeEquipment->id,
            'request_number' => 'REQ-ADMIN-ANA',
            'maintenance_type' => 'Preventive service',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(5),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sta Ana Admin Equipment')
            ->assertDontSee('Sta Banao Admin Equipment')
            ->assertViewHas('metricEquipment', fn ($records) => $records->contains('asset_code', 'EQ-ADMIN-ANA')
                && ! $records->contains('asset_code', 'EQ-ADMIN-BANAO'))
            ->assertViewHas('metricUsers', fn ($records) => $records->contains('id', $sameOfficeUser->id)
                && ! $records->contains('id', $otherOfficeUser->id));
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
            ->assertDontSee('Predictions')
            ->assertSee('Defect Reports')
            ->assertSee('Audit Log')
            ->assertDontSee('Recommendation service')
            ->assertDontSee('External recommendations')
            ->assertDontSee('Add user')
            ->assertDontSee('Create user account');
    }

    public function test_super_admin_dashboard_shows_schedule_based_equipment_priorities(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-DASH-PRIORITY',
            'equipment_name' => 'Dashboard priority equipment',
            'category' => 'Fire Engine',
            'status' => 'Out of Service',
        ]);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Equipment priorities')
            ->assertSee('Dashboard priority equipment')
            ->assertSee('High priority');
        $response->assertViewHas('priorityCounts', [
            'Low' => 0,
            'Medium' => 0,
            'High' => 1,
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
            ->assertSee('Maintenance overview')
            ->assertDontSee('maintenance-focus-date', false)
            ->assertDontSee('<small>Today</small>', false)
            ->assertSee('My tasks by urgency')
            ->assertSee('Quick actions')
            ->assertSee('Priority of assigned equipment')
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
            ->assertSee('FP-MAINT-001')
            ->assertSee('maintenance-task-scroll')
            ->assertDontSee('Serial ')
            ->assertDontSee('Other Fire Pump')
            ->assertDontSee('Unassigned service');
    }

    public function test_maintenance_dashboard_counts_assigned_inspections_and_saves_update_comments(): void
    {
        $maintenanceUser = User::factory()->create([
            'name' => 'Inspection Maintenance User',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'STA-ANA',
        ]);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-MAINT-DASH-MIXED',
            'equipment_name' => 'Mixed work dashboard equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);
        $maintenance = Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-MIXED-DASHBOARD',
            'maintenance_type' => 'Pump service',
            'assigned_to_id' => $maintenanceUser->id,
            'technician_name' => $maintenanceUser->name,
            'priority' => 'Medium',
            'status' => 'In Progress',
            'start_date' => today(),
        ]);
        $inspection = Inspection::create([
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'High',
            'reference_number' => 'INSP-MIXED-DASHBOARD',
            'inspection_date' => now(),
            'inspector_name' => $maintenanceUser->name,
            'inspection_type' => 'Annual inspection',
            'outcome' => 'Requires Further Assessment',
            'status' => 'In Progress',
        ]);
        Inspection::create([
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'Low',
            'reference_number' => 'INSP-MIXED-COMPLETED',
            'inspection_date' => now()->startOfMonth()->addDays(2),
            'inspector_name' => $maintenanceUser->name,
            'inspection_type' => 'Completed inspection',
            'outcome' => 'Passed',
            'status' => 'Completed',
        ]);
        Inspection::create([
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'Medium',
            'reference_number' => 'INSP-MIXED-OVERDUE',
            'inspection_date' => today()->subDays(2),
            'inspector_name' => $maintenanceUser->name,
            'inspection_type' => 'Overdue inspection',
            'outcome' => 'Requires Further Assessment',
            'status' => 'Pending',
        ]);

        $this->actingAs($maintenanceUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Annual inspection')
            ->assertSee('Inspection')
            ->assertSee('Update comment')
            ->assertViewHas('tasksTodayCount', 2)
            ->assertViewHas('inProgressCount', 2)
            ->assertViewHas('completedThisMonthCount', 1)
            ->assertViewHas('overdueCount', 1)
            ->assertViewHas('todayTasks', fn ($tasks) => $tasks->contains('id', $inspection->id));

        $this->patch(route('inspections.status', $inspection), [
            'status' => 'In Progress',
            'notes' => 'Pressure test is underway.',
        ])->assertRedirect(route('inspections.index'));
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'notes' => 'Pressure test is underway.',
        ]);

        $this->patch(route('maintenances.status', $maintenance), [
            'status' => 'In Progress',
            'notes' => 'Pump inspection started.',
        ])->assertRedirect(route('maintenances.index'));
        $this->assertDatabaseHas('maintenances', [
            'id' => $maintenance->id,
            'notes' => 'Pump inspection started.',
        ]);

        $repair = Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'REQ-MIXED-DASHBOARD-REPAIR',
            'maintenance_type' => 'Pump repair',
            'reported_issue' => 'Pressure leak',
            'assigned_to_id' => $maintenanceUser->id,
            'technician_name' => $maintenanceUser->name,
            'priority' => 'High',
            'status' => 'Scheduled',
            'start_date' => today(),
        ]);

        $this->patch(route('repairs.status', $repair), [
            'status' => 'In Progress',
            'notes' => 'Repair started after inspection.',
        ])->assertRedirect(route('repairs.index'));
        $this->assertDatabaseHas('maintenances', [
            'id' => $repair->id,
            'notes' => 'Repair started after inspection.',
        ]);
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
            'request_number' => 'TAS-'.today()->format('m-d-Y').'-00001',
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
            ->assertSee('Recent records')
            ->assertSee('REQ-TEST-001')
            ->assertSee('Test Fire Pump');

        $this->get('/dashboard?maintenance_tab=completed')
            ->assertOk()
            ->assertViewHas('recentActivity', fn ($activity) => $activity->contains(
                fn (array $record) => str_contains($record['detail'], 'REQ-TEST-001')
            ));
    }

    public function test_admin_can_open_all_screens_shown_in_the_operations_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        foreach (['/repairs', '/schedule', '/personnel', '/defect-reports', '/approvals'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_approves_completed_work_or_returns_it_for_further_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $maintenanceUser = User::factory()->create(['role' => 'maintenance', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-APPROVAL-001',
            'equipment_name' => 'Approval Test Equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);
        $inspection = Inspection::create([
            'equipment_id' => $equipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'High',
            'reference_number' => 'INSP-APPROVAL-001',
            'inspection_date' => now(),
            'inspector_name' => $maintenanceUser->name,
            'inspection_type' => 'Annual inspection',
            'outcome' => 'Requires Further Assessment',
            'status' => 'Completed',
            'approval_status' => 'Pending',
        ]);
        $maintenance = Maintenance::create([
            'equipment_id' => $equipment->id,
            'request_number' => 'MAINT-APPROVAL-001',
            'maintenance_type' => 'Preventive maintenance',
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'Medium',
            'status' => 'Completed',
            'approval_status' => 'Pending',
            'completion_date' => today(),
        ]);
        $otherOfficeEquipment = Equipment::create([
            'asset_code' => 'EQ-APPROVAL-OTHER',
            'equipment_name' => 'Other Office Equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'STA-BANAO',
        ]);
        Inspection::create([
            'equipment_id' => $otherOfficeEquipment->id,
            'reference_number' => 'INSP-APPROVAL-OTHER',
            'inspection_date' => now(),
            'inspector_name' => 'Other Inspector',
            'inspection_type' => 'Annual inspection',
            'outcome' => 'Requires Further Assessment',
            'status' => 'Completed',
            'approval_status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('INSP-APPROVAL-001')
            ->assertSee('MAINT-APPROVAL-001')
            ->assertDontSee('INSP-APPROVAL-OTHER')
            ->assertViewHas('approvalRecords', fn ($records) => $records->count() === 2);

        $this->actingAs($maintenanceUser)
            ->get(route('approvals.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('approvals.update', ['inspection', $inspection->id]), [
                'decision' => 'Requires Further Assessment',
                'approval_notes' => 'Repeat the safety test.',
            ])
            ->assertRedirect(route('approvals.index'));
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'status' => 'In Progress',
            'approval_status' => 'Requires Further Assessment',
            'approval_notes' => 'Repeat the safety test.',
            'reviewed_by_id' => $admin->id,
        ]);

        $this->patch(route('approvals.update', ['maintenance', $maintenance->id]), [
            'decision' => 'Passed',
            'approval_notes' => 'Work verified.',
        ])->assertRedirect(route('approvals.index'));
        $this->assertDatabaseHas('maintenances', [
            'id' => $maintenance->id,
            'status' => 'Completed',
            'approval_status' => 'Passed',
            'approval_notes' => 'Work verified.',
            'reviewed_by_id' => $admin->id,
        ]);
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

    public function test_repairs_page_opens_a_modal_to_create_repair_records(): void
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
            ->assertSee('Add repair')
            ->assertSee('data-modal-open="repair-create-modal"', false)
            ->assertSee('id="repair-create-modal"', false)
            ->assertSee('name="reported_issue"', false)
            ->assertDontSee('href="'.route('maintenances.create', ['type' => 'Repair']).'"', false)
            ->assertDontSee('Create new')
            ->assertSee('Record')
            ->assertSee('Type')
            ->assertSee('Owner')
            ->assertSee('Status')
            ->assertSee('Repair · Pump repair')
            ->assertSee('Test Technician')
            ->assertSee('data-modal-open="repair-record-'.$repair->id.'"', false)
            ->assertSee('Reported issue');

        $this->get(route('maintenances.create', ['type' => 'Repair']))
            ->assertOk()
            ->assertSee('value="Repair"', false);
    }

    public function test_repair_modal_submission_creates_repair_and_returns_to_repairs_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        $equipment = Equipment::create([
            'asset_code' => 'FP-REPAIR-MODAL-001',
            'equipment_name' => 'Modal Repair Pump',
            'category' => 'Pump',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);

        $this->actingAs($admin)
            ->post(route('maintenances.store'), [
                '_form' => 'create-repair',
                'equipment_id' => $equipment->id,
                'maintenance_type' => 'Pump repair',
                'priority' => 'High',
                'status' => 'Pending',
                'reported_issue' => 'Pump is leaking.',
            ])
            ->assertRedirect(route('repairs.index'))
            ->assertSessionHas('success', 'Repair record saved.');

        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'request_number' => 'MOD-'.today()->format('m-d-Y').'-00001',
            'maintenance_type' => 'Pump repair',
            'reported_issue' => 'Pump is leaking.',
        ]);
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
