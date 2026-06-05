<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'password' => bcrypt('password123!'),
            'is_active' => true,
        ]);
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->user->assignRole('Read Only');

        $response = $this->actingAs($this->user)->get('/');
        $response->assertStatus(200);
    }
}
