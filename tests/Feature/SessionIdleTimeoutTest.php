<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionIdleTimeoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_successful_login_initializes_the_idle_activity_timestamp(): void
    {
        $user = $this->makeUser();

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(EnforceIdleSession::LAST_ACTIVITY_KEY);

        $this->assertAuthenticatedAs($user);
    }

    public function test_recent_activity_can_refresh_the_session_heartbeat(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession([
                EnforceIdleSession::LAST_ACTIVITY_KEY => now()->subMinutes(14)->timestamp,
            ])
            ->postJson(route('session.heartbeat'))
            ->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }

    public function test_session_is_rejected_after_fifteen_minutes_without_activity(): void
    {
        config()->set('session.idle_timeout', 15);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession([
                EnforceIdleSession::LAST_ACTIVITY_KEY => now()->subMinutes(15)->timestamp,
            ])
            ->postJson(route('session.heartbeat'))
            ->assertUnauthorized()
            ->assertJson([
                'code' => 'SESSION_IDLE_TIMEOUT',
                'redirect' => route('login', ['timeout' => 1]),
            ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'session_timeout',
            'module' => 'Authentication',
            'user_id' => $user->id,
        ]);
    }

    public function test_browser_timeout_endpoint_ends_an_otherwise_active_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession([
                EnforceIdleSession::LAST_ACTIVITY_KEY => now()->timestamp,
            ])
            ->postJson(route('session.timeout'))
            ->assertOk()
            ->assertJson([
                'code' => 'SESSION_IDLE_TIMEOUT',
                'redirect' => route('login', ['timeout' => 1]),
            ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'session_timeout',
            'module' => 'Authentication',
            'user_id' => $user->id,
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Session Timeout User',
            'email' => 'session-timeout-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PROVINCIAL_STAFF,
            'municipality_id' => null,
            'is_active' => true,
        ]);
    }
}
