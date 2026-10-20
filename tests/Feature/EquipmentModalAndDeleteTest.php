<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EquipmentModalAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_list_uses_view_and_edit_modals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment();

        $this->actingAs($admin)
            ->get('/equipment')
            ->assertOk()
            ->assertSee('equipment-view-'.$equipment->id)
            ->assertSee('equipment-edit-'.$equipment->id)
            ->assertSee('data-modal-open="equipment-view-'.$equipment->id.'"', false)
            ->assertSee('data-modal-open="equipment-edit-'.$equipment->id.'"', false)
            ->assertDontSee('equipment-delete-'.$equipment->id);
    }

    public function test_only_super_admin_can_open_delete_controls_and_delete_equipment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'TEST-OFFICE']);
        $equipment = $this->createEquipment();

        $this->actingAs($admin)
            ->get('/equipment')
            ->assertDontSee('equipment-delete-'.$equipment->id);
        $this->delete(route('equipment.destroy', $equipment), [
            '_form' => 'delete-equipment',
            'password' => 'password',
        ])->assertForbidden();
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $this->actingAs($superAdmin)
            ->get('/equipment')
            ->assertSee('equipment-delete-'.$equipment->id);
    }

    public function test_super_admin_must_confirm_their_password_to_delete_equipment(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $equipment = $this->createEquipment();

        $this->actingAs($superAdmin)
            ->from('/equipment')
            ->delete(route('equipment.destroy', $equipment), [
                '_form' => 'delete-equipment',
                '_equipment_id' => $equipment->id,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/equipment')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
    }

    public function test_super_admin_can_delete_equipment_after_password_confirmation(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'password' => Hash::make('super-secret'),
        ]);
        $equipment = $this->createEquipment();

        $this->actingAs($superAdmin)
            ->delete(route('equipment.destroy', $equipment), [
                '_form' => 'delete-equipment',
                '_equipment_id' => $equipment->id,
                'password' => 'super-secret',
            ])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('success', 'Equipment removed successfully.');

        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
    }

    private function createEquipment(): Equipment
    {
        return Equipment::query()->create([
            'asset_code' => 'EQ-'.strtoupper(bin2hex(random_bytes(4))),
            'equipment_name' => 'Test station equipment',
            'category' => 'Fire Engine',
            'status' => 'Operational',
            'office_code' => 'TEST-OFFICE',
        ]);
    }
}
