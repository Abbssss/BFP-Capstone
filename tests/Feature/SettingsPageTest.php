<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_links_to_features_available_to_the_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('id="account-settings"', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('personnel.index').'"', false)
            ->assertDontSee('Risk model')
            ->assertSee('href="'.route('reports.index').'"', false)
            ->assertSee('Notifications')
            ->assertSee('Not configured')
            ->assertSee('database backups are not available yet');
    }

    public function test_super_admin_settings_links_to_role_management(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('href="'.route('roles.permissions').'"', false)
            ->assertSee('User &amp; Role', false);
    }

    public function test_account_settings_form_saves_and_returns_to_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'office_code' => 'TEST-OFFICE',
        ]);

        $this->actingAs($admin)
            ->patch(route('profile.update'), [
                'name' => 'Updated Settings User',
                'email' => $admin->email,
                'return_to' => 'settings.index',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'profile-updated');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Updated Settings User',
        ]);
    }
}
