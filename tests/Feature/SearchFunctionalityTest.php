<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_search_matches_multiple_terms_across_equipment_fields(): void
    {
        $admin = $this->admin();
        $equipment = $this->equipment([
            'asset_code' => 'EQ-SEARCH-01',
            'equipment_name' => 'Rescue Pump',
            'manufacturer' => 'Acme Fire',
            'serial_number' => 'SERIAL-Z9',
        ]);
        $this->equipment([
            'asset_code' => 'EQ-SEARCH-02',
            'equipment_name' => 'Unrelated equipment',
            'office_code' => 'OTHER-OFFICE',
        ]);

        $this->actingAs($admin)
            ->get(route('equipment.index', ['search' => 'rescue acme']))
            ->assertOk()
            ->assertSee($equipment->equipment_name)
            ->assertDontSee('Unrelated equipment');

        $this->get(route('equipment.index', ['search' => 'serial-z9']))
            ->assertOk()
            ->assertSee($equipment->equipment_name);
    }

    public function test_inspection_search_matches_findings_and_equipment_details(): void
    {
        $admin = $this->admin();
        $equipment = $this->equipment(['equipment_name' => 'Emergency Generator']);
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'INSP-SEARCH-01',
            'inspection_date' => now(),
            'inspector_name' => $admin->name,
            'inspection_type' => 'Annual inspection',
            'defects_identified' => 'Loose drive belt',
            'outcome' => 'Passed with Findings',
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.index', ['search' => 'drive loose']))
            ->assertOk()
            ->assertSee($inspection->reference_number)
            ->assertSee($equipment->equipment_name);
    }

    public function test_maintenance_and_global_search_match_issue_equipment_and_reference_terms(): void
    {
        $admin = $this->admin();
        $equipment = $this->equipment([
            'asset_code' => 'EQ-SEARCH-MAINT',
            'equipment_name' => 'Foam Transfer Pump',
        ]);
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'FOA-10-26-2026-00001',
            'maintenance_type' => 'Pump service',
            'priority' => 'High',
            'status' => 'Scheduled',
            'reported_issue' => 'Pressure leak',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.index', ['search' => 'foam pressure']))
            ->assertOk()
            ->assertSee($maintenance->maintenance_type)
            ->assertSee($equipment->equipment_name);

        $this->get(route('search', ['q' => 'foam pressure']))
            ->assertOk()
            ->assertSee($maintenance->request_number)
            ->assertSee($equipment->equipment_name);
    }

    public function test_user_search_matches_office_code_and_role_terms(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $admin = User::factory()->create([
            'name' => 'Morgan Station',
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'NORTH-02',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('users.index', ['search' => 'north admin']))
            ->assertOk()
            ->assertSee($admin->name);
    }

    public function test_repair_and_schedule_search_match_maintenance_details(): void
    {
        $admin = $this->admin();
        $equipment = $this->equipment(['equipment_name' => 'Transfer Pump']);
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'TRA-10-26-2026-00002',
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Scheduled',
            'diagnosis' => 'Hydraulic seal rupture',
        ]);

        $this->actingAs($admin)
            ->get(route('repairs.index', ['search' => 'hydraulic rupture']))
            ->assertOk()
            ->assertSee($maintenance->request_number);

        $this->get(route('schedule.index', ['search' => 'hydraulic rupture']))
            ->assertOk()
            ->assertSee($maintenance->request_number);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        static $sequence = 0;
        $sequence++;

        return Equipment::query()->create(array_merge([
            'asset_code' => 'EQ-SEARCH-'.$sequence,
            'equipment_name' => 'Search Equipment '.$sequence,
            'category' => 'Mechanical',
            'status' => 'Operational',
            'office_code' => 'TEST-OFFICE',
        ], $attributes));
    }
}
