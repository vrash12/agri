<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('role');
            $table->boolean('is_active');
            $table->rememberToken();
            $table->timestamps();
        });
        Route::middleware(['web', 'auth'])->get('/_test/private-history', fn () => response('Private workspace'));
    }

    public function test_authenticated_pages_and_login_cannot_be_stored_in_http_caches(): void
    {
        $this->get(route('login'))->assertHeader('Pragma', 'no-cache');
        $response = $this->actingAs($this->user())->get('/_test/private-history');
        $response->assertOk()->assertHeader('Expires', '0');
        foreach (['private', 'no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $response->headers->get('Cache-Control'));
        }
    }

    public function test_logout_invalidates_access_before_a_history_page_can_be_requested_again(): void
    {
        $this->actingAs($this->user())->withSession(['private_marker' => 'previous-session']);
        $this->get('/_test/private-history')->assertOk();
        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('private_marker')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertGuest();
        $this->get('/_test/private-history')->assertRedirect(route('login'));
        $this->postJson(route('session.heartbeat'))->assertUnauthorized();
    }

    private function user(): User
    {
        return User::query()->create([
            'name' => 'History Test User',
            'email' => 'history@example.test',
            'role' => User::ROLE_SYSTEM_OWNER,
            'is_active' => true,
        ]);
    }
}
