<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ->assertSee('<title>Farmer services | AgriLGU</title>', false)
            ->assertSee('alt="AgriLGU"', false)
            ->assertSee(asset('images/branding/agrilgu-wordmark-v2.png'), false)
            ->assertSee(asset('images/branding/eagrilgu-system-mark.png'), false)
            ->assertDontSee('AgriGOV')
            ->assertSeeText('Mas malapit ang serbisyo sa magsasaka.')
            ->assertSee('DA initiatives')
            ->assertSee('Office sign in')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('id="services"', false)
            ->assertSee('id="initiatives"', false)
            ->assertSee('id="visit"', false)
            ->assertSee('id="system"', false)
            ->assertSee('aria-label="Feature categories"', false)
            ->assertSeeText('Farmer self-service portal')
            ->assertSeeText('Satellite field checks')
            ->assertSeeText('Crop guidance & field visits')
            ->assertSeeText('Harvests & production')
            ->assertSeeText('Agriculture & system Assistant')
            ->assertSeeText('Public LGU websites')
            ->assertSee('href="'.route('lgu.index').'"', false)
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
            ->assertSee(asset('js/welcome.js'), false)
            ->assertSee(asset('css/welcome-showcase.css'), false)
            ->assertSee(asset('js/welcome-showcase.js'), false);

        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_every_feature_has_a_labeled_public_sample_without_database_or_provider_access(): void
    {
        DB::connection()->enableQueryLog();
        Http::preventStrayRequests();

        $response = $this->get('/');
        $response->assertOk()
            ->assertSeeText('Sample data only')
            ->assertSeeText('All names, figures, maps and messages in these previews are fictional.')
            ->assertSeeText('Outside LGU and Delisted')
            ->assertSee('aria-labelledby="welcome-preview-title"', false)
            ->assertSee('data-preview-close', false);

        $html = new \DOMDocument;
        @$html->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($html);
        $links = $xpath->query('//a[@data-welcome-preview]');
        $this->assertCount(28, $links);
        $paths = [];
        foreach ($links as $link) {
            $path = parse_url($link->getAttribute('href'), PHP_URL_PATH);
            $this->assertMatchesRegularExpression('#^/images/showcase/(people|maps|climate|programs|operations|connected|administration)-0[1-4]\.svg$#', $path);
            $this->assertNotSame('', $link->getAttribute('data-preview-title'));
            $this->assertStringContainsString('Enlarge sample:', $link->getAttribute('aria-label'));
            $image = $link->getElementsByTagName('img')->item(0);
            $this->assertNotNull($image);
            $this->assertSame('lazy', $image->getAttribute('loading'));
            $this->assertStringContainsString('fictional sample data', $image->getAttribute('alt'));
            $paths[] = $path;
        }
        $this->assertCount(28, array_unique($paths));
        $this->assertSame([], DB::connection()->getQueryLog());
        Http::assertNothingSent();
    }

    public function test_showcase_images_are_self_contained_non_executable_fictional_illustrations(): void
    {
        $images = glob(public_path('images/showcase/*.svg'));
        $this->assertCount(28, $images);
        foreach ($images as $path) {
            $source = file_get_contents($path);
            $svg = new \DOMDocument;
            $this->assertTrue($svg->loadXML($source, LIBXML_NONET), basename($path));
            $this->assertSame('960 600', $svg->documentElement->getAttribute('width').' '.$svg->documentElement->getAttribute('height'));
            $this->assertStringContainsString('SAMPLE DATA', $svg->textContent);
            $this->assertStringContainsString('fictional records', $svg->textContent);
            $this->assertStringContainsString('Sample LGU', $svg->textContent);
            $this->assertDoesNotMatchRegularExpression('/<(?:script|foreignObject|iframe|image|a)(?:\s|>)/i', $source);
            $this->assertDoesNotMatchRegularExpression('/\s(?:on[a-z]+|(?:xlink:)?href)\s*=|<!DOCTYPE|<!ENTITY/i', $source);
            $this->assertLessThan(65000, strlen($source), 'Preview payload must remain bounded: '.basename($path));
        }
    }

    #[DataProvider('authenticatedDestinations')]
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
