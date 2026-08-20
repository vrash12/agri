<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiceSeedDistribution extends Model
{
    use HasFactory;

    public const INPUT_CATEGORY_LABELS = [
        'rice_seed' => 'Rice seed',
        'corn_seed' => 'Corn seed',
        'vegetable_seed' => 'Vegetable seed',
        'fertilizer' => 'Fertilizer / Abono',
        'soil_amendment' => 'Soil amendment',
        'other_input' => 'Other farm input',
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
    ];

    protected $table = 'rice_seed_distributions';

    protected $fillable = [
        'municipality_id',

        // FK (connected to farmers.id in your SQL)
        'farmer_id',

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

        // Distribution fields
        'kgs_received',
        'date_received',

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
    ];

    protected $attributes = [
        'input_category' => 'rice_seed',
        'quantity_unit' => 'kg',
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
}
