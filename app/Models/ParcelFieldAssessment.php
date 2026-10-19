<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelFieldAssessment extends Model
{
    public const CROPS = ['rice' => 'Rice / Palay', 'corn' => 'Corn', 'mungbean' => 'Mungbean / Munggo'];

    public const STAGES = ['not_planted' => 'Not planted yet', 'establishment' => 'Newly planted', 'vegetative' => 'Growing leaves', 'flowering' => 'Flowering', 'grain_filling' => 'Filling grain or pods', 'mature' => 'Ready to harvest', 'harvested' => 'Harvested', 'unknown' => 'Not checked'];

    public const WATER = ['reliable' => 'Enough water available', 'limited' => 'Water supply is limited', 'unavailable' => 'No water available', 'unknown' => 'Not checked'];

    public const CONDITIONS = ['normal' => 'No visible problem observed', 'wilting' => 'Wilting', 'yellowing' => 'Yellowing', 'waterlogged' => 'Waterlogged', 'unknown' => 'Not checked'];

    public const EVIDENCE_FIELDS = [
        'soil_ph', 'soil_texture', 'drainage', 'slope_percent', 'growing_temperature_c', 'growing_rainfall_mm',
        'water_availability', 'soil_source', 'soil_observed_on', 'terrain_source', 'terrain_observed_on',
        'climate_source', 'climate_period_start', 'climate_period_end', 'water_source', 'water_observed_on',
        'crop_stage', 'planted_on', 'field_condition', 'next_inspection_on', 'follow_up_status', 'follow_up_notes',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'farm_plot_id' => 'integer', 'farmer_id' => 'integer', 'municipality_id' => 'integer',
        'crop_year' => 'integer', 'observed_on' => 'immutable_date', 'evidence' => 'array',
    ];

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class);
    }
}
