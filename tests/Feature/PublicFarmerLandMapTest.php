<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicFarmerLandMapTest extends TestCase
{
    use DatabaseTransactions;

    private Farmer $farmer;

    private User $municipalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(md5(uniqid('', true)), 0, 10);
        $municipality = Municipality::create([
            'name' => 'QR Map Municipality '.$suffix,
            'province' => 'Tarlac',
            'code' => 'QR'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->municipalUser = User::create([
            'municipality_id' => $municipality->id,
            'name' => 'QR Map Staff',
            'email' => 'qr-map-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'is_active' => true,
        ]);

        $this->farmer = Farmer::create([
            'municipality_id' => $municipality->id,
            'first_name' => 'Maria',
            'last_name' => 'MapOwner',
            'contact_number' => '09171234567',
            'ffrs' => 'PRIVATE-FFRS-'.$suffix,
            'farm_location' => 'Poblacion',
            'farm_area_ha' => 1.25,
        ]);

        FarmPlot::create([
            'farmer_id' => $this->farmer->id,
            'name' => 'North Rice Parcel',
            'polygon_json' => [
                ['lat' => 15.5457, 'lng' => 120.7106],
                ['lat' => 15.5461, 'lng' => 120.7113],
                ['lat' => 15.5458, 'lng' => 120.7115],
            ],
            'area_ha' => 0.46,
            'centroid_lat' => 15.5458,
            'centroid_lng' => 120.7111,
            'color' => '#16834b',
        ]);
    }

    public function test_qr_land_map_is_public_interactive_and_privacy_limited(): void
    {
        config([
            'services.google_maps.key' => 'test-browser-key',
            'services.google_maps.map_id' => 'test-map-id',
        ]);

        $this->assertSame(40, strlen($this->farmer->public_map_token));
        $this->assertArrayNotHasKey(
            'public_map_token',
            $this->farmer->toArray()
        );

        $this->get(route('farmers.public-land', [
            'token' => $this->farmer->public_map_token,
        ]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertSee('Interactive parcel map')
            ->assertSee('North Rice Parcel')
            ->assertSee('id="landMap"', false)
            ->assertSee('Google Maps parcel view')
            ->assertSee('maps.googleapis.com/maps/api/js', false)
            ->assertSee('test-browser-key', false)
            ->assertDontSee('unpkg.com/leaflet', false)
            ->assertDontSee('09171234567')
            ->assertDontSee($this->farmer->ffrs);
    }

    public function test_invalid_public_map_token_is_not_found(): void
    {
        $this->get(route('farmers.public-land', [
            'token' => str_repeat('x', 40),
        ]))->assertNotFound();
    }

    public function test_farmer_id_contains_qr_for_the_interactive_land_map(): void
    {
        $scanUrl = route('farmers.public-land', [
            'token' => $this->farmer->public_map_token,
        ]);

        $this->actingAs($this->municipalUser)
            ->get(route('farmers.id-card', $this->farmer))
            ->assertOk()
            ->assertSee('SCAN LAND MAP')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee($scanUrl, false);
    }

    public function test_authorized_static_map_proxy_returns_same_origin_image(): void
    {
        config([
            'services.google_maps.static_key' => 'test-static-key',
            'app.url' => 'https://agritarlac.example',
        ]);
        Cache::flush();
        Http::fake([
            'maps.googleapis.com/*' => Http::response(
                'fake-png-binary',
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $plot = $this->farmer->farmPlots()->firstOrFail();

        $this->actingAs($this->municipalUser)
            ->get(route('farm-plots.static-map', $plot))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('fake-png-binary');

        Http::assertSent(function ($request) {
            return str_starts_with(
                $request->url(),
                'https://maps.googleapis.com/maps/api/staticmap'
            )
                && $request['maptype'] === 'hybrid'
                && $request['key'] === 'test-static-key'
                && str_contains((string) $request['path'], 'fillcolor:');
        });
    }
}
