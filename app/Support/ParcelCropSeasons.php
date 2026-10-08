<?php

namespace App\Support;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ParcelCropSeasons
{
    public function __construct(private ConcurrentWrite $writes, private MunicipalityAccess $access, private PlantedCropAreas $areas)
    {
    }

    public function save(FarmPlot $plot, User $user, array $data): ParcelCropSeason
    {
        // The parent lock also serializes simultaneous first entries for a period.
        return $this->writes->locked($plot, function (FarmPlot $current) use ($user, $data) {
            Gate::forUser($user)->authorize('update', $current);
            $municipalityId = (int) $current->farmer->municipality_id;
            $record = ParcelCropSeason::query()->where('farm_plot_id', $current->id)
                ->where('crop_year', $data['crop_year'])->where('season', $data['season'])
                ->lockForUpdate()->first();
            if ($record) {
                Gate::forUser($user)->authorize('update', $record);
            }
            $version = $record ? ConcurrentWrite::version($record) : 'new';
            if (! hash_equals($version, $data['_record_version'])) {
                throw ValidationException::withMessages(['_record_version' => 'This season was changed by another user. Reload the season, review the latest crop, then save again.']);
            }
            $record ??= new ParcelCropSeason(['farm_plot_id' => $current->id, 'municipality_id' => $municipalityId]);
            $areas = $record->planted_areas ?: [];
            if (array_key_exists('planted_areas', $data)) {
                if (! Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
                    throw ValidationException::withMessages(['planted_areas' => 'Planted-area drawing is not available yet. Contact the System Owner.']);
                }
                if (! hash_equals(ConcurrentWrite::version($current), $data['_plot_version'])) {
                    throw ValidationException::withMessages(['_plot_version' => 'The parcel boundary changed. Reload it and review the crop areas before saving.']);
                }
                $areas = $this->areas->prepare($current, $data['planted_areas']);
                $record->planted_areas = $areas;
            }
            $areaCrops = array_unique(array_column($areas, 'crop'));
            $crop = count($areaCrops) === 1 ? reset($areaCrops) : (count($areaCrops) > 1 ? 'mixed' : $data['crop']);
            $record->fill([
                'crop_year' => $data['crop_year'], 'season' => $data['season'], 'crop' => $crop,
                'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id,
            ])->save();

            return $record;
        });
    }

    public function layer(User $user, array $plotIds, int $year, string $season): array
    {
        $plots = FarmPlot::query()->whereIn('id', $plotIds)->whereHas('farmer',
            fn ($query) => $this->access->scope($query, $user, 'farmers.municipality_id'))
            ->with('farmer:id,municipality_id')->get(['id', 'farmer_id']);
        $columns = ['farm_plot_id', 'municipality_id', 'crop'];
        if (Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            // The broad classification layer needs crop names, never coordinates.
            $columns[] = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? DB::raw("JSON_EXTRACT(planted_areas, '$[*].crop') AS area_crops_json")
                : 'planted_areas';
        }
        $records = ParcelCropSeason::query()->whereIn('farm_plot_id', $plots->pluck('id'))
            ->where('crop_year', $year)->where('season', $season)
            ->get($columns)->keyBy('farm_plot_id');

        return [
            'year' => $year, 'season' => $season,
            'records' => $plots->map(function ($plot) use ($records) {
                $record = $records->get($plot->id);
                $crop = $record && $record->municipality_id === (int) $plot->farmer->municipality_id
                    && isset(ParcelCropSeason::CROPS[$record->crop]) ? $record->crop : 'not_recorded';

                $areaCrops = $record?->area_crops_json !== null ? (json_decode($record->area_crops_json, true) ?: []) : array_column($record?->planted_areas ?: [], 'crop');
                $areaCrops = $crop !== 'not_recorded' ? array_values(array_unique($areaCrops)) : [];

                return ['plot_id' => $plot->id, 'crop' => $crop, 'crop_label' => ParcelCropSeason::CROPS[$crop], 'color' => ParcelCropSeason::COLORS[$crop], 'area_crops' => $areaCrops, 'has_planted_areas' => $areaCrops !== []];
            })->values()->all(),
            'legend' => collect(ParcelCropSeason::CROPS)->map(fn ($label, $crop) => [
                'crop' => $crop, 'label' => $label, 'color' => ParcelCropSeason::COLORS[$crop],
            ])->values()->all(),
        ];
    }

    /** Bounded detail layer for the selected farmer, never the whole registry. */
    public function plantedLayer(User $user, array $plotIds, int $year, string $season): array
    {
        if (! Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            return ['available' => false, 'records' => [], 'message' => 'Planted-area drawing is not available yet. Contact the System Owner.'];
        }
        $plots = FarmPlot::query()->whereIn('id', $plotIds)->whereHas('farmer',
            fn ($query) => $this->access->scope($query, $user, 'farmers.municipality_id'))
            ->with('farmer:id,municipality_id')->get(['id', 'farmer_id', 'polygon_json']);
        $records = ParcelCropSeason::query()->whereIn('farm_plot_id', $plots->pluck('id'))
            ->where('crop_year', $year)->where('season', $season)
            ->get(['farm_plot_id', 'municipality_id', 'planted_areas'])->keyBy('farm_plot_id');
        $result = [];
        foreach ($plots as $plot) {
            $record = $records->get($plot->id);
            $areas = [];
            $needsReview = false;
            if ($record && $record->municipality_id === (int) $plot->farmer->municipality_id) {
                try {
                    $areas = $this->areas->prepare($plot, $record->planted_areas ?: []);
                } catch (ValidationException $exception) {
                    $needsReview = true;
                }
            }
            $result[] = ['plot_id' => $plot->id, 'needs_review' => $needsReview, 'areas' => array_map(fn ($area) => $area + [
                'crop_label' => ParcelCropSeason::CROPS[$area['crop']], 'color' => ParcelCropSeason::COLORS[$area['crop']],
            ], $areas)];
        }

        return ['available' => true, 'year' => $year, 'season' => $season, 'records' => $result];
    }
}
