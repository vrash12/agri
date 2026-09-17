<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

/**
 * Staff accounts are created by an administrator rather than self-registered, so the
 * password rule is the only thing standing between a convenient choice and a
 * guessable one.
 */
class AccountPasswordPolicyTest extends TestCase
{
    use ReferenceProvinceSchema;

    private User $owner;

    private Municipality $municipality;

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

        $province = Province::query()->where('name', 'Tarlac')->sole();
        $this->municipality = Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => 'Policy Test Municipality', 'code' => 'POLICYTEST', 'province' => 'Tarlac',
            'province_id' => $province->id, 'is_active' => true,
        ])->refresh());
        $this->owner = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Policy Test Owner', 'email' => 'policy-owner@example.test',
            'password' => Hash::make('unused-test-placeholder'), 'role' => User::ROLE_SYSTEM_OWNER,
            'is_active' => true,
        ])->refresh());
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->fakeBreachService();

        $this->createAccount('Short1234')->assertSessionHasErrors('password');

        $this->assertSame(1, User::query()->count());
    }

    public function test_an_eleven_character_password_is_still_refused(): void
    {
        $this->fakeBreachService();

        $this->createAccount('elevenchars')->assertSessionHasErrors('password');

        $this->assertSame(1, User::query()->count());
    }

    public function test_a_long_passphrase_is_accepted_and_stored_as_a_hash(): void
    {
        $this->fakeBreachService();
        $passphrase = 'harvest ledger tuesday rainfall';

        $this->createAccount($passphrase)->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'new-staff@example.test')->sole();
        $this->assertNotSame($passphrase, $created->password);
        $this->assertTrue(Hash::check($passphrase, $created->password));
    }

    public function test_a_password_found_in_a_breach_corpus_is_refused(): void
    {
        $passphrase = 'password123456789';
        // The service answers with the suffixes of every hash sharing the prefix.
        $this->fakeBreachService(substr(strtoupper(sha1($passphrase)), 5).':42');

        $this->createAccount($passphrase)->assertSessionHasErrors('password');

        $this->assertSame(1, User::query()->count());
    }

    public function test_an_unreachable_breach_service_does_not_block_account_creation(): void
    {
        // An office with no connection must still be able to add staff.
        Http::fake(['api.pwnedpasswords.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('offline')]);

        $this->createAccount('harvest ledger tuesday rainfall')->assertSessionHasNoErrors();

        $this->assertSame(2, User::query()->count());
    }

    /**
     * Stub the k-anonymity range lookup so the suite never reaches the network.
     */
    private function fakeBreachService(string $body = ''): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response($body, 200)]);
    }

    private function createAccount(string $password): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)
            ->withSession([EnforceIdleSession::LAST_ACTIVITY_KEY => now()->timestamp])
            ->from(route('admins.create'))
            ->post(route('admins.store'), [
                'name' => 'New Staff Member',
                'email' => 'new-staff@example.test',
                'role' => User::ROLE_MUNICIPAL_STAFF,
                'municipality_id' => $this->municipality->id,
                'is_active' => '1',
                'password' => $password,
                'password_confirmation' => $password,
            ]);
    }
}
