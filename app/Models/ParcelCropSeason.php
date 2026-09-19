<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelCropSeason extends Model
{
    public const CROPS = [
        'rice' => 'Rice / Palay', 'corn' => 'Corn', 'vegetables' => 'Vegetables',
        'root_crops' => 'Root crops', 'fruit' => 'Fruit', 'legumes' => 'Legumes',
        'mixed' => 'Mixed crops', 'other' => 'Other crop', 'not_recorded' => 'Not recorded',
    ];

    public const COLORS = [
        'rice' => '#219653', 'corn' => '#D4A017', 'vegetables' => '#8E44AD',
        'root_crops' => '#A65C32', 'fruit' => '#DB5B36', 'legumes' => '#168C94',
        'mixed' => '#4263C7', 'other' => '#C44379', 'not_recorded' => '#8A9299',
    ];

    public const SEASONS = ['dry' => 'Dry season', 'wet' => 'Wet season'];

    protected $fillable = ['municipality_id', 'farm_plot_id', 'crop_year', 'season', 'crop', 'notes', 'recorded_by'];

    protected $casts = ['municipality_id' => 'integer', 'farm_plot_id' => 'integer', 'crop_year' => 'integer', 'recorded_by' => 'integer'];

    public function plot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class, 'farm_plot_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
