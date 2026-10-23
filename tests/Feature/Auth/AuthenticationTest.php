<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_form_uses_https_when_forwarded_by_a_trusted_proxy(): void
    {
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/login');

        $response->assertOk();
        $response->assertSee('action="https://localhost/login"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_with_a_pending_role_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'role' => 'pending',
            'status' => 'pending',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors(['email' => 'Your account is awaiting role assignment by a Super Admin.']);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_after_a_role_is_assigned(): void
    {
        $user = User::factory()->create([
            'role' => 'pending',
            'status' => 'pending',
        ]);

        $user->update([
            'role' => 'maintenance',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('form method="POST" action="'.route('logout').'"', false)
            ->assertSee('<button type="submit"', false)
            ->assertDontSee('href="'.route('logout').'"', false);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
