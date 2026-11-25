<?php

namespace App\Modules\Land\Application;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\ParcelCropSeason;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Executes an interpreted map question as scoped, read-only counts.
 *
 * Every query starts from the account's MunicipalityAccess scope and is then
 * narrowed to the map's current workspace municipalities. A typed place name
 * can only narrow that set further; an unknown place clarifies instead of
 * widening. Responses carry counts and allowlisted filter descriptors, never
 * names, identifiers, addresses or geometry.
 */
final class MapQuestionAnswers
{
    public const BATCH = 200;

    /** Distinct place names read for matching; a bound, not a business rule. */
    private const PLACE_LIMIT = 2000;

    public function __construct(private MunicipalityAccess $access, private MapQuestionInterpreter $interpreter) {}

    /**
     * @param  list<int>  $municipalityIds  the map workspace, already validated against scope
     * @param  array{year:int,season:string}  $context
     * @param  array<string, mixed>  $workspaceParameters
     * @return array<string, mixed>
     */
    public function answer(User $user, array $municipalityIds, string $question, array $context, int $currentYear, array $workspaceParameters = []): array
    {
        $this->authorize($user);
        $parsed = $this->interpreter->interpret($question, $context, $currentYear);
        if ($parsed['status'] !== 'ok') {
            return $this->clarification($parsed['message']);
        }

        $location = null;
        if ($parsed['location_text'] !== null) {
            $location = $this->resolveLocation($user, $municipalityIds, $parsed['location_text']);
            if (isset($location['clarify'])) {
                return $this->clarification($location['clarify']);
            }
        }
        if ($parsed['intent'] === 'field_visit' && $location !== null) {
            return $this->clarification('The field-visit layer checks every loaded parcel. Use the location filters above the registry to narrow the map first.');
        }

        $interpretation = [
            'intent' => $parsed['intent'], 'crop' => $parsed['crop'],
            'crop_label' => $parsed['crop'] ? ParcelCropSeason::CROPS[$parsed['crop']] : null,
            'year' => $parsed['year'], 'season' => $parsed['season'],
            'season_label' => $parsed['season'] ? ParcelCropSeason::SEASONS[$parsed['season']] : null,
            'location' => $location['label'] ?? null,
        ];
        $where = $location ? ' in '.$location['label'] : ' in this map view';

        return ['status' => 'answered', 'interpretation' => $interpretation] + match ($parsed['intent']) {
            'crop' => $this->cropAnswer($user, $municipalityIds, $interpretation, $location, $where),
            'no_harvest' => $this->noHarvestAnswer($user, $municipalityIds, $interpretation, $location, $where),
            'no_boundary' => $this->noBoundaryAnswer($user, $municipalityIds, $location, $where, $workspaceParameters),
            'without_parcel' => $this->withoutParcelAnswer($user, $municipalityIds, $location, $where, $workspaceParameters),
            default => [
                'answer' => 'Showing the field-visit priority layer for the '.$interpretation['year'].' '.strtolower($interpretation['season_label'])
                    .'. Loaded parcels are checked in batches of '.self::BATCH.'; flagged parcels are outlined in orange with their reasons.',
                'notes' => ['This is a visit-planning aid from saved records only. It does not diagnose crop health.'],
                'action' => ['type' => 'priority', 'year' => $interpretation['year'], 'season' => $interpretation['season']],
                'counts' => null, 'links' => [],
            ],
        };
    }

    /**
     * The subset of up to 200 loaded parcel IDs that match an allowlisted filter.
     *
     * @param  list<int>  $municipalityIds
     * @param  list<int>  $plotIds
     * @return array{matches:list<int>,checked:int}|array{clarify:string}
     */
    public function matches(User $user, array $municipalityIds, array $plotIds, string $filter, ?string $crop, int $year, string $season, ?string $locationText): array
    {
        $this->authorize($user);
        $location = null;
        if ($locationText !== null && $locationText !== '') {
            $location = $this->resolveLocation($user, $municipalityIds, MapQuestionInterpreter::place($locationText));
            if (isset($location['clarify'])) {
                return ['clarify' => $location['clarify']];
            }
        }
        $plotIds = array_slice(array_values(array_unique(array_map('intval', $plotIds))), 0, self::BATCH);
        $checked = $this->plotQuery($user, $municipalityIds, null)->whereIn('farm_plots.id', $plotIds)->count();
        $rows = $this->seasonRows($user, $municipalityIds, $location, $year, $season, $filter === 'no_harvest', $plotIds);

        return ['matches' => $this->filterCrop($rows, $crop, $filter === 'no_harvest')->pluck('id')->map(fn ($id) => (int) $id)->values()->all(), 'checked' => $checked];
    }

    private function authorize(User $user): void
    {
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
    }

    /** @return array<string, mixed> */
    private function cropAnswer(User $user, array $ids, array $i, ?array $location, string $where): array
    {
        $count = $this->filterCrop($this->seasonRows($user, $ids, $location, $i['year'], $i['season'], false), $i['crop'], false)->count();
        $period = $i['year'].' '.strtolower($i['season_label']);
        $answer = $i['crop'] === 'not_recorded'
            ? $count.' '.$this->plural($count, 'parcel has', 'parcels have').' a saved “Not recorded” crop entry for the '.$period.$where.'.'
            : $count.' '.$this->plural($count, 'parcel has', 'parcels have').' a saved '.$i['crop_label'].' record for the '.$period.$where.'.';
        $answer .= $count > 0 ? ($location ? ' They are outlined on the map.' : ' The map now shows only those parcels.') : ' Nothing to highlight.';
        $notes = ['Counts use saved seasonal crop records, not live crop growth.'];
        if ($i['crop'] === 'not_recorded') {
            $notes[] = 'Parcels with no crop entry at all are not counted; a missing record does not mean the land is unplanted.';
        }
        $view = ['year' => $i['year'], 'season' => $i['season'], 'crop' => $i['crop']];

        return ['answer' => $answer, 'notes' => $notes, 'counts' => ['matched' => $count], 'links' => [],
            'action' => $location
                ? ['type' => 'highlight', 'match' => ['filter' => 'crop', 'crop' => $i['crop'], 'year' => $i['year'], 'season' => $i['season'], 'location' => $location['label']], 'crop_view' => $view]
                : ['type' => 'crop_filter'] + $view];
    }

    /** @return array<string, mixed> */
    private function noHarvestAnswer(User $user, array $ids, array $i, ?array $location, string $where): array
    {
        $planted = $this->filterCrop($this->seasonRows($user, $ids, $location, $i['year'], $i['season'], false), $i['crop'], true);
        $rows = $this->filterCrop($this->seasonRows($user, $ids, $location, $i['year'], $i['season'], true), $i['crop'], true);
        $count = $rows->count();
        $what = $i['crop'] ? strtolower($i['crop_label']).' ' : 'planted ';
        $answer = $count.' '.$what.$this->plural($count, 'parcel', 'parcels').$where.' in the '.$i['year'].' '.strtolower($i['season_label'])
            .' '.$this->plural($count, 'has', 'have').' no harvest recorded yet'.($planted->count() ? ' (of '.$planted->count().' with a saved crop record).' : '.')
            .($count > 0 ? ' They are outlined on the map.' : '');
        $notes = ['A harvest counts when it is linked to the parcel or its crop season for the same year and season.'];
        $unlinked = $this->farmersWithUnlinkedHarvests($rows, $i['year'], $i['season']);
        if ($unlinked > 0) {
            $notes[] = $unlinked.' of these '.$this->plural($unlinked, 'farmer has', 'farmers have').' a harvest in this period that is not linked to a parcel. Review those first.';
        }

        return ['answer' => $answer, 'notes' => $notes, 'counts' => ['matched' => $count, 'planted' => $planted->count()], 'links' => [],
            'action' => ['type' => 'highlight',
                'match' => ['filter' => 'no_harvest', 'crop' => $i['crop'], 'year' => $i['year'], 'season' => $i['season'], 'location' => $location['label'] ?? null],
                'crop_view' => ['year' => $i['year'], 'season' => $i['season'], 'crop' => $i['crop']]]];
    }

    /** @return array<string, mixed> */
    private function noBoundaryAnswer(User $user, array $ids, ?array $location, string $where, array $parameters): array
    {
        $missing = $this->plotQuery($user, $ids, $location)->where(function (Builder $query): void {
            $query->whereNull('farm_plots.polygon_json')->orWhere('farm_plots.polygon_json', '')->orWhere('farm_plots.polygon_json', '[]');
        })->count();
        $unmapped = $this->farmerQuery($user, $ids, $location)->whereDoesntHave('farmPlots')->count();
        $answer = $missing.' saved parcel '.$this->plural($missing, 'record', 'records').$where.' '.$this->plural($missing, 'has', 'have')
            .' no boundary geometry, so '.$this->plural($missing, 'it cannot', 'they cannot').' be drawn on the map. Separately, '
            .$unmapped.' '.$this->plural($unmapped, 'farmer has', 'farmers have').' no saved parcel.';

        return ['answer' => $answer, 'notes' => ['Records without a boundary cannot be highlighted; use the registry list to review them.'],
            'counts' => ['matched' => $missing, 'farmers_without_parcel' => $unmapped], 'action' => ['type' => 'none'],
            'links' => [['label' => 'Review unmapped farmers', 'url' => $this->registryUrl($parameters, $location)]]];
    }

    /** @return array<string, mixed> */
    private function withoutParcelAnswer(User $user, array $ids, ?array $location, string $where, array $parameters): array
    {
        $count = $this->farmerQuery($user, $ids, $location)->whereDoesntHave('farmPlots')->count();
        $total = $this->farmerQuery($user, $ids, $location)->count();

        return ['answer' => $count.' of '.$total.' '.$this->plural($total, 'farmer', 'farmers').$where.' '.$this->plural($count, 'has', 'have').' no saved parcel yet. Farmers without a parcel have nothing to highlight on the map.',
            'notes' => $location && $location['type'] === 'farm_location' ? ['The location is the farmer’s recorded farm location.'] : [],
            'counts' => ['matched' => $count, 'total' => $total], 'action' => ['type' => 'none'],
            'links' => [['label' => 'Open these farmers in the registry', 'url' => $this->registryUrl($parameters, $location)]]];
    }

    /**
     * Parcel rows with a saved crop record for the period, optionally without a linked harvest.
     *
     * @param  list<int>|null  $plotIds
     */
    private function seasonRows(User $user, array $ids, ?array $location, int $year, string $season, bool $withoutHarvest, ?array $plotIds = null): Collection
    {
        $query = $this->plotQuery($user, $ids, $location)
            ->join('parcel_crop_seasons as pcs', function ($join) use ($year, $season): void {
                $join->on('pcs.farm_plot_id', '=', 'farm_plots.id')->on('pcs.municipality_id', '=', 'farmers.municipality_id')
                    ->where('pcs.crop_year', '=', $year)->where('pcs.season', '=', $season);
            });
        if ($plotIds !== null) {
            $query->whereIn('farm_plots.id', $plotIds);
        }
        if ($withoutHarvest) {
            $query->where('pcs.crop', '!=', 'not_recorded')->whereNotExists(function (QueryBuilder $harvest) use ($year, $season): void {
                $harvest->selectRaw('1')->from('harvest_records as h')
                    ->whereColumn('h.municipality_id', 'farmers.municipality_id')
                    ->where('h.harvest_year', $year)->where('h.season', $season)
                    ->where(fn (QueryBuilder $link) => $link->whereColumn('h.farm_plot_id', 'farm_plots.id')->orWhereColumn('h.parcel_crop_season_id', 'pcs.id'));
            });
        }
        $columns = ['farm_plots.id', 'farm_plots.farmer_id', 'pcs.crop'];
        if (Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            // Crop names only; section coordinates never leave the database on MySQL.
            $columns[] = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? DB::raw("JSON_EXTRACT(pcs.planted_areas, '$[*].crop') AS area_crops_json") : 'pcs.planted_areas';
        }

        return $query->toBase()->get($columns);
    }

    /** Same visibility rule as the map's crop filter: the summary crop or any drawn section crop. */
    private function filterCrop(Collection $rows, ?string $crop, bool $plantedOnly): Collection
    {
        return $rows->filter(function ($row) use ($crop, $plantedOnly): bool {
            if ($plantedOnly && $row->crop === 'not_recorded') {
                return false;
            }
            if ($crop === null) {
                return true;
            }
            if ($row->crop === $crop) {
                return true;
            }
            $areas = isset($row->area_crops_json) ? json_decode((string) $row->area_crops_json, true)
                : array_column(json_decode((string) ($row->planted_areas ?? '[]'), true) ?: [], 'crop');

            return $crop !== 'not_recorded' && $row->crop !== 'not_recorded' && in_array($crop, is_array($areas) ? $areas : [], true);
        })->values();
    }

    private function farmersWithUnlinkedHarvests(Collection $rows, int $year, string $season): int
    {
        $count = 0;
        foreach ($rows->pluck('farmer_id')->unique()->chunk(500) as $farmers) {
            $count += HarvestRecord::query()->whereIn('farmer_id', $farmers->all())
                ->whereHas('farmer', fn (Builder $query) => $query->whereColumn('farmers.municipality_id', 'harvest_records.municipality_id'))
                ->where('harvest_year', $year)->where('season', $season)
                ->whereNull('farm_plot_id')->whereNull('parcel_crop_season_id')->distinct()->count('farmer_id');
        }

        return $count;
    }

    private function plotQuery(User $user, array $ids, ?array $location): Builder
    {
        $query = FarmPlot::query()->join('farmers', 'farmers.id', '=', 'farm_plots.farmer_id');
        $this->access->scope($query, $user, 'farmers.municipality_id');
        $query->whereIn('farmers.municipality_id', $ids);

        return $this->applyLocation($query, $location);
    }

    private function farmerQuery(User $user, array $ids, ?array $location): Builder
    {
        $query = $this->access->scope(Farmer::query(), $user, 'farmers.municipality_id')->whereIn('farmers.municipality_id', $ids);

        return $this->applyLocation($query, $location);
    }

    private function applyLocation(Builder $query, ?array $location): Builder
    {
        if ($location === null) {
            return $query;
        }

        return $location['type'] === 'municipality'
            ? $query->where('farmers.municipality_id', $location['id'])
            : $query->whereIn('farmers.farm_location', $location['values']);
    }

    /**
     * Match a typed place to a farm location or municipality inside the current scope only.
     *
     * @return array<string, mixed>
     */
    private function resolveLocation(User $user, array $ids, string $text): array
    {
        $place = MapQuestionInterpreter::place($text);
        $values = $this->farmerQuery($user, $ids, null)->whereNotNull('farmers.farm_location')->where('farmers.farm_location', '!=', '')
            ->distinct()->orderBy('farmers.farm_location')->limit(self::PLACE_LIMIT)->pluck('farmers.farm_location');
        $byPlace = [];
        foreach ($values as $value) {
            $key = MapQuestionInterpreter::place((string) $value);
            if ($key !== '' && $key !== 'unknown') {
                $byPlace[$key][] = (string) $value;
            }
        }
        $best = isset($byPlace[$place]) ? [$place] : [];
        if ($best === []) {
            $length = 0;
            foreach (array_keys($byPlace) as $key) {
                if (mb_strlen($key) >= 3 && str_contains(' '.$place.' ', ' '.$key.' ')) {
                    if (mb_strlen($key) > $length) {
                        [$best, $length] = [[$key], mb_strlen($key)];
                    } elseif (mb_strlen($key) === $length) {
                        $best[] = $key;
                    }
                }
            }
        }
        if (count($best) > 1) {
            return ['clarify' => 'That matches more than one farm location. Ask about one location at a time.'];
        }
        if ($best !== []) {
            return ['type' => 'farm_location', 'values' => $byPlace[$best[0]], 'label' => mb_convert_case($best[0], MB_CASE_TITLE)];
        }
        $municipality = $this->access->scopeMunicipalities(Municipality::query(), $user)->whereIn('id', $ids)->get(['id', 'name'])
            ->first(fn (Municipality $row) => MapQuestionInterpreter::place($row->name) === $place);
        if ($municipality) {
            return ['type' => 'municipality', 'id' => (int) $municipality->id, 'label' => $municipality->name];
        }

        return ['clarify' => 'I could not find “'.mb_substr($text, 0, 60).'” among the farm locations in your current map view. Check the spelling, or change the location filters above the registry.'];
    }

    private function registryUrl(array $parameters, ?array $location): string
    {
        $query = $parameters + ['mapping' => 'unmapped'];
        if ($location !== null) {
            $query = $location['type'] === 'municipality' ? ['municipality_id' => $location['id']] + $query : $query + ['q' => $location['values'][0]];
        }

        return route('farmers.index', $query).'#farmerDirectory';
    }

    /** @return array<string, mixed> */
    private function clarification(string $message): array
    {
        return ['status' => 'clarify', 'answer' => $message, 'examples' => MapQuestionInterpreter::EXAMPLES,
            'action' => ['type' => 'none'], 'notes' => [], 'links' => [], 'counts' => null, 'interpretation' => null];
    }

    private function plural(int $count, string $one, string $many): string
    {
        return $count === 1 ? $one : $many;
    }
}
