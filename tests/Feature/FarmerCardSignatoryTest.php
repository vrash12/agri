<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\Farmer;
use App\Models\User;
use App\Support\FarmerCardSignatory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Signature lines on the back of the farmer registry card. Every card has a line
 * for the cardholder; each office prints its own head beside it. The signatory
 * configured for Ramos, Tarlac must never appear on another office's cards,
 * including a same-named municipality under a different province.
 */
class FarmerCardSignatoryTest extends TestCase
{
    private const RAMOS = 1;

    private const ANAO = 2;

    private const OTHER_RAMOS = 3;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array', 'session.driver' => 'array', 'services.google_maps.key' => '', 'services.google_maps.map_id' => '']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->schema();

        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'Tarlac', 'is_active' => true],
            ['id' => 2, 'name' => 'Elsewhere', 'is_active' => true],
        ]);
        DB::table('municipalities')->insert([
            ['id' => self::RAMOS, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 1, 'is_active' => true],
            ['id' => self::ANAO, 'name' => 'Anao', 'province' => 'Tarlac', 'province_id' => 1, 'is_active' => true],
            // Same name, different supervising province; the legacy string even claims Tarlac.
            ['id' => self::OTHER_RAMOS, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 2, 'is_active' => true],
        ]);
    }

    public function test_ramos_farmers_receive_the_configured_signatory(): void
    {
        $this->assertSame(
            ['name' => 'Engr. Dennish C. Pascua', 'title' => 'Head Agriculturist'],
            app(FarmerCardSignatory::class)->forFarmer($this->farmer(self::RAMOS))
        );
    }

    public function test_other_offices_print_no_signatory(): void
    {
        $signatories = app(FarmerCardSignatory::class);

        $this->assertNull($signatories->forFarmer($this->farmer(self::ANAO)));
        $this->assertNull($signatories->forFarmer($this->farmer(self::OTHER_RAMOS)));
    }

    public function test_an_inactive_supervising_province_or_municipality_fails_closed(): void
    {
        $farmer = $this->farmer(self::RAMOS);

        DB::table('provinces')->where('id', 1)->update(['is_active' => false]);
        $this->assertNull(app(FarmerCardSignatory::class)->forFarmer($farmer));

        DB::table('provinces')->where('id', 1)->update(['is_active' => true]);
        DB::table('municipalities')->where('id', self::RAMOS)->update(['is_active' => false]);
        $this->assertNull(app(FarmerCardSignatory::class)->forFarmer($farmer));
    }

    public function test_an_incomplete_entry_prints_nothing_rather_than_half_a_signature(): void
    {
        config(['farmer_card.signatories' => [
            ['municipalities' => ['Ramos'], 'province' => 'Tarlac', 'name' => 'Engr. Dennish C. Pascua', 'title' => ' '],
        ]]);

        $this->assertNull(app(FarmerCardSignatory::class)->forFarmer($this->farmer(self::RAMOS)));
    }

    public function test_each_configured_office_prints_its_own_head(): void
    {
        config(['farmer_card.signatories' => array_merge(config('farmer_card.signatories'), [
            ['municipalities' => ['Anao'], 'province' => 'Tarlac', 'name' => 'Sample Anao Head', 'title' => 'Municipal Agriculturist'],
        ])]);
        $signatories = app(FarmerCardSignatory::class);

        $this->assertSame('Sample Anao Head', $signatories->forFarmer($this->farmer(self::ANAO))['name']);
        $this->assertSame('Engr. Dennish C. Pascua', $signatories->forFarmer($this->farmer(self::RAMOS))['name']);
    }

    public function test_the_ramos_card_prints_the_signature_line_with_name_and_title(): void
    {
        $farmer = $this->farmer(self::RAMOS);

        $this->as($this->staff(self::RAMOS))->get(route('farmers.id-card', $farmer))->assertOk()
            ->assertSee('AgriLGU ID')
            ->assertSee($farmer->agri_gov_id)
            ->assertSee(asset('images/branding/agrilgu-wordmark-v1.png'), false)
            ->assertSee(asset('images/branding/agrilgu-mark-v1.png'), false)
            ->assertSee('brandWordmark:', false)
            ->assertSee('brandMark:', false)
            ->assertDontSee('AgriGOV')
            ->assertSee('class="has-signatory"', false)
            ->assertSee('<strong>ENGR. DENNISH C. PASCUA</strong>', false)
            ->assertSee('<small>Head Agriculturist</small>', false)
            // The canvas export receives the same signatory for the digital ID and PNG downloads.
            ->assertSee('"name":"ENGR. DENNISH C. PASCUA","title":"Head Agriculturist"', false);
    }

    public function test_another_office_card_keeps_the_unsigned_footer(): void
    {
        $farmer = $this->farmer(self::ANAO);

        $this->as($this->staff(self::ANAO))->get(route('farmers.id-card', $farmer))->assertOk()
            ->assertDontSee('PASCUA')
            ->assertDontSee('farmer-card-signatory"', false)
            ->assertSee('signatory: null', false);
    }

    public function test_every_card_has_a_cardholder_signature_line_over_the_farmers_printed_name(): void
    {
        foreach ([self::RAMOS, self::ANAO] as $municipalityId) {
            $farmer = Farmer::create(['municipality_id' => $municipalityId, 'first_name' => 'María',
                'middle_name' => 'Dela', 'last_name' => 'Peña', 'farm_location' => 'Sample barangay']);

            $this->as($this->staff($municipalityId))->get(route('farmers.id-card', $farmer))->assertOk()
                ->assertSee('farmer-card-holder-signature', false)
                ->assertSee('<strong>MARÍA DELA PEÑA</strong>', false)
                ->assertSee("<small>Cardholder's signature</small>", false)
                // The digital ID and PNG downloads draw the same line from this value.
                ->assertSee('holderName: '.json_encode('MARÍA DELA PEÑA', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false);

            auth()->logout();
        }
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession([EnforceIdleSession::LAST_ACTIVITY_KEY => now()->timestamp]);
    }

    private function staff(int $municipalityId): User
    {
        return User::create(['name' => 'Registry staff '.$municipalityId, 'email' => 'card-'.$municipalityId.'@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $municipalityId, 'is_active' => true]);
    }

    private function farmer(int $municipalityId): Farmer
    {
        return Farmer::create(['municipality_id' => $municipalityId, 'first_name' => 'Sample',
            'last_name' => 'Farmer', 'farm_location' => 'Sample barangay']);
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->foreignId('province_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'email', 'password', 'role'] as $field) {
                $table->string($field);
            }
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->foreignId('province_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained();
            $table->string('first_name');
            $table->string('last_name');
            foreach (['middle_name', 'ext_name', 'rsbsa_no', 'ffrs', 'contact_number', 'farm_location', 'farm_municipality', 'farm_province', 'ecosystem', 'profile_photo_path', 'public_map_token'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $field) {
                $table->boolean($field)->default(false);
            }
            $table->date('date_of_birth')->nullable();
            $table->decimal('farm_area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained();
            $table->string('name');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
    }
}
