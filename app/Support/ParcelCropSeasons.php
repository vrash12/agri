<?php

namespace App\Support;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ParcelCropSeasons
{
    public function __construct(private ConcurrentWrite $writes, private MunicipalityAccess $access)
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
            $record->fill([
                'crop_year' => $data['crop_year'], 'season' => $data['season'], 'crop' => $data['crop'],
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
        $records = ParcelCropSeason::query()->whereIn('farm_plot_id', $plots->pluck('id'))
            ->where('crop_year', $year)->where('season', $season)
            ->get(['farm_plot_id', 'municipality_id', 'crop'])->keyBy('farm_plot_id');

        return [
            'year' => $year, 'season' => $season,
            'records' => $plots->map(function ($plot) use ($records) {
                $record = $records->get($plot->id);
                $crop = $record && $record->municipality_id === (int) $plot->farmer->municipality_id
                    && isset(ParcelCropSeason::CROPS[$record->crop]) ? $record->crop : 'not_recorded';

                return ['plot_id' => $plot->id, 'crop' => $crop, 'crop_label' => ParcelCropSeason::CROPS[$crop], 'color' => ParcelCropSeason::COLORS[$crop]];
            })->values()->all(),
            'legend' => collect(ParcelCropSeason::CROPS)->map(fn ($label, $crop) => [
                'crop' => $crop, 'label' => $label, 'color' => ParcelCropSeason::COLORS[$crop],
            ])->values()->all(),
        ];
    }
}
