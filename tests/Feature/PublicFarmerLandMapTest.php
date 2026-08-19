<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('Interactive parcel map')
            ->assertSee('North Rice Parcel')
            ->assertSee('id="landMap"', false)
            ->assertSee('Satellite')
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
}
