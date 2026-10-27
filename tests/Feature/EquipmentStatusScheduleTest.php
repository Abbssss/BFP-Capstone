<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Services\ReportDataService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentStatusScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_options_follow_role_and_office(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $officeMaintenance = User::factory()->create([
            'name' => 'Office Inspector',
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $inactiveUser = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'inactive',
            'office_code' => 'TEST-OFFICE',
        ]);
        $otherOfficeUser = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'OTHER-OFFICE',
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertViewHas('inspectorOptions', fn ($users) => $users->contains('id', $admin->id)
                && $users->contains('id', $officeMaintenance->id)
                && ! $users->contains('id', $inactiveUser->id)
                && ! $users->contains('id', $otherOfficeUser->id));

        $maintenanceUser = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $this->actingAs($maintenanceUser)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertSee('name="inspector_user_id" value="'.$maintenanceUser->id.'"', false)
            ->assertSee('value="'.$maintenanceUser->name.'" readonly', false)
            ->assertViewHas('inspectorOptions', fn ($users) => $users->isEmpty());
    }

    public function test_inspection_store_restricts_admin_inspectors_and_uses_maintenance_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $outsideInspector = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'OTHER-OFFICE',
        ]);
        $equipment = $this->createEquipment('Operational');
        $payload = [
            '_form' => 'create-inspection',
            'equipment_id' => $equipment->id,
            'inspection_date' => now()->format('Y-m-d\\TH:i'),
            'inspection_type' => 'Routine',
            'outcome' => 'Passed',
        ];

        $this->actingAs($admin)
            ->from(route('inspections.index'))
            ->post(route('inspections.store'), [...$payload, 'inspector_user_id' => $outsideInspector->id])
            ->assertSessionHasErrors('inspector_user_id');

        $maintenanceUser = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $sameOfficeSubstitute = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($maintenanceUser)
            ->post(route('inspections.store'), [...$payload, 'inspector_user_id' => $sameOfficeSubstitute->id])
            ->assertRedirect(route('inspections.index'));

        $this->assertDatabaseHas('inspections', [
            'equipment_id' => $equipment->id,
            'inspector_name' => $maintenanceUser->name,
        ]);
    }

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

    public function test_equipment_status_sections_show_at_most_ten_rows_below_the_main_ops_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        for ($index = 0; $index < 11; $index++) {
            $this->createEquipment('Under Inspection');
            $this->createEquipment($index % 2 === 0 ? 'Under Maintenance' : 'Under Repair');
            $this->createEquipment('Under Repair');
        }

        $this->actingAs($admin);

        $inspectionResponse = $this->get(route('inspections.index'))
            ->assertOk()
            ->assertViewHas('equipmentUnderInspection', fn ($records) => $records->count() === 10);
        $inspectionHtml = $inspectionResponse->getContent();
        $this->assertGreaterThan(
            strpos($inspectionHtml, 'No inspection records match these filters.'),
            strpos($inspectionHtml, 'Equipment currently under inspection')
        );

        $maintenanceResponse = $this->get(route('maintenances.index'))
            ->assertOk()
            ->assertViewHas('equipmentUnderMaintenance', fn ($records) => $records->count() === 10);
        $maintenanceHtml = $maintenanceResponse->getContent();
        $this->assertGreaterThan(
            strpos($maintenanceHtml, 'No maintenance tasks match these filters.'),
            strpos($maintenanceHtml, 'Equipment currently under maintenance or repair')
        );

        $repairResponse = $this->get(route('repairs.index'))
            ->assertOk()
            ->assertViewHas('statusEquipment', fn ($records) => $records->count() === 10);
        $repairHtml = $repairResponse->getContent();
        $this->assertGreaterThan(
            strpos($repairHtml, 'No repair records have been reported.'),
            strpos($repairHtml, 'Equipment currently under repair')
        );
    }

    public function test_archived_equipment_is_hidden_from_operational_views_but_available_in_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $activeEquipment = $this->createEquipment('Operational');
        $outOfServiceEquipment = $this->createEquipment('Out of Service');
        $retiredEquipment = $this->createEquipment('Retired');
        $activeEquipment->update(['equipment_name' => 'Active inventory asset']);
        $outOfServiceEquipment->update(['equipment_name' => 'Archived out of service asset']);
        $retiredEquipment->update(['equipment_name' => 'Archived retired asset']);
        $outOfServiceEquipment->update(['next_inspection_date' => today()->addDays(3)]);
        $retiredEquipment->update(['next_inspection_date' => today()->addDays(4)]);
        $archivedIds = [$outOfServiceEquipment->id, $retiredEquipment->id];

        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertViewHas('equipment', fn ($records) => $records->getCollection()->whereIn('id', $archivedIds)->isEmpty())
            ->assertViewHas('equipmentOptions', fn ($records) => $records->whereIn('id', $archivedIds)->isEmpty());

        $this->get(route('equipment.index', ['section' => 'archive']))
            ->assertOk()
            ->assertSee($outOfServiceEquipment->equipment_name)
            ->assertSee($retiredEquipment->equipment_name);

        $this->get(route('inspections.index'))
            ->assertOk()
            ->assertViewHas('equipmentOptions', fn ($records) => $records->whereIn('id', $archivedIds)->isEmpty())
            ->assertViewHas('priorityInspectionSchedule', fn ($records) => $records->every(
                fn (array $record) => ! in_array($record['equipment']->id, $archivedIds, true)
            ));

        $this->get(route('maintenances.index'))
            ->assertOk()
            ->assertViewHas('equipmentOptions', fn ($records) => $records->whereIn('id', $archivedIds)->isEmpty());

        $this->get(route('repairs.index'))
            ->assertOk()
            ->assertViewHas('equipmentOptions', fn ($records) => $records->whereIn('id', $archivedIds)->isEmpty());

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertViewHas('paginatedRecords', fn ($records) => $records->getCollection()->every(
                fn (array $record) => ! in_array($record['record'], [
                    $outOfServiceEquipment->asset_code,
                    $retiredEquipment->asset_code,
                ], true)
            ));

        $this->get(route('search', ['q' => 'Archived']))
            ->assertOk()
            ->assertViewHas('results', fn ($records) => $records->where('type', 'Equipment')->isEmpty());

        $this->get('/dashboard')
            ->assertOk()
            ->assertViewHas('equipmentCount', 1);

        $registry = app(ReportDataService::class)->report('equipment-registry', 'quarter', null, 'category', 'TEST-OFFICE');
        $this->assertSame([$activeEquipment->asset_code], $registry['records']->pluck('values.Asset code')->all());
    }

    public function test_recording_an_inspection_from_under_inspection_updates_equipment_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Inspection');
        $generatedReference = 'STA-'.today()->format('m-d-Y').'-00001';

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
            'reference_number' => $generatedReference,
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
            'reason' => 'Inspection '.$generatedReference,
        ]);

        $this->get('/inspections')
            ->assertOk()
            ->assertSee($generatedReference)
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

    public function test_admin_can_schedule_inspection_maintenance_and_repair_for_office_maintenance_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $maintenanceUser = User::factory()->create(['role' => 'maintenance', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $inspectionEquipment = $this->createEquipment('Operational');
        $maintenanceEquipment = $this->createEquipment('Operational');
        $repairEquipment = $this->createEquipment('Operational');
        $scheduledDate = today()->addDays(7)->toDateString();

        $this->actingAs($admin)
            ->post(route('schedule.store'), [
                '_form' => 'create-schedule',
                'work_type' => 'Inspection',
                'equipment_id' => $inspectionEquipment->id,
                'assigned_to_id' => $maintenanceUser->id,
                'scheduled_date' => $scheduledDate,
                'priority' => 'High',
                'notes' => 'Annual safety inspection',
            ])
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseHas('inspections', [
            'equipment_id' => $inspectionEquipment->id,
            'assigned_to_id' => $maintenanceUser->id,
            'priority' => 'High',
            'inspector_name' => $maintenanceUser->name,
            'inspection_date' => $scheduledDate.' 09:00:00',
        ]);
        $this->assertSame($scheduledDate, $inspectionEquipment->fresh()->next_inspection_date->toDateString());

        foreach ([['Maintenance', $maintenanceEquipment], ['Repair', $repairEquipment]] as [$type, $equipment]) {
            $this->post(route('schedule.store'), [
                '_form' => 'create-schedule',
                'work_type' => $type,
                'equipment_id' => $equipment->id,
                'assigned_to_id' => $maintenanceUser->id,
                'scheduled_date' => $scheduledDate,
                'priority' => 'Medium',
                'notes' => 'Scheduled '.$type,
            ])->assertRedirect(route('schedule.index'));

            $this->assertDatabaseHas('maintenances', [
                'equipment_id' => $equipment->id,
                'assigned_to_id' => $maintenanceUser->id,
                'maintenance_type' => $type === 'Repair' ? 'Repair' : 'Preventive maintenance',
                'priority' => 'Medium',
                'status' => 'Scheduled',
                'start_date' => $scheduledDate.' 00:00:00',
            ]);
        }
    }

    public function test_equipment_priority_updates_across_inspections_maintenance_repairs_and_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Operational');
        $equipment->update(['commission_date' => today()]);
        Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'PRIORITY-SYNC-INSP',
            'inspection_date' => now(),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
            'outcome' => 'Passed',
        ]);
        Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'PRIORITY-SYNC-MAINT',
            'maintenance_type' => 'Preventive service',
            'priority' => 'Low',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(5),
        ]);
        Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'PRIORITY-SYNC-REPAIR',
            'maintenance_type' => 'Pump repair',
            'priority' => 'Low',
            'reported_issue' => 'Pressure leak',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(7),
        ]);
        $this->actingAs($admin);

        $this->get(route('inspections.index'))->assertViewHas('inspections', fn ($records) =>
            $records->every(fn (Inspection $record) => $record->equipment_priority === 'Low')
        );
        $this->get(route('maintenances.index'))->assertViewHas('maintenances', fn ($records) =>
            $records->every(fn (Maintenance $record) => $record->display_priority === 'Low')
        );
        $this->get(route('repairs.index'))->assertViewHas('paginatedRecords', fn ($records) =>
            $records->getCollection()->every(fn (array $record) => $record['priority'] === 'Low')
        );
        $this->get(route('schedule.index'))->assertViewHas('paginatedRecords', fn ($records) =>
            $records->getCollection()->every(fn (array $record) => $record['priority'] === 'Low')
        );

        $equipment->update(['status' => 'Out of Service']);

        $this->get(route('inspections.index'))->assertViewHas('inspections', fn ($records) =>
            $records->every(fn (Inspection $record) => $record->equipment_priority === 'High')
        );
        $this->get(route('maintenances.index'))->assertViewHas('maintenances', fn ($records) =>
            $records->every(fn (Maintenance $record) => $record->display_priority === 'High')
        );
        $this->get(route('repairs.index'))->assertViewHas('paginatedRecords', fn ($records) =>
            $records->getCollection()->every(fn (array $record) => $record['priority'] === 'High')
        );
        $this->get(route('schedule.index'))->assertViewHas('paginatedRecords', fn ($records) =>
            $records->getCollection()->every(fn (array $record) => $record['priority'] === 'High')
        );
    }

    public function test_high_priority_equipment_is_scheduled_for_inspection_within_one_week(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $automatic = $this->createEquipment('Operational');
        $automatic->update(['commission_date' => today()->subMonthNoOverflow()->subDays(15)]);
        $rescheduled = $this->createEquipment('Operational');
        $rescheduled->update([
            'commission_date' => today()->subMonthNoOverflow()->subDays(15),
            'next_inspection_date' => today()->addDays(30),
        ]);
        $earlier = $this->createEquipment('Operational');
        $earlier->update([
            'commission_date' => today()->subMonthNoOverflow()->subDays(15),
            'next_inspection_date' => today()->addDays(3),
        ]);

        $this->actingAs($admin)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertViewHas('paginatedRecords', function ($records) use ($automatic, $rescheduled, $earlier): bool {
                $rows = $records->getCollection()->keyBy('record');
                $oneWeekFromToday = today()->addWeek()->format('M d, Y');

                return ($rows[$automatic->asset_code]['date'] ?? null) === $oneWeekFromToday
                    && ($rows[$automatic->asset_code]['priority'] ?? null) === 'High'
                    && ($rows[$rescheduled->asset_code]['date'] ?? null) === $oneWeekFromToday
                    && ($rows[$earlier->asset_code]['date'] ?? null) === today()->addDays(3)->format('M d, Y');
            });

                $this->get(route('inspections.index'))
                    ->assertOk()
                    ->assertViewHas('priorityInspectionSchedule', function ($records) use ($automatic, $rescheduled, $earlier): bool {
                    $rows = $records->keyBy(fn (array $record) => $record['equipment']->id);
                    $oneWeekFromToday = today()->addWeek()->format('M d, Y');

                    return ($rows[$automatic->id]['date'] ?? null)?->format('M d, Y') === $oneWeekFromToday
                        && ($rows[$rescheduled->id]['date'] ?? null)?->format('M d, Y') === $oneWeekFromToday
                        && ($rows[$earlier->id]['date'] ?? null)?->format('M d, Y') === today()->addDays(3)->format('M d, Y');
                    });
    }

    public function test_dashboard_cards_and_maintenance_filters_show_due_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $overdueEquipment = $this->createEquipment('Operational');
        $soonEquipment = $this->createEquipment('Operational');
        $overdueEquipment->update(['equipment_name' => 'Overdue equipment']);
        $soonEquipment->update(['equipment_name' => 'Due soon equipment']);
        $overdue = Maintenance::query()->create([
            'equipment_id' => $overdueEquipment->id,
            'request_number' => 'DUE-OVERDUE-01',
            'maintenance_type' => 'Preventive service',
            'status' => 'Scheduled',
            'start_date' => today()->subDays(40),
        ]);
        Maintenance::query()->create([
            'equipment_id' => $soonEquipment->id,
            'request_number' => 'DUE-SOON-01',
            'maintenance_type' => 'Preventive service',
            'status' => 'Scheduled',
            'start_date' => today()->addDays(5),
        ]);
        Maintenance::query()->create([
            'equipment_id' => $overdueEquipment->id,
            'request_number' => 'DUE-COMPLETED-01',
            'maintenance_type' => 'Preventive service',
            'status' => 'Completed',
            'start_date' => today()->subDays(50),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Total Equipment')
            ->assertSee('Total Users')
            ->assertSee('Active Users')
            ->assertSee('High Priority')
            ->assertSee('Medium Priority')
            ->assertSee('Low Priority')
            ->assertViewHas('overdueMaintenanceCount', 1)
            ->assertViewHas('maintenanceDueSoonCount', 1);

        $this->get(route('maintenances.index', ['due' => 'overdue']))
            ->assertOk()
            ->assertViewHas('maintenances', function ($records): bool {
                return $records->contains('request_number', 'DUE-OVERDUE-01')
                    && ! $records->contains('request_number', 'DUE-SOON-01')
                    && ! $records->contains('request_number', 'DUE-COMPLETED-01');
            });

        $this->get('/dashboard?priority_filter=High')
            ->assertOk()
            ->assertViewHas('priorityRecords', function ($records) use ($overdueEquipment, $soonEquipment): bool {
                return $records->contains(fn (array $record) => $record['equipment']->is($overdueEquipment))
                    && ! $records->contains(fn (array $record) => $record['equipment']->is($soonEquipment));
            });
    }

    public function test_completed_maintenance_requires_completion_date_and_work_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Operational');

        $this->actingAs($admin)
            ->from('/maintenances/create')
            ->post(route('maintenances.store'), [
                'equipment_id' => $equipment->id,
                'request_number' => 'COMPLETE-REQUIRED-01',
                'maintenance_type' => 'Preventive',
                'priority' => 'High',
                'status' => 'Completed',
            ])
            ->assertSessionHasErrors(['completion_date', 'action_taken']);
    }

    public function test_under_maintenance_equipment_can_be_recorded_and_released_from_maintenance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment('Under Maintenance');
        $equipment->update(['maintenance_interval_days' => 90]);

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
        $this->assertSame(today()->toDateString(), $equipment->fresh()->last_maintenance_date->toDateString());
        $completedMaintenance = Maintenance::query()
            ->where('equipment_id', $equipment->id)
            ->where('maintenance_type', 'Pump service')
            ->firstOrFail();
        $this->assertSame('Completed', $completedMaintenance->status);
        $this->assertSame(today()->addDays(90)->toDateString(), $completedMaintenance->next_recommended_maintenance_date->toDateString());
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
