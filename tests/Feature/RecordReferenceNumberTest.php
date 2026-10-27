<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordReferenceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspections_maintenance_and_repairs_share_an_automatic_equipment_reference_sequence(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);
        $equipment = Equipment::query()->create([
            'asset_code' => 'EQ-REFERENCE-01',
            'equipment_name' => 'Fire Engine Pump',
            'category' => 'Mechanical',
            'status' => 'Operational',
            'office_code' => 'TEST-OFFICE',
        ]);
        $expectedReferences = [
            'FIR-'.today()->format('m-d-Y').'-00001',
            'FIR-'.today()->format('m-d-Y').'-00002',
            'FIR-'.today()->format('m-d-Y').'-00003',
        ];

        $this->actingAs($admin)->post(route('inspections.store'), [
            'equipment_id' => $equipment->id,
            'reference_number' => 'MANUAL-INSPECTION',
            'inspection_date' => now()->format('Y-m-d\TH:i'),
            'inspector_name' => $admin->name,
            'inspection_type' => 'Routine',
            'outcome' => 'Passed',
        ])->assertRedirect(route('inspections.index'));

        $this->post(route('maintenances.store'), [
            'equipment_id' => $equipment->id,
            'request_number' => 'MANUAL-MAINTENANCE',
            'maintenance_type' => 'Preventive',
            'priority' => 'Medium',
            'status' => 'Scheduled',
        ])->assertRedirect(route('maintenances.index'));

        $this->post(route('maintenances.store'), [
            '_form' => 'create-repair',
            'equipment_id' => $equipment->id,
            'request_number' => 'MANUAL-REPAIR',
            'maintenance_type' => 'Pump repair',
            'priority' => 'High',
            'status' => 'Pending',
            'reported_issue' => 'Pump pressure is low.',
        ])->assertRedirect(route('repairs.index'));

        $this->assertDatabaseHas('inspections', [
            'equipment_id' => $equipment->id,
            'reference_number' => $expectedReferences[0],
        ]);
        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'request_number' => $expectedReferences[1],
        ]);
        $this->assertDatabaseHas('maintenances', [
            'equipment_id' => $equipment->id,
            'request_number' => $expectedReferences[2],
        ]);
        $this->assertDatabaseMissing('inspections', ['reference_number' => 'MANUAL-INSPECTION']);
        $this->assertDatabaseMissing('maintenances', ['request_number' => 'MANUAL-MAINTENANCE']);
        $this->assertDatabaseMissing('maintenances', ['request_number' => 'MANUAL-REPAIR']);
    }
}
