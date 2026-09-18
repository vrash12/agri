<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * The parcel map payload is bounded, and says so when it stops early.
 *
 * Parcel geometry is the heaviest thing this system sends to a browser, and the
 * offices using it work over rural connections. An unbounded response is a page that
 * never finishes rather than one that loads slowly.
 *
 * The cap matters less than the honesty about it: a map that quietly drops parcels
 * looks complete, and nobody reading it can tell that part of their area is missing.
 */
class MapPayloadBoundsTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private Municipality $otherMunicipality;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = $this->makeMunicipality('Bounds Own '.$suffix, 'BO'.substr($suffix, -8));
        $this->otherMunicipality = $this->makeMunicipality('Bounds Other '.$suffix, 'BX'.substr($suffix, -8));

        $this->staff = User::create([
            'name' => 'Bounds Staff',
            'email' => 'bounds-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    public function test_the_parcel_endpoint_reports_how_many_parcels_it_returned_and_how_many_exist(): void
    {
        $farmer = $this->farmer($this->municipality->id);
        $this->plot($farmer);
        $this->plot($farmer);
        $this->plot($farmer);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farm-plots.all'))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('plots', $body);
        $this->assertSame(3, $body['total']);
        $this->assertSame(3, $body['returned']);
        $this->assertFalse($body['truncated']);
        $this->assertCount(3, $body['plots']);
    }

    public function test_the_parcel_endpoint_stops_at_the_cap_and_says_that_it_did(): void
    {
        config(['map.max_plots_per_request' => 2]);

        $farmer = $this->farmer($this->municipality->id);
        foreach (range(1, 5) as $ignored) {
            $this->plot($farmer);
        }

        $body = $this->actingAs($this->staff)
            ->getJson(route('farm-plots.all'))
            ->assertOk()
            ->json();

        $this->assertCount(2, $body['plots'], 'The cap was not applied.');
        $this->assertSame(2, $body['returned']);
        $this->assertSame(5, $body['total'], 'The total must count every parcel, not only the ones returned.');
        $this->assertTrue($body['truncated'], 'The response stopped early without saying so.');
        $this->assertSame(2, $body['limit']);
    }

    public function test_the_cap_never_lets_another_municipalitys_parcels_in(): void
    {
        // Capping changes which rows come back, so the isolation check has to hold
        // with the cap active, not only on an unbounded query.
        config(['map.max_plots_per_request' => 100]);

        $own = $this->farmer($this->municipality->id);
        $this->plot($own);

        $foreign = $this->farmer($this->otherMunicipality->id);
        $this->plot($foreign);
        $this->plot($foreign);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farm-plots.all'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['total'], 'The total leaked parcels from another municipality.');
        $returnedFarmerIds = collect($body['plots'])->pluck('farmer_id')->unique()->all();
        $this->assertSame([$own], $returnedFarmerIds);
    }

    public function test_a_cap_of_zero_or_less_is_treated_as_one_rather_than_returning_nothing(): void
    {
        // A misconfigured deployment should degrade to a tiny map, not a blank one
        // that reports no parcels exist.
        config(['map.max_plots_per_request' => 0]);

        $farmer = $this->farmer($this->municipality->id);
        $this->plot($farmer);
        $this->plot($farmer);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farm-plots.all'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $body['plots']);
        $this->assertSame(2, $body['total']);
        $this->assertTrue($body['truncated']);
    }

    public function test_the_finder_never_returns_a_farmer_from_another_municipality(): void
    {
        // The scope is applied to the query before the search term. Reversed, this
        // endpoint would let one municipality confirm whether a farmer exists in
        // another just by searching for their name.
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Visible', 'last_name' => 'Sentinel',
            'farm_location' => 'Barangay Uno',
        ]);
        Farmer::create([
            'municipality_id' => $this->otherMunicipality->id,
            'first_name' => 'Hidden', 'last_name' => 'Sentinel',
            'farm_location' => 'Barangay Uno',
        ]);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => 'Sentinel']))
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['total']);
        $this->assertSame(['Visible'], collect($body['farmers'])->pluck('first_name')->all());
    }

    public function test_the_finder_caps_its_results_and_reports_what_it_left_out(): void
    {
        foreach (range(1, 8) as $index) {
            Farmer::create([
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Cap'.$index, 'last_name' => 'Capped',
                'farm_location' => 'Barangay Uno',
            ]);
        }

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => 'Capped', 'limit' => 3]))
            ->assertOk()
            ->json();

        $this->assertCount(3, $body['farmers']);
        $this->assertSame(3, $body['returned']);
        $this->assertSame(8, $body['total']);
        $this->assertTrue($body['truncated']);
    }

    public function test_the_finder_clamps_an_oversized_limit_rather_than_honouring_it(): void
    {
        foreach (range(1, 60) as $index) {
            Farmer::create([
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Many'.$index, 'last_name' => 'Clamped',
                'farm_location' => 'Barangay Uno',
            ]);
        }

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => 'Clamped', 'limit' => 5000]))
            ->assertOk()
            ->json();

        $this->assertCount(50, $body['farmers'], 'A caller asking for 5,000 results was given them.');
        $this->assertSame(60, $body['total']);
        $this->assertTrue($body['truncated']);
    }

    public function test_the_finder_asks_for_more_input_rather_than_returning_a_slice_of_everyone(): void
    {
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Anyone', 'last_name' => 'Atall',
            'farm_location' => 'Barangay Uno',
        ]);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => 'A']))
            ->assertOk()
            ->json();

        $this->assertSame([], $body['farmers']);
        $this->assertTrue($body['needs_more_input']);
    }

    public function test_a_wildcard_character_in_the_search_is_searched_for_literally(): void
    {
        // Unescaped, '%' would match every farmer in scope.
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Percent', 'last_name' => 'Literal',
            'farm_location' => 'Barangay Uno',
        ]);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => '%%']))
            ->assertOk()
            ->json();

        $this->assertSame(0, $body['total'], 'A percent sign acted as a wildcard.');
    }

    public function test_the_finder_returns_only_the_fields_the_map_renders(): void
    {
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Fields', 'last_name' => 'Checked',
            'farm_location' => 'Barangay Uno',
            'date_of_birth' => '1970-01-01',
            'contact_number' => '09170000000',
            'rsbsa_no' => 'RSBSA-'.uniqid(),
        ]);

        $body = $this->actingAs($this->staff)
            ->getJson(route('farmers.lookup', ['q' => 'Checked']))
            ->assertOk()
            ->json();

        $farmer = $body['farmers'][0];
        foreach (['date_of_birth', 'contact_number', 'rsbsa_no', 'registry_id'] as $withheld) {
            $this->assertArrayNotHasKey($withheld, $farmer, "The finder returned {$withheld}.");
        }
        $this->assertSame('Checked', $farmer['last_name']);
    }

    private function makeMunicipality(string $name, string $code): Municipality
    {
        return Municipality::create([
            'name' => $name,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function farmer(int $municipalityId): int
    {
        return Farmer::create([
            'municipality_id' => $municipalityId,
            'first_name' => 'Bounds',
            'last_name' => 'Farmer',
            'farm_location' => 'Barangay Uno',
        ])->id;
    }

    private function plot(int $farmerId): void
    {
        DB::table('farm_plots')->insert([
            'farmer_id' => $farmerId,
            'name' => 'Parcel',
            'polygon_json' => json_encode([[15.3, 120.8], [15.31, 120.8], [15.31, 120.81], [15.3, 120.8]]),
            'area_ha' => 1.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
