<?php

namespace Tests\Feature\Auth;

use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->admin = $this->user('Super Admin');
        $this->target = $this->user('Sales Representative');
    }

    private function user(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, ...$attrs]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_impersonate_a_user(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->target->id}/impersonate")
            ->assertRedirect('/');

        // The active session is now the target user, with the original admin
        // stashed so the banner/return can work.
        $this->assertAuthenticatedAs($this->target);
        $this->assertSame($this->admin->id, session(ImpersonationService::SESSION_KEY));

        // …and the session is audited (open log).
        $log = ImpersonationLog::firstOrFail();
        $this->assertSame($this->admin->id, $log->impersonator_id);
        $this->assertSame($this->target->id, $log->impersonated_id);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->ended_at);
    }

    public function test_returning_restores_the_admin_and_closes_the_log(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->target->id}/impersonate")->assertRedirect();

        $this->post('/impersonate/stop')->assertRedirect(route('admin.users'));

        // Back to the admin, no impersonation banner, log closed.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $this->admin->id)
            ->where('impersonating', null));

        $this->assertNotNull(ImpersonationLog::firstOrFail()->ended_at);
    }

    public function test_non_super_admin_cannot_impersonate(): void
    {
        $regular = $this->user('Sales Representative');

        $this->actingAs($regular)
            ->post("/admin/users/{$this->target->id}/impersonate")
            ->assertForbidden();

        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_cannot_impersonate_a_user_in_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->create(['organization_id' => $otherOrg->id, 'is_active' => true]);
        $foreign->assignRole('Sales Representative');

        $this->actingAs($this->admin)
            ->post("/admin/users/{$foreign->id}/impersonate")
            ->assertForbidden();

        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_cannot_impersonate_another_super_admin(): void
    {
        $otherAdmin = $this->user('Super Admin');

        $this->actingAs($this->admin)
            ->post("/admin/users/{$otherAdmin->id}/impersonate")
            ->assertForbidden();
    }

    public function test_cannot_impersonate_self(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->admin->id}/impersonate")
            ->assertForbidden();
    }

    public function test_cannot_impersonate_a_deactivated_user(): void
    {
        $inactive = $this->user('Sales Representative', ['is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/admin/users/{$inactive->id}/impersonate")
            ->assertForbidden();
    }

    public function test_cannot_start_while_already_impersonating(): void
    {
        // Directly exercise the service guard (the HTTP route is role-gated, so a
        // second start can't be reached as the impersonated non-admin user).
        $request = Request::create('/x', 'GET');
        $session = $this->app['session']->driver();
        $session->put(ImpersonationService::SESSION_KEY, 999);
        $request->setLaravelSession($session);

        $this->expectException(HttpException::class);
        app(ImpersonationService::class)->start($request, $this->admin, $this->target);
    }

    public function test_stop_is_a_noop_when_not_impersonating(): void
    {
        $this->actingAs($this->target)
            ->post('/impersonate/stop')
            ->assertRedirect('/');

        $this->assertSame(0, ImpersonationLog::count());
    }
}
