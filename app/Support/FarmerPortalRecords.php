<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Models\RiceSeedDistribution;
use Illuminate\Database\Eloquent\Builder;

final class FarmerPortalRecords
{
    public function farmer(FarmerPortalAccount $account): Farmer
    {
        abort_unless($account->hasUsableScope(), 403);

        return Farmer::query()->whereKey($account->farmer_id)->where('municipality_id', $account->municipality_id)
            ->with('municipality:id,name')->firstOrFail([
                'id', 'municipality_id', 'first_name', 'middle_name', 'last_name', 'ext_name', 'owner_name',
                'rsbsa_no', 'ffrs', 'date_of_birth', 'contact_number', 'farm_location', 'farm_municipality', 'farm_area_ha',
            ]);
    }

    public function plots(FarmerPortalAccount $account): Builder
    {
        return FarmPlot::query()->where('farmer_id', $account->farmer_id)
            ->whereHas('farmer', fn (Builder $query) => $query->where('municipality_id', $account->municipality_id));
    }

    public function releases(FarmerPortalAccount $account): Builder
    {
        // A legacy identity snapshot alone never grants access to a release.
        return RiceSeedDistribution::query()->where('farmer_id', $account->farmer_id)
            ->where('municipality_id', $account->municipality_id);
    }

    public function seasonalCrops(FarmerPortalAccount $account, array $plotIds, int $year)
    {
        return ParcelCropSeason::query()->where('municipality_id', $account->municipality_id)
            ->whereIn('farm_plot_id', $plotIds)->where('crop_year', $year)->orderBy('season')
            ->get(['farm_plot_id', 'crop_year', 'season', 'crop'])->groupBy('farm_plot_id');
    }

    public function geometry(FarmerPortalAccount $account, int $plotId): array
    {
        $query = $this->plots($account)->whereKey($plotId);
        abort_unless((clone $query)->exists(), 404);
        // Check the stored byte length before loading/decoding a large legacy ring.
        $plot = $query->whereRaw('LENGTH(polygon_json) <= ?', [1000000])
            ->first(['id', 'name', 'area_ha', 'color', 'polygon_json']);
        abort_unless($plot, 422, 'This parcel is too detailed to display here. Contact your agriculture office.');
        $ring = $plot->polygon_json;
        abort_unless(is_array($ring) && count($ring) >= 3 && count($ring) <= 10000, 422, 'The recorded parcel geometry needs office review.');
        $path = [];
        foreach ($ring as $point) {
            abort_unless(is_array($point) && isset($point['lat'], $point['lng'])
                && is_numeric($point['lat']) && is_numeric($point['lng'])
                && abs((float) $point['lat']) <= 90 && abs((float) $point['lng']) <= 180, 422, 'The recorded parcel geometry needs office review.');
            $path[] = ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
        }

        return ['id' => $plot->id, 'name' => $plot->name, 'area_ha' => $plot->area_ha,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) $plot->color) ? $plot->color : '#236344', 'paths' => [$path]];
    }
}
