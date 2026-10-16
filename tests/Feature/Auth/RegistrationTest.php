<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Account created inactive, not logged in: an Admin must activate it.
        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('Technicien'));
        $this->assertSame('unactive', $user->status);

        // Even with the right password, an inactive account cannot reach the application.
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'password']);
        $this->get(route('dashboard'))->assertRedirect('login');
        $this->assertGuest();
    }

    public function test_registration_cannot_grant_admin_role()
    {
        $response = $this->post('/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_name' => 'Admin',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'status' => 'unactive',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($user->hasRole('Technicien'));
        $this->assertFalse($user->hasRole('Admin'));
    }
}
