<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiceSeedDistribution extends Model
{
    use HasFactory;

    public const FISHERIES_INPUT_CATEGORIES = [
        'fish_fingerlings',
        'fish_feed',
        'fishing_gear',
        'aquaculture_input',
        'other_fisheries',
    ];

    public const ASSISTANCE_SECTOR_LABELS = [
        'agriculture' => 'Crops & farm inputs',
        'fisheries' => 'Fisheries assistance',
    ];

    public const INPUT_CATEGORY_LABELS = [
        'rice_seed' => 'Rice seed',
        'corn_seed' => 'Corn seed',
        'vegetable_seed' => 'Vegetable seed',
        'fertilizer' => 'Fertilizer / Abono',
        'soil_amendment' => 'Soil amendment',
        'other_input' => 'Other farm input',
        'fish_fingerlings' => 'Fish fingerlings',
        'fish_feed' => 'Fish feed',
        'fishing_gear' => 'Fishing gear',
        'aquaculture_input' => 'Aquaculture input',
        'other_fisheries' => 'Other fisheries assistance',
    ];

    /**
     * Data-privacy consent recorded on the Rice Seed Distribution Sheet.
     *
     * A release whose consent was never asked stays `unrecorded`; it must never be
     * read as either a granted or a refused consent.
     */
    public const CONSENT_UNRECORDED = 'unrecorded';

    public const CONSENT_YES = 'yes';

    public const CONSENT_NO = 'no';

    public const CONSENT_STATUS_LABELS = [
        self::CONSENT_UNRECORDED => 'Not recorded',
        self::CONSENT_YES => 'Yes',
        self::CONSENT_NO => 'No',
    ];

    public const QUANTITY_UNIT_LABELS = [
        'kg' => 'kg',
        'sack' => 'sacks',
        'pack' => 'packs',
        'g' => 'g',
        'l' => 'L',
        'ml' => 'mL',
        'bottle' => 'bottles',
        'piece' => 'pieces',
        'set' => 'sets',
        'roll' => 'rolls',
        'box' => 'boxes',
        'bundle' => 'bundles',
    ];

    protected $table = 'rice_seed_distributions';

    protected $fillable = [
        'municipality_id',

        // FK (connected to farmers.id in your SQL)
        'farmer_id',

        // Optional Rice Seed Distribution Sheet grouping; legacy releases stay null.
        'batch_id',

        // Flexible seed and farm-input details
        'input_category',
        'quantity_unit',
        'input_notes',

        // Excel/NRP claim fields
        'seed_variety_claimed',
        'claimed_area_ha',
        'claimed_seeds_kg',
        'lot_series',
        'crop_establishment',
        'date_of_sowing_label',
        'avg_weight_per_bag_kg',
        'total_production_bags',
        'avg_area_harvested_ha',
        'seed_variety_planted',
        'seed_class',

        // Harvest season is stated explicitly and is never inferred from a date.
        'harvest_season',
        'harvest_year',

        // Distribution fields
        'kgs_received',
        'date_received',

        // Rice Seed Distribution Sheet details
        'seed_bags',
        'seed_bag_kg',
        'consent_status',
        'kp_kits_received',
        'representative_name',

        // Farmer identity snapshot
        'last_name',
        'first_name',
        'middle_name',
        'ext_name',
        'ffrs',
        'date_of_birth',
        'gender',
        'contact_number',

        // Farm location snapshot
        'farm_location',
        'farm_province',
        'farm_municipality',
        'farm_area_ha',

        // Declared rice area, kept separate from the total farm area above.
        'registered_rice_area_ha',

        // Optional ecosystem fields
        'ecosystem',
        'ecosystem_source',

        // Eligibility tags
        'is_arb',
        'is_4ps',
        'is_ip',
        'is_pwd',
        'is_sc',
        'is_ofw',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'farmer_id' => 'integer',
        'batch_id' => 'integer',
        'seed_bags' => 'integer',
        'harvest_year' => 'integer',
        'kp_kits_received' => 'integer',

        // Dates (SQL: date)
        'date_of_birth' => 'date',
        'date_received' => 'date',

        // Booleans (SQL: tinyint(1))
        'is_arb' => 'boolean',
        'is_4ps' => 'boolean',
        'is_ip' => 'boolean',
        'is_pwd' => 'boolean',
        'is_sc' => 'boolean',
        'is_ofw' => 'boolean',

        // Decimals (SQL: decimal)
        'claimed_area_ha' => 'decimal:2', // decimal(8,2)
        'claimed_seeds_kg' => 'decimal:2', // decimal(8,2)
        'avg_area_harvested_ha' => 'decimal:2', // decimal(8,2)
        'kgs_received' => 'decimal:2', // decimal(8,2)
        'farm_area_ha' => 'decimal:2', // decimal(10,2)
        'registered_rice_area_ha' => 'decimal:2', // decimal(8,2)
        'seed_bag_kg' => 'decimal:2', // decimal(8,2)
    ];

    protected $attributes = [
        'input_category' => 'rice_seed',
        'quantity_unit' => 'kg',
        'consent_status' => self::CONSENT_UNRECORDED,
    ];

    /**
     * rice_seed_distributions.farmer_id -> farmers.id
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Optional Rice Seed Distribution Sheet grouping. Releases recorded before
     * sheets existed keep a null batch and remain fully editable.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RiceDistributionBatch::class, 'batch_id');
    }

    public function consentStatusLabel(): string
    {
        $status = $this->consent_status ?: self::CONSENT_UNRECORDED;

        return self::CONSENT_STATUS_LABELS[$status] ?? self::CONSENT_STATUS_LABELS[self::CONSENT_UNRECORDED];
    }

    public function harvestSeasonHeading(): string
    {
        return RiceDistributionBatch::seasonHeading(
            $this->harvest_season,
            $this->harvest_year
        );
    }

    public function inputCategoryLabel(): string
    {
        $category = $this->input_category ?: 'rice_seed';

        return self::INPUT_CATEGORY_LABELS[$category]
            ?? ucfirst(str_replace('_', ' ', $category));
    }

    public function quantityUnitLabel(): string
    {
        $unit = $this->quantity_unit ?: 'kg';

        return self::QUANTITY_UNIT_LABELS[$unit] ?? $unit;
    }

    public function isSeedInput(): bool
    {
        return str_ends_with(
            $this->input_category ?: 'rice_seed',
            '_seed'
        );
    }

    public function isFisheriesInput(): bool
    {
        return in_array(
            $this->input_category,
            self::FISHERIES_INPUT_CATEGORIES,
            true
        );
    }

    public function assistanceSectorLabel(): string
    {
        return self::ASSISTANCE_SECTOR_LABELS[
            $this->isFisheriesInput() ? 'fisheries' : 'agriculture'
        ];
    }
}
