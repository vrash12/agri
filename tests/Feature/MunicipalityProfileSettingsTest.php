<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityProfile;
use App\Models\User;
use App\Modules\LocalGovernment\Application\MunicipalityProfiles;
use App\Modules\LocalGovernment\Application\MunicipalityWebsites;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MunicipalityProfileSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array', 'session.driver' => 'array', 'services.google_maps.key' => '', 'services.google_maps.map_id' => '']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('region_id')->nullable()->constrained();
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->foreignId('province_id')->constrained();
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'email', 'password', 'role'] as $field) {
                $table->string($field);
            }
            $table->foreignId('region_id')->nullable()->constrained();
            $table->foreignId('province_id')->nullable()->constrained();
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->boolean('visitor_mode')->default(false);
            $table->timestamp('evaluation_expires_at')->nullable();
            $table->boolean('evaluation_password_pending')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained();
            foreach (['first_name', 'last_name', 'middle_name', 'ext_name', 'rsbsa_no', 'ffrs', 'contact_number', 'farm_location', 'farm_municipality', 'farm_province', 'ecosystem', 'profile_photo_path', 'public_map_token'] as $field) {
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
        (require database_path('migrations/2026_10_19_000100_create_municipality_profiles_table.php'))->up();
        (require database_path('migrations/2026_10_19_000200_create_municipality_websites_table.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        Schema::table('audit_logs', fn (Blueprint $table) => $table->unsignedBigInteger('province_id')->nullable());
        DB::table('regions')->insert(['id' => 1, 'name' => 'Region III', 'is_active' => true]);
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'Tarlac', 'region_id' => 1, 'is_active' => true],
            ['id' => 2, 'name' => 'Elsewhere', 'region_id' => null, 'is_active' => true],
        ]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 1, 'is_active' => true],
            ['id' => 2, 'name' => 'Anao', 'province' => 'Tarlac', 'province_id' => 1, 'is_active' => true],
            ['id' => 3, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 2, 'is_active' => true],
        ]);
    }

    public function test_the_directory_lists_unconfigured_lgus_only_within_scope_and_is_paginated(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->get(route('settings.lgu.index'))->assertOk()->assertSee('Ramos')->assertDontSee('Anao')->assertSee('Not configured');
        $response = $this->asRole(User::ROLE_SUPER_ADMIN, null, 1)->get(route('settings.lgu.index'));
        $response->assertOk()->assertSee('Anao');
        $this->assertCount(2, $response->viewData('municipalities'));
        $response = $this->asRole(User::ROLE_SYSTEM_OWNER, null, null)->get(route('settings.lgu.index'));
        $this->assertCount(3, $response->viewData('municipalities'));
    }

    public function test_authorized_managers_save_a_profile_and_log_only_public_details(): void
    {
        foreach ([[User::ROLE_MUNICIPAL_HEAD, 1, null, 1], [User::ROLE_SUPER_ADMIN, null, 1, 2], [User::ROLE_SYSTEM_OWNER, null, null, 3]] as [$role, $municipality, $province, $target]) {
            $this->asRole($role, $municipality, $province)->put(route('settings.lgu.update', $target), $this->data($target))
                ->assertRedirect(route('settings.lgu.edit', $target));
            $this->assertDatabaseHas('municipality_profiles', ['municipality_id' => $target, 'office_name' => 'Local Agriculture Office']);
        }
        $this->assertSame(3, DB::table('audit_logs')->where('module', 'LGU settings')->count());
        $this->assertStringNotContainsString('logo_path', DB::table('audit_logs')->where('module', 'LGU settings')->value('new_values'));
    }

    public function test_staff_and_regional_heads_can_view_but_cannot_modify_profiles(): void
    {
        foreach ([[User::ROLE_MUNICIPAL_STAFF, 1, null, null], [User::ROLE_PROVINCIAL_STAFF, null, 1, null], [User::ROLE_REGIONAL_HEAD, null, null, 1]] as [$role, $municipality, $province, $region]) {
            $this->asRole($role, $municipality, $province, $region)->get(route('settings.lgu.edit', 1))->assertOk()->assertSee('You can view this profile.');
            $this->put(route('settings.lgu.update', 1), $this->data())->assertForbidden();
        }
        $this->assertDatabaseCount('municipality_profiles', 0);
    }

    public function test_foreign_lgu_pages_writes_and_logo_requests_do_not_expose_profiles(): void
    {
        foreach ([[User::ROLE_MUNICIPAL_HEAD, 1, null, 2], [User::ROLE_SUPER_ADMIN, null, 1, 3]] as [$role, $municipality, $province, $target]) {
            $this->asRole($role, $municipality, $province)->get(route('settings.lgu.edit', $target))->assertNotFound();
            $this->get(route('settings.lgu.logo', $target))->assertNotFound();
            $this->put(route('settings.lgu.update', $target), $this->data($target))->assertNotFound();
        }
    }

    public function test_veterinary_evaluator_visitor_and_guest_access_is_denied(): void
    {
        $this->get(route('settings.lgu.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_PROVINCIAL_VET, User::ROLE_GIS_EVALUATOR] as $role) {
            $this->asRole($role, null, $role === User::ROLE_PROVINCIAL_VET ? 1 : null)->getJson(route('settings.lgu.index'))->assertForbidden();
        }
        $this->asRole(User::ROLE_SUPER_ADMIN, null, null, null, true)->getJson(route('settings.lgu.index'))->assertForbidden();
    }

    public function test_invalid_urls_location_pairs_signatories_and_logos_are_rejected(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD);
        foreach ([['website_url' => 'javascript:alert(1)'], ['website_url' => 'https://user:pass@example.test/'], ['latitude' => 91], ['latitude' => 15.5], ['signatory_title' => null], ['logo' => UploadedFile::fake()->create('seal.svg', 10, 'image/svg+xml')], ['logo' => UploadedFile::fake()->image('seal.png', 2500, 64)]] as $invalid) {
            $this->put(route('settings.lgu.update', 1), array_replace($this->data(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('municipality_profiles', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_logo_upload_replacement_removal_and_scoped_safe_delivery(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data() + ['logo' => UploadedFile::fake()->image('seal.png', 128, 128)])
            ->assertSessionHasNoErrors();
        $first = MunicipalityProfile::firstOrFail()->logo_path;
        Storage::disk('local')->assertExists($first);
        $this->get(route('settings.lgu.logo', 1))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->put(route('settings.lgu.update', 1), $this->data() + ['logo' => UploadedFile::fake()->image('new.png', 128, 128)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        $second = MunicipalityProfile::firstOrFail()->logo_path;
        $this->put(route('settings.lgu.update', 1), $this->data() + ['remove_logo' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($second);
        $this->get(route('settings.lgu.logo', 1))->assertNotFound();
    }

    public function test_stale_first_creation_and_updates_cannot_overwrite_newer_settings_or_leave_uploads(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD);
        $initial = $this->data();
        $this->put(route('settings.lgu.update', 1), $initial)->assertSessionHasNoErrors();
        $this->put(route('settings.lgu.update', 1), $initial + ['logo' => UploadedFile::fake()->image('stale.png', 128, 128)])->assertSessionHasErrors('_record_version');
        $edit = $this->data();
        $this->put(route('settings.lgu.update', 1), array_replace($edit, ['office_name' => 'New office name']))->assertSessionHasNoErrors();
        $this->put(route('settings.lgu.update', 1), $edit + ['logo' => UploadedFile::fake()->image('stale.png', 128, 128)])->assertSessionHasErrors('_record_version');
        $this->assertDatabaseHas('municipality_profiles', ['office_name' => 'New office name']);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_card_html_and_digital_payload_use_only_the_farmers_own_lgu_and_keep_the_qr_destination(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data() + ['logo' => UploadedFile::fake()->image('seal.png', 128, 128)])->assertSessionHasNoErrors();
        $farmer = Farmer::create(['municipality_id' => 1, 'first_name' => 'Sample', 'last_name' => 'Farmer']);
        $foreign = Farmer::create(['municipality_id' => 2, 'first_name' => 'Other', 'last_name' => 'Farmer']);
        $response = $this->get(route('farmers.id-card', $farmer));
        $response->assertOk()->assertSee('LOCAL AGRICULTURE OFFICE')->assertSee('SAMPLE LGU HEAD')
            ->assertSee('https://example.test/lgu')->assertSee('Sample Municipal Hall')->assertSee(route('settings.lgu.logo', 1), false)
            ->assertSee(route('farmers.public-land', $farmer->public_map_token), false)->assertSee('Cardholder')->assertSee('not a substitute for a Philippine national government ID');
        $this->asRole(User::ROLE_MUNICIPAL_STAFF, 2)->get(route('farmers.id-card', $foreign))->assertOk()->assertDontSee('Sample LGU Head')->assertDontSee('https://example.test/lgu');
    }

    public function test_inactive_lgus_fail_closed_and_profile_removal_is_not_an_available_route(): void
    {
        $this->asRole(User::ROLE_SYSTEM_OWNER, null, null);
        DB::table('municipalities')->where('id', 1)->update(['is_active' => false]);
        $this->get(route('settings.lgu.edit', 1))->assertNotFound();
        $this->put(route('settings.lgu.update', 1), $this->data())->assertNotFound();
        $this->delete(route('settings.lgu.edit', 2))->assertStatus(405);
    }

    public function test_saved_blank_signatory_overrides_the_config_fallback_and_ownership_fields_are_ignored(): void
    {
        $data = array_replace($this->data(), ['municipality_id' => 3, 'logo_path' => 'private/another-file.png', 'signatory_name' => null, 'signatory_title' => null]);
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('municipality_profiles', ['municipality_id' => 1, 'logo_path' => null, 'signatory_name' => null]);
        $farmer = Farmer::create(['municipality_id' => 1, 'first_name' => 'Sample', 'last_name' => 'Farmer']);
        $this->get(route('farmers.id-card', $farmer))->assertOk()->assertDontSee('PASCUA')->assertSee('signatory: null', false);
    }

    public function test_same_named_lgus_resolve_independent_profiles_and_the_active_supervising_province(): void
    {
        $this->asRole(User::ROLE_SYSTEM_OWNER, null, null);
        $this->put(route('settings.lgu.update', 1), $this->data())->assertSessionHasNoErrors();
        $this->put(route('settings.lgu.update', 3), array_replace($this->data(3), ['office_name' => 'Other Ramos Office', 'website_url' => 'https://other.example.test/']))->assertSessionHasNoErrors();
        $farmer = Farmer::create(['municipality_id' => 3, 'first_name' => 'Sample', 'last_name' => 'Farmer']);
        $this->get(route('farmers.id-card', $farmer))->assertOk()->assertSee('OTHER RAMOS OFFICE')->assertSee('ELSEWHERE')
            ->assertSee('https://other.example.test/')->assertDontSee('https://example.test/lgu');
    }

    public function test_logo_delivery_refuses_stored_path_traversal_and_disguised_markup(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data())->assertSessionHasNoErrors();
        $profile = MunicipalityProfile::firstOrFail();
        $profile->logo_path = '../private-file.png';
        $profile->save();
        $this->get(route('settings.lgu.logo', 1))->assertNotFound();
        $profile->logo_path = 'lgu-logos/1/disguised.png';
        $profile->save();
        Storage::disk('local')->put($profile->logo_path, '<script>bad image</script>');
        $this->get(route('settings.lgu.logo', 1))->assertNotFound();
    }

    public function test_bad_website_shapes_and_conflicting_logo_actions_are_field_errors(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD);
        $this->put(route('settings.lgu.update', 1), array_replace($this->data(), ['website_url' => ['invalid']]))->assertSessionHasErrors('website_url');
        $this->put(route('settings.lgu.update', 1), $this->data() + ['logo' => UploadedFile::fake()->image('seal.png', 128, 128), 'remove_logo' => 1])->assertSessionHasErrors('logo');
        $this->put(route('settings.lgu.update', 1), $this->data() + ['logo' => UploadedFile::fake()->image('seal.png', 128, 128)->size(2049)])->assertSessionHasErrors('logo');
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_office_details_are_escaped_and_location_links_use_only_the_recorded_office(): void
    {
        $data = array_replace($this->data(), ['office_name' => '<script>office</script>', 'description' => '<script>description</script>', 'latitude' => 15.5, 'longitude' => 120.5]);
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $data)->assertSessionHasNoErrors();
        $this->get(route('settings.lgu.edit', 1))->assertOk()->assertSee('&lt;script&gt;description&lt;/script&gt;', false)
            ->assertDontSee('<script>description</script>', false)->assertSee('15.5000000%2C120.5000000', false);
    }

    public function test_profile_migration_rolls_back_without_changing_existing_municipalities(): void
    {
        $migration = require database_path('migrations/2026_10_19_000100_create_municipality_profiles_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('municipality_profiles'));
        $this->assertDatabaseCount('municipalities', 3);
        $migration->up();
        $this->assertDatabaseCount('municipality_profiles', 0);
    }

    public function test_without_the_migration_existing_cards_keep_their_previous_issuer_and_settings_explain_setup(): void
    {
        Schema::drop('municipality_profiles');
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->get(route('settings.lgu.index'))->assertOk()->assertSee('awaiting the database update');
        $farmer = Farmer::create(['municipality_id' => 1, 'first_name' => 'Sample', 'last_name' => 'Farmer']);
        $this->get(route('farmers.id-card', $farmer))->assertOk()->assertSee('MUNICIPAL AGRICULTURE OFFICE')->assertSee('PASCUA');
    }

    public function test_lgu_websites_are_private_until_published_and_then_appear_in_the_public_directory(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data())->assertRedirect();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), $this->websiteData(1))
            ->assertRedirect()->assertSessionHas('success', 'LGU website saved as unpublished. Only authorized staff can preview it.');

        $this->get(route('lgu.index'))->assertOk()->assertSee('LGU websites are being prepared.');
        $this->get(route('lgu.show', 'ramos-tarlac-1'))->assertNotFound();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->get(route('settings.lgu.website.preview', 1))->assertOk()->assertSee('Staff preview');

        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), array_replace($this->websiteData(1), ['is_published' => 1]))
            ->assertRedirect()->assertSessionHas('success', 'LGU website published. Saved changes are now public.');
        $this->get(route('lgu.index'))->assertOk()->assertSee('Ramos');
        $this->get(route('lgu.show', 'ramos-tarlac-1'))->assertOk()->assertSee('Welcome to Ramos')->assertSee('Sample service');
    }

    public function test_website_permissions_are_scoped_and_staff_are_read_only(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), $this->websiteData(1))->assertRedirect();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->get(route('settings.lgu.website.edit', 2))->assertNotFound();
        $this->asRole(User::ROLE_MUNICIPAL_STAFF)->get(route('settings.lgu.website.edit', 1))->assertOk()->assertSee('You can view and preview this website.');
        $this->put(route('settings.lgu.website.update', 1), $this->websiteData(1))->assertForbidden();
    }

    public function test_website_addresses_are_validated_as_unique_and_reserved_names_are_rejected(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), $this->websiteData(1))->assertRedirect();
        $this->asRole(User::ROLE_SUPER_ADMIN, null, 1)->put(route('settings.lgu.website.update', 2), array_replace($this->websiteData(2), ['slug' => 'ramos-tarlac-1']))
            ->assertSessionHasErrors('slug');
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), array_replace($this->websiteData(1), ['slug' => 'admin']))
            ->assertSessionHasErrors('slug');
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), array_replace($this->websiteData(1), ['slug' => 'demo']))
            ->assertSessionHasErrors('slug');
    }

    public function test_subdomain_hosts_render_only_the_matching_published_page_and_never_office_routes(): void
    {
        config(['lgu_websites.domain' => 'example.test']);
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data())->assertRedirect();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), array_replace($this->websiteData(1), ['is_published' => 1]))->assertRedirect();

        $this->get('http://ramos-tarlac-1.example.test/')->assertOk()->assertSee('Welcome to Ramos');
        $this->get('http://ramos-tarlac-1.example.test/login')->assertNotFound();
        $this->get('http://other.example.test/')->assertNotFound();
    }

    public function test_website_editor_shows_the_configured_tenant_address(): void
    {
        config(['lgu_websites.domain' => 'agritarlac.online']);
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), $this->data())->assertRedirect();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), $this->websiteData(1))->assertRedirect();

        $this->get(route('settings.lgu.website.edit', 1))
            ->assertOk()
            ->assertSee('ramos-tarlac-1.agritarlac.online')
            ->assertSee('The matching Hostinger hostname and HTTPS certificate must exist before publishing.');
    }

    public function test_public_media_requires_publication_and_uses_restricted_headers(): void
    {
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.update', 1), array_replace($this->data(), ['logo' => UploadedFile::fake()->image('seal.png', 128, 128)]))->assertRedirect();
        $this->asRole(User::ROLE_MUNICIPAL_HEAD)->put(route('settings.lgu.website.update', 1), array_replace($this->websiteData(1), ['is_published' => 1, 'cover' => UploadedFile::fake()->image('cover.jpg', 640, 360)]))->assertRedirect();
        $response = $this->get(route('lgu.media', ['ramos-tarlac-1', 'logo']));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get(route('lgu.media', ['ramos-tarlac-1', 'cover']))->assertOk();
    }

    private function data(int $id = 1): array
    {
        $municipality = Municipality::findOrFail($id);

        return ['_record_version' => app(MunicipalityProfiles::class)->version($municipality, $municipality->profile),
            'office_name' => 'Local Agriculture Office', 'website_url' => 'https://example.test/lgu',
            'office_address' => 'Sample Municipal Hall', 'phone' => 'Office telephone', 'email' => 'office@example.test',
            'signatory_name' => 'Sample LGU Head', 'signatory_title' => 'Municipal Agriculturist'];
    }

    private function websiteData(int $id = 1): array
    {
        $municipality = Municipality::findOrFail($id);
        $website = $municipality->website;

        return ['_record_version' => app(MunicipalityWebsites::class)->version($municipality, $website),
            'slug' => $id === 1 ? 'ramos-tarlac-1' : 'anao-tarlac-2', 'is_published' => 0,
            'headline' => 'Welcome to '.$municipality->name, 'tagline' => 'Local agriculture services',
            'welcome_text' => 'Welcome to our office.', 'office_hours' => 'Monday to Friday, 8:00 AM to 5:00 PM',
            'services' => [['title' => 'Sample service', 'description' => 'Information from the local office.', 'url' => null]],
            'announcements' => [['title' => 'Office notice', 'body' => 'Please contact the office.', 'date' => '2026-10-19']]];
    }

    private function asRole(string $role, ?int $municipality = 1, ?int $province = null, ?int $region = null, bool $visitor = false): self
    {
        $user = User::withoutEvents(fn () => User::create(['name' => 'Test office user', 'email' => uniqid().'@example.test', 'password' => 'unused-test-placeholder',
            'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province, 'region_id' => $region, 'is_active' => true]));
        $user->visitor_mode = $visitor;
        $user->evaluation_expires_at = now()->addDay();
        $user->saveQuietly();

        return $this->actingAs($user)->withSession([EnforceIdleSession::LAST_ACTIVITY_KEY => now()->timestamp]);
    }
}
