<?php

namespace App\Models;

use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClimateRiskAssessment extends Model
{
    public const LEVELS = ['normal' => 'Normal', 'watch' => 'Watch', 'alert' => 'Alert', 'critical' => 'Critical'];

    public const DROUGHT = ['not_recorded' => 'Not recorded', 'none' => 'No dry condition reported', 'dry_condition' => 'Dry condition', 'dry_spell' => 'Dry spell', 'drought' => 'Drought'];

    public const RAINFALL = ['not_recorded' => 'Not recorded', 'below_normal' => 'Below normal', 'near_normal' => 'Near normal', 'above_normal' => 'Above normal'];

    protected $fillable = ['risk_level', 'drought_status', 'rainfall_outlook', 'observed_on', 'valid_until', 'source_type', 'source_label', 'source_url', 'notes'];

    protected $casts = ['municipality_id' => 'integer', 'observed_on' => 'immutable_date', 'valid_until' => 'immutable_date'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function plots(): BelongsToMany
    {
        return $this->belongsToMany(FarmPlot::class, 'climate_risk_assessment_plots');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $date = LocalTime::now()->toDateString();

        return $query->whereDate('observed_on', '<=', $date)->whereDate('valid_until', '>=', $date);
    }

    public function isCurrent(): bool
    {
        $today = LocalTime::now()->toDateString();

        return $this->observed_on->toDateString() <= $today && $this->valid_until->toDateString() >= $today;
    }
}
