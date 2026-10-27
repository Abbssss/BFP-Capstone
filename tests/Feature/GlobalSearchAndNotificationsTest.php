<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\RecentProjectChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalSearchAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_returns_only_matching_records_in_the_users_office(): void
    {
        $user = User::factory()->create([
            'role' => 'maintenance',
            'status' => 'active',
            'office_code' => 'OFFICE-A',
        ]);
        $equipment = $this->createEquipment('EQ-SEARCH-A', 'Searchable pump', 'OFFICE-A');
        $this->createEquipment('EQ-SEARCH-B', 'Searchable pump elsewhere', 'OFFICE-B');

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Searchable pump']))
            ->assertOk()
            ->assertSee('Searchable pump')
            ->assertDontSee('Searchable pump elsewhere')
            ->assertSee('Equipment')
            ->assertSee('View record')
            ->assertSee(route('equipment.records', $equipment));
    }

    public function test_notifications_show_only_the_five_latest_changes_in_the_users_office(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'OFFICE-A',
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $equipment = $this->createEquipment(
                'EQ-NOTIFY-'.$index,
                'Office equipment '.$index,
                'OFFICE-A'
            );
            DB::table('equipment')->where('id', $equipment->id)->update([
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ]);
        }
        $offsite = $this->createEquipment('EQ-NOTIFY-OUTSIDE', 'Outside office equipment', 'OFFICE-B');
        DB::table('equipment')->where('id', $offsite->id)->update([
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Latest changes')
            ->assertSee('Office equipment 1')
            ->assertSee('href="'.route('equipment.records', $this->lookupEquipment('EQ-NOTIFY-1')).'"', false)
            ->assertDontSee('Outside office equipment');
        $this->assertCount(5, app(RecentProjectChanges::class)->latest());
    }

    public function test_recent_inspection_and_maintenance_changes_link_to_their_records(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'OFFICE-A',
        ]);
        $equipment = $this->createEquipment('EQ-NOTIFY-RECORD', 'Notification equipment', 'OFFICE-A');
        $inspection = Inspection::query()->create([
            'equipment_id' => $equipment->id,
            'reference_number' => 'INSP-NOTIFY-01',
            'inspection_date' => now(),
            'inspector_name' => 'Test Inspector',
            'inspection_type' => 'Annual',
            'outcome' => 'Passed',
        ]);
        $maintenance = Maintenance::query()->create([
            'equipment_id' => $equipment->id,
            'request_number' => 'MAINT-NOTIFY-01',
            'maintenance_type' => 'Preventive service',
            'priority' => 'Medium',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('inspections.show', $inspection).'"', false)
            ->assertSee('href="'.route('maintenances.show', $maintenance).'"', false);
    }

    private function lookupEquipment(string $assetCode): Equipment
    {
        return Equipment::query()->where('asset_code', $assetCode)->firstOrFail();
    }

    private function createEquipment(string $assetCode, string $name, string $officeCode): Equipment
    {
        return Equipment::query()->create([
            'asset_code' => $assetCode,
            'equipment_name' => $name,
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => $officeCode,
        ]);
    }
}
