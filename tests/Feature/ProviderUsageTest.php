<?php

namespace Tests\Feature;

use App\Exceptions\SatelliteUnavailable;
use App\Models\User;
use App\Support\ProviderUsage;
use App\Support\SatelliteRequestBudget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class ProviderUsageTest extends TestCase
{
    use PresentationProvinceSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'sentinel.enabled' => true,
            'sentinel.client_id' => 'synthetic-client', 'sentinel.client_secret' => 'synthetic-secret',
            'sentinel.daily_requests' => 10, 'sentinel.monthly_requests' => 30,
            'services.google_maps.key' => 'synthetic-browser-key', 'services.google_maps.static_key' => 'synthetic-static-key']);
        DB::purge('sqlite');
        $this->createPresentationScope();
        Cache::flush();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10 23:59:30', 'UTC'));
        Http::preventStrayRequests();
    }

    public function test_admin_snapshot_uses_the_enforced_shared_allowances_and_exposes_no_credentials(): void
    {
        $budget = app(SatelliteRequestBudget::class);
        $budget->reserve();
        $budget->reserve();
        $service = app(ProviderUsage::class);
        $service->recordGoogleStaticRequest();
        $service->recordGoogleStaticRequest();
        $data = $service->forUser($this->user());
        $this->assertSame(2, $data['satellite']['periods']['day']['used']);
        $this->assertSame(8, $data['satellite']['periods']['day']['remaining']);
        $this->assertSame(20.0, $data['satellite']['periods']['day']['percent']);
        $this->assertSame(28, $data['satellite']['periods']['month']['remaining']);
        $this->assertSame(2, $data['google']['periods']['month']['used']);
        $this->assertSame('11 Oct 2026, 00:00 UTC', $data['satellite']['periods']['day']['reset_at']);
        $this->assertSame('01 Nov 2026, 00:00 UTC', $data['satellite']['periods']['month']['reset_at']);
        $this->assertSame($data, $service->forUser($this->user(User::ROLE_SYSTEM_OWNER)));
        $this->assertStringNotContainsString('synthetic-', json_encode($data));
        $this->assertArrayNotHasKey('cost', $data['google']);
        Http::assertNothingSent();
    }

    public function test_staff_regional_heads_visitors_and_invalid_scopes_receive_no_provider_data(): void
    {
        $service = app(ProviderUsage::class);
        Cache::shouldReceive('get')->never();
        foreach ([User::ROLE_MUNICIPAL_HEAD, User::ROLE_MUNICIPAL_STAFF, User::ROLE_PROVINCIAL_STAFF,
            User::ROLE_PROVINCIAL_VET, User::ROLE_REGIONAL_HEAD, User::ROLE_GIS_EVALUATOR] as $role) {
            $this->assertNull($service->forUser($this->user($role)));
        }
        $visitor = $this->user();
        $visitor->visitor_mode = true;
        $this->assertNull($service->forUser($visitor));
        $inactive = $this->user();
        $inactive->is_active = false;
        $this->assertNull($service->forUser($inactive));
        $unassigned = $this->user();
        $unassigned->province_id = null;
        $this->assertNull($service->forUser($unassigned));
    }

    public function test_utc_rollover_resets_daily_counts_and_preserves_monthly_counts(): void
    {
        $service = app(ProviderUsage::class);
        $service->recordGoogleStaticRequest();
        app(SatelliteRequestBudget::class)->reserve();
        $this->travel(1)->minutes();
        $data = $service->forUser($this->user());
        $this->assertSame(0, $data['satellite']['periods']['day']['used']);
        $this->assertSame(1, $data['satellite']['periods']['month']['used']);
        $this->assertSame(0, $data['google']['periods']['day']['used']);
        $this->assertSame(1, $data['google']['periods']['month']['used']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 00:00:00', 'UTC'));
        $data = $service->forUser($this->user());
        $this->assertSame(0, $data['satellite']['periods']['month']['used']);
        $this->assertSame(0, $data['google']['periods']['month']['used']);
        $this->assertNull($data['google']['periods']['month']['started_at']);
    }

    public function test_zero_allowance_pauses_requests_and_lowered_limits_clamp_remaining_and_progress(): void
    {
        $budget = app(SatelliteRequestBudget::class);
        $budget->reserve();
        $budget->reserve();
        config(['sentinel.daily_requests' => 1]);
        $data = $budget->snapshot();
        $this->assertSame(0, $data['day']['remaining']);
        $this->assertSame(100.0, $data['day']['percent']);
        config(['sentinel.daily_requests' => 0]);
        try {
            $budget->reserve();
            $this->fail('A zero allowance must block processing.');
        } catch (SatelliteUnavailable $exception) {
            $this->assertSame(429, $exception->status);
        }
        $this->assertSame(2, $budget->snapshot()['month']['used']);
    }

    public function test_cache_failure_displays_unavailable_and_telemetry_failure_does_not_break_exports(): void
    {
        Log::spy();
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('synthetic-sensitive-cache-details'));
        Cache::shouldReceive('lock')->andThrow(new \RuntimeException('synthetic-sensitive-cache-details'));
        $service = app(ProviderUsage::class);
        $service->recordGoogleStaticRequest();
        $data = $service->forUser($this->user());
        $this->assertNull($data['satellite']['periods']);
        $this->assertNull($data['google']['periods']);
        $this->actingAs($this->user());
        $this->view('dashboard', ['providerUsage' => $data])
            ->assertSee('Usage is temporarily unavailable')->assertDontSee('synthetic-sensitive-cache-details');
        Log::shouldHaveReceived('warning')->with('Google Static Maps usage could not be recorded.')->once();
        Log::shouldHaveReceived('warning')->with('Provider usage counters could not be read.')->twice();
    }

    public function test_panel_has_plain_labels_accessible_progress_and_no_fake_billing_or_secrets(): void
    {
        $service = app(ProviderUsage::class);
        for ($i = 0; $i < 8; $i++) {
            app(SatelliteRequestBudget::class)->reserve();
        }
        $this->actingAs($this->user());
        $data = $service->forUser($this->user());
        $this->view('dashboard', ['providerUsage' => $data])->assertSee('Satellite &amp; map usage', false)
            ->assertSee('Allowance is nearly used')->assertSee('2 remaining')->assertSee('No tracked requests yet')
            ->assertSee('Full Google usage: not connected')->assertSee('Shared across AgriLGU offices')
            ->assertSee('aria-valuenow="80"', false)->assertDontSee('synthetic-');
        $this->actingAs($this->user(User::ROLE_MUNICIPAL_STAFF));
        $this->view('dashboard', ['providerUsage' => $data])->assertDontSee('id="providerUsageHeading"', false);
    }

    private function user(string $role = User::ROLE_SUPER_ADMIN): User
    {
        $user = new User(['name' => 'Preview Admin', 'role' => $role, 'is_active' => true,
            'province_id' => $role === User::ROLE_SYSTEM_OWNER ? null : $this->presentationProvinceId,
            'municipality_id' => in_array($role, User::MUNICIPAL_ROLES, true) ? $this->presentationMunicipalityId : null]);
        $user->id = 1;

        return $user;
    }
}
