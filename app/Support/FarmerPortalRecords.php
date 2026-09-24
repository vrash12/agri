<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\RiceSeedDistribution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FarmerPortalRecords
{
    private const MAP_PAGE_SIZE = 20;

    private const MAP_GEOMETRY_BYTES = 1000000;

    public function farmer(FarmerPortalAccount $account): Farmer
    {
        abort_unless($account->hasUsableScope(), 403);

        return Farmer::query()->whereKey($account->farmer_id)->where('municipality_id', $account->municipality_id)
            ->with('municipality:id,name')->firstOrFail([
                'id', 'municipality_id', 'first_name', 'middle_name', 'last_name', 'ext_name', 'owner_name',
                'rsbsa_no', 'ffrs', 'date_of_birth', 'contact_number', 'gender', 'farm_location',
                'farm_municipality', 'farm_province', 'farm_area_ha', 'ecosystem', 'ecosystem_source',
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

    public function releaseDetails(FarmerPortalAccount $account): Builder
    {
        return $this->releases($account)->select([
            'id', 'batch_id', 'date_received', 'input_category', 'seed_variety_claimed', 'kgs_received', 'quantity_unit',
            'seed_bags', 'seed_bag_kg', 'claimed_area_ha', 'registered_rice_area_ha', 'lot_series',
            'crop_establishment', 'date_of_sowing_label', 'seed_variety_planted', 'seed_class',
        ])->with(['batch' => fn ($query) => $query->where('municipality_id', $account->municipality_id)
            ->select(['id', 'title', 'reference', 'planting_year', 'planting_season'])]);
    }

    /**
     * Harvests recorded against this farmer and the account's municipality.
     *
     * The municipality predicate is intentional even though farmer_id is already
     * present: it keeps legacy identity rows from becoming a read path after a
     * transfer and mirrors the assistance ownership rule.
     */
    public function harvests(FarmerPortalAccount $account): Builder
    {
        return HarvestRecord::query()->where('farmer_id', $account->farmer_id)
            ->where('municipality_id', $account->municipality_id);
    }

    public function harvestDetails(FarmerPortalAccount $account): Builder
    {
        // A malformed legacy link must not reveal another farmer's parcel name.
        return $this->harvests($account)->select([
            'id', 'farm_plot_id', 'commodity', 'variety', 'season', 'harvest_year',
            'date_harvested', 'area_harvested_ha', 'quantity', 'quantity_unit',
        ])->with(['farmPlot' => fn ($query) => $query->whereIn('id', $this->plots($account)->select('id'))
            ->select(['id', 'name'])]);
    }

    /**
     * Bounded headline figures for the farmer's overview.
     *
     * @return array{parcels:int, area_ha:?float, area_recorded:int, assistance:int, harvests:int}
     */
    public function overview(FarmerPortalAccount $account): array
    {
        return $this->parcelTotals($account) + [
            'assistance' => $this->releases($account)->count(),
            'harvests' => $this->harvests($account)->count(),
        ];
    }

    /** @return array{parcels:int, area_ha:?float, area_recorded:int} */
    public function parcelTotals(FarmerPortalAccount $account): array
    {
        $parcelTotals = $this->plots($account)
            ->selectRaw('COUNT(*) as parcels, COUNT(area_ha) as area_recorded, SUM(area_ha) as area_ha')
            ->toBase()->first();

        return [
            'parcels' => (int) ($parcelTotals?->parcels ?? 0),
            'area_ha' => $parcelTotals?->area_ha === null ? null : (float) $parcelTotals->area_ha,
            'area_recorded' => (int) ($parcelTotals?->area_recorded ?? 0),
        ];
    }

    /**
     * Recent activity for the overview. Each query is capped before it is loaded.
     *
     * @return array{assistance:Collection, harvests:Collection, parcels:Collection}
     */
    public function recentActivity(FarmerPortalAccount $account, int $limit = 5): array
    {
        $limit = max(1, min($limit, 10));

        return [
            'assistance' => $this->releases($account)
                ->select(['id', 'date_received', 'input_category', 'seed_variety_claimed', 'kgs_received', 'quantity_unit'])
                ->orderByDesc('date_received')->orderByDesc('id')->limit($limit)->get(),
            'harvests' => $this->harvestDetails($account)
                ->orderByDesc('date_harvested')->orderByDesc('harvest_year')->orderByDesc('id')->limit($limit)->get(),
            'parcels' => $this->plots($account)->select(['id', 'name', 'area_ha'])->orderBy('id')->limit(3)->get(),
        ];
    }

    /** Summaries preserve both item/category and unit, including missing quantities. */
    public function assistanceTotals(FarmerPortalAccount $account): Collection
    {
        return $this->releases($account)
            ->selectRaw('input_category, quantity_unit, COUNT(*) as records, COUNT(kgs_received) as quantities_recorded, SUM(kgs_received) as total_quantity')
            ->groupBy('input_category', 'quantity_unit')->orderBy('input_category')->orderBy('quantity_unit')
            ->limit(24)->toBase()->get();
    }

    public function harvestTotals(FarmerPortalAccount $account, ?int $year): Collection
    {
        return $this->harvests($account)->when($year !== null, fn (Builder $query) => $query->where('harvest_year', $year))
            ->selectRaw('commodity, quantity_unit, COUNT(*) as records, COUNT(quantity) as quantities_recorded, SUM(quantity) as total_quantity')
            ->groupBy('commodity', 'quantity_unit')->orderBy('commodity')->orderBy('quantity_unit')
            ->limit(24)->toBase()->get();
    }

    public function seasonalCrops(FarmerPortalAccount $account, array $plotIds, int $year): Collection
    {
        return ParcelCropSeason::query()->where('municipality_id', $account->municipality_id)
            ->whereIn('farm_plot_id', $plotIds)
            ->whereIn('farm_plot_id', $this->plots($account)->select('id'))
            ->where('crop_year', $year)->orderBy('season')
            ->get(['farm_plot_id', 'crop_year', 'season', 'crop'])->groupBy('farm_plot_id');
    }

    public function geometry(FarmerPortalAccount $account, int $plotId): array
    {
        $query = $this->plots($account)->whereKey($plotId);
        abort_unless((clone $query)->exists(), 404);
        // Check the stored byte length before loading/decoding a large legacy ring.
        $plot = $query->whereRaw('LENGTH(polygon_json) <= ?', [self::MAP_GEOMETRY_BYTES])
            ->first(['id', 'name', 'area_ha', 'color', 'polygon_json']);
        abort_unless($plot, 422, 'This parcel is too detailed to display here. Contact your agriculture office.');
        $geometry = $this->parcelGeometry($plot);
        abort_unless($geometry, 422, 'The recorded parcel geometry needs office review.');

        return $geometry;
    }

    /**
     * Progress through every owned parcel without loading all stored rings at once.
     * Invalid/oversized boundaries remain listed, so missing land is never silent.
     *
     * @return array{plots:array, total:int, next_after_id:?int}
     */
    public function mapPage(FarmerPortalAccount $account, int $year, int $afterId = 0): array
    {
        $candidates = $this->plots($account)->where('id', '>', $afterId)->orderBy('id')
            ->select(['id', 'name', 'area_ha', 'color'])->selectRaw('LENGTH(polygon_json) as geometry_bytes')
            ->limit(self::MAP_PAGE_SIZE + 1)->get();
        $selected = collect();
        $bytes = 0;
        foreach ($candidates->take(self::MAP_PAGE_SIZE) as $plot) {
            $size = (int) $plot->geometry_bytes;
            $loadableBytes = $size <= self::MAP_GEOMETRY_BYTES ? $size : 0;
            if ($bytes + $loadableBytes > self::MAP_GEOMETRY_BYTES) {
                break;
            }
            $bytes += $loadableBytes;
            $selected->push($plot);
        }
        $ids = $selected->pluck('id')->all();
        // A growing ring must not escape the page's byte budget between queries.
        $loadable = $selected->filter(fn (FarmPlot $plot) => (int) $plot->geometry_bytes <= self::MAP_GEOMETRY_BYTES);
        $rings = $loadable->isEmpty() ? collect() : $this->plots($account)
            ->where(function (Builder $query) use ($loadable) {
                foreach ($loadable as $plot) {
                    $query->orWhere(fn (Builder $parcel) => $parcel->whereKey($plot->id)
                        ->whereRaw('LENGTH(polygon_json) <= ?', [(int) $plot->geometry_bytes]));
                }
            })->get(['id', 'polygon_json'])->keyBy('id');
        $crops = $this->seasonalCrops($account, $ids, $year);
        $plots = $selected->map(function (FarmPlot $plot) use ($rings, $crops) {
            $plot->polygon_json = $rings->get($plot->id)?->polygon_json;
            $geometry = $this->parcelGeometry($plot) ?? [
                'id' => $plot->id, 'name' => $plot->name, 'area_ha' => $plot->area_ha, 'paths' => null,
            ];

            return $geometry + ['crops' => $crops->get($plot->id, collect())->map(fn ($crop) => [
                'season' => ParcelCropSeason::SEASONS[$crop->season] ?? 'Season not recorded',
                'crop' => ParcelCropSeason::CROPS[$crop->crop] ?? 'Crop not recorded',
            ])->values()->all()];
        })->all();

        return ['plots' => $plots, 'total' => $this->plots($account)->count(),
            'next_after_id' => $candidates->count() > $selected->count() ? (int) $selected->last()->id : null];
    }

    private function parcelGeometry(FarmPlot $plot): ?array
    {
        $ring = $plot->polygon_json;
        if (! is_array($ring) || count($ring) < 3 || count($ring) > 10000) {
            return null;
        }
        $path = [];
        foreach ($ring as $point) {
            if (! is_array($point) || ! isset($point['lat'], $point['lng'])
                || ! is_numeric($point['lat']) || ! is_numeric($point['lng'])
                || abs((float) $point['lat']) > 90 || abs((float) $point['lng']) > 180) {
                return null;
            }
            $path[] = ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
        }

        return ['id' => $plot->id, 'name' => $plot->name, 'area_ha' => $plot->area_ha,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) $plot->color) ? $plot->color : '#236344', 'paths' => [$path]];
    }
}
