<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FarmerPortalTest extends TestCase
{
    private string $passphrase;

    private bool $breachedPassword = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Configure the disposable connection before any schema or model access.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array', 'session.driver' => 'array', 'auth.defaults.guard' => 'web']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->passphrase = bin2hex(random_bytes(16));
        \Illuminate\Support\Facades\Http::fake(['api.pwnedpasswords.com/*' => fn () => \Illuminate\Support\Facades\Http::response($this->breachedPassword ? substr(strtoupper(sha1($this->passphrase)), 5).':5' : '')]);
        $this->schema();
        $this->fixtures();
    }

    public function test_guests_can_open_login_without_activation_prompt_and_cannot_read_farmer_pages(): void
    {
        $this->get(route('farmer-portal.login'))
            ->assertOk()
            ->assertSee('Farmer sign in')
            ->assertSee('Office sign in')
            ->assertSee('AgriGOV ID or RSBSA number')
            ->assertDontSee('activation code')
            ->assertDontSee('Activate my account');
        $this->get(route('farmer-portal.activate'))->assertOk()->assertSee('activation_code');
        foreach (['home', 'profile', 'parcels', 'assistance', 'harvests'] as $page) {
            $this->get(route('farmer-portal.'.$page))->assertRedirect(route('farmer-portal.login'));
        }
        $this->getJson($this->geometry(1))->assertUnauthorized();
        $this->getJson(route('farmer-portal.parcels.geometries'))->assertUnauthorized();
    }

    public function test_staff_issue_hashed_one_time_activation_without_changing_farmer_or_leaking_code_to_session_or_audit(): void
    {
        $farmerBefore = Farmer::findOrFail(1)->getAttributes();
        $response = $this->actingAs(User::findOrFail(1))->post($this->issueUrl(), $this->issuePayload());
        $response->assertOk();
        $code = $response->viewData('activationCode');
        $this->assertIsString($code);
        $this->assertGreaterThanOrEqual(32, strlen($code));
        $account = FarmerPortalAccount::firstOrFail();
        $this->assertSame(1, $account->farmer_id);
        $this->assertSame(1, $account->municipality_id);
        $this->assertSame('AGRI-F-000001', $account->login_id);
        $this->assertSame(hash('sha256', $code), $account->activation_code_hash);
        $this->assertNull($account->password);
        $this->assertNull($account->activated_at);
        $this->assertTrue($account->activation_expires_at->isFuture());
        $this->assertSame($farmerBefore, Farmer::findOrFail(1)->getAttributes());
        $this->assertStringNotContainsString($code, json_encode(session()->all()));
        $this->assertStringNotContainsString($code, DB::table('audit_logs')->get()->toJson());
        $this->assertStringNotContainsString($account->activation_code_hash, DB::table('audit_logs')->get()->toJson());
        $this->get(route('farmers.portal-account.show', 1))->assertOk()->assertDontSee($code);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_missing_identity_confirmation_or_stale_version_cannot_issue_or_replace_an_account(): void
    {
        $this->actingAs(User::findOrFail(1));
        $payload = $this->issuePayload();
        unset($payload['identity_verified']);
        $this->postJson($this->issueUrl(), $payload)->assertUnprocessable()->assertJsonValidationErrors('identity_verified');
        $this->postJson($this->issueUrl(), array_replace($this->issuePayload(), ['_record_version' => str_repeat('0', 64)]))
            ->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame(0, FarmerPortalAccount::count());
        $firstVersion = $this->issuePayload();
        $this->post($this->issueUrl(), $firstVersion)->assertOk();
        $accountBefore = FarmerPortalAccount::firstOrFail()->getAttributes();
        $this->postJson($this->issueUrl(), $firstVersion)->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame($accountBefore, FarmerPortalAccount::firstOrFail()->getAttributes());
    }

    public function test_foreign_staff_veterinary_and_oversight_accounts_cannot_issue_or_disable(): void
    {
        $this->account();
        foreach ([2, 3, 4, 5] as $userId) {
            $this->actingAs(User::findOrFail($userId));
            $this->postJson($this->issueUrl(), $this->issuePayload())->assertForbidden();
            $this->postJson(route('farmers.portal-account.disable', 1), ['_record_version' => $this->version()])->assertForbidden();
        }
        $this->assertTrue(FarmerPortalAccount::firstOrFail()->is_active);
        $this->actingAs(User::findOrFail(6))->post($this->issueUrl(), $this->issuePayload())->assertOk();
    }

    public function test_valid_activation_consumes_code_sets_password_and_only_authenticates_farmer_guard(): void
    {
        $code = bin2hex(random_bytes(16));
        $this->account(['password' => null, 'activated_at' => null, 'activation_code_hash' => hash('sha256', $code),
            'activation_expires_at' => now()->addDay()]);
        $this->post(route('farmer-portal.activate.submit'), $this->activationPayload($code))->assertRedirect();
        $account = FarmerPortalAccount::firstOrFail();
        $this->assertTrue(Hash::check($this->passphrase, $account->password));
        $this->assertNotNull($account->activated_at);
        $this->assertNull($account->activation_code_hash);
        $this->assertNull($account->activation_expires_at);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertStringNotContainsString($code, json_encode(session()->all()));
        $this->assertStringNotContainsString($this->passphrase, json_encode(session()->all()));
        Auth::guard('farmer')->logout();
        $this->postJson(route('farmer-portal.activate.submit'), $this->activationPayload($code))->assertUnprocessable();
    }

    public function test_expired_unknown_and_invalid_activation_use_generic_errors_and_never_flash_secrets(): void
    {
        $code = bin2hex(random_bytes(16));
        $this->account(['password' => null, 'activated_at' => null, 'activation_code_hash' => hash('sha256', $code),
            'activation_expires_at' => now()->subMinute()]);
        $expired = $this->postJson(route('farmer-portal.activate.submit'), $this->activationPayload($code));
        $expired->assertUnprocessable();
        $unknown = $this->postJson(route('farmer-portal.activate.submit'), array_replace($this->activationPayload($code), ['login_id' => 'AGRI-F-999999']));
        $unknown->assertUnprocessable();
        $this->assertSame($expired->json('errors'), $unknown->json('errors'));
        $this->from(route('farmer-portal.activate'))->post(route('farmer-portal.activate.submit'), $this->activationPayload($code))->assertSessionHasErrors();
        $this->assertStringNotContainsString($code, json_encode(session()->getOldInput()));
        $this->assertStringNotContainsString($this->passphrase, json_encode(session()->getOldInput()));
        $this->assertNull(FarmerPortalAccount::firstOrFail()->activated_at);
    }

    public function test_password_requires_fifteen_characters_confirmation_and_a_bcrypt_safe_length(): void
    {
        $code = bin2hex(random_bytes(16));
        $this->account(['password' => null, 'activated_at' => null, 'activation_code_hash' => hash('sha256', $code),
            'activation_expires_at' => now()->addDay()]);
        foreach ([substr($this->passphrase, 0, 14), str_repeat('x', 73)] as $password) {
            $this->postJson(route('farmer-portal.activate.submit'), array_replace($this->activationPayload($code),
                ['password' => $password, 'password_confirmation' => $password]))->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson(route('farmer-portal.activate.submit'), array_replace($this->activationPayload($code), ['password_confirmation' => 'mismatch']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertNull(FarmerPortalAccount::firstOrFail()->activated_at);
    }

    public function test_login_uses_stable_id_or_unique_rsbsa_and_rejects_duplicates(): void
    {
        $this->account();
        $this->login()->assertRedirect(route('farmer-portal.home'));
        $this->assertAuthenticated('farmer');
        $this->assertGuest('web');
        $this->post(route('farmer-portal.logout'))->assertRedirect();
        $this->login('TEST-RSBSA-ONE')->assertRedirect(route('farmer-portal.home'));
        $this->post(route('farmer-portal.logout'))->assertRedirect();
        DB::table('farmers')->where('id', 2)->update(['rsbsa_no' => 'TEST-RSBSA-ONE']);
        $this->login('TEST-RSBSA-ONE', true)->assertUnprocessable();
        $this->assertGuest('farmer');
        $this->login()->assertRedirect(route('farmer-portal.home'));
    }

    public function test_login_failures_are_generic_rate_limited_and_do_not_persist_the_password(): void
    {
        $this->account();
        $wrong = bin2hex(random_bytes(16));
        $known = $this->postJson(route('farmer-portal.login.attempt'), ['login_id' => 'AGRI-F-000001', 'password' => $wrong]);
        $unknown = $this->postJson(route('farmer-portal.login.attempt'), ['login_id' => 'AGRI-F-999999', 'password' => $wrong]);
        $known->assertUnprocessable();
        $unknown->assertUnprocessable();
        $this->assertSame($known->json('errors'), $unknown->json('errors'));
        $this->from(route('farmer-portal.login'))->post(route('farmer-portal.login.attempt'), ['login_id' => 'AGRI-F-000001', 'password' => $wrong])->assertSessionHasErrors();
        $this->assertStringNotContainsString($wrong, json_encode(session()->getOldInput()));
        for ($i = 0; $i < 7; $i++) {
            $last = $this->postJson(route('farmer-portal.login.attempt'), ['login_id' => 'AGRI-F-000001', 'password' => $wrong]);
        }
        $this->assertContains($last->status(), [422, 429]);
        $this->login(trueJson: true)->assertStatus($last->status());
        $this->assertGuest('farmer');
    }

    public function test_farmer_pages_expose_only_own_profile_parcels_and_same_municipality_assistance(): void
    {
        $this->account();
        $this->login()->assertRedirect();
        $this->get(route('farmer-portal.home'))->assertOk()->assertDontSee('OtherPrivate');
        $this->get(route('farmer-portal.profile'))->assertOk()->assertSee('OwnFarmer')->assertDontSee('OtherPrivate');
        $this->get(route('farmer-portal.parcels'))->assertOk()->assertSee('Own parcel')->assertDontSee('Foreign parcel');
        $this->get(route('farmer-portal.assistance'))->assertOk()->assertSee('OWN-RELEASE')
            ->assertDontSee('FOREIGN-RELEASE')->assertDontSee('WRONG-MUNICIPALITY')->assertDontSee('UNLINKED-RELEASE');
        $this->get(route('farmer-portal.profile', ['farmer_id' => 2, 'municipality_id' => 2]))->assertOk()->assertDontSee('OtherPrivate');
        $this->getJson($this->geometry(1))->assertOk()->assertJsonPath('plot.id', 1)->assertJsonMissing(['farmer_id' => 2]);
        $this->getJson($this->geometry(2))->assertNotFound();
        $this->get(route('farmer-portal.parcels.map', 2))->assertNotFound();
    }

    public function test_farmer_guard_never_grants_office_or_registry_write_access(): void
    {
        $this->account();
        $this->login()->assertRedirect();
        foreach (['dashboard', 'farmers.index', 'rice-seed-distributions.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->postJson($this->issueUrl(), $this->issuePayload())->assertUnauthorized();
        $this->postJson(route('farmers.store'), ['first_name' => 'Attempt'])->assertUnauthorized();
        $this->assertSame(2, DB::table('farmers')->count());
    }

    public function test_office_session_alone_does_not_authenticate_a_farmer(): void
    {
        $this->account();
        $this->actingAs(User::findOrFail(1))->get(route('farmer-portal.home'))->assertRedirect(route('farmer-portal.login'));
        $this->getJson(route('farmer-portal.parcels.geometries'))->assertUnauthorized();
        $this->assertGuest('farmer');
    }

    public function test_disabled_account_moved_farmer_and_inactive_scope_reject_login(): void
    {
        $this->account();
        foreach ([['farmer_portal_accounts', 'is_active', false], ['farmers', 'municipality_id', 2],
            ['municipalities', 'is_active', false], ['provinces', 'is_active', false]] as [$table, $column, $value]) {
            $original = DB::table($table)->where('id', 1)->value($column);
            DB::table($table)->where('id', 1)->update([$column => $value]);
            $this->login(trueJson: true)->assertUnprocessable();
            $this->assertGuest('farmer');
            DB::table($table)->where('id', 1)->update([$column => $original]);
        }
    }

    public function test_version_scope_and_idle_changes_end_existing_farmer_sessions(): void
    {
        $this->account();
        $this->login()->assertRedirect();
        DB::table('farmer_portal_accounts')->where('id', 1)->increment('session_version');
        $this->get(route('farmer-portal.home'))->assertRedirect();
        $this->assertGuest('farmer');
        $this->login()->assertRedirect();
        DB::table('farmers')->where('id', 1)->update(['municipality_id' => 2]);
        $this->getJson(route('farmer-portal.home'))->assertUnauthorized();
        $this->assertGuest('farmer');
        DB::table('farmers')->where('id', 1)->update(['municipality_id' => 1]);
        $this->login()->assertRedirect();
        $this->withSession(['farmer_portal.last_activity_at' => now()->subMinutes(16)->timestamp])
            ->getJson(route('farmer-portal.home'))->assertUnauthorized();
        $this->assertGuest('farmer');
    }

    public function test_reissue_resets_password_and_invalidates_previous_activation_and_sessions(): void
    {
        $this->account();
        $this->login()->assertRedirect();
        $oldVersion = FarmerPortalAccount::firstOrFail()->session_version;
        $response = $this->actingAs(User::findOrFail(1), 'web')->post($this->issueUrl(), $this->issuePayload());
        $response->assertOk();
        $account = FarmerPortalAccount::firstOrFail();
        $this->assertNull($account->password);
        $this->assertNull($account->activated_at);
        $this->assertGreaterThan($oldVersion, $account->session_version);
        $this->getJson(route('farmer-portal.home'))->assertUnauthorized();
        $this->assertGuest('farmer');
        $code = $response->viewData('activationCode');
        $this->post($this->issueUrl(), $this->issuePayload())->assertOk();
        $this->postJson(route('farmer-portal.activate.submit'), $this->activationPayload($code))->assertUnprocessable();
    }

    public function test_disable_is_versioned_clears_credentials_and_cannot_be_undone_with_old_activation(): void
    {
        $this->account();
        $this->actingAs(User::findOrFail(1));
        $this->postJson(route('farmers.portal-account.disable', 1), ['_record_version' => str_repeat('0', 64)])
            ->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->post(route('farmers.portal-account.disable', 1), ['_record_version' => $this->version()])->assertRedirect();
        $account = FarmerPortalAccount::firstOrFail();
        $this->assertFalse($account->is_active);
        $this->assertNull($account->password);
        $this->assertNull($account->activation_code_hash);
        $this->login(trueJson: true)->assertUnprocessable();
    }

    public function test_logout_clears_farmer_access_and_responses_are_private_non_storable(): void
    {
        $this->account();
        $this->login()->assertRedirect();
        $response = $this->get(route('farmer-portal.profile'))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post(route('farmer-portal.logout'))->assertRedirect();
        $this->assertGuest('farmer');
        $this->get(route('farmer-portal.profile'))->assertRedirect(route('farmer-portal.login'));
    }

    public function test_another_farmer_in_the_same_municipality_is_still_private(): void
    {
        DB::table('farmers')->where('id', 2)->update(['municipality_id' => 1]);
        DB::table('rice_seed_distributions')->where('farmer_id', 2)->update(['municipality_id' => 1]);
        $this->account();
        $this->login();
        $this->get(route('farmer-portal.parcels'))->assertDontSee('Foreign parcel');
        $this->get(route('farmer-portal.assistance'))->assertDontSee('FOREIGN-RELEASE');
        $this->getJson($this->geometry(2))->assertNotFound();
    }

    public function test_parcel_payloads_and_crop_periods_are_bounded_and_scope_checked(): void
    {
        $this->account();
        $this->login();
        DB::table('parcel_crop_seasons')->insert([
            ['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'rice'],
            ['farm_plot_id' => 1, 'municipality_id' => 2, 'crop_year' => 2025, 'season' => 'dry', 'crop' => 'corn'],
        ]);
        $this->get(route('farmer-portal.parcels', ['year' => 2026]))->assertSee('Rice / Palay')->assertDontSee('Corn');
        $this->get(route('farmer-portal.parcels', ['year' => 2025]))->assertDontSee('Corn');
        $this->getJson(route('farmer-portal.parcels', ['year' => 100]))->assertUnprocessable();
        $this->get(route('farmer-portal.parcels.map', 1))->assertOk()->assertDontSee('polygon_json');
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode([['lat' => 999, 'lng' => 120], ['lat' => 10, 'lng' => 120], ['lat' => 11, 'lng' => 120]])]);
        $this->getJson($this->geometry(1))->assertUnprocessable();
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode(array_fill(0, 10001, ['lat' => 15, 'lng' => 120]))]);
        $this->getJson($this->geometry(1))->assertUnprocessable();
    }

    public function test_all_owned_parcels_map_endpoint_returns_crops_and_never_foreign_geometry(): void
    {
        $this->account();
        $this->login();
        DB::table('parcel_crop_seasons')->insert(['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'rice']);

        $this->getJson(route('farmer-portal.parcels.geometries', ['year' => 2026]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('plots.0.id', 1)
            ->assertJsonPath('plots.0.crops.0.crop', 'Rice / Palay')
            ->assertJsonPath('next_after_id', null)->assertJsonMissing(['farmer_id' => 2]);
        $this->getJson(route('farmer-portal.parcels.geometries', ['after_id' => 1]))
            ->assertOk()->assertJsonPath('plots', [])->assertJsonPath('total', 1);
        $this->assertStringContainsString('no-store', $this->getJson(route('farmer-portal.parcels.geometries'))->headers->get('Cache-Control'));
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode([['lat' => 999, 'lng' => 120], ['lat' => 10, 'lng' => 120], ['lat' => 11, 'lng' => 120]])]);
        $this->getJson(route('farmer-portal.parcels.geometries'))->assertOk()->assertJsonPath('plots.0.paths', null);
        $this->getJson(route('farmer-portal.parcels.geometries', ['year' => 100]))->assertUnprocessable();
        $this->getJson(route('farmer-portal.parcels.geometries', ['after_id' => -1]))->assertUnprocessable();
    }

    public function test_collection_map_retrieves_all_owned_parcels_beyond_the_record_page_with_constant_queries(): void
    {
        $this->account();
        $this->login();
        DB::table('farmers')->where('id', 2)->update(['municipality_id' => 1]);
        $base = (array) DB::table('farm_plots')->where('id', 1)->first();
        unset($base['id']);
        for ($i = 0; $i < 24; $i++) {
            DB::table('farm_plots')->insert(array_replace($base, ['name' => 'Additional owned parcel '.$i]));
        }
        DB::table('parcel_crop_seasons')->insert([
            ['farm_plot_id' => 1, 'municipality_id' => 2, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'corn'],
            ['farm_plot_id' => 2, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'vegetables'],
            ['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2025, 'season' => 'dry', 'crop' => 'rice'],
        ]);
        $page = $this->get(route('farmer-portal.parcels'))->assertOk()
            ->assertSee('data-collection-url', false)->assertSee('js/farmer-portal-map.js', false)
            ->assertDontSee('120.51')->assertDontSee('polygon_json');
        $this->assertCount(10, $page->viewData('plots'));
        $this->assertSame(25, $page->viewData('totals')['parcels']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson(route('farmer-portal.parcels.geometries', ['year' => 2026, 'farmer_id' => 2, 'municipality_id' => 2]))
            ->assertOk()->assertJsonCount(20, 'plots')->assertJsonPath('total', 25)
            ->assertJsonPath('plots.0.crops', []);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "farm_plots"'))->count();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, $queries, 'Map query count must not grow with each parcel.');
        $second = $this->getJson(route('farmer-portal.parcels.geometries', ['year' => 2026, 'after_id' => $first->json('next_after_id')]))
            ->assertOk()->assertJsonCount(5, 'plots')->assertJsonPath('next_after_id', null);
        $ids = array_column(array_merge($first->json('plots'), $second->json('plots')), 'id');
        $this->assertCount(25, array_unique($ids));
        $this->assertNotContains(2, $ids);
        $this->getJson(route('farmer-portal.parcels.geometries', ['year' => 2025]))
            ->assertOk()->assertJsonPath('plots.0.crops.0.crop', 'Rice / Palay');
    }

    public function test_collection_map_limits_byte_budget_and_reports_invalid_boundaries_without_hiding_valid_land(): void
    {
        $this->account();
        $this->login();
        $base = (array) DB::table('farm_plots')->where('id', 1)->first();
        // Keep the large fixtures valid polygons so the test proves the page
        // budget, rather than the geometry validator, controls cursoring.
        $largeRing = json_encode(array_map(
            fn (int $index): array => [
                'lat' => 15 + ($index % 2) / 100000,
                'lng' => 120 + ($index % 2) / 100000,
                'note' => str_repeat('x', 55),
            ],
            range(1, 9000)
        ));
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => $largeRing]);
        DB::table('farm_plots')->insert(array_replace($base, ['id' => 3, 'polygon_json' => $largeRing]));
        DB::table('farm_plots')->insert(array_replace($base, ['id' => 4, 'polygon_json' => json_encode(str_repeat('x', 1000001))]));
        DB::table('farm_plots')->insert(array_replace($base, ['id' => 5, 'polygon_json' => json_encode(array_fill(0, 10001, ['lat' => 15, 'lng' => 120]))]));
        DB::table('farm_plots')->insert(array_replace($base, ['id' => 6, 'color' => 'invalid']));
        $first = $this->getJson(route('farmer-portal.parcels.geometries'))
            ->assertOk()->assertJsonCount(1, 'plots')->assertJsonPath('next_after_id', 1);
        $second = $this->getJson(route('farmer-portal.parcels.geometries', ['after_id' => 1]))
            ->assertOk()->assertJsonCount(2, 'plots')->assertJsonPath('next_after_id', 4)
            ->assertJsonPath('plots.0.id', 3)->assertJsonPath('plots.1.paths', null);
        $this->assertNotEmpty($first->json('plots.0.paths'));
        $this->assertNotEmpty($second->json('plots.0.paths'));
        $this->assertStringNotContainsString('geometry_bytes', $second->getContent());
        $this->assertStringNotContainsString('polygon_json', $second->getContent());
        $third = $this->getJson(route('farmer-portal.parcels.geometries', ['after_id' => $second->json('next_after_id')]))
            ->assertOk()->assertJsonCount(2, 'plots')->assertJsonPath('next_after_id', null)
            ->assertJsonPath('plots.0.id', 5)->assertJsonPath('plots.0.paths', null)
            ->assertJsonPath('plots.1.id', 6)->assertJsonPath('plots.1.color', '#236344');
        $this->assertStringNotContainsString('polygon_json', $third->getContent());
    }

    public function test_collection_map_validation_and_empty_records_fail_safely(): void
    {
        $this->account();
        $this->login();
        foreach ([['year' => now()->year + 2], ['year' => [2026]], ['after_id' => 'invalid'], ['after_id' => 2147483648], ['after_id' => [1]]] as $parameters) {
            $this->getJson(route('farmer-portal.parcels.geometries', $parameters))->assertUnprocessable();
        }
        DB::table('farm_plots')->where('farmer_id', 1)->delete();
        $this->getJson(route('farmer-portal.parcels.geometries'))->assertOk()
            ->assertJsonPath('plots', [])->assertJsonPath('total', 0)->assertJsonPath('next_after_id', null);
        $this->get(route('farmer-portal.parcels'))->assertOk()->assertSee('No farm parcels recorded yet')
            ->assertDontSee('data-collection-url', false);
    }

    public function test_password_breach_check_and_multibyte_limits_are_enforced(): void
    {
        $code = bin2hex(random_bytes(16));
        $this->account(['password' => null, 'activated_at' => null, 'activation_code_hash' => hash('sha256', $code), 'activation_expires_at' => now()->addDay()]);
        $this->breachedPassword = true;
        $this->postJson(route('farmer-portal.activate.submit'), $this->activationPayload($code))->assertUnprocessable()->assertJsonValidationErrors('password');
        $value = str_repeat('ñ', 37);
        $this->postJson(route('farmer-portal.activate.submit'), array_replace($this->activationPayload($code), ['password' => $value, 'password_confirmation' => $value]))->assertUnprocessable();
        $this->postJson(route('farmer-portal.login.attempt'), ['login_id' => 'AGRI-F-000001', 'password' => $value])->assertUnprocessable();
    }

    public function test_activation_secrets_in_query_strings_are_not_copied_to_audit_urls(): void
    {
        $code = bin2hex(random_bytes(16));
        $this->account(['password' => null, 'activated_at' => null, 'activation_code_hash' => hash('sha256', $code), 'activation_expires_at' => now()->addDay()]);
        $this->post(route('farmer-portal.activate.submit', ['activation_code' => $code]), $this->activationPayload($code))->assertRedirect();
        $this->assertStringNotContainsString($code, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_office_login_ends_an_existing_farmer_session(): void
    {
        $this->account();
        $this->login();
        DB::table('users')->where('id', 1)->update(['password' => Hash::make($this->passphrase)]);
        $this->post(route('login.attempt'), ['email' => User::findOrFail(1)->email, 'password' => $this->passphrase, 'confidentiality_acknowledged' => '1'])->assertRedirect();
        $this->assertAuthenticated('web');
        $this->assertGuest('farmer');
    }

    public function test_schema_prevents_duplicate_accounts_and_supports_additive_rollback(): void
    {
        $account = $this->account();
        try {
            DB::table('farmer_portal_accounts')->insert(['farmer_id' => 1, 'municipality_id' => 1, 'login_id' => 'AGRI-F-DUPLICATE']);
            $this->fail('The database must reject two portal accounts for the same farmer.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame(1, FarmerPortalAccount::count());
        }
        DB::table('farmers')->where('id', 1)->delete();
        $this->assertNull(FarmerPortalAccount::find($account->id));
        (require database_path('migrations/2026_09_20_000100_create_farmer_portal_accounts_table.php'))->down();
        $this->assertFalse(Schema::hasTable('farmer_portal_accounts'));
        $this->assertTrue(Schema::hasTable('farmers'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_overview_has_owned_records_area_and_bounded_recent_lists_without_geometry(): void
    {
        $this->account();
        $this->login();
        $response = $this->get(route('farmer-portal.home'))->assertOk()
            ->assertSee('My plotted land')->assertSee('OWN-RELEASE')->assertSee('OWN-HARVEST')
            ->assertDontSee('FOREIGN-RELEASE')->assertDontSee('FOREIGN-HARVEST')->assertDontSee('WRONG-HARVEST')
            ->assertDontSee('polygon_json')->assertDontSee('120.51')->assertDontSee('Foreign parcel');
        $this->assertSame(['parcels' => 1, 'area_ha' => 1.2, 'area_recorded' => 1, 'assistance' => 1, 'harvests' => 1], $response->viewData('overview'));
        $harvest = (array) DB::table('harvest_records')->where('id', 1)->first();
        $release = (array) DB::table('rice_seed_distributions')->where('id', 1)->first();
        $plot = (array) DB::table('farm_plots')->where('id', 1)->first();
        unset($harvest['id'], $release['id'], $plot['id']);
        for ($i = 0; $i < 20; $i++) {
            DB::table('harvest_records')->insert($harvest);
            DB::table('rice_seed_distributions')->insert($release);
            DB::table('farm_plots')->insert($plot);
        }
        $response = $this->get(route('farmer-portal.home'))->assertOk();
        $this->assertSame(21, $response->viewData('overview')['harvests']);
        $this->assertSame(21, $response->viewData('overview')['assistance']);
        $this->assertCount(5, $response->viewData('recent')['harvests']);
        $this->assertCount(5, $response->viewData('recent')['assistance']);
        $this->assertCount(3, $response->viewData('recent')['parcels']);
    }

    public function test_harvests_require_both_farmer_and_municipality_and_keep_unknown_years_in_all_years(): void
    {
        DB::table('farmers')->where('id', 2)->update(['municipality_id' => 1]);
        DB::table('harvest_records')->where('farmer_id', 2)->update(['municipality_id' => 1]);
        DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'commodity' => 'corn', 'variety' => 'UNDATED-OWN', 'harvest_year' => null]);
        $this->account();
        $this->login();
        foreach ([[], ['year' => ''], ['farmer_id' => 2, 'municipality_id' => 2]] as $parameters) {
            $response = $this->get(route('farmer-portal.harvests', $parameters))->assertOk()
                ->assertSee('OWN-HARVEST')->assertSee('UNDATED-OWN')->assertDontSee('FOREIGN-HARVEST')
                ->assertDontSee('WRONG-HARVEST')->assertDontSee('UNLINKED-HARVEST');
            $this->assertSame(2, $response->viewData('harvests')->total());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->get(route('farmer-portal.harvests', ['year' => 2026]))->assertOk()->assertSee('OWN-HARVEST')->assertDontSee('UNDATED-OWN');
        $this->get(route('farmer-portal.harvests', ['year' => 2025]))->assertOk()->assertSee('No harvest records for this period')->assertDontSee('OWN-HARVEST');
        foreach ([['year' => 1899], ['year' => now()->year + 2], ['year' => [2026]], ['page' => 0], ['page' => 100001]] as $parameters) {
            $this->getJson(route('farmer-portal.harvests', $parameters))->assertUnprocessable();
        }
    }

    public function test_bad_related_record_links_cannot_expose_foreign_parcel_or_distribution_program(): void
    {
        DB::table('harvest_records')->where('id', 1)->update(['farm_plot_id' => 2]);
        DB::table('rice_distribution_batches')->insert(['id' => 1, 'municipality_id' => 2, 'title' => 'PRIVATE-FOREIGN-PROGRAM']);
        DB::table('rice_seed_distributions')->where('id', 1)->update(['batch_id' => 1]);
        $this->account();
        $this->login();
        $harvests = $this->get(route('farmer-portal.harvests'))->assertOk()->assertSee('OWN-HARVEST')
            ->assertSee('No parcel linked to your account')->assertDontSee('Foreign parcel');
        $this->assertNull($harvests->viewData('harvests')->first()->farmPlot);
        $this->get(route('farmer-portal.assistance'))->assertOk()->assertDontSee('PRIVATE-FOREIGN-PROGRAM');
        $this->get(route('farmer-portal.home'))->assertOk()->assertDontSee('Foreign parcel');
        $this->getJson($this->geometry(2))->assertNotFound();
    }

    public function test_quantity_totals_keep_types_units_and_missing_values_separate(): void
    {
        foreach ([['rice', 'kg', null], ['rice', 'sack', 2], ['corn', 'kg', 10], ['fruit', 'piece', null]] as [$commodity, $unit, $quantity]) {
            DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'commodity' => $commodity, 'quantity_unit' => $unit, 'quantity' => $quantity, 'harvest_year' => 2026]);
        }
        foreach ([['rice_seed', 'kg', null], ['rice_seed', 'sack', 1], ['fertilizer', 'kg', 50], ['vegetable_seed', 'pack', null]] as [$category, $unit, $quantity]) {
            DB::table('rice_seed_distributions')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'input_category' => $category, 'quantity_unit' => $unit, 'kgs_received' => $quantity]);
        }
        $this->account();
        $this->login();
        $harvests = $this->get(route('farmer-portal.harvests'))->assertOk()
            ->assertSee('1 without quantity')->assertDontSee('0 without quantity')->assertDontSee('@if', false)
            ->viewData('totals')->keyBy(fn ($row) => $row->commodity.':'.$row->quantity_unit);
        $this->assertCount(4, $harvests);
        $this->assertSame(100.0, (float) $harvests['rice:kg']->total_quantity);
        $this->assertSame(2, (int) $harvests['rice:kg']->records);
        $this->assertSame(1, (int) $harvests['rice:kg']->quantities_recorded);
        $this->assertSame(2.0, (float) $harvests['rice:sack']->total_quantity);
        $this->assertSame(10.0, (float) $harvests['corn:kg']->total_quantity);
        $this->assertNull($harvests['fruit:piece']->total_quantity);
        $assistance = $this->get(route('farmer-portal.assistance'))->assertOk()
            ->assertSee('1 without quantity')->assertDontSee('0 without quantity')->assertDontSee('@if', false)
            ->viewData('totals')->keyBy(fn ($row) => $row->input_category.':'.$row->quantity_unit);
        $this->assertCount(4, $assistance);
        $this->assertSame(20.0, (float) $assistance['rice_seed:kg']->total_quantity);
        $this->assertSame(50.0, (float) $assistance['fertilizer:kg']->total_quantity);
        $this->assertSame(1.0, (float) $assistance['rice_seed:sack']->total_quantity);
        $this->assertNull($assistance['vegetable_seed:pack']->total_quantity);
    }

    public function test_history_pagination_retains_year_and_does_not_load_all_records(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'commodity' => 'rice', 'harvest_year' => 2025, 'variety' => 'OLDER-'.$i]);
            DB::table('rice_seed_distributions')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'input_category' => 'rice_seed']);
        }
        $this->account();
        $this->login();
        $harvests = $this->get(route('farmer-portal.harvests', ['year' => 2025]))->assertOk()->viewData('harvests');
        $this->assertCount(15, $harvests);
        $this->assertSame(16, $harvests->total());
        $this->assertStringContainsString('year=2025', $harvests->nextPageUrl());
        $this->assertCount(1, $this->get($harvests->nextPageUrl())->assertOk()->viewData('harvests'));
        $releases = $this->get(route('farmer-portal.assistance'))->assertOk()->viewData('releases');
        $this->assertCount(15, $releases);
        $this->assertSame(17, $releases->total());
        $this->assertCount(2, $this->get($releases->nextPageUrl())->assertOk()->viewData('releases'));
    }

    public function test_profile_and_release_details_show_recorded_values_and_escape_text(): void
    {
        DB::table('farmers')->where('id', 1)->update(['farm_province' => 'Recorded province', 'ecosystem' => 'Irrigated', 'ecosystem_source' => 'Canal', 'public_map_token' => 'PRIVATE-TOKEN']);
        DB::table('rice_distribution_batches')->insert(['id' => 1, 'municipality_id' => 1, 'title' => 'Local seed program', 'reference' => 'TEST-BATCH', 'planting_year' => 2026, 'planting_season' => 'wet']);
        $unsafe = '<img src=x onerror=alert(1)>';
        DB::table('rice_seed_distributions')->where('id', 1)->update(['batch_id' => 1, 'seed_bags' => 0, 'seed_bag_kg' => 20, 'lot_series' => $unsafe, 'claimed_area_ha' => 0.75, 'registered_rice_area_ha' => 1, 'seed_variety_planted' => 'NSIC Rc 222', 'seed_class' => 'Certified', 'crop_establishment' => 'Transplanted', 'date_of_sowing_label' => 'June 2026']);
        $this->account();
        $this->login();
        $this->get(route('farmer-portal.profile'))->assertOk()->assertSee('Recorded province')->assertSee('Irrigated')->assertSee('Canal')->assertDontSee('PRIVATE-TOKEN');
        $this->get(route('farmer-portal.assistance'))->assertOk()->assertSee('Local seed program')->assertSee('NSIC Rc 222')->assertSee('Certified')->assertSee('20.00 kg per bag')->assertSee('0.75 ha')->assertSee('June 2026')->assertSee($unsafe)->assertDontSee($unsafe, false);
    }

    public function test_empty_overview_and_missing_quantities_do_not_invent_zero_production(): void
    {
        DB::table('farm_plots')->where('farmer_id', 1)->delete();
        DB::table('harvest_records')->where('farmer_id', 1)->delete();
        DB::table('rice_seed_distributions')->where('farmer_id', 1)->delete();
        $this->account();
        $this->login();
        $response = $this->get(route('farmer-portal.home'))->assertOk()->assertSee('No farm parcels recorded yet')->assertSee('No harvest records yet');
        $this->assertNull($response->viewData('overview')['area_ha']);
        $this->assertSame(0, $response->viewData('overview')['harvests']);
        $this->get(route('farmer-portal.harvests'))->assertOk()->assertSee('No harvest records for this period');
        $this->get(route('farmer-portal.assistance'))->assertOk()->assertSee('No assistance releases recorded yet');
    }

    private function login(string $id = 'AGRI-F-000001', bool $trueJson = false)
    {
        $method = $trueJson ? 'postJson' : 'post';

        return $this->$method(route('farmer-portal.login.attempt'), ['login_id' => $id, 'password' => $this->passphrase]);
    }

    private function account(array $extra = []): FarmerPortalAccount
    {
        DB::table('farmer_portal_accounts')->insert(array_replace(['id' => 1, 'farmer_id' => 1, 'municipality_id' => 1,
            'login_id' => 'AGRI-F-000001', 'password' => Hash::make($this->passphrase), 'activated_at' => now(),
            'is_active' => true, 'session_version' => 1, 'created_at' => now(), 'updated_at' => now()], $extra));

        return FarmerPortalAccount::findOrFail(1);
    }

    private function activationPayload(string $code): array
    {
        return ['login_id' => 'AGRI-F-000001', 'activation_code' => $code, 'password' => $this->passphrase, 'password_confirmation' => $this->passphrase];
    }

    private function issueUrl(): string
    {
        return route('farmers.portal-account.issue', 1);
    }

    private function issuePayload(): array
    {
        return ['identity_verified' => '1', '_record_version' => $this->version()];
    }

    private function version(): string
    {
        return ConcurrentWrite::version(FarmerPortalAccount::first() ?? Farmer::findOrFail(1));
    }

    private function geometry(int $plot): string
    {
        return route('farmer-portal.parcels.geometry', $plot);
    }

    private function fixtures(): void
    {
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Home province'], ['id' => 2, 'name' => 'Other province']]);
        DB::table('municipalities')->insert([['id' => 1, 'name' => 'Own Municipality', 'province_id' => 1], ['id' => 2, 'name' => 'Other Municipality', 'province_id' => 2]]);
        foreach ([1 => ['municipal_staff', 1, null], 2 => ['municipal_staff', 2, null], 3 => ['super_admin', null, 1],
            4 => ['system_owner', null, null], 5 => ['provincial_vet', null, 1], 6 => ['provincial_staff', null, 1]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Test Staff', 'email' => 'portal'.$id.'@example.test', 'password' => 'unused',
                'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province]);
        }
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1, 'first_name' => 'OwnFarmer', 'last_name' => 'Example', 'rsbsa_no' => 'TEST-RSBSA-ONE'],
            ['id' => 2, 'municipality_id' => 2, 'first_name' => 'OtherPrivate', 'last_name' => 'Example', 'rsbsa_no' => 'TEST-RSBSA-TWO']]);
        foreach ([1 => 'Own parcel', 2 => 'Foreign parcel'] as $id => $name) {
            DB::table('farm_plots')->insert(['id' => $id, 'farmer_id' => $id, 'name' => $name, 'area_ha' => 1.2, 'color' => '#123456',
                'polygon_json' => json_encode([['lat' => 15.5, 'lng' => 120.5], ['lat' => 15.5, 'lng' => 120.51], ['lat' => 15.51, 'lng' => 120.51], ['lat' => 15.5, 'lng' => 120.5]])]);
        }
        foreach ([[1, 1, 'OWN-RELEASE'], [2, 2, 'FOREIGN-RELEASE'], [2, 1, 'WRONG-MUNICIPALITY'], [1, null, 'UNLINKED-RELEASE']] as [$municipality, $farmer, $label]) {
            DB::table('rice_seed_distributions')->insert(['municipality_id' => $municipality, 'farmer_id' => $farmer,
                'seed_variety_claimed' => $label, 'input_category' => 'rice_seed', 'kgs_received' => 20, 'quantity_unit' => 'kg', 'date_received' => '2026-09-01']);
        }
        foreach ([[1, 1, 'OWN-HARVEST'], [2, 2, 'FOREIGN-HARVEST'], [2, 1, 'WRONG-HARVEST'], [1, null, 'UNLINKED-HARVEST']] as [$municipality, $farmer, $label]) {
            DB::table('harvest_records')->insert(['municipality_id' => $municipality, 'farmer_id' => $farmer, 'farm_plot_id' => $farmer, 'commodity' => 'rice', 'variety' => $label, 'quantity' => 100, 'quantity_unit' => 'kg', 'date_harvested' => '2026-09-02', 'harvest_year' => 2026, 'season' => 'wet', 'area_harvested_ha' => 1.1]);
        }
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('province')->nullable();
            $t->unsignedBigInteger('province_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->unsignedBigInteger('municipality_id')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('farmers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('first_name');
            $t->string('last_name');
            foreach (['middle_name', 'ext_name', 'owner_name', 'rsbsa_no', 'ffrs', 'gender', 'contact_number', 'farm_location', 'farm_municipality', 'farm_province', 'ecosystem', 'ecosystem_source', 'profile_photo_path', 'public_map_token'] as $field) {
                $t->string($field)->nullable();
            }
            foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $field) {
                $t->boolean($field)->default(false);
            }
            $t->date('date_of_birth')->nullable();
            $t->decimal('farm_area_ha', 15, 4)->nullable();
            $t->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('farmer_id');
            $t->string('name');
            $t->string('color');
            $t->json('polygon_json');
            $t->decimal('area_ha', 15, 4);
            $t->decimal('centroid_lat', 12, 8)->nullable();
            $t->decimal('centroid_lng', 12, 8)->nullable();
            $t->timestamps();
        });
        Schema::create('rice_distribution_batches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('reference')->nullable();
            $t->string('planting_season')->nullable();
            $t->integer('planting_year')->nullable();
            $t->string('title')->nullable();
            $t->timestamps();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->unsignedBigInteger('farmer_id')->nullable();
            $t->unsignedBigInteger('batch_id')->nullable();
            foreach (['seed_variety_claimed', 'input_category', 'quantity_unit', 'input_notes', 'lot_series', 'crop_establishment', 'date_of_sowing_label', 'seed_variety_planted', 'seed_class'] as $field) {
                $t->string($field)->nullable();
            }
            $t->decimal('kgs_received', 15, 4)->nullable();
            $t->integer('seed_bags')->nullable();
            foreach (['seed_bag_kg', 'claimed_area_ha', 'registered_rice_area_ha'] as $field) {
                $t->decimal($field, 15, 4)->nullable();
            }
            $t->date('date_received')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_09_19_000100_create_parcel_crop_seasons_table.php'))->up();
        (require database_path('migrations/2026_09_19_000100_create_harvest_records_table.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $migrations = glob(database_path('migrations/*_create_farmer_portal_accounts_table.php'));
        $this->assertCount(1, $migrations);
        (require $migrations[0])->up();
    }
}
