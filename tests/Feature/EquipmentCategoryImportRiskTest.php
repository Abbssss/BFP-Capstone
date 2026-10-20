<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\RiskPrediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EquipmentCategoryImportRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_add_equipment_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertDontSee('Equipment categories');
        $this->post(route('equipment.categories.store'), ['name' => 'New Category'])
            ->assertForbidden();
        $this->assertDatabaseMissing('equipment_categories', ['name' => 'New Category']);

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($superAdmin)
            ->post(route('equipment.categories.store'), ['name' => 'New Category'])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('equipment_categories', ['name' => 'New Category']);
        $this->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('New Category')
            ->assertSee('value="New Category"', false);
    }

    public function test_equipment_table_shows_latest_risk_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-RISK-01',
            'equipment_name' => 'Risk test equipment',
            'category' => 'Fire Engine',
            'office_code' => 'TEST-OFFICE',
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

        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('Risk level')
            ->assertSee('High Risk')
            ->assertDontSee('Low Risk');
    }

    public function test_bulk_csv_import_creates_equipment_and_status_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $csv = UploadedFile::fake()->createWithContent(
            'equipment.csv',
            "equipment_name,category,office_code,status,asset_code,asset_tag,serial_number,manufacturer,model,location,commission_date\n"
            ."Imported pump,Portable Water Pump,OTHER-OFFICE,Operational,EQ-IMPORT-01,,,Test Maker,Model X,Station A,2026-01-15\n"
        );

        $this->actingAs($admin)
            ->from(route('equipment.index'))
            ->post(route('equipment.import'), ['file' => $csv])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success', '1 equipment records imported successfully.');

        $this->assertDatabaseHas('equipment', [
            'asset_code' => 'EQ-IMPORT-01',
            'equipment_name' => 'Imported pump',
            'category' => 'Portable Water Pump',
            'office_code' => 'TEST-OFFICE',
            'manufacturer' => 'Test Maker',
        ]);
        $equipment = Equipment::where('asset_code', 'EQ-IMPORT-01')->firstOrFail();
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'new_status' => 'Operational',
            'reason' => 'Bulk equipment import',
        ]);
    }

    public function test_bulk_csv_import_rejects_invalid_rows_without_partial_saves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $csv = UploadedFile::fake()->createWithContent(
            'equipment.csv',
            "equipment_name,category\nValid pump,Portable Water Pump\nMissing category,\n"
        );

        $this->actingAs($admin)
            ->from(route('equipment.index'))
            ->post(route('equipment.import'), ['file' => $csv])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Valid pump']);
    }
}
