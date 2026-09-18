<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertNotFound();
    }

    public function test_users_without_two_factor_are_sent_to_security_settings()
    {
        config(['cyber.require_two_factor' => true]);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('security.edit'));
        $this->actingAs(User::factory()->withTwoFactor()->create())->get(route('dashboard'))->assertOk();
    }

    public function test_viewers_can_read_but_not_configure_or_triage()
    {
        $viewer = User::factory()->viewer()->create();

        foreach (['dashboard', 'items.index', 'alerts.index', 'incidents.index', 'briefings.index'] as $route) {
            $this->actingAs($viewer)->get(route($route))->assertOk();
        }

        $this->actingAs($viewer)->get(route('sources.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('watchlist.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('items.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('incidents.store'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_analysts_configure_but_only_admins_manage_users_and_see_the_audit_log()
    {
        $analyst = User::factory()->create();

        $this->actingAs($analyst)->get(route('sources.index'))->assertOk();
        $this->actingAs($analyst)->get(route('watchlist.index'))->assertOk();
        $this->actingAs($analyst)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($analyst)->get(route('admin.audit.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk();
    }

    public function test_admin_creates_users_and_changes_roles()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Analyst',
            'email' => 'analyst@example.com',
            'role' => 'analyst',
            'password' => 'a-long-password',
        ])->assertRedirect();

        $user = User::where('email', 'analyst@example.com')->sole();
        $this->assertSame(Role::Analyst, $user->role);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), ['role' => 'viewer']);
        $this->assertSame(Role::Viewer, $user->fresh()->role);
        $this->assertSame(['user_created', 'user_role_changed'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_admins_cannot_demote_or_delete_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['role' => 'viewer'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_logins_and_failed_logins_are_audited()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertSame(['login_failed', 'login'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_create_user_command_creates_an_admin()
    {
        $this->artisan('users:create', ['email' => 'chief@example.com', 'name' => 'Chief'])
            ->expectsQuestion('Password', 'a-long-password')
            ->assertSuccessful();

        $this->assertSame(Role::Admin, User::where('email', 'chief@example.com')->sole()->role);
    }
}
