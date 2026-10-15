<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHarvestRecordRequest;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Modules\Agriculture\Application\RecordHarvest;
use App\Modules\Farmers\Application\FarmerIdentifier;
use App\Modules\Farmers\Application\FarmerPicker;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\CsvExport;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Recording what was actually harvested.
 *
 * The assistance register could say what was handed out and never what came of it.
 * Rice harvest arrives on its own through the Rice Seed Distribution Sheet, which
 * already has a production section staff fill in; this is where every other
 * commodity is entered, and where all of them are read back.
 *
 * Records projected from an assistance release appear here but are changed at their
 * source. `HarvestRecordPolicy` is what enforces that — the interface only explains
 * it.
 */
class HarvestRecordController extends Controller
{
    /**
     * How many rows a page shows unless the operator asks for more.
     */
    private const DEFAULT_PER_PAGE = 20;

    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite,
        private FarmerPicker $farmerPicker,
        private RecordHarvest $harvests,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', HarvestRecord::class);

        $filters = $this->filters($request);
        $base = $this->filteredQuery($request);

        $relations = ['municipality:id,name', 'farmer:id,first_name,middle_name,last_name,ext_name', 'riceSeedDistribution:id'];
        if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
            $relations[] = 'parcelCropSeason:id,farm_plot_id,crop_year,season,planted_areas';
        }
        $records = (clone $base)
            ->with($relations)
            ->orderByDesc('harvest_year')
            ->orderByDesc('date_harvested')
            ->orderByDesc('id')
            ->paginate(max(5, min((int) $filters['per_page'], 200)))
            ->withQueryString();

        return view('harvest_records.index', [
            'records' => $records,
            'filters' => $filters,
            'summary' => $this->summary($base),
            'commodityOptions' => HarvestRecord::COMMODITY_LABELS,
            'seasonOptions' => HarvestRecord::SEASON_LABELS,
            'unitOptions' => HarvestRecord::QUANTITY_UNIT_LABELS,
            'yearOptions' => $this->yearOptions($request),
            'municipalities' => $this->municipalityAccess->choices($request->user()),
            'canChooseMunicipality' => $request->user()->canAccessAllMunicipalities(),
            'canManageOperations' => $request->user()->canManageOperationalData(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', HarvestRecord::class);

        return view('harvest_records.create', $this->formData($request));
    }

    public function store(StoreHarvestRecordRequest $request)
    {
        $this->authorize('create', HarvestRecord::class);

        $record = $this->harvests->create($request->user(), $request->validated());

        return redirect()
            ->route('harvest-records.index', ['harvest_year' => $record->harvest_year])
            ->with('success', 'Harvest recorded successfully.');
    }

    public function edit(Request $request, HarvestRecord $harvestRecord)
    {
        // A projected record is viewable but not editable, and `update` is what says
        // so. Authorizing `view` here and then refusing would show the form and reject
        // the save, which teaches nothing; the redirect names the release instead.
        $this->authorize('view', $harvestRecord);

        if ($harvestRecord->isProjected()) {
            return redirect()
                ->route('harvest-records.index')
                ->with('error', $this->projectedExplanation($harvestRecord));
        }

        $this->authorize('update', $harvestRecord);

        return view('harvest_records.edit', $this->formData($request, $harvestRecord));
    }

    public function update(StoreHarvestRecordRequest $request, HarvestRecord $harvestRecord)
    {
        // The form request already authorized this record. Repeating the check here
        // keeps the route-bound record protected if the request class is ever swapped
        // for plain controller validation.
        $this->authorize('update', $harvestRecord);

        $this->harvests->update($request->user(), $harvestRecord, $request->validated(), $request->input('_record_version'));

        return redirect()
            ->route('harvest-records.index', ['harvest_year' => $harvestRecord->fresh()->harvest_year])
            ->with('success', 'Harvest updated successfully.');
    }

    public function destroy(Request $request, HarvestRecord $harvestRecord)
    {
        $this->authorize('view', $harvestRecord);

        if ($harvestRecord->isProjected()) {
            return redirect()
                ->route('harvest-records.index')
                ->with('error', $this->projectedExplanation($harvestRecord));
        }

        $this->authorize('delete', $harvestRecord);

        // Locked rather than version-checked: a delete has no field the operator could
        // be overwriting, and every other module deletes this way.
        $this->concurrentWrite->locked(
            $harvestRecord,
            fn (HarvestRecord $current) => $current->delete()
        );

        return redirect()
            ->route('harvest-records.index')
            ->with('success', 'Harvest deleted.');
    }

    public function export(Request $request)
    {
        $this->authorize('export', HarvestRecord::class);

        $exportRelations = ['municipality:id,name', 'farmer:id,first_name,middle_name,last_name,ext_name,ffrs'];
        if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
            $exportRelations[] = 'parcelCropSeason:id,farm_plot_id,crop_year,season,planted_areas';
        }
        $query = $this->filteredQuery($request)->with($exportRelations);

        // Bounded the same way the machinery export is: the row set is fixed to the
        // ids present when the export was authorized, so a concurrent write cannot
        // stretch the stream after the audit entry has stated its size.
        $maximumId = (int) ((clone $query)->max('id') ?? 0);
        $rowCount = $maximumId === 0
            ? 0
            : (clone $query)->where('id', '<=', $maximumId)->count();

        AuditTrail::record(
            'exported',
            'Harvest records',
            $request->user()->name.' exported the harvest records.',
            [
                'metadata' => [
                    'row_count' => $rowCount,
                    'filters' => $request->only([
                        'q', 'municipality_id', 'commodity', 'season', 'harvest_year', 'source',
                    ]),
                ],
            ]
        );

        $filename = 'harvest_records_'.LocalTime::now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $maximumId) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'Municipality', 'Farmer', 'FFRS', 'Commodity', 'Variety',
                'Season', 'Year', 'Date Harvested', 'Area Harvested (ha)',
                'Quantity', 'Unit', 'Source', 'Planted section', 'Notes',
            ]);

            if ($maximumId === 0) {
                fclose($stream);

                return;
            }

            $query->where('id', '<=', $maximumId)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($stream) {
                    foreach ($rows as $record) {
                        fputcsv($stream, CsvExport::row([
                            $record->municipality?->name,
                            $record->farmer ? $this->farmerName($record->farmer) : '',
                            $record->farmer?->ffrs,
                            $record->commodityLabel(),
                            $record->variety,
                            $record->season ? $record->seasonLabel() : '',
                            $record->harvest_year,
                            $record->date_harvested?->format('Y-m-d'),
                            $record->area_harvested_ha,
                            $record->quantity,
                            $record->quantityUnitLabel(),
                            $record->isProjected() ? 'Assistance release' : 'Entered directly',
                            $this->plantedSectionLabel($record),
                            $record->notes,
                        ]));
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The scoped, filtered query every list, statistic and export is built from.
     *
     * Municipality scope is applied first, before search or any aggregate, so no
     * count or total can ever be computed over rows the account cannot see.
     */
    private function filteredQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = $this->municipalityAccess->scope(HarvestRecord::query(), $user);

        $requested = $request->query('municipality_id');
        if (filled($requested) && $user->canAccessAllMunicipalities()) {
            $query->where('municipality_id', (int) $requested);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $farmerId = FarmerIdentifier::parse($search);
            $query->where(function (Builder $inner) use ($search, $farmerId) {
                $inner->where('variety', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('farmer', function (Builder $farmer) use ($search) {
                        $farmer->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('ffrs', 'like', '%'.$search.'%');
                    });

                if ($farmerId !== null) {
                    $inner->orWhere('farmer_id', $farmerId);
                }
            });
        }

        $commodity = (string) $request->query('commodity', '');
        if (array_key_exists($commodity, HarvestRecord::COMMODITY_LABELS)) {
            $query->where('commodity', $commodity);
        }

        $season = (string) $request->query('season', '');
        if (array_key_exists($season, HarvestRecord::SEASON_LABELS)) {
            $query->where('season', $season);
        }

        $year = (string) $request->query('harvest_year', '');
        if ($year !== '' && ctype_digit($year)) {
            $query->where('harvest_year', (int) $year);
        }

        // Where the figure came from, which is the difference between a number the
        // office typed here and one it wrote on a distribution sheet.
        $source = (string) $request->query('source', '');
        if ($source === 'projected') {
            $query->whereNotNull('rice_seed_distribution_id');
        } elseif ($source === 'direct') {
            $query->whereNull('rice_seed_distribution_id');
        }

        return $query;
    }

    /**
     * Headline figures for the current view.
     *
     * Quantity is summed per unit and never across units. Sacks and kilograms of one
     * crop are two measurements, and a single total through both would be a number
     * nobody could stand behind — the same rule the production chart follows.
     *
     * @return array<string, mixed>
     */
    private function summary(Builder $base): array
    {
        $totals = (clone $base)
            ->groupBy('quantity_unit')
            ->selectRaw('quantity_unit, COUNT(*) as records, COALESCE(SUM(quantity), 0) as total')
            ->get();

        return [
            'records' => (int) (clone $base)->count(),
            'commodities' => (int) (clone $base)->distinct()->count('commodity'),
            'area' => (float) ((clone $base)->sum('area_harvested_ha') ?: 0),
            'by_unit' => $totals->map(fn ($row) => [
                'unit' => HarvestRecord::QUANTITY_UNIT_LABELS[$row->quantity_unit] ?? ($row->quantity_unit ?: 'Unit not recorded'),
                'records' => (int) $row->records,
                'total' => (float) $row->total,
            ])->values()->all(),
            'projected' => (int) (clone $base)->whereNotNull('rice_seed_distribution_id')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'municipality_id' => $request->query('municipality_id'),
            'commodity' => (string) $request->query('commodity', ''),
            'season' => (string) $request->query('season', ''),
            'harvest_year' => (string) $request->query('harvest_year', ''),
            'source' => (string) $request->query('source', ''),
            'per_page' => (int) $request->query('per_page', self::DEFAULT_PER_PAGE),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(Request $request): array
    {
        return $this->municipalityAccess
            ->scope(HarvestRecord::query(), $request->user())
            ->whereNotNull('harvest_year')
            ->distinct()
            ->orderByDesc('harvest_year')
            ->pluck('harvest_year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?HarvestRecord $record = null): array
    {
        $user = $request->user();

        $selectedFarmerId = (int) old('farmer_id', $record?->farmer_id ?? $request->query('farmer_id', 0));

        // Only the chosen farmer travels with the page; the rest of the registry is
        // searched on the server. `browse=1` is the escape hatch that renders the
        // whole list the old way for an operator who would rather scroll.
        $browsingAll = $request->boolean('browse');

        $farmerOptions = $this->farmerPicker->initialOptions(
            $user,
            $selectedFarmerId ?: null,
            $browsingAll
        );

        // Only the selected farmer's parcels, because the form offers a parcel only
        // once a farmer is chosen and validation refuses any other farmer's land.
        $plots = $selectedFarmerId === 0
            ? collect()
            : FarmPlot::query()
                ->where('farmer_id', $selectedFarmerId)
                ->whereHas('farmer', fn (Builder $farmer) => $this->municipalityAccess->scope($farmer, $user))
                ->orderBy('id')
                ->get(['id', 'farmer_id', 'name', 'area_ha']);

        $cropSections = collect();
        if (Schema::hasTable('parcel_crop_seasons')
            && Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            $cropSections = ParcelCropSeason::query()
                ->whereIn('farm_plot_id', $plots->pluck('id'))
                ->with('plot.farmer:id,municipality_id')
                ->orderByDesc('crop_year')->orderBy('season')->get()
                ->flatMap(function (ParcelCropSeason $season): array {
                    if ((int) $season->municipality_id !== (int) $season->plot?->farmer?->municipality_id) {
                        return [];
                    }

                    return collect($season->planted_areas ?: [])->filter(fn ($area) => is_array($area) && filled($area['area_key'] ?? null))
                        ->map(fn (array $area) => [
                            'season_id' => $season->id,
                            'plot_id' => $season->farm_plot_id,
                            'year' => $season->crop_year,
                            'season' => $season->season,
                            'crop' => $area['crop'] ?? 'other',
                            'crop_label' => ParcelCropSeason::CROPS[$area['crop'] ?? 'other'] ?? 'Other crop',
                            'area_key' => $area['area_key'],
                            'name' => $area['name'] ?? '',
                            'variety' => $area['variety'] ?? '',
                        ])->values()->all();
                })->values();
        }

        $requestedSectionId = (int) old(
            'parcel_crop_season_id',
            $record?->parcel_crop_season_id ?? $request->query('parcel_crop_season_id', 0)
        );
        $requestedAreaKey = (string) old(
            'planted_area_key',
            $record?->planted_area_key ?? $request->query('planted_area_key', '')
        );
        $linkedSection = $cropSections->first(
            fn (array $section): bool => (int) $section['season_id'] === $requestedSectionId
                && (string) $section['area_key'] === $requestedAreaKey
        );
        $defaultCommodity = [
            'rice' => 'rice',
            'corn' => 'corn',
            'vegetables' => 'vegetable',
            'root_crops' => 'rootcrop',
            'fruit' => 'fruit',
            'legumes' => 'other',
            'mixed' => 'other',
            'other' => 'other',
        ][$linkedSection['crop'] ?? ''] ?? null;

        return [
            'record' => $record,
            'farmerOptions' => $farmerOptions,
            'selectedFarmer' => $selectedFarmerId,
            'selectedFarmerId' => $selectedFarmerId,
            'browsingAll' => $browsingAll,
            'browseUrl' => $request->fullUrlWithQuery(['browse' => 1]),
            'plots' => $plots,
            'cropSections' => $cropSections,
            'defaultCropSeasonId' => $linkedSection['season_id'] ?? null,
            'defaultPlotId' => $linkedSection['plot_id'] ?? null,
            'defaultAreaKey' => $linkedSection['area_key'] ?? null,
            'defaultCommodity' => $defaultCommodity,
            'defaultHarvestYear' => $linkedSection['year'] ?? null,
            'defaultHarvestSeason' => $linkedSection['season'] ?? null,
            'commodityOptions' => HarvestRecord::COMMODITY_LABELS,
            'seasonOptions' => HarvestRecord::SEASON_LABELS,
            'unitOptions' => HarvestRecord::QUANTITY_UNIT_LABELS,
            'municipalities' => $this->municipalityAccess->choices($user),
            'canChooseMunicipality' => $user->canAccessAllMunicipalities(),
            'currentYear' => LocalTime::now()->year,
        ];
    }

    private function projectedExplanation(HarvestRecord $record): string
    {
        return 'This harvest comes from an assistance release, so it is changed on that '
            .'release rather than here. Saving the release updates this record.';
    }

    private function plantedSectionLabel(HarvestRecord $record): string
    {
        if (! $record->parcel_crop_season_id || ! $record->parcelCropSeason) {
            return '';
        }

        $area = collect($record->parcelCropSeason->planted_areas ?: [])
            ->firstWhere('area_key', $record->planted_area_key);

        if (! is_array($area)) {
            return 'Section unavailable';
        }

        return trim(collect([$area['name'] ?? null, $area['crop'] ?? null])->filter()->implode(' · '))
            ?: 'Planted section';
    }

    private function farmerName(Farmer $farmer): string
    {
        return trim(collect([
            $farmer->first_name,
            $farmer->middle_name,
            $farmer->last_name,
            $farmer->ext_name,
        ])->filter()->implode(' '));
    }
}
