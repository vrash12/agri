<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHarvestRecordRequest;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\CsvExport;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
        private ConcurrentWrite $concurrentWrite
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', HarvestRecord::class);

        $filters = $this->filters($request);
        $base = $this->filteredQuery($request);

        $records = (clone $base)
            ->with(['municipality:id,name', 'farmer:id,first_name,middle_name,last_name,ext_name', 'riceSeedDistribution:id'])
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

        $validated = $request->validated();
        $municipalityId = $this->resolveMunicipality($request, $validated);

        $record = $this->concurrentWrite->transaction(
            fn () => HarvestRecord::create($this->payload($validated, $municipalityId, $request))
        );

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

        $validated = $request->validated();
        $municipalityId = $this->resolveMunicipality($request, $validated, $harvestRecord);

        $this->concurrentWrite->execute(
            $harvestRecord,
            $request->input('_record_version'),
            fn (HarvestRecord $current) => $current->update(
                $this->payload($validated, $municipalityId, $request)
            )
        );

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

        $query = $this->filteredQuery($request)
            ->with(['municipality:id,name', 'farmer:id,first_name,middle_name,last_name,ext_name,ffrs']);

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
                'Quantity', 'Unit', 'Source', 'Notes',
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
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('variety', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('farmer', function (Builder $farmer) use ($search) {
                        $farmer->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('ffrs', 'like', '%'.$search.'%');
                    });
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

        $farmers = $this->municipalityAccess
            ->scope(Farmer::query(), $user)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'ext_name', 'ffrs', 'municipality_id']);

        // Only the selected farmer's parcels, because the form offers a parcel only
        // once a farmer is chosen and validation refuses any other farmer's land.
        $selectedFarmerId = (int) old('farmer_id', $record?->farmer_id ?? $request->query('farmer_id', 0));
        $plots = $selectedFarmerId === 0
            ? collect()
            : FarmPlot::query()
                ->where('farmer_id', $selectedFarmerId)
                ->whereIn('farmer_id', $farmers->pluck('id'))
                ->orderBy('id')
                ->get(['id', 'farmer_id', 'name', 'area_ha']);

        return [
            'record' => $record,
            'farmers' => $farmers,
            'plots' => $plots,
            'commodityOptions' => HarvestRecord::COMMODITY_LABELS,
            'seasonOptions' => HarvestRecord::SEASON_LABELS,
            'unitOptions' => HarvestRecord::QUANTITY_UNIT_LABELS,
            'municipalities' => $this->municipalityAccess->choices($user),
            'canChooseMunicipality' => $user->canAccessAllMunicipalities(),
            'currentYear' => LocalTime::now()->year,
        ];
    }

    /**
     * Which municipality owns this harvest.
     *
     * The farmer decides it when there is one, because a harvest naming a farmer and
     * owned by a different municipality would be visible to an office the farmer does
     * not belong to. Otherwise the account's own scope decides, and a provincial
     * account has to choose — `resolveForWrite` refuses to guess.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveMunicipality(
        Request $request,
        array $validated,
        ?HarvestRecord $existing = null
    ): int {
        if (! empty($validated['farmer_id'])) {
            $farmer = $this->municipalityAccess
                ->scope(Farmer::query(), $request->user())
                ->findOrFail($validated['farmer_id']);

            return (int) $farmer->municipality_id;
        }

        return $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $validated['municipality_id'] ?? $existing?->municipality_id
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, int $municipalityId, Request $request): array
    {
        return [
            'municipality_id' => $municipalityId,
            'farmer_id' => $validated['farmer_id'] ?? null,
            'farm_plot_id' => $validated['farm_plot_id'] ?? null,
            'commodity' => $validated['commodity'],
            'variety' => $validated['variety'] ?? null,
            'season' => $validated['season'] ?? null,
            'harvest_year' => (int) $validated['harvest_year'],
            'date_harvested' => $validated['date_harvested'] ?? null,
            'area_harvested_ha' => $validated['area_harvested_ha'] ?? null,
            'quantity' => $validated['quantity'],
            'quantity_unit' => $validated['quantity_unit'],
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ];
    }

    private function projectedExplanation(HarvestRecord $record): string
    {
        return 'This harvest comes from an assistance release, so it is changed on that '
            .'release rather than here. Saving the release updates this record.';
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
