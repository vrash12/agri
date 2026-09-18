<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The measurement rules behind the dashboard indicators.
 *
 * These tests exist because the office's reporting feedback was about meaning, not
 * about charts: a release was being read as production, a farmer who collected three
 * times was being counted as three farmers, and quantities in different units were
 * being added up. Each test below pins one of those rules, so a later change that
 * re-introduces the confusion fails here rather than in a printed report.
 *
 * Operational data lives in MySQL, so these run against an in-memory SQLite schema
 * built by hand — the project has no complete migration history to migrate from.
 */
class DashboardMetricsTest extends TestCase
{
    private DashboardMetrics $metrics;

    private int $ownMunicipality;

    private int $siblingMunicipality;

    private int $foreignMunicipality;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        $this->seedScope();

        $this->metrics = new DashboardMetrics(new MunicipalityAccess);
    }

    public function test_releases_and_unique_beneficiaries_are_counted_separately(): void
    {
        // One farmer collecting three times is three releases but one beneficiary.
        $farmer = $this->farmer($this->ownMunicipality);
        $this->release($this->ownMunicipality, ['farmer_id' => $farmer, 'input_category' => 'rice_seed']);
        $this->release($this->ownMunicipality, ['farmer_id' => $farmer, 'input_category' => 'rice_seed']);
        $this->release($this->ownMunicipality, ['farmer_id' => $farmer, 'input_category' => 'rice_seed']);

        $metric = $this->metrics->assistanceByCategory($this->municipalUser());

        $this->assertSame(['Rice seed'], $metric['labels']);
        $this->assertSame([3], $this->seriesValues($metric, 'Releases'));
        $this->assertSame([1], $this->seriesValues($metric, 'Unique beneficiaries'));
    }

    public function test_a_release_with_no_farmer_is_a_transaction_but_not_a_beneficiary(): void
    {
        $this->release($this->ownMunicipality, ['farmer_id' => null, 'input_category' => 'fertilizer']);

        $metric = $this->metrics->assistanceByCategory($this->municipalUser());

        $this->assertSame([1], $this->seriesValues($metric, 'Releases'));
        $this->assertSame([0], $this->seriesValues($metric, 'Unique beneficiaries'));
    }

    public function test_quantities_in_different_units_are_never_summed_together(): void
    {
        $this->release($this->ownMunicipality, ['quantity_unit' => 'kg', 'kgs_received' => 40]);
        $this->release($this->ownMunicipality, ['quantity_unit' => 'kg', 'kgs_received' => 2.5]);
        $this->release($this->ownMunicipality, ['quantity_unit' => 'piece', 'kgs_received' => 5000]);
        $this->release($this->ownMunicipality, ['quantity_unit' => 'sack', 'kgs_received' => 12]);

        $metric = $this->metrics->quantityByUnit($this->municipalUser());

        // Rows follow the declared unit vocabulary, and nothing produces a combined
        // 5054.5 total across kilograms, sacks and pieces.
        $this->assertSame(['kg', 'sacks', 'pieces'], $metric['labels']);
        $this->assertSame([42.5, 12.0, 5000.0], $metric['series'][0]['values']);
        $this->assertCount(1, $metric['series']);
    }

    public function test_quantity_is_reported_with_decimals_so_part_kilograms_survive(): void
    {
        $this->release($this->ownMunicipality, ['quantity_unit' => 'kg', 'kgs_received' => 2.25]);

        $metric = $this->metrics->quantityByUnit($this->municipalUser());

        $this->assertSame(2, $metric['series'][0]['decimals']);
        $this->assertSame([2.25], $metric['series'][0]['values']);
    }

    public function test_mapping_coverage_counts_farmers_not_parcels(): void
    {
        $mapped = $this->farmer($this->ownMunicipality);
        $this->plot($mapped);
        $this->plot($mapped);
        $this->plot($mapped);
        $this->farmer($this->ownMunicipality);

        $metric = $this->metrics->mappingCoverage($this->municipalUser());

        // Three parcels belong to one farmer, so coverage is one of two farmers.
        $this->assertSame(
            ['Farmers with a mapped parcel', 'Farmers without a mapped parcel'],
            $metric['labels']
        );
        $this->assertSame([1, 1], $metric['series'][0]['values']);
    }

    public function test_machinery_condition_and_availability_stay_separate_measures(): void
    {
        // A unit can be in excellent condition and still be unavailable because it is
        // already out on loan, so the two questions never share an answer.
        $this->machinery($this->ownMunicipality, 'excellent', 'in_use');
        $this->machinery($this->ownMunicipality, 'excellent', 'available');
        $this->machinery($this->ownMunicipality, 'needs_repair', 'maintenance');

        $condition = $this->metrics->machineryByCondition($this->municipalUser());
        $availability = $this->metrics->machineryByAvailability($this->municipalUser());

        $this->assertSame(['Excellent', 'Good', 'Fair', 'Needs repair', 'Unserviceable'], $condition['labels']);
        $this->assertSame([2, 0, 0, 1, 0], $condition['series'][0]['values']);

        $this->assertSame(['Available', 'In use', 'Under maintenance', 'Inactive'], $availability['labels']);
        $this->assertSame([1, 1, 1, 0], $availability['series'][0]['values']);
    }

    public function test_missing_grouping_values_are_reported_as_not_recorded_rather_than_zero(): void
    {
        $this->release($this->ownMunicipality, ['input_category' => 'rice_seed']);
        $this->release($this->ownMunicipality, ['input_category' => null]);
        $this->release($this->ownMunicipality, ['input_category' => '']);

        $metric = $this->metrics->assistanceByCategory($this->municipalUser());

        // The two unrecorded releases are neither dropped nor folded into a zero bar.
        $this->assertSame(['Rice seed'], $metric['labels']);
        $this->assertSame([1], $this->seriesValues($metric, 'Releases'));
        $this->assertSame(
            ['label' => 'Releases with no category recorded', 'count' => 2],
            $metric['not_recorded']
        );
    }

    public function test_a_record_belonging_to_no_municipality_is_reported_only_to_an_account_that_sees_everything(): void
    {
        // Counting these through the municipality scope is impossible: the scope adds
        // `municipality_id IN (...)`, and `NULL IN (...)` is UNKNOWN rather than true,
        // so the combined predicate matches nothing and the figure reads zero however
        // many orphan rows exist.
        DB::table('farmers')->insert([
            'municipality_id' => null,
            'first_name' => 'Orphan',
            'last_name' => 'Record',
            'public_map_token' => bin2hex(random_bytes(12)),
        ]);
        $this->farmer($this->ownMunicipality);

        // A row with no municipality belongs to no province either, so neither of
        // these accounts has an honest figure to be given.
        $this->assertNull($this->metrics->farmersByMunicipality($this->municipalUser())['not_recorded']);
        $this->assertNull($this->metrics->farmersByMunicipality($this->provincialUser())['not_recorded']);

        $this->assertSame(
            ['label' => 'Farmers with no municipality recorded', 'count' => 1],
            $this->metrics->farmersByMunicipality($this->systemOwner())['not_recorded'],
            'The account that can see every municipality was not told about the orphan row.'
        );
    }

    public function test_an_owner_with_no_usable_scope_is_not_handed_an_unscoped_count(): void
    {
        DB::table('farmers')->insert([
            'municipality_id' => null,
            'first_name' => 'Orphan',
            'last_name' => 'Record',
            'public_map_token' => bin2hex(random_bytes(12)),
        ]);

        $owner = $this->systemOwner();
        $owner->forceFill(['is_active' => false])->save();

        // Deactivating the account must close the view, not open an unscoped one.
        $this->assertNull($this->metrics->farmersByMunicipality($owner->fresh())['not_recorded']);
    }

    public function test_production_is_reported_by_commodity_over_time(): void
    {
        // The graph the office asked for, and the one the register could not answer
        // while its only harvest figures lived on a rice seed release.
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2025, 'quantity' => 100, 'quantity_unit' => 'sack']);
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 140, 'quantity_unit' => 'sack']);
        $this->harvest(['commodity' => 'corn', 'harvest_year' => 2026, 'quantity' => 60, 'quantity_unit' => 'sack']);

        $metric = $this->metrics->productionByCommodity($this->municipalUser());

        $this->assertSame(['2025', '2026'], $metric['labels']);
        $this->assertSame([100.0, 140.0], $this->seriesValues($metric, 'Rice / Palay'));
        // Corn has no 2025 harvest, which is a true zero rather than a gap.
        $this->assertSame([0.0, 60.0], $this->seriesValues($metric, 'Corn'));
    }

    public function test_a_commodity_recorded_in_two_units_is_never_summed_into_one(): void
    {
        // Sacks and kilograms are different measurements and nothing converts between
        // them, so one rice line carrying 100 sacks plus 500 kg would be invented.
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 100, 'quantity_unit' => 'sack']);
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 500, 'quantity_unit' => 'kg']);

        $metric = $this->metrics->productionByCommodity($this->municipalUser());
        $units = collect($metric['series'])->pluck('unit')->sort()->values()->all();

        $this->assertCount(2, $metric['series'], 'Two units were collapsed into one series.');
        $this->assertSame(['Kilograms (kg)', 'Sacks'], $units);
        foreach ($metric['series'] as $series) {
            $this->assertNotContains(600.0, $series['values'], 'Sacks and kilograms were added together.');
        }
    }

    public function test_a_harvest_with_no_year_is_reported_as_not_recorded_rather_than_placed_in_one(): void
    {
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 100, 'quantity_unit' => 'sack']);
        $this->harvest(['commodity' => 'rice', 'harvest_year' => null, 'quantity' => 40, 'quantity_unit' => 'sack']);
        $this->harvest(['commodity' => 'corn', 'harvest_year' => 2026, 'quantity' => null, 'quantity_unit' => 'sack']);

        $metric = $this->metrics->productionByCommodity($this->municipalUser());

        $this->assertSame(['2026'], $metric['labels']);
        $this->assertSame([100.0], $this->seriesValues($metric, 'Rice / Palay'));
        $this->assertSame(
            ['label' => 'Harvest records with no year or no quantity recorded', 'count' => 2],
            $metric['not_recorded']
        );
    }

    public function test_production_stays_inside_the_account_scope(): void
    {
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 100, 'quantity_unit' => 'sack']);
        $this->harvest([
            'municipality_id' => $this->foreignMunicipality,
            'commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 999, 'quantity_unit' => 'sack',
        ]);

        $metric = $this->metrics->productionByCommodity($this->municipalUser());

        $this->assertSame([100.0], $this->seriesValues($metric, 'Rice / Palay'), 'Another municipality harvest was counted.');
    }

    public function test_production_is_never_described_as_assistance(): void
    {
        // The reverse of the rule the assistance charts follow: what came off a field
        // is not a hand-out, just as a hand-out is not production.
        $this->harvest(['commodity' => 'rice', 'harvest_year' => 2026, 'quantity' => 100, 'quantity_unit' => 'sack']);

        $metric = $this->metrics->productionByCommodity($this->municipalUser());
        $wording = $metric['title'].' '.implode(' ', array_column($metric['series'], 'name'));

        $this->assertDoesNotMatchRegularExpression('/released|assistance|beneficiar|distribut/i', $wording);
    }

    public function test_not_recorded_is_null_when_nothing_is_missing(): void
    {
        $this->release($this->ownMunicipality, ['input_category' => 'rice_seed']);

        $this->assertNull($this->metrics->assistanceByCategory($this->municipalUser())['not_recorded']);
    }

    public function test_a_value_outside_the_known_vocabulary_is_shown_rather_than_dropped(): void
    {
        $this->release($this->ownMunicipality, ['input_category' => 'legacy_import_code']);

        $metric = $this->metrics->assistanceByCategory($this->municipalUser());

        $this->assertSame(['legacy_import_code'], $metric['labels']);
        $this->assertSame([1], $this->seriesValues($metric, 'Releases'));
        $this->assertNull($metric['not_recorded']);
    }

    public function test_a_municipal_user_sees_only_their_own_municipality(): void
    {
        $this->farmer($this->ownMunicipality);
        $this->farmer($this->ownMunicipality);
        $this->farmer($this->siblingMunicipality);
        $this->farmer($this->foreignMunicipality);

        $metric = $this->metrics->farmersByMunicipality($this->municipalUser());

        $this->assertSame(['Own Municipality'], $metric['labels']);
        $this->assertSame([2], $metric['series'][0]['values']);
    }

    public function test_a_provincial_user_sees_their_province_and_not_another(): void
    {
        $this->farmer($this->ownMunicipality);
        $this->farmer($this->siblingMunicipality);
        $this->farmer($this->siblingMunicipality);
        $this->farmer($this->foreignMunicipality);

        $metric = $this->metrics->farmersByMunicipality($this->provincialUser());

        $this->assertSame(['Own Municipality', 'Sibling Municipality'], $metric['labels']);
        $this->assertSame([1, 2], $metric['series'][0]['values']);
    }

    public function test_the_comparison_is_withheld_from_an_account_that_sees_one_municipality(): void
    {
        // A single row is not a comparison, so it is not offered at all.
        $this->assertNull($this->metrics->municipalityComparison($this->municipalUser()));
    }

    public function test_the_comparison_reports_each_indicator_separately_and_never_scores_them(): void
    {
        $mapped = $this->farmer($this->ownMunicipality);
        $this->plot($mapped);
        $this->farmer($this->ownMunicipality);
        $this->release($this->ownMunicipality, ['farmer_id' => $mapped]);
        $this->release($this->ownMunicipality, ['farmer_id' => $mapped]);
        $this->farmer($this->siblingMunicipality);

        $metric = $this->metrics->municipalityComparison($this->provincialUser());

        $this->assertSame(['Own Municipality', 'Sibling Municipality'], $metric['labels']);
        $this->assertSame(
            ['Registered farmers', 'Farmers with a mapped parcel', 'Assistance releases', 'Unique beneficiaries'],
            array_column($metric['series'], 'name')
        );
        $this->assertSame([2, 1], $this->seriesValues($metric, 'Registered farmers'));
        $this->assertSame([1, 0], $this->seriesValues($metric, 'Farmers with a mapped parcel'));
        $this->assertSame([2, 0], $this->seriesValues($metric, 'Assistance releases'));
        $this->assertSame([1, 0], $this->seriesValues($metric, 'Unique beneficiaries'));

        // No weighting, index, total or rank is produced anywhere in the result.
        foreach (array_column($metric['series'], 'name') as $name) {
            $this->assertDoesNotMatchRegularExpression('/score|index|rank|overall|total/i', $name);
        }
    }

    public function test_the_comparison_leaves_out_municipalities_the_office_has_deactivated(): void
    {
        DB::table('municipalities')->insert([
            'id' => 90, 'name' => 'Closed Municipality', 'province_id' => 1, 'is_active' => false,
        ]);

        $metric = $this->metrics->municipalityComparison($this->provincialUser());

        $this->assertNotContains('Closed Municipality', $metric['labels']);
    }

    public function test_animal_health_separates_services_from_the_animals_they_covered(): void
    {
        // One service record can cover a whole herd.
        $this->vaccination($this->ownMunicipality, 'vaccination', 12);
        $this->vaccination($this->ownMunicipality, 'vaccination', 8);
        $this->vaccination($this->ownMunicipality, 'deworming', 3);

        $metric = $this->metrics->animalHealthByServiceType($this->municipalUser());

        $this->assertSame(['Vaccination', 'Deworming', 'Vitamins / supplementation', 'Treatment'], $metric['labels']);
        $this->assertSame([2, 1, 0, 0], $this->seriesValues($metric, 'Services recorded'));
        $this->assertSame([20, 3, 0, 0], $this->seriesValues($metric, 'Animals covered'));
    }

    public function test_fisheries_reports_releases_and_beneficiaries_without_mixing_in_a_quantity(): void
    {
        $farmer = $this->farmer($this->ownMunicipality);
        $this->release($this->ownMunicipality, [
            'farmer_id' => $farmer, 'input_category' => 'fish_fingerlings',
            'quantity_unit' => 'piece', 'kgs_received' => 5000,
        ]);
        $this->release($this->ownMunicipality, [
            'farmer_id' => $farmer, 'input_category' => 'fish_feed',
            'quantity_unit' => 'kg', 'kgs_received' => 25,
        ]);

        $metric = $this->metrics->fisheriesAssistance($this->municipalUser());

        // A 5000-piece count on the same axis as a release count of 1 would flatten
        // the chart, so quantities stay in quantityByUnit where the unit is named.
        $this->assertSame(['Fish fingerlings', 'Fish feed'], $metric['labels']);
        $this->assertSame(['Releases', 'Unique beneficiaries'], array_column($metric['series'], 'name'));
        $this->assertSame([1, 1], $this->seriesValues($metric, 'Releases'));
    }

    public function test_no_indicator_describes_a_release_as_production_or_yield(): void
    {
        $this->release($this->ownMunicipality, ['input_category' => 'rice_seed', 'kgs_received' => 40]);
        $user = $this->provincialUser();

        foreach ($this->allMetrics($user) as $name => $metric) {
            if ($metric === null) {
                continue;
            }
            $wording = $metric['title'].' '.implode(' ', array_column($metric['series'], 'name'));
            $this->assertDoesNotMatchRegularExpression(
                '/production|yield|harvested|output/i',
                $wording,
                $name.' describes handed-out assistance in the language of production.'
            );
        }
    }

    public function test_every_series_lines_up_with_its_labels_for_every_role(): void
    {
        $farmer = $this->farmer($this->ownMunicipality);
        $this->plot($farmer);
        $this->release($this->ownMunicipality, ['farmer_id' => $farmer, 'input_category' => 'rice_seed']);
        $this->vaccination($this->ownMunicipality, 'vaccination', 4);
        $this->machinery($this->ownMunicipality, 'good', 'available');

        foreach (['municipal' => $this->municipalUser(), 'provincial' => $this->provincialUser()] as $role => $user) {
            foreach ($this->allMetrics($user) as $name => $metric) {
                if ($metric === null) {
                    continue;
                }
                $this->assertIsString($metric['title'], "$role/$name title");
                foreach ($metric['series'] as $series) {
                    $this->assertCount(
                        count($metric['labels']),
                        $series['values'],
                        "$role/$name: series '{$series['name']}' does not line up with its labels."
                    );
                }
            }
        }
    }

    public function test_an_office_with_no_records_reports_empty_rather_than_failing(): void
    {
        foreach ($this->allMetrics($this->municipalUser()) as $name => $metric) {
            if ($metric === null) {
                continue;
            }
            foreach ($metric['series'] as $series) {
                $this->assertCount(count($metric['labels']), $series['values'], $name);
                foreach ($series['values'] as $value) {
                    $this->assertSame(0, (int) $value, "$name reported a figure with no records behind it.");
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function allMetrics(User $user): array
    {
        return [
            'farmersByMunicipality' => $this->metrics->farmersByMunicipality($user),
            'assistanceByCategory' => $this->metrics->assistanceByCategory($user),
            'assistanceByMunicipality' => $this->metrics->assistanceByMunicipality($user),
            'quantityByUnit' => $this->metrics->quantityByUnit($user),
            'mappingCoverage' => $this->metrics->mappingCoverage($user),
            'animalHealthByServiceType' => $this->metrics->animalHealthByServiceType($user),
            'fisheriesAssistance' => $this->metrics->fisheriesAssistance($user),
            'machineryByCondition' => $this->metrics->machineryByCondition($user),
            'machineryByAvailability' => $this->metrics->machineryByAvailability($user),
            'municipalityComparison' => $this->metrics->municipalityComparison($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<int, int|float>
     */
    private function seriesValues(array $metric, string $name): array
    {
        foreach ($metric['series'] as $series) {
            if ($series['name'] === $name) {
                return $series['values'];
            }
        }

        $this->fail("The metric has no series named '{$name}'.");
    }

    private function municipalUser(): User
    {
        return User::query()->where('role', User::ROLE_MUNICIPAL_STAFF)->firstOrFail();
    }

    private function provincialUser(): User
    {
        return User::query()->where('role', User::ROLE_PROVINCIAL_STAFF)->firstOrFail();
    }

    private function systemOwner(): User
    {
        return User::query()->where('role', User::ROLE_SYSTEM_OWNER)->firstOrFail();
    }

    private function farmer(int $municipalityId): int
    {
        return DB::table('farmers')->insertGetId([
            'municipality_id' => $municipalityId,
            'first_name' => 'Test',
            'last_name' => 'Farmer',
            'public_map_token' => bin2hex(random_bytes(12)),
        ]);
    }

    private function plot(int $farmerId): void
    {
        DB::table('farm_plots')->insert([
            'farmer_id' => $farmerId,
            'polygon_json' => '[]',
            'area_ha' => 1.0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function release(int $municipalityId, array $attributes = []): void
    {
        DB::table('rice_seed_distributions')->insert($attributes + [
            'municipality_id' => $municipalityId,
            'farmer_id' => null,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 10,
        ]);
    }

    private function vaccination(int $municipalityId, string $serviceType, int $animals): void
    {
        DB::table('anti_rabies_vaccinations')->insert([
            'municipality_id' => $municipalityId,
            'service_type' => $serviceType,
            'animal_count' => $animals,
        ]);
    }

    private function machinery(int $municipalityId, string $condition, string $availability): void
    {
        DB::table('agricultural_machineries')->insert([
            'municipality_id' => $municipalityId,
            'condition_status' => $condition,
            'availability_status' => $availability,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function harvest(array $attributes = []): void
    {
        DB::table('harvest_records')->insert($attributes + [
            'municipality_id' => $this->ownMunicipality,
            'farmer_id' => null,
            'commodity' => 'rice',
            'season' => null,
            'harvest_year' => 2026,
            'quantity' => 100,
            'quantity_unit' => 'sack',
        ]);
    }

    private function seedScope(): void
    {
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'Home Province', 'is_active' => true],
            ['id' => 2, 'name' => 'Other Province', 'is_active' => true],
        ]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Own Municipality', 'province_id' => 1, 'is_active' => true],
            ['id' => 2, 'name' => 'Sibling Municipality', 'province_id' => 1, 'is_active' => true],
            ['id' => 3, 'name' => 'Foreign Municipality', 'province_id' => 2, 'is_active' => true],
        ]);
        $this->ownMunicipality = 1;
        $this->siblingMunicipality = 2;
        $this->foreignMunicipality = 3;

        DB::table('users')->insert([
            [
                'name' => 'Municipal Staff', 'email' => 'staff@example.test', 'password' => 'x',
                'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => 1, 'province_id' => null, 'is_active' => true,
            ],
            [
                'name' => 'Provincial Staff', 'email' => 'province@example.test', 'password' => 'x',
                'role' => User::ROLE_PROVINCIAL_STAFF, 'municipality_id' => null, 'province_id' => 1, 'is_active' => true,
            ],
            [
                'name' => 'System Owner', 'email' => 'owner@example.test', 'password' => 'x',
                'role' => User::ROLE_SYSTEM_OWNER, 'municipality_id' => null, 'province_id' => null, 'is_active' => true,
            ],
        ]);
    }

    private function createSchema(): void
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
            $table->string('province')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('public_map_token', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->json('polygon_json');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->string('input_category')->nullable();
            $table->string('quantity_unit')->nullable();
            $table->decimal('kgs_received', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('anti_rabies_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('service_type')->nullable();
            $table->unsignedInteger('animal_count')->default(1);
            $table->timestamps();
        });
        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->string('commodity')->nullable();
            $table->string('season')->nullable();
            $table->unsignedSmallInteger('harvest_year')->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('quantity_unit')->nullable();
            $table->timestamps();
        });
        Schema::create('agricultural_machineries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('condition_status')->nullable();
            $table->string('availability_status')->nullable();
            $table->timestamps();
        });
    }
}
