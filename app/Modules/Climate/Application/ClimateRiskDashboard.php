<?php

namespace App\Modules\Climate\Application;

use App\Models\ClimateRiskAssessment;
use App\Models\ClimateRiskSourceReading;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ClimateRiskDashboard
{
    public const COLORS = ['normal' => '#236344', 'watch' => '#BC9309', 'alert' => '#C06B18', 'critical' => '#A6504A', 'unassessed' => '#75857D'];

    private const RANK = "MAX(CASE risk_level WHEN 'critical' THEN 4 WHEN 'alert' THEN 3 WHEN 'watch' THEN 2 WHEN 'normal' THEN 1 ELSE 0 END)";

    public function __construct(private MunicipalityAccess $access, private ParcelVegetationSnapshot $vegetation) {}

    public function installed(): bool
    {
        return Schema::hasTable('climate_risk_assessments') && Schema::hasTable('climate_risk_assessment_plots');
    }

    public function assessments(User $user): Builder
    {
        return $this->access->scope(ClimateRiskAssessment::query(), $user)
            ->whereHas('municipality', fn (Builder $query) => $query->active()->whereHas('supervisingProvince', fn (Builder $province) => $province->active()));
    }

    /** Bounded overview rows contain aggregate counts, never parcel coordinates or farmer fields. */
    public function overview(User $user, Collection $municipalities): Collection
    {
        $ids = $municipalities->modelKeys();
        $current = $this->assessments($user)->current()->whereIn('climate_risk_assessments.municipality_id', $ids);
        $statuses = (clone $current)->select('municipality_id')->selectRaw(self::RANK.' as severity, MAX(observed_on) as observed_on, MIN(valid_until) as review_on')->groupBy('municipality_id')->get()->keyBy('municipality_id');
        $expired = $this->assessments($user)->whereIn('municipality_id', $ids)->select('municipality_id')->selectRaw('MAX(observed_on) as last_observed_on')->groupBy('municipality_id')->get()->keyBy('municipality_id');
        $counts = (clone $current)->join('climate_risk_assessment_plots as impact', 'impact.climate_risk_assessment_id', '=', 'climate_risk_assessments.id')
            ->join('farm_plots', 'farm_plots.id', '=', 'impact.farm_plot_id')->join('farmers', 'farmers.id', '=', 'farm_plots.farmer_id')
            ->whereColumn('farmers.municipality_id', 'climate_risk_assessments.municipality_id')
            ->select('climate_risk_assessments.municipality_id')->selectRaw('COUNT(DISTINCT farm_plots.id) as affected_count')
            ->groupBy('climate_risk_assessments.municipality_id')->get()->keyBy('municipality_id');

        return $municipalities->map(function (Municipality $municipality) use ($statuses, $expired, $counts): array {
            $row = $statuses->get($municipality->id);
            $level = array_keys(ClimateRiskAssessment::LEVELS)[max(0, (int) ($row?->severity ?? 0) - 1)] ?? 'unassessed';
            if (! $row) {
                $level = 'unassessed';
            }

            return ['id' => $municipality->id, 'name' => $municipality->name, 'province' => $municipality->province,
                'level' => $level, 'label' => ClimateRiskAssessment::LEVELS[$level] ?? 'Not assessed', 'color' => self::COLORS[$level],
                'observed_on' => $row?->observed_on?->toDateString(), 'review_on' => $row?->review_on,
                'last_observed_on' => $expired->get($municipality->id)?->last_observed_on,
                'affected_count' => (int) ($counts->get($municipality->id)?->affected_count ?? 0)];
        });
    }

    public function affectedPlots(User $user, int $municipalityId): Builder
    {
        $current = $this->assessments($user)->current()->where('municipality_id', $municipalityId)->select('id');

        return FarmPlot::query()->with('farmer:id,municipality_id')
            ->whereHas('farmer', fn (Builder $query) => $query->where('municipality_id', $municipalityId))
            ->whereIn('id', DB::table('climate_risk_assessment_plots')->whereIn('climate_risk_assessment_id', $current)->select('farm_plot_id'));
    }

    public function plotSummary(FarmPlot $plot): array
    {
        return ['id' => $plot->id, 'name' => $plot->name ?: 'Parcel #'.$plot->id,
            'ndvi' => $this->vegetation->forPlot($plot),
            'satellite_url' => route('farm-plots.satellite.show', $plot),
            'map_url' => route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id, 'farmer_id' => $plot->farmer_id]).'#farmersMapModule'];
    }

    /**
     * Return the latest bounded provider summaries for the selected area.
     * These are evidence snapshots only; they never become an office level.
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function automatedEvidence(User $user, Municipality $municipality): array
    {
        if (! Schema::hasTable('climate_risk_source_readings')
            || ! $this->access->scopeMunicipalities(Municipality::query()->active(), $user)->whereKey($municipality->getKey())->exists()) {
            return ['open_meteo' => null, 'chirps' => null, 'sentinel2' => null];
        }

        $rows = ClimateRiskSourceReading::query()->where('municipality_id', $municipality->getKey())
            ->whereIn('provider', array_keys(ClimateRiskSourceReading::PROVIDERS))
            ->orderByDesc('fetched_at')->get()->groupBy('provider');
        $result = [];
        foreach (array_keys(ClimateRiskSourceReading::PROVIDERS) as $provider) {
            $result[$provider] = $rows->get($provider)?->sortByDesc('fetched_at')->first()?->only([
                'provider', 'metric', 'observed_on', 'period_start', 'period_end', 'value', 'baseline_value',
                'anomaly_percent', 'unit', 'status', 'source_label', 'source_url', 'metadata', 'fetched_at',
            ]);
        }

        return $result;
    }
}
