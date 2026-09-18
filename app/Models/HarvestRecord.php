<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a farmer actually harvested.
 *
 * The register could say what was handed out and never what came of it. This is the
 * other half, and it is a record in its own right rather than a field on a release:
 * a farmer who planted their own seed still has a harvest, and a harvest of corn or
 * tilapia is not a property of a rice seed hand-out.
 */
class HarvestRecord extends Model
{
    use HasFactory;

    /**
     * The commodities a harvest can be recorded against.
     *
     * Deliberately broader than rice. The office's reporting asks for production "by
     * major commodity", which the previous rice-only fields could never answer.
     */
    public const COMMODITY_LABELS = [
        'rice' => 'Rice / Palay',
        'corn' => 'Corn',
        'vegetable' => 'Vegetables',
        'rootcrop' => 'Root crops',
        'fruit' => 'Fruit',
        'fish' => 'Fish / Aquaculture',
        'livestock' => 'Livestock',
        'poultry' => 'Poultry',
        'other' => 'Other commodity',
    ];

    /**
     * Seasons, matching the vocabulary the distribution sheet already uses so the two
     * halves of a cropping year can be read together.
     */
    public const SEASON_LABELS = [
        'dry' => 'Dry season',
        'wet' => 'Wet season',
    ];

    /**
     * Units a harvest is measured in.
     *
     * Kept separate from the assistance unit list because what comes off a field is
     * weighed differently from what is handed out: harvest arrives in sacks, cavans
     * and kilograms, not in packs or bottles. Nothing converts between them.
     */
    public const QUANTITY_UNIT_LABELS = [
        'kg' => 'Kilograms (kg)',
        'sack' => 'Sacks',
        'cavan' => 'Cavans',
        'bag' => 'Bags',
        'ton' => 'Metric tons',
        'piece' => 'Pieces',
    ];

    protected $table = 'harvest_records';

    protected $fillable = [
        'municipality_id',
        'farmer_id',
        'farm_plot_id',
        'rice_seed_distribution_id',
        'commodity',
        'variety',
        'season',
        'harvest_year',
        'date_harvested',
        'area_harvested_ha',
        'quantity',
        'quantity_unit',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'farmer_id' => 'integer',
        'farm_plot_id' => 'integer',
        'rice_seed_distribution_id' => 'integer',
        'harvest_year' => 'integer',
        'date_harvested' => 'date',
        'area_harvested_ha' => 'decimal:4',
        'quantity' => 'decimal:3',
        'recorded_by' => 'integer',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class);
    }

    /**
     * The assistance release this harvest was projected from, when it came from one.
     *
     * Null for a harvest entered on its own, which is every commodity no seed sheet
     * covers and every farmer who planted their own seed.
     */
    public function riceSeedDistribution(): BelongsTo
    {
        return $this->belongsTo(RiceSeedDistribution::class);
    }

    public function commodityLabel(): string
    {
        return self::COMMODITY_LABELS[$this->commodity] ?? ($this->commodity ?: 'Not recorded');
    }

    public function seasonLabel(): string
    {
        return self::SEASON_LABELS[$this->season] ?? ($this->season ?: 'Not recorded');
    }

    public function quantityUnitLabel(): string
    {
        return self::QUANTITY_UNIT_LABELS[$this->quantity_unit] ?? ($this->quantity_unit ?: '');
    }

    /**
     * The period this harvest is reported in, stated rather than derived.
     *
     * A harvest with a season and year says so; one with only a year says only that.
     * Nothing here guesses a season from the harvest date, which is the same rule the
     * assistance register follows.
     */
    public function periodLabel(): string
    {
        if ($this->harvest_year === null) {
            return 'Period not recorded';
        }

        return $this->season
            ? $this->harvest_year.' '.($this->season === 'dry' ? 'DS' : 'WS')
            : (string) $this->harvest_year;
    }
}
