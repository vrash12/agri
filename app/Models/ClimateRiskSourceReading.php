<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateRiskSourceReading extends Model
{
    public const PROVIDERS = [
        'open_meteo' => 'Open-Meteo',
        'chirps' => 'CHIRPS',
        'sentinel2' => 'Copernicus Sentinel-2',
    ];

    protected $fillable = [
        'municipality_id', 'farm_plot_id', 'provider', 'metric', 'observed_on',
        'period_start', 'period_end', 'value', 'baseline_value', 'anomaly_percent',
        'unit', 'status', 'source_label', 'source_url', 'metadata', 'source_key',
        'fetched_at',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'farm_plot_id' => 'integer',
        'observed_on' => 'immutable_date',
        'period_start' => 'immutable_date',
        'period_end' => 'immutable_date',
        'value' => 'float',
        'baseline_value' => 'float',
        'anomaly_percent' => 'float',
        'metadata' => 'array',
        'fetched_at' => 'immutable_datetime',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class);
    }
}
