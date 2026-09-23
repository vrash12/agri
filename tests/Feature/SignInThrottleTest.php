<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

/**
 * The sign-in form is the only unauthenticated write in the system, so credential
 * guessing has to stop at the form rather than at the password hash.
 */
class SignInThrottleTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const PASSWORD = 'correct-horse-battery-staple';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $this->createProvinceSchema();
    }

    public function test_repeated_failures_lock_the_address_out_and_are_audited_once(): void
    {
        $user = $this->account();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attempt($user->email, 'wrong-password')
                ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        }

        // The sixth request is refused before the password is looked at.
        $this->attempt($user->email, self::PASSWORD);

        $this->assertStringContainsString(
            'Too many sign-in attempts',
            session('errors')->first('email')
        );
        $this->assertGuest();

        $throttled = AuditLog::query()->where('event', 'login_throttled')->get();
        $this->assertCount(1, $throttled, 'The lockout must be audited once, not on every blocked request.');
        $this->assertSame('Authentication', $throttled->first()->module);
        $this->assertSame(
            'Too many unsuccessful sign-in attempts',
            $throttled->first()->metadata['reason']
        );

        // Still blocked on the next request, and still only one audit entry.
        $this->attempt($user->email, self::PASSWORD);
        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('event', 'login_throttled')->count());
    }

    public function test_sign_in_requires_notice_acknowledgment_before_checking_credentials(): void
    {
        $user = $this->account();
        foreach ([null, '0', 'false', 'no'] as $acknowledgment) {
            $payload = ['email' => $user->email, 'password' => self::PASSWORD];
            if ($acknowledgment !== null) {
                $payload['confidentiality_acknowledged'] = $acknowledgment;
            }
            $this->from(route('login'))->post(route('login.attempt'), $payload)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['confidentiality_acknowledged' => 'Please confirm that you have read and understood the confidentiality and testing notice.']);
            $this->assertGuest();
        }
        $this->assertSame(0, AuditLog::count());
        $this->assertNull($user->fresh()->last_login_at);
        $this->attempt($user->email, self::PASSWORD)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_credentials_preserve_acknowledgment_but_never_flash_the_password(): void
    {
        $user = $this->account();
        $this->attempt($user->email, 'incorrect-fixture-password')
            ->assertSessionHasErrors('email')
            ->assertSessionHas('_old_input.confidentiality_acknowledged', '1')
            ->assertSessionMissing('_old_input.password');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('I have read and understood the confidentiality and testing notice.')
            ->assertSee('Farmer sign in');
        $this->assertGuest();
    }

    public function test_the_stored_password_is_never_written_to_the_audit_trail(): void
    {
        $user = $this->account();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attempt($user->email, self::PASSWORD.'-wrong');
        }
        $this->attempt($user->email, self::PASSWORD);

        foreach (AuditLog::query()->get() as $entry) {
            $encoded = json_encode($entry->getAttributes());
            $this->assertStringNotContainsString(self::PASSWORD, $encoded);
            $this->assertStringNotContainsString($user->password, $encoded);
        }
    }

    public function test_a_successful_sign_in_clears_the_counter(): void
    {
        $user = $this->account();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->attempt($user->email, 'wrong-password');
        }

        $this->attempt($user->email, self::PASSWORD)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        // Without the reset these four would be attempts five to eight and would lock out.
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->attempt($user->email, 'wrong-password')
                ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        }
        $this->assertSame(0, AuditLog::query()->where('event', 'login_throttled')->count());
    }

    public function test_the_lockout_is_scoped_to_one_email_address(): void
    {
        $locked = $this->account('locked-out@example.test');
        $colleague = $this->account('colleague@example.test');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attempt($locked->email, 'wrong-password');
        }
        $this->attempt($locked->email, self::PASSWORD);
        $this->assertGuest();

        // A second account from the same client must not be collateral damage.
        $this->attempt($colleague->email, self::PASSWORD)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($colleague);
    }

    public function test_refusals_that_are_not_credential_failures_also_count(): void
    {
        $user = $this->account();
        $user->forceFill(['is_active' => false])->saveQuietly();

        // Correct password, but the account is blocked; guessing must still be limited.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attempt($user->email, self::PASSWORD)
                ->assertSessionHasErrors(['email' => 'Your account is inactive. Please contact the system administrator.']);
        }

        $this->attempt($user->email, self::PASSWORD);
        $this->assertStringContainsString(
            'Too many sign-in attempts',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_an_address_working_through_many_accounts_cannot_flood_the_audit_trail(): void
    {
        // The per-address lockout only bounds one email, so a sprayer moves between
        // them. The audit trail needs its own ceiling for the client address.
        foreach (['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test'] as $email) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->attempt($email, 'wrong-password');
            }
        }

        // Twenty-four entries were attempted; the ceiling is twenty.
        $this->assertSame(20, AuditLog::query()->count());
        $suppressed = AuditLog::query()->where('event', 'login_failures_suppressed')->get();
        $this->assertCount(1, $suppressed);
        $this->assertSame(20, $suppressed->first()->metadata['entries']);
        $this->assertSame(900, $suppressed->first()->metadata['window_seconds']);

        // Continuing adds nothing further.
        $this->attempt('e@example.test', 'wrong-password');
        $this->attempt('f@example.test', 'wrong-password');
        $this->assertSame(20, AuditLog::query()->count());
    }

    public function test_a_real_sign_in_is_still_recorded_after_failures_are_suppressed(): void
    {
        $user = $this->account('genuine@example.test');
        foreach (['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test'] as $email) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->attempt($email, 'wrong-password');
            }
        }
        $this->assertSame(20, AuditLog::query()->count());

        $this->attempt($user->email, self::PASSWORD)->assertRedirect(route('dashboard'));

        // Suppression covers unsuccessful attempts only; a genuine sign-in is never hidden.
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, AuditLog::query()->where('event', 'login')->count());
    }

    public function test_the_new_events_read_as_security_alerts_in_the_audit_trail(): void
    {
        $user = $this->account();
        foreach (['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test'] as $email) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->attempt($email, 'wrong-password');
            }
        }

        // Both events need a readable label and the alert colouring, otherwise the
        // audit screen shows a raw key and the alert count misses them.
        $lockout = AuditLog::query()->where('event', 'login_throttled')->first();
        $this->assertSame('Sign-in locked out', $lockout->event_label);
        $this->assertSame('red', $lockout->event_tone);

        $suppressed = AuditLog::query()->where('event', 'login_failures_suppressed')->sole();
        $this->assertSame('Repeated sign-in failures', $suppressed->event_label);
        $this->assertSame('red', $suppressed->event_tone);
    }

    public function test_an_oversized_email_is_refused_before_it_reaches_the_audit_trail(): void
    {
        $oversized = str_repeat('a', 250).'@example.test';

        $this->attempt($oversized, 'wrong-password')->assertSessionHasErrors('email');

        $this->assertSame(0, AuditLog::query()->count());
    }

    private function attempt(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->from(route('login'))->post(route('login.attempt'), [
            'email' => $email,
            'password' => $password,
            'confidentiality_acknowledged' => '1',
        ]);
    }

    private function account(string $email = 'owner@example.test'): User
    {
        return User::withoutEvents(fn () => User::query()->create([
            'name' => 'Sign-in Test Owner',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'role' => User::ROLE_SYSTEM_OWNER,
            'is_active' => true,
        ])->refresh());
    }
}
