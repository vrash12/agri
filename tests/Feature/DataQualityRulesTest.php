<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\FarmerDataQuality;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * Rules that have to mean the same thing everywhere they are applied.
 *
 * A municipal officer reads a count on the dashboard, clicks it, and expects the
 * list that opens to hold exactly those records. That only holds if the count and
 * the list ask the same question. These rules had been written out separately in
 * three and five places respectively, and the copies had drifted.
 */
class DataQualityRulesTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = Municipality::create([
            'name' => 'Quality Test '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'QT'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Quality Staff',
            'email' => 'quality-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    public function test_the_missing_location_rule_matches_every_capitalisation_of_the_unknown_sentinel(): void
    {
        // The importer writes 'UNKNOWN'. Anyone typing into the form can write
        // 'Unknown' or 'unknown', and all three mean the location was never recorded.
        foreach (['UNKNOWN', 'Unknown', 'unknown', '', null] as $index => $location) {
            $this->farmer(['farm_location' => $location, 'last_name' => 'Missing'.$index]);
        }
        $this->farmer(['farm_location' => 'Barangay Uno', 'last_name' => 'Recorded']);

        $counted = FarmerDataQuality::missingLocation(
            Farmer::query()->where('municipality_id', $this->municipality->id)
        )->count();

        $this->assertSame(5, $counted, 'A capitalisation of UNKNOWN escaped the missing-location rule.');
    }

    public function test_the_grouped_expression_and_the_query_scope_count_the_same_records(): void
    {
        // The dashboard headline uses the scope; the per-municipality rollup on the
        // same page cannot take a scope and uses the expression. They must agree, or
        // one page contradicts itself.
        foreach (['UNKNOWN', 'unknown', '', null, 'Barangay Dos'] as $index => $location) {
            $this->farmer(['farm_location' => $location, 'ffrs' => $index === 0 ? '' : 'FFRS-'.$index]);
        }

        $scoped = FarmerDataQuality::missingLocation(
            Farmer::query()->where('municipality_id', $this->municipality->id)
        )->count();

        $rollup = DB::table('farmers')
            ->where('municipality_id', $this->municipality->id)
            ->selectRaw(FarmerDataQuality::missingLocationCountExpression().' as missing_location')
            ->selectRaw(FarmerDataQuality::missingFfrsCountExpression().' as missing_ffrs')
            ->first();

        $this->assertSame($scoped, (int) $rollup->missing_location);

        $scopedFfrs = FarmerDataQuality::missingFfrs(
            Farmer::query()->where('municipality_id', $this->municipality->id)
        )->count();

        $this->assertSame($scopedFfrs, (int) $rollup->missing_ffrs);
    }

    public function test_the_farmer_list_behind_the_dashboard_count_holds_exactly_the_records_it_counted(): void
    {
        foreach (['UNKNOWN', 'unknown', '', null] as $index => $location) {
            $this->farmer(['farm_location' => $location, 'last_name' => 'Gap'.$index]);
        }
        $this->farmer(['farm_location' => 'Barangay Tres', 'last_name' => 'Complete']);

        $counted = FarmerDataQuality::missingLocation(
            Farmer::query()->where('municipality_id', $this->municipality->id)
        )->count();

        $response = $this->actingAs($this->staff)
            ->get(route('farmers.index', ['quality' => 'missing_location', 'per_page' => 100]))
            ->assertOk();

        $listed = collect($response->viewData('farmers')->items());

        $this->assertSame($counted, $listed->count(), 'The list does not hold the records the count counted.');
        $this->assertTrue(
            $listed->every(fn (Farmer $farmer) => in_array(
                strtoupper((string) $farmer->farm_location),
                ['UNKNOWN', ''],
                true
            )),
            'A farmer with a recorded location appeared in the missing-location list.'
        );
    }

    public function test_every_gender_the_system_accepts_can_also_be_filtered_for(): void
    {
        // A value that can be saved but never searched for is a record that quietly
        // disappears from the directory it belongs to.
        foreach (Farmer::GENDERS as $gender) {
            $farmer = $this->farmer(['gender' => $gender, 'last_name' => 'Gender'.$gender]);

            RiceSeedDistribution::create([
                'municipality_id' => $this->municipality->id,
                'farmer_id' => $farmer->id,
                'input_category' => 'rice_seed',
                'quantity_unit' => 'kg',
                'gender' => $gender,
                'last_name' => $farmer->last_name,
                'first_name' => $farmer->first_name,
                'kgs_received' => 10,
                'date_received' => '2025-02-01',
            ]);
        }

        foreach (Farmer::GENDERS as $gender) {
            $farmers = collect(
                $this->actingAs($this->staff)
                    ->get(route('farmers.index', ['gender' => $gender, 'per_page' => 100]))
                    ->assertOk()
                    ->viewData('farmers')
                    ->items()
            );

            $this->assertTrue(
                $farmers->isNotEmpty() && $farmers->every(fn (Farmer $f) => $f->gender === $gender),
                "The farmer directory does not filter on gender '{$gender}'."
            );

            $releases = collect(
                $this->actingAs($this->staff)
                    ->get(route('rice-seed-distributions.index', ['gender' => $gender, 'per_page' => 100]))
                    ->assertOk()
                    ->viewData('records')
                    ->items()
            );

            $this->assertTrue(
                $releases->isNotEmpty() && $releases->every(fn ($r) => $r->gender === $gender),
                "The assistance directory does not filter on gender '{$gender}'."
            );
        }
    }

    public function test_each_attention_figure_leads_to_exactly_the_records_it_counted(): void
    {
        // The dashboard's "Attention needed" rows are links. A count that opens a list
        // holding a different number of records is worse than no link at all, because
        // the officer has no way to tell which of the two figures to believe.
        $mapped = $this->farmer(['last_name' => 'Mapped', 'ffrs' => 'FFRS-M-'.uniqid()]);
        DB::table('farm_plots')->insert([
            'farmer_id' => $mapped->id,
            'name' => 'Plot',
            'polygon_json' => '[]',
            'area_ha' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->farmer(['last_name' => 'NoParcel', 'ffrs' => 'FFRS-U-'.uniqid()]);
        $this->farmer(['last_name' => 'NoFfrs', 'ffrs' => null]);
        $this->farmer(['last_name' => 'NoPlace', 'farm_location' => 'UNKNOWN', 'ffrs' => 'FFRS-L-'.uniqid()]);

        $scoped = fn () => Farmer::query()->where('municipality_id', $this->municipality->id);

        $expected = [
            'quality=missing_ffrs' => [
                ['quality' => 'missing_ffrs'],
                FarmerDataQuality::missingFfrs($scoped())->count(),
            ],
            'quality=missing_location' => [
                ['quality' => 'missing_location'],
                FarmerDataQuality::missingLocation($scoped())->count(),
            ],
            'mapping=unmapped' => [
                ['mapping' => 'unmapped'],
                $scoped()->whereNotIn('id', DB::table('farm_plots')->whereNotNull('farmer_id')->pluck('farmer_id'))->count(),
            ],
        ];

        foreach ($expected as $label => [$query, $count]) {
            $listed = collect(
                $this->actingAs($this->staff)
                    ->get(route('farmers.index', $query + ['per_page' => 100]))
                    ->assertOk()
                    ->viewData('farmers')
                    ->items()
            )->filter(fn (Farmer $f) => $f->municipality_id === $this->municipality->id);

            $this->assertSame(
                $count,
                $listed->count(),
                "The list behind '{$label}' does not hold the records that figure counts."
            );
        }
    }

    public function test_an_identifier_with_stray_spaces_is_caught_as_a_duplicate_not_a_database_error(): void
    {
        $existing = 'FFRS-DUP-'.strtoupper(substr(uniqid(), -8));
        $this->farmer(['ffrs' => $existing, 'last_name' => 'First']);

        // Submitted with a leading space. Before the identifiers were trimmed ahead of
        // validation, this slipped past the unique rule and failed in the database.
        $this->actingAs($this->staff)
            ->post(route('farmers.store'), [
                'first_name' => 'Second',
                'last_name' => 'Farmer',
                'ffrs' => ' '.$existing,
                'farm_location' => 'Barangay Uno',
            ])
            ->assertSessionHasErrors('ffrs');

        $this->assertSame(
            1,
            Farmer::query()->where('municipality_id', $this->municipality->id)->where('ffrs', $existing)->count()
        );
    }

    public function test_a_whitespace_only_identifier_is_stored_as_absent_rather_than_blank(): void
    {
        $this->actingAs($this->staff)
            ->post(route('farmers.store'), [
                'first_name' => 'Blank',
                'last_name' => 'Identifier',
                'ffrs' => '   ',
                'rsbsa_no' => '   ',
                'farm_location' => 'Barangay Uno',
            ])
            ->assertSessionHasNoErrors();

        $farmer = Farmer::query()
            ->where('municipality_id', $this->municipality->id)
            ->where('last_name', 'Identifier')
            ->firstOrFail();

        // Absent, not an empty string — two blanks must never collide on the unique index.
        $this->assertNull($farmer->ffrs);
        $this->assertNull($farmer->rsbsa_no);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function farmer(array $attributes = []): Farmer
    {
        return Farmer::create(array_merge([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Quality',
            'last_name' => 'Farmer',
            'farm_location' => 'Barangay Uno',
        ], $attributes));
    }
}
