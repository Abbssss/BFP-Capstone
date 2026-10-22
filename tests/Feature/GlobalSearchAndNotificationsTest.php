<?php

namespace Tests\Feature;

use App\Models\Equipment;
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
        $this->createEquipment('EQ-SEARCH-A', 'Searchable pump', 'OFFICE-A');
        $this->createEquipment('EQ-SEARCH-B', 'Searchable pump elsewhere', 'OFFICE-B');

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Searchable pump']))
            ->assertOk()
            ->assertSee('Searchable pump')
            ->assertDontSee('Searchable pump elsewhere')
            ->assertSee('Equipment');
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
            ->assertDontSee('Office equipment 6')
            ->assertDontSee('Outside office equipment');
        $this->assertCount(5, app(RecentProjectChanges::class)->latest());
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
