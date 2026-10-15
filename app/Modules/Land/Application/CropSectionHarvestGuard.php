<?php

namespace App\Modules\Land\Application;

use App\Models\ParcelCropSeason;
use App\Models\User;
use App\Modules\Land\Contracts\HarvestSectionGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CropSectionHarvestGuard implements HarvestSectionGuard
{
    public function assertMatches(array $validated, User $user): void
    {
        $seasonId = $validated['parcel_crop_season_id'] ?? null;
        $areaKey = $validated['planted_area_key'] ?? null;
        if ($seasonId === null || $areaKey === null || ! Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
            return;
        }

        $season = ParcelCropSeason::query()->lockForUpdate()->find($seasonId);
        $area = $season
            ? collect($season->planted_areas ?: [])->firstWhere('area_key', $areaKey)
            : null;
        if (! $season || ! is_array($area)) {
            throw ValidationException::withMessages([
                'planted_area_key' => 'That planted section changed while the harvest was being saved. Reload and choose it again.',
            ]);
        }
        Gate::forUser($user)->authorize('view', $season);
        $plot = $season->plot()->with('farmer')->first();
        if (! $plot || (int) $plot->id !== (int) ($validated['farm_plot_id'] ?? 0)
            || (int) $plot->farmer_id !== (int) ($validated['farmer_id'] ?? 0)
            || (int) $season->municipality_id !== (int) $plot->farmer?->municipality_id) {
            throw ValidationException::withMessages([
                'parcel_crop_season_id' => 'The planted section no longer belongs to the selected farmer and parcel.',
            ]);
        }

        $expected = [
            'rice' => 'rice', 'corn' => 'corn', 'vegetables' => 'vegetable',
            'root_crops' => 'rootcrop', 'fruit' => 'fruit', 'legumes' => 'other',
        ][$area['crop'] ?? ''] ?? null;
        if ((int) $season->crop_year !== (int) ($validated['harvest_year'] ?? 0)
            || ($validated['season'] ?? null) !== $season->season
            || ($expected !== null && $expected !== ($validated['commodity'] ?? null))) {
            throw ValidationException::withMessages([
                'parcel_crop_season_id' => 'The planted section changed and no longer matches this harvest. Reload and choose a current section.',
            ]);
        }
    }
}
