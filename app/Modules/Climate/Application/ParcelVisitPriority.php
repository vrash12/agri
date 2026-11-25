<?php

namespace App\Modules\Climate\Application;

use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\ParcelFieldAssessment;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Field-visit planning flags for a bounded batch of loaded parcels.
 *
 * Uses saved data only: the latest field check's open follow-up date, already
 * completed satellite history (a cache read, never a provider request) and saved
 * crop seasons without a linked harvest. It is a visit-planning aid, not a
 * diagnosis, and returns reason keys and dates only.
 */
final class ParcelVisitPriority
{
    public const BATCH = 200;

    /** Minimum fall in NDVI/NDMI between two comparable saved pictures. */
    public const DECLINE_THRESHOLD = 0.05;

    public const REASONS = [
        'visit_overdue' => 'Planned field visit is overdue',
        'satellite_decline' => 'Greenness or moisture fell in saved pictures',
        'no_harvest' => 'Planted this season, no harvest recorded',
    ];

    public function __construct(private MunicipalityAccess $access, private ParcelSatelliteHistory $satellites, private ParcelStressMonitor $monitor) {}

    /**
     * @param  list<int>  $municipalityIds  the validated map workspace
     * @param  list<int>  $plotIds
     * @return array<string, mixed>
     */
    public function evaluate(User $user, array $municipalityIds, array $plotIds, int $year, string $season): array
    {
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        $plotIds = array_slice(array_values(array_unique(array_map('intval', $plotIds))), 0, self::BATCH);
        $plots = FarmPlot::query()->whereIn('id', $plotIds)
            ->whereHas('farmer', function ($query) use ($user, $municipalityIds): void {
                $this->access->scope($query, $user, 'farmers.municipality_id');
                $query->whereIn('farmers.municipality_id', $municipalityIds);
            })
            ->with('farmer:id,municipality_id')->get(['id', 'farmer_id', 'polygon_json']);
        $ids = $plots->modelKeys();
        $assessments = $this->latestAssessments($ids, $year, $season);
        $seasons = ParcelCropSeason::query()->whereIn('farm_plot_id', $ids)->where('crop_year', $year)->where('season', $season)
            ->where('crop', '!=', 'not_recorded')->get(['id', 'farm_plot_id', 'municipality_id'])->keyBy('farm_plot_id');
        $harvested = $this->harvestedPlots($ids, $seasons->pluck('id')->all(), $year, $season);
        $today = ParcelSuitability::today();

        $flagged = [];
        $counts = array_fill_keys(array_keys(self::REASONS), 0);
        foreach ($plots as $plot) {
            $municipalityId = (int) $plot->farmer->municipality_id;
            $reasons = [];
            $assessment = $assessments->get($plot->id);
            if ($assessment && $this->current($assessment, $plot, $municipalityId)) {
                $due = ParcelSuitability::date($assessment->evidence['next_inspection_on'] ?? null);
                if (($assessment->evidence['follow_up_status'] ?? null) === 'open' && $due && $due->lte($today)) {
                    $reasons[] = ['key' => 'visit_overdue', 'label' => self::REASONS['visit_overdue'], 'date' => $due->toDateString()];
                }
            }
            $decline = $this->decline($plot);
            if ($decline !== null) {
                $reasons[] = ['key' => 'satellite_decline', 'label' => self::REASONS['satellite_decline'], 'date' => $decline];
            }
            $record = $seasons->get($plot->id);
            if ($record && (int) $record->municipality_id === $municipalityId
                && ! isset($harvested['plots'][$plot->id]) && ! isset($harvested['seasons'][$record->id])) {
                $reasons[] = ['key' => 'no_harvest', 'label' => self::REASONS['no_harvest'], 'date' => null];
            }
            if ($reasons === []) {
                continue;
            }
            foreach ($reasons as $reason) {
                $counts[$reason['key']]++;
            }
            $flagged[] = ['plot_id' => (int) $plot->id, 'reasons' => $reasons,
                'level' => count($reasons) > 1 || $reasons[0]['key'] === 'visit_overdue' ? 'high' : 'check'];
        }

        return ['year' => $year, 'season' => $season, 'checked' => count($ids), 'flagged' => $flagged, 'counts' => $counts,
            'legend' => collect(self::REASONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'limits' => 'Visit-planning aid from saved records only. It does not diagnose crop health, damage or eligibility.'];
    }

    private function latestAssessments(array $ids, int $year, string $season)
    {
        if ($ids === [] || ! Schema::hasTable('parcel_field_assessments')) {
            return collect();
        }
        $latest = ParcelFieldAssessment::query()->selectRaw('MAX(id)')->whereIn('farm_plot_id', $ids)
            ->where('crop_year', $year)->where('season', $season)->groupBy('farm_plot_id');

        return ParcelFieldAssessment::query()->whereIn('id', $latest)
            ->get(['id', 'farm_plot_id', 'farmer_id', 'municipality_id', 'context_fingerprint', 'evidence'])->keyBy('farm_plot_id');
    }

    /** Old ownership or boundary context never becomes current evidence. */
    private function current(ParcelFieldAssessment $assessment, FarmPlot $plot, int $municipalityId): bool
    {
        return (int) $assessment->farmer_id === (int) $plot->farmer_id && (int) $assessment->municipality_id === $municipalityId
            && hash_equals(ClimateEvidenceContext::plot($plot), (string) $assessment->context_fingerprint);
    }

    /** @return array{plots:array<int,bool>,seasons:array<int,bool>} */
    private function harvestedPlots(array $plotIds, array $seasonIds, int $year, string $season): array
    {
        $result = ['plots' => [], 'seasons' => []];
        if ($plotIds === [] || ! Schema::hasTable('harvest_records')) {
            return $result;
        }
        $hasSeasonLink = Schema::hasColumn('harvest_records', 'parcel_crop_season_id');
        $rows = HarvestRecord::query()->where('harvest_year', $year)->where('season', $season)
            ->where(function ($query) use ($plotIds, $seasonIds, $hasSeasonLink): void {
                $query->whereIn('farm_plot_id', $plotIds);
                if ($hasSeasonLink && $seasonIds !== []) {
                    $query->orWhereIn('parcel_crop_season_id', $seasonIds);
                }
            })->get($hasSeasonLink ? ['farm_plot_id', 'parcel_crop_season_id'] : ['farm_plot_id']);
        foreach ($rows as $row) {
            if ($row->farm_plot_id) {
                $result['plots'][(int) $row->farm_plot_id] = true;
            }
            if ($hasSeasonLink && $row->parcel_crop_season_id) {
                $result['seasons'][(int) $row->parcel_crop_season_id] = true;
            }
        }

        return $result;
    }

    /** Latest picture date when saved NDVI or NDMI fell by the threshold between comparable pictures. */
    private function decline(FarmPlot $plot): ?string
    {
        $history = $this->satellites->forPlot($plot);
        if (($history['ndvi'] ?? []) === [] && ($history['ndmi'] ?? []) === []) {
            return null;
        }
        $dates = [];
        foreach ($this->monitor->summarize(null, [], $history)['satellite'] as $trend) {
            if (in_array($trend['index'], ['ndvi', 'ndmi'], true) && $trend['status'] === 'comparable'
                && $trend['delta'] !== null && $trend['delta'] <= -self::DECLINE_THRESHOLD && $trend['observations'] !== []) {
                $dates[] = $trend['observations'][count($trend['observations']) - 1]['date'];
            }
        }

        return $dates === [] ? null : max($dates);
    }
}
