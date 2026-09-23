<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'security.csp_enabled' => true,
            'security.csp_report_only' => false,
        ]);
        DB::purge('sqlite');
    }

    public function test_guests_can_read_public_guidance_without_querying_operational_records(): void
    {
        // No schema or records are needed: this page must not expose office data.
        DB::connection()->enableQueryLog();

        $response = $this->get(route('welcome'));

        $response->assertOk()
            ->assertViewIs('welcome')
            ->assertSeeText('Mas malapit ang serbisyo sa magsasaka.')
            ->assertSee('DA initiatives')
            ->assertSee('Office sign in')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('id="services"', false)
            ->assertSee('id="initiatives"', false)
            ->assertSee('id="visit"', false)
            ->assertSee('id="system"', false)
            ->assertSee('data-welcome-slideshow', false)
            ->assertSee('aria-label="Previous collage"', false)
            ->assertSee('aria-label="Next collage"', false)
            ->assertSee('data-gallery-status', false)
            ->assertDontSee('data-scene-select', false)
            ->assertDontSee('data-gallery-play', false)
            ->assertDontSee('20 photographs. Five different collages.')
            ->assertSee('dry or wet season')
            ->assertSee('Department of Agriculture seal')
            ->assertSee(asset('images/da.jpg'), false)
            ->assertSee(asset('photo-credits.html'), false)
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertDontSee('href="'.url('/register').'"', false);

        $this->assertSame([], DB::connection()->getQueryLog());
    }

    public function test_public_welcome_keeps_browser_security_headers_and_local_assets(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertSee(asset('css/welcome.css'), false)
            ->assertSee(asset('js/welcome.js'), false);

        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    /** @dataProvider authenticatedDestinations */
    public function test_signed_in_users_keep_their_role_destination(string $role, string $destination): void
    {
        $user = new User(['role' => $role, 'is_active' => true]);
        $user->id = 1;

        $this->actingAs($user)
            ->get(route('welcome'))
            ->assertRedirect(route($destination));
    }

    public static function authenticatedDestinations(): array
    {
        return [
            'municipal staff' => [User::ROLE_MUNICIPAL_STAFF, 'dashboard'],
            'system owner' => [User::ROLE_SYSTEM_OWNER, 'dashboard'],
            'provincial veterinary office' => [User::ROLE_PROVINCIAL_VET, 'anti-rabies-vaccinations.index'],
        ];
    }

    public function test_public_entry_does_not_open_protected_office_records_to_guests(): void
    {
        foreach (['dashboard', 'farmers.index', 'rice-seed-distributions.index', 'municipality-boundaries.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $this->get(route('login'))->assertOk()->assertViewIs('auth.login');
    }
}
