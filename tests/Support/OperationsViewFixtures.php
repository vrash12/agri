<?php

namespace Tests\Support;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/** Synthetic view data only: no model is inserted and no relationships query a database. */
final class OperationsViewFixtures
{
    public static function user(string $role = User::ROLE_MUNICIPAL_STAFF): User
    {
        $user = new User(['name' => 'Preview Staff', 'role' => $role, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = 99;
        $user->setRelation('municipality', self::municipality());

        return $user;
    }

    private static function municipality(): Municipality
    {
        $municipality = new Municipality(['name' => 'Preview Municipality', 'province' => 'Tarlac', 'is_active' => true]);
        $municipality->id = 1;

        return $municipality;
    }

    public static function data(string $view): array
    {
        $municipality = self::municipality();
        $farmer = new Farmer(['first_name' => 'Sample', 'last_name' => 'Farmer', 'municipality_id' => 1, 'ffrs' => 'PREVIEW-001', 'farm_location' => 'Sample Barangay', 'farm_municipality' => 'Preview Municipality', 'farm_area_ha' => 2.5]);
        $farmer->id = 11;
        $farmer->setRelation('municipality', $municipality);
        $farmer2 = clone $farmer;
        $farmer2->id = 12;
        $farmer2->first_name = '<img src=x onerror=alert(1)> Sample';
        $cooperative = new FarmersCooperative(['name' => 'Preview Growers Cooperative', 'municipality_id' => 1, 'chairperson' => 'Sample Chairperson', 'address' => 'Sample Barangay', 'description' => 'Synthetic profile notes']);
        $cooperative->id = 21;
        $cooperative->setRelation('municipality', $municipality);
        $cooperative->farmers_count = 1;
        $cooperative->machineries_count = 1;
        $cooperative->setRelation('farmers', collect([$farmer]));
        $machinery = new AgriculturalMachinery(['asset_code' => 'PREVIEW-TR-01', 'name' => 'Sample Tractor', 'category' => 'tractor', 'municipality_id' => 1, 'farmer_id' => 11, 'condition_status' => 'good', 'availability_status' => 'available', 'brand' => 'Sample Brand', 'year_acquired' => 2024, 'acquisition_cost' => 125000, 'next_maintenance_date' => '2026-09-20']);
        $machinery->id = 31;
        $machinery->setRelation('municipality', $municipality)->setRelation('farmer', $farmer)->setRelation('cooperative', null);
        $release = new RiceSeedDistribution(['municipality_id' => 1, 'farmer_id' => 11, 'first_name' => 'Sample', 'last_name' => 'Farmer', 'ffrs' => 'PREVIEW-001', 'input_category' => 'fish_feed', 'seed_variety_claimed' => 'Sample fish feed', 'quantity_unit' => 'kg', 'kgs_received' => 15.5, 'date_received' => '2026-09-01', 'lot_series' => 'BATCH-PREVIEW', 'claimed_area_ha' => 2.5, 'total_production_bags' => 4, 'seed_class' => 'Certified']);
        $release->id = 41;
        $release->setRelation('farmer', $farmer)->setRelation('municipality', $municipality);
        $animal = new AntiRabiesVaccination(['municipality_id' => 1, 'owner_name' => 'Sample Raiser', 'barangay' => 'Sample Barangay', 'service_type' => 'vaccination', 'pet_type' => 'Dog', 'animal_count' => 1, 'service_name' => 'Anti-rabies vaccine', 'vaccination_date' => '2026-09-01', 'dosage' => '1 mL', 'pet_breed' => 'Mixed Breed']);
        $animal->id = 51;
        $animal->setRelation('municipality', $municipality);

        $record = match (explode('.', $view)[0]) {
            'rice_seed_distributions' => $release,
            'anti_rabies_vaccinations' => $animal,
            'farmers_cooperatives' => $cooperative,
            default => $machinery,
        };
        $record->updated_at = '2026-09-01 01:00:00';
        $record->exists = true;

        return [
            'record' => str_ends_with($view, '.create') ? null : $record,
            'records' => new LengthAwarePaginator([$record], 1, 10, 1, ['path' => '/preview']),
            'municipalities' => collect([$municipality]), 'selectedMunicipalityId' => 1, 'canChooseMunicipality' => false,
            'farmers' => collect([$farmer, $farmer2]), 'cooperatives' => collect([$cooperative]), 'selectedFarmerIds' => [11],
            'q' => '', 'perPage' => str_starts_with($view, 'anti_rabies') ? 20 : 10, 'status' => '', 'sort' => 'name', 'serviceType' => '',
            'inputCategoryOptions' => RiceSeedDistribution::INPUT_CATEGORY_LABELS, 'assistanceSectorOptions' => RiceSeedDistribution::ASSISTANCE_SECTOR_LABELS,
            'defaultInputCategory' => 'rice_seed',
            'quantityUnitOptions' => RiceSeedDistribution::QUANTITY_UNIT_LABELS, 'preferredUnitsByCategory' => ['rice_seed' => 'kg', 'fish_feed' => 'kg', 'fish_fingerlings' => 'piece'],
            'inputSuggestions' => [], 'seedVarietyClaimedOptions' => [], 'cropEstablishmentOptions' => ['Direct', 'Transplanted'], 'seedClassOptions' => ['Certified'],
            'charts' => ['monthly_labels' => ['Jan', 'Feb'], 'monthly_values' => [15.5, 0]], 'stats' => ['trendYear' => 2026],
            'serviceTypeOptions' => AntiRabiesVaccination::SERVICE_TYPE_LABELS, 'animalTypeOptions' => AntiRabiesVaccination::ANIMAL_TYPE_LABELS,
            'petTypeOptions' => AntiRabiesVaccination::ANIMAL_TYPE_LABELS, 'latestServiceDate' => null,
            'monthlyChartLabels' => ['Jan', 'Feb'], 'monthlyChartData' => [2, 1],
            'categories' => AgriculturalMachinery::CATEGORIES, 'conditions' => AgriculturalMachinery::CONDITIONS,
            'availabilityStatuses' => AgriculturalMachinery::AVAILABILITY_STATUSES, 'acquisitionSources' => AgriculturalMachinery::ACQUISITION_SOURCES,
            'filters' => [], 'summary' => (object) ['total' => 1, 'available' => 1, 'in_use' => 0, 'total_cost' => 125000],
            'maintenanceAttention' => 1, 'maintenanceQueue' => collect([$machinery]),
            'categoryChart' => collect([['key' => 'tractor', 'label' => 'Four-wheel tractor', 'total' => 1]]),
            'conditionChart' => collect([['key' => 'good', 'label' => 'Good', 'total' => 1]]),
        ];
    }
}
