<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EquipmentCategoryImportRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_categories_are_limited_to_the_two_supported_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('value="Mechanical"', false)
            ->assertSee('value="Non Mechanical"', false)
            ->assertSee('data-modal-open="equipment-bulk-upload-modal"', false)
            ->assertSee('id="equipment-bulk-upload-modal"', false)
            ->assertSee('name="file"', false)
            ->assertDontSee('ops-card-heading">Import equipment from CSV', false)
            ->assertDontSee('Equipment categories');
        $this->get(route('equipment.create'))
            ->assertOk()
            ->assertSee('value="Mechanical"', false)
            ->assertSee('value="Non Mechanical"', false);

        $this->post('/equipment/categories', ['name' => 'New Category'])->assertMethodNotAllowed();
        $this->from(route('equipment.index'))
            ->post(route('equipment.store'), [
                'equipment_name' => 'Invalid category equipment',
                'category' => 'Fire Engine',
                'office_code' => 'TEST-OFFICE',
                'status' => 'Operational',
            ])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Invalid category equipment']);
    }

    public function test_equipment_creation_requires_all_fields_except_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);

        $this->actingAs($admin)
            ->from(route('equipment.index'))
            ->post(route('equipment.store'), [
                'equipment_name' => 'Incomplete equipment',
                'category' => 'Mechanical',
                'status' => 'Operational',
            ])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHasErrors([
                'asset_tag',
                'serial_number',
                'manufacturer',
                'model',
                'commission_date',
                'location',
            ]);

        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Incomplete equipment']);
    }

    public function test_equipment_table_shows_schedule_based_priority(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = Equipment::create([
            'asset_code' => 'EQ-PRIORITY-01',
            'equipment_name' => 'Priority test equipment',
            'category' => 'Fire Engine',
            'office_code' => 'TEST-OFFICE',
            'status' => 'Operational',
            'last_maintenance_date' => today()->subDays(90),
            'maintenance_interval_days' => 30,
        ]);

        $this->actingAs($admin)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('Priority')
            ->assertSee('High')
            ->assertDontSee('Risk level');
    }

    public function test_bulk_csv_import_creates_equipment_and_status_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $csv = UploadedFile::fake()->createWithContent(
            'equipment.csv',
            "equipment_name,category,office_code,status,asset_code,asset_tag,serial_number,manufacturer,model,location,date_acquired\n"
            ."Imported pump,Mechanical,OTHER-OFFICE,Operational,EQ-IMPORT-01,,,Test Maker,Model X,Station A,2026-01-15\n"
        );

        $this->actingAs($admin)
            ->from(route('equipment.index'))
            ->post(route('equipment.import'), ['file' => $csv])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success', '1 equipment records imported successfully.');

        $this->assertDatabaseHas('equipment', [
            'asset_code' => 'EQ-IMPORT-01',
            'equipment_name' => 'Imported pump',
            'category' => 'Mechanical',
            'commission_date' => '2026-01-15 00:00:00',
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
            "equipment_name,category\nValid pump,Mechanical\nInvalid category,Fire Engine\n"
        );

        $this->actingAs($admin)
            ->from(route('equipment.index'))
            ->post(route('equipment.import'), ['file' => $csv])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Valid pump']);
    }
}
