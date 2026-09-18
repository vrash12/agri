<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiceDistributionBatchRequest;
use App\Models\RiceDistributionBatch;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use App\Support\RiceSeedDistributionSheet;
use App\Support\RiceSeedDistributionSheetWorkbook;
use App\Support\SeedReleaseQuantity;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Rice Seed Distribution Sheets inside the assistance module.
 *
 * A sheet groups existing assistance releases for one programme reference and
 * planting season so the office can print, sign and file them. The controller
 * stays HTTP-only: ownership resolution lives in `MunicipalityAccess`, sheet
 * assembly in `RiceSeedDistributionSheet`, and the workbook in its writer.
 */
class RiceDistributionBatchController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite,
        private RiceSeedDistributionSheet $sheet,
        private RiceSeedDistributionSheetWorkbook $workbook
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', RiceDistributionBatch::class);

        $query = RiceDistributionBatch::query();
        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $request->query('municipality_id')
        );

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where('reference', 'like', '%'.$search.'%');
        }

        $plantingYear = $request->query('planting_year');

        if (is_numeric($plantingYear)) {
            $query->where('planting_year', (int) $plantingYear);
        }

        $batches = $query
            ->with(['municipality:id,name'])
            // Aggregated in SQL so a long sheet list never loads its releases.
            ->withCount('releases')
            // Only releases recorded in kilograms reach the kilogram total. The
            // column is headed "Total seed (kg)"; adding a release counted in pieces
            // or sacks to it would print a quantity as a weight.
            ->withSum(
                ['releases as releases_kgs_sum' => fn ($query) => SeedReleaseQuantity::onlyKilograms($query)],
                'kgs_received'
            )
            // Counted so the list can say when a sheet holds releases the kilogram
            // total leaves out, rather than quietly showing a short number.
            ->withCount(['releases as non_kilogram_releases_count' => fn ($query) => $query
                ->whereNotNull('quantity_unit')
                ->where('quantity_unit', '!=', '')
                ->where('quantity_unit', '!=', 'kg'),
            ])
            ->orderByDesc('planting_year')
            ->orderBy('planting_season')
            ->orderBy('reference')
            ->paginate(15)
            ->withQueryString();

        return view('rice_seed_distributions.batches.index', [
            'batches' => $batches,
            'q' => $search,
            'plantingYear' => $plantingYear,
            'seasonOptions' => RiceDistributionBatch::SEASONS,
            'municipalities' => $this->municipalityAccess->choices($request->user()),
            'canChooseMunicipality' => $request->user()->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id'),
            'canManage' => $request->user()->can('create', RiceDistributionBatch::class),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', RiceDistributionBatch::class);

        return view('rice_seed_distributions.batches.create', [
            'seasonOptions' => RiceDistributionBatch::SEASONS,
            'municipalities' => $this->municipalityAccess->choices($request->user()),
            'canChooseMunicipality' => $request->user()->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id')
                ?? $request->user()->municipality_id,
        ]);
    }

    public function store(StoreRiceDistributionBatchRequest $request)
    {
        // The form request ran the policy before these rules.
        $validated = $request->validated();

        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $validated['municipality_id'] ?? null
        );

        $batch = $this->concurrentWrite->transaction(
            fn (): RiceDistributionBatch => RiceDistributionBatch::create(
                $this->payload($validated) + [
                    'municipality_id' => $municipalityId,
                    'created_by' => $request->user()->getKey(),
                    'updated_by' => $request->user()->getKey(),
                ]
            )
        );

        return redirect()
            ->route('rice-distribution-batches.index')
            ->with('success', 'Distribution sheet "'.$batch->reference.'" created.');
    }

    public function edit(Request $request, RiceDistributionBatch $riceDistributionBatch)
    {
        $this->authorize('update', $riceDistributionBatch);

        return view('rice_seed_distributions.batches.edit', [
            'batch' => $riceDistributionBatch,
            'seasonOptions' => RiceDistributionBatch::SEASONS,
            'municipalities' => $this->municipalityAccess->choices($request->user()),
            'canChooseMunicipality' => false,
            'selectedMunicipalityId' => $riceDistributionBatch->municipality_id,
        ]);
    }

    public function update(
        StoreRiceDistributionBatchRequest $request,
        RiceDistributionBatch $riceDistributionBatch
    ) {
        $validated = $request->validated();

        $this->concurrentWrite->execute(
            $riceDistributionBatch,
            $request->input('_record_version'),
            // Ownership is settled by the existing record. A sheet is never moved
            // to another municipality, because the releases it groups cannot move.
            fn (RiceDistributionBatch $current): bool => $current->update(
                $this->payload($validated) + ['updated_by' => $request->user()->getKey()]
            )
        );

        return redirect()
            ->route('rice-distribution-batches.index')
            ->with('success', 'Distribution sheet updated.');
    }

    public function destroy(RiceDistributionBatch $riceDistributionBatch)
    {
        $this->authorize('delete', $riceDistributionBatch);

        $this->concurrentWrite->locked(
            $riceDistributionBatch,
            function (RiceDistributionBatch $current): void {
                // Re-counted inside the row lock: another member of staff may have
                // attached a release between opening the list and confirming.
                if ($current->releases()->exists()) {
                    throw ValidationException::withMessages([
                        'batch' => 'Remove the assistance releases from this sheet before deleting it.',
                    ]);
                }

                $current->delete();
            }
        );

        return redirect()
            ->route('rice-distribution-batches.index')
            ->with('success', 'Distribution sheet deleted.');
    }

    /**
     * The sheet on screen, built from the same query and columns as the workbook.
     */
    public function sheet(Request $request, RiceDistributionBatch $riceDistributionBatch)
    {
        $this->authorize('view', $riceDistributionBatch);
        $riceDistributionBatch->loadMissing('municipality:id,name,province');

        $releases = $this->sheet->releases($riceDistributionBatch)
            ->paginate(50)
            ->withQueryString();

        return view('rice_seed_distributions.batches.sheet', [
            'batch' => $riceDistributionBatch,
            'titles' => $this->sheet->titles($riceDistributionBatch),
            'groups' => $this->sheet->groups($riceDistributionBatch),
            'columns' => $this->sheet->columns($riceDistributionBatch),
            'releases' => $releases,
            'rows' => $this->sheet->presentRows(
                $riceDistributionBatch,
                $releases->items(),
                (int) ($releases->firstItem() ?? 1)
            ),
            'totals' => $this->sheet->totals($riceDistributionBatch),
            'canExport' => $request->user()->can('export', $riceDistributionBatch),
        ]);
    }

    public function export(Request $request, RiceDistributionBatch $riceDistributionBatch)
    {
        $this->authorize('export', $riceDistributionBatch);
        $riceDistributionBatch->loadMissing('municipality:id,name,province');

        $workbook = $this->workbook->write($riceDistributionBatch);

        AuditTrail::record(
            'exported',
            'Assistance distributions',
            $request->user()->name.' exported the rice seed distribution sheet "'
                .$riceDistributionBatch->displayLabel().'".',
            [
                'auditable' => $riceDistributionBatch,
                'metadata' => [
                    'reference' => $riceDistributionBatch->reference,
                    'planting_season' => $riceDistributionBatch->plantingSeasonHeading(),
                    'harvest_season' => $riceDistributionBatch->harvestSeasonHeading(),
                    'released_rows' => $workbook['rows'],
                ],
            ]
        );

        return response()
            ->download($workbook['path'], $workbook['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'reference' => trim((string) $validated['reference']),
            'planting_season' => $validated['planting_season'],
            'planting_year' => (int) $validated['planting_year'],
            'harvest_season' => $validated['harvest_season'] ?? null,
            'harvest_year' => $validated['harvest_year'] ?? null,
            'default_seed_bag_kg' => $validated['default_seed_bag_kg'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
