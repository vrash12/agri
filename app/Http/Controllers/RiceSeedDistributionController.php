<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiceSeedDistributionRequest;
use App\Models\Farmer;
use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\CsvExport;
use App\Support\DuplicateClaimCheck;
use App\Support\HarvestFromRelease;
use App\Support\MunicipalityAccess;
use App\Support\SeedReleaseQuantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RiceSeedDistributionController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite
    ) {
        $this->middleware('auth');
    }

    /**
     * Centralized dropdown options (from your Excel unique values)
     */
    private array $seedVarietyClaimedOptions = [
        'BIO ZARAP',
        'Habilis Plus',
        'Hatao Dinorado',
        'LP 2096',
        'LP 534',
        'LP 937',
        'S6003',
        'SL-19H',
        'SL-20H',
        'SL-39H',
        'SL-68H',
        'SL-8H',
        'US 88',
    ];

    private array $inputCategoryOptions = [
        'rice_seed' => 'Rice seed',
        'corn_seed' => 'Corn seed',
        'vegetable_seed' => 'Vegetable seed',
        'fertilizer' => 'Fertilizer / Abono',
        'soil_amendment' => 'Soil amendment',
        'other_input' => 'Other farm input',
        'fish_fingerlings' => 'Fish fingerlings',
        'fish_feed' => 'Fish feed',
        'fishing_gear' => 'Fishing gear',
        'aquaculture_input' => 'Aquaculture input',
        'other_fisheries' => 'Other fisheries assistance',
    ];

    private array $inputSuggestions = [
        'rice_seed' => [
            'BIO ZARAP', 'Habilis Plus', 'Hatao Dinorado', 'LP 2096',
            'LP 534', 'LP 937', 'S6003', 'SL-19H', 'SL-20H', 'SL-39H',
            'SL-68H', 'SL-8H', 'US 88',
        ],
        'corn_seed' => [
            'Hybrid yellow corn seed',
            'Open-pollinated corn seed',
            'White corn seed',
        ],
        'vegetable_seed' => [
            'Eggplant seed',
            'Okra seed',
            'Tomato seed',
            'String bean seed',
            'Squash seed',
        ],
        'fertilizer' => [
            'Urea 46-0-0',
            'Complete 14-14-14',
            'Ammonium Sulfate 21-0-0',
            'Muriate of Potash 0-0-60',
            'Organic fertilizer',
        ],
        'soil_amendment' => [
            'Agricultural lime',
            'Compost',
            'Vermicast',
            'Dolomite',
        ],
        'other_input' => [
            'Seedling tray',
            'Inoculant',
            'Plant growth supplement',
        ],
        'fish_fingerlings' => [
            'Tilapia fingerlings',
            'Hito (catfish) fingerlings',
            'Bangus fingerlings',
            'Carp fingerlings',
        ],
        'fish_feed' => [
            'Tilapia starter feed',
            'Tilapia grower feed',
            'Catfish starter feed',
            'Floating fish feed',
        ],
        'fishing_gear' => [
            'Fishing net',
            'Hapa net',
            'Gill net',
            'Nylon fishing line',
            'Fishing hooks',
            'Fish cage materials',
        ],
        'aquaculture_input' => [
            'Agricultural lime for fishpond',
            'Pond probiotic',
            'Water quality test kit',
            'Fishpond disinfectant',
        ],
        'other_fisheries' => [
            'Fish container',
            'Harvesting basket',
            'Aeration equipment',
        ],
    ];

    private array $quantityUnitOptions = [
        'kg' => 'Kilogram (kg)',
        'sack' => 'Sack / bag',
        'pack' => 'Pack',
        'g' => 'Gram (g)',
        'l' => 'Liter (L)',
        'ml' => 'Milliliter (mL)',
        'bottle' => 'Bottle',
        'piece' => 'Piece',
        'set' => 'Set',
        'roll' => 'Roll',
        'box' => 'Box',
        'bundle' => 'Bundle',
    ];

    private array $preferredUnitsByCategory = [
        'rice_seed' => 'kg',
        'corn_seed' => 'kg',
        'vegetable_seed' => 'pack',
        'fertilizer' => 'sack',
        'soil_amendment' => 'kg',
        'other_input' => 'piece',
        'fish_fingerlings' => 'piece',
        'fish_feed' => 'kg',
        'fishing_gear' => 'piece',
        'aquaculture_input' => 'kg',
        'other_fisheries' => 'piece',
    ];

    /** @var array<int,string> Offered on the form; the form request validates against the same list. */
    private array $cropEstablishmentOptions = StoreRiceSeedDistributionRequest::CROP_ESTABLISHMENT;

    /** @var array<int,string> */
    private array $seedClassOptions = StoreRiceSeedDistributionRequest::SEED_CLASSES;

    public function index(Request $request)
    {
        $this->authorize('viewAny', RiceSeedDistribution::class);

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $baseQuery = $this->buildFilteredQuery($request);

        // Totals (filtered)
        $totalRecords = (clone $baseQuery)->count();
        $totalKgs = (float) $this->kilogramReleases(clone $baseQuery)
            ->sum('kgs_received');
        $averageKgs = (float) ($this->kilogramReleases(clone $baseQuery)
            ->avg('kgs_received') ?? 0);
        $uniqueRecipients = (clone $baseQuery)
            ->whereNotNull('farmer_id')
            ->distinct()
            ->count('farmer_id');
        $fisheriesRecords = (clone $baseQuery)
            ->whereIn(
                'input_category',
                RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
            )
            ->count();
        $fingerlingsReleased = (float) (clone $baseQuery)
            ->where('input_category', 'fish_fingerlings')
            ->where('quantity_unit', 'piece')
            ->sum('kgs_received');

        // Stats
        $latestReceived = (clone $baseQuery)->max('date_received');
        $trendYear = $latestReceived
            ? (int) date('Y', strtotime($latestReceived))
            : (int) now()->year;

        $monthlyReleases = $this->kilogramReleases(clone $baseQuery)
            ->whereYear('date_received', $trendYear)
            ->selectRaw('MONTH(date_received) as month_number, SUM(kgs_received) as total_kgs')
            ->groupBy('month_number')
            ->orderBy('month_number')
            ->pluck('total_kgs', 'month_number');

        // ===== Charts (based on current filters) =====

        // 1) Top 10 Locations by total kgs
        $topLocations = $this->kilogramReleases(clone $baseQuery)
            ->whereNotNull('farm_location')
            ->where('farm_location', '!=', '')
            ->selectRaw('farm_location, SUM(kgs_received) as total')
            ->groupBy('farm_location')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 2) Gender distribution
        $genderDist = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(gender,''),'Unknown') as gender, COUNT(*) as cnt")
            ->groupBy('gender')
            ->orderByDesc('cnt')
            ->get();

        // 3) Eligibility counts
        $eligCols = ['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'];
        $eligCounts = [];
        foreach ($eligCols as $c) {
            $eligCounts[$c] = (clone $baseQuery)->where($c, 1)->count();
        }

        // 4) Age group distribution
        $ageGroups = (clone $baseQuery)
            ->whereNotNull('date_of_birth')
            ->selectRaw("
                CASE
                  WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN '0-17'
                  WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 29 THEN '18-29'
                  WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 30 AND 44 THEN '30-44'
                  WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 45 AND 59 THEN '45-59'
                  ELSE '60+'
                END as grp,
                COUNT(*) as cnt
            ")
            ->groupBy('grp')
            ->orderByRaw("FIELD(grp,'0-17','18-29','30-44','45-59','60+')")
            ->get();

        // 5) Top Seed Varieties Claimed (By Kgs)
        $seedVarieties = $this->kilogramReleases(clone $baseQuery)
            ->where(function (Builder $query) {
                $query->whereNull('input_category')
                    ->orWhere('input_category', 'rice_seed');
            })
            ->whereNotNull('seed_variety_claimed')
            ->where('seed_variety_claimed', '!=', '')
            ->selectRaw('seed_variety_claimed, SUM(kgs_received) as total_kgs')
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('total_kgs')
            ->limit(10)
            ->get();

        $inputCategories = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(input_category,''),'rice_seed') as category, COUNT(*) as cnt")
            ->groupBy('category')
            ->orderByDesc('cnt')
            ->get();

        // 6) Crop Establishment Methods (Count)
        $cropEst = (clone $baseQuery)
            ->whereNotNull('crop_establishment')
            ->where('crop_establishment', '!=', '')
            ->selectRaw('crop_establishment, COUNT(*) as cnt')
            ->groupBy('crop_establishment')
            ->orderByDesc('cnt')
            ->get();

        // 7) Top Yielding Planted Varieties (By Production Bags)
        $topYieldingVarieties = (clone $baseQuery)
            ->whereNotNull('seed_variety_planted')
            ->where('seed_variety_planted', '!=', '')
            ->selectRaw('seed_variety_planted, SUM(total_production_bags) as total_bags')
            ->groupBy('seed_variety_planted')
            ->orderByDesc('total_bags')
            ->limit(10)
            ->get();

        // 8) Seed Class Distribution
        $seedClasses = (clone $baseQuery)
            ->whereNotNull('seed_class')
            ->where('seed_class', '!=', '')
            ->selectRaw('seed_class, COUNT(*) as cnt')
            ->groupBy('seed_class')
            ->orderByDesc('cnt')
            ->get();

        // 9) Total Farm Area by Municipality
        $areaByMunicipality = (clone $baseQuery)
            ->whereNotNull('farm_municipality')
            ->where('farm_municipality', '!=', '')
            ->selectRaw('farm_municipality, SUM(farm_area_ha) as total_area')
            ->groupBy('farm_municipality')
            ->orderByDesc('total_area')
            ->limit(10)
            ->get();

        $stats = [
            'latestReceived' => $latestReceived,
            'trendYear' => $trendYear,
        ];

        $charts = [
            'toploc_labels' => $topLocations->pluck('farm_location')->values(),
            'toploc_values' => $topLocations->pluck('total')->map(fn ($v) => (float) $v)->values(),

            'gender_labels' => $genderDist->pluck('gender')->values(),
            'gender_values' => $genderDist->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'elig_labels' => ['ARB', '4Ps', 'IP', 'PWD', 'SC', 'OFW'],
            'elig_values' => [
                (int) ($eligCounts['is_arb'] ?? 0),
                (int) ($eligCounts['is_4ps'] ?? 0),
                (int) ($eligCounts['is_ip'] ?? 0),
                (int) ($eligCounts['is_pwd'] ?? 0),
                (int) ($eligCounts['is_sc'] ?? 0),
                (int) ($eligCounts['is_ofw'] ?? 0),
            ],

            'age_labels' => $ageGroups->pluck('grp')->values(),
            'age_values' => $ageGroups->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'seed_variety_labels' => $seedVarieties->pluck('seed_variety_claimed')->values(),
            'seed_variety_values' => $seedVarieties->pluck('total_kgs')->map(fn ($v) => (float) $v)->values(),

            'input_category_labels' => $inputCategories
                ->pluck('category')
                ->map(fn ($category) => $this->inputCategoryOptions[$category]
                    ?? ucfirst(str_replace('_', ' ', $category)))
                ->values(),
            'input_category_values' => $inputCategories->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'crop_est_labels' => $cropEst->pluck('crop_establishment')->values(),
            'crop_est_values' => $cropEst->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'yield_variety_labels' => $topYieldingVarieties->pluck('seed_variety_planted')->values(),
            'yield_variety_values' => $topYieldingVarieties->pluck('total_bags')->map(fn ($v) => (int) $v)->values(),

            'seed_class_labels' => $seedClasses->pluck('seed_class')->values(),
            'seed_class_values' => $seedClasses->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'area_mun_labels' => $areaByMunicipality->pluck('farm_municipality')->values(),
            'area_mun_values' => $areaByMunicipality->pluck('total_area')->map(fn ($v) => (float) $v)->values(),

            'monthly_labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'monthly_values' => collect(range(1, 12))
                ->map(fn ($month) => (float) ($monthlyReleases[$month] ?? 0))
                ->values(),
        ];

        $records = $baseQuery
            ->orderByDesc('date_received')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();

        $q = $request->query('q');

        return view('rice_seed_distributions.index', compact(
            'records',
            'q',
            'perPage',
            'totalRecords',
            'totalKgs',
            'averageKgs',
            'uniqueRecipients',
            'stats',
            'charts'
        ) + [
            'seedVarietyClaimedOptions' => $this->seedVarietyClaimedOptions,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id'),
            'inputCategoryOptions' => $this->inputCategoryOptions,
            'quantityUnitOptions' => $this->quantityUnitOptions,
            'assistanceSectorOptions' => RiceSeedDistribution::ASSISTANCE_SECTOR_LABELS,
            'fisheriesRecords' => $fisheriesRecords,
            'fingerlingsReleased' => $fingerlingsReleased,
        ]);
    }

    public function importForm(Request $request)
    {
        $this->authorize('import', RiceSeedDistribution::class);

        return view('rice_seed_distributions.import', [
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
        ]);
    }

    public function showImport(Request $request)
    {
        return $this->importForm($request);
    }

    public function import(Request $request)
    {
        $this->authorize('import', RiceSeedDistribution::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'municipality_id' => ['nullable', 'integer'],
        ]);

        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $validated['municipality_id'] ?? null
        );

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getSheetByName('NRP DISTRIBUTION') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->with('error', 'No data rows found in the file.');
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->makeHeaderMap($headerRow);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use (
            &$created,
            &$updated,
            &$skipped,
            $rows,
            $headerMap,
            $municipalityId
        ) {
            foreach ($rows as $row) {
                $ffrs = $this->cellStr($row, $headerMap, ['FFRS RSBSA Number']);
                if ($ffrs === '') {
                    $skipped++;

                    continue;
                }

                $farmer = Farmer::query()
                    ->where('municipality_id', $municipalityId)
                    ->where(function (Builder $query) use ($ffrs) {
                        $query->where('ffrs', $ffrs)
                            ->orWhere('rsbsa_no', $ffrs);
                    })
                    ->first();

                $prov = $this->cellStr($row, $headerMap, ['Farm Address (Province)']);
                $mun = $this->cellStr($row, $headerMap, ['Farm Address (Municipality)']);
                $fallbackFarmLocation = trim(implode(', ', array_filter([$mun, $prov])));

                $seedVarietyClaimed = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Variety Claimed']));

                $cropEst = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Crop Establishment']));
                if ($cropEst !== null && ! in_array($cropEst, $this->cropEstablishmentOptions, true)) {
                    $cropEst = null;
                }

                $seedClass = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Class (Hybrid, Inbred, etc.)']));
                if ($seedClass !== null && ! in_array($seedClass, $this->seedClassOptions, true)) {
                    $seedClass = 'Not Specified';
                }

                $claimedArea = $this->cellFloat($row, $headerMap, ['Claimed Area (ha)']);
                $claimedSeeds = $this->cellFloat($row, $headerMap, ['Claimed seeds (kg)']);
                $lotSeries = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Lot Series']));
                $sowingLabel = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Date of Sowing']));

                $sex = $this->normalizeGender($this->cellStr($row, $headerMap, ['Sex']));
                $dob = $this->cellDateYmd($row, $headerMap, ['Birthdate']);

                $data = [
                    'municipality_id' => $municipalityId,
                    'farmer_id' => $farmer?->id,

                    'last_name' => $farmer?->last_name ?? $this->cellStr($row, $headerMap, ['Farmer Last Name']),
                    'first_name' => $farmer?->first_name ?? $this->cellStr($row, $headerMap, ['Farmer First Name']),
                    'middle_name' => $farmer?->middle_name ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Farmer Middle Name'])),
                    'ext_name' => $farmer?->ext_name ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Farmer Ext Name'])),
                    'ffrs' => $farmer?->ffrs ?? $ffrs,
                    'date_of_birth' => $farmer?->date_of_birth?->format('Y-m-d') ?? $dob,
                    'gender' => $farmer?->gender ?? $sex,
                    'contact_number' => $farmer?->contact_number ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Contact Number'])),

                    'farm_province' => $farmer?->farm_province ?? ($prov ?: null),
                    'farm_municipality' => $farmer?->farm_municipality ?? ($mun ?: null),
                    'farm_location' => $farmer?->farm_location ?? ($fallbackFarmLocation !== '' ? $fallbackFarmLocation : 'UNKNOWN'),

                    'ecosystem' => $farmer?->ecosystem ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Eco-System'])),
                    'ecosystem_source' => $farmer?->ecosystem_source ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Eco-System Source'])),

                    'farm_area_ha' => $farmer?->farm_area_ha ?? $claimedArea,
                    'kgs_received' => $claimedSeeds ?? 0,
                    'input_category' => 'rice_seed',
                    'quantity_unit' => 'kg',
                    'date_received' => now()->toDateString(),

                    'seed_variety_claimed' => $seedVarietyClaimed,
                    'claimed_area_ha' => $claimedArea,
                    'claimed_seeds_kg' => $claimedSeeds,
                    'lot_series' => $lotSeries,
                    'crop_establishment' => $cropEst,
                    'date_of_sowing_label' => $sowingLabel,

                    'avg_weight_per_bag_kg' => $this->cellInt($row, $headerMap, ['Average Weight per Bag (kg) - for all variety(ies)']),
                    'total_production_bags' => $this->cellInt($row, $headerMap, ['Total Production (no. of bags) - for all variety(ies)']),
                    'avg_area_harvested_ha' => $this->cellFloat($row, $headerMap, ['Average Area Harvested (ha)']),
                    'seed_variety_planted' => $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Variety Planted'])),
                    'seed_class' => $seedClass,
                ];

                $unique = [
                    'municipality_id' => $municipalityId,
                    'ffrs' => $ffrs,
                    'seed_variety_claimed' => $seedVarietyClaimed,
                    'lot_series' => $lotSeries,
                    'date_of_sowing_label' => $sowingLabel,
                ];

                $existing = RiceSeedDistribution::where($unique)->first();

                if ($existing) {
                    $existing->fill($data)->save();
                    $updated++;
                } else {
                    RiceSeedDistribution::create($data);
                    $created++;
                }
            }
        });

        return redirect()->route('rice-seed-distributions.index')->with(
            'success',
            "Import done. Created: {$created}, Updated: {$updated}, Skipped (no FFRS): {$skipped}"
        );
    }

    public function create(Request $request)
    {
        $this->authorize('create', RiceSeedDistribution::class);

        $farmers = $this->getFarmersForForm($request);
        $selectedFarmerId = $request->query('farmer_id');
        $selectedFarmer = $farmers->firstWhere('id', (int) $selectedFarmerId);

        return view('rice_seed_distributions.create', [
            'farmers' => $farmers,
            'batches' => $this->getBatchesForForm($request),
            'seasonOptions' => RiceDistributionBatch::SEASONS,
            'consentStatusOptions' => RiceSeedDistribution::CONSENT_STATUS_LABELS,
            'seedVarietyClaimedOptions' => $this->seedVarietyClaimedOptions,
            'cropEstablishmentOptions' => $this->cropEstablishmentOptions,
            'seedClassOptions' => $this->seedClassOptions,
            'inputCategoryOptions' => $this->inputCategoryOptions,
            'inputSuggestions' => $this->inputSuggestions,
            'quantityUnitOptions' => $this->quantityUnitOptions,
            'preferredUnitsByCategory' => $this->preferredUnitsByCategory,
            'farmer_id' => $selectedFarmerId,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $selectedFarmer?->municipality_id
                ?? $request->query('municipality_id')
                ?? $request->user()->municipality_id,
            'defaultInputCategory' => $request->query('assistance_sector') === 'fisheries'
                ? 'fish_fingerlings'
                : 'rice_seed',
        ]);
    }

    public function store(StoreRiceSeedDistributionRequest $request)
    {
        // The form request runs the policy before the rules.
        $validated = $request->validated();
        $farmer = Farmer::findOrFail($validated['farmer_id']);
        $municipalityId = $this->resolveDistributionMunicipality(
            $request,
            $validated,
            $farmer
        );

        /*
         * One entitlement should be issued once, and nothing here used to notice when
         * it was issued twice. This warns rather than refuses: a second release is
         * sometimes right — a replanting after a typhoon, a delivery split because the
         * truck was short — and refusing those would teach staff to work around the
         * register, which is worse than not checking at all.
         *
         * The officer is shown what the earlier release was and can save again to
         * confirm. That confirmation is audited, so "who issued a second claim and
         * when" is answerable afterwards.
         */
        $warning = app(DuplicateClaimCheck::class)->warningFor(
            (int) $validated['farmer_id'],
            $validated['input_category'] ?? null,
            $validated['date_received'] ?? null,
            $validated['harvest_season'] ?? null,
            isset($validated['harvest_year']) ? (int) $validated['harvest_year'] : null
        );

        if ($warning !== null && ! $request->boolean('confirm_repeat_claim')) {
            return back()
                ->withInput()
                ->with('repeat_claim_warning', $warning);
        }

        $payload = $this->buildDistributionPayload(
            $validated,
            $farmer,
            $municipalityId
        );

        $release = $this->concurrentWrite->transaction(
            fn () => RiceSeedDistribution::create($payload)
        );

        // The sheet's production section is where rice harvest is entered. Projecting
        // it here means the figure reaches the production chart without anyone typing
        // it twice.
        app(HarvestFromRelease::class)->sync($release);

        if ($warning !== null) {
            AuditTrail::record(
                'repeat_claim_confirmed',
                'Assistance distributions',
                $request->user()->name.' recorded a repeat release after being warned about an earlier one.',
                [
                    'auditable_id' => (string) $release->getKey(),
                    'metadata' => [
                        'farmer_id' => (int) $validated['farmer_id'],
                        'input_category' => $validated['input_category'] ?? null,
                        'warning' => $warning,
                    ],
                ]
            );
        }

        return redirect()
            ->route('rice-seed-distributions.index')
            ->with('success', 'Agriculture or fisheries assistance release added successfully.');
    }

    public function edit(
        Request $request,
        RiceSeedDistribution $riceSeedDistribution
    ) {
        $this->authorize('update', $riceSeedDistribution);
        $riceSeedDistribution->loadMissing('farmer');

        return view('rice_seed_distributions.edit', [
            'record' => $riceSeedDistribution,
            'farmers' => $this->getFarmersForForm($request),
            'batches' => $this->getBatchesForForm($request),
            'seasonOptions' => RiceDistributionBatch::SEASONS,
            'consentStatusOptions' => RiceSeedDistribution::CONSENT_STATUS_LABELS,
            'seedVarietyClaimedOptions' => $this->seedVarietyClaimedOptions,
            // Imported releases carry establishment methods and seed classes this
            // form never offered. Keeping the stored value in the list is what
            // makes those records editable instead of unsavable.
            'cropEstablishmentOptions' => $this->optionsWithStoredValue(
                $this->cropEstablishmentOptions,
                $riceSeedDistribution->crop_establishment
            ),
            'seedClassOptions' => $this->optionsWithStoredValue(
                $this->seedClassOptions,
                $riceSeedDistribution->seed_class
            ),
            'inputCategoryOptions' => $this->inputCategoryOptions,
            'inputSuggestions' => $this->inputSuggestions,
            'quantityUnitOptions' => $this->quantityUnitOptions,
            'preferredUnitsByCategory' => $this->preferredUnitsByCategory,
            'farmer_id' => $riceSeedDistribution->farmer_id,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $riceSeedDistribution
                ->municipality_id,
        ]);
    }

    public function update(StoreRiceSeedDistributionRequest $request, RiceSeedDistribution $riceSeedDistribution)
    {
        // The form request already authorized this record. Repeating the check
        // here keeps the route-bound record protected even if the request class is
        // ever swapped for plain controller validation.
        $this->authorize('update', $riceSeedDistribution);

        $validated = $request->validated();
        $farmer = Farmer::findOrFail($validated['farmer_id']);
        $municipalityId = $this->resolveDistributionMunicipality(
            $request,
            $validated,
            $farmer
        );

        $payload = $this->buildDistributionPayload(
            $validated,
            $farmer,
            $municipalityId
        );

        $this->concurrentWrite->execute(
            $riceSeedDistribution,
            $request->input('_record_version'),
            fn (RiceSeedDistribution $current) => $current->update($payload)
        );

        // Re-projected rather than left alone: an edited harvest updates the same
        // record, and a production section that has been cleared removes it instead
        // of leaving a figure behind that the sheet no longer claims.
        app(HarvestFromRelease::class)->sync($riceSeedDistribution->fresh());

        return redirect()
            ->route('rice-seed-distributions.index')
            ->with('success', 'Agriculture or fisheries assistance release updated successfully.');
    }

    public function destroy(RiceSeedDistribution $riceSeedDistribution)
    {
        $this->authorize('delete', $riceSeedDistribution);
        $releaseId = $riceSeedDistribution->getKey();

        $this->concurrentWrite->locked(
            $riceSeedDistribution,
            fn (RiceSeedDistribution $current) => $current->delete()
        );

        // A harvest projected from a release does not outlive it.
        \App\Models\HarvestRecord::query()->where('rice_seed_distribution_id', $releaseId)->delete();

        return redirect()->route('rice-seed-distributions.index')
            ->with('success', 'Assistance release deleted successfully.');
    }

    public function export(Request $request)
    {
        $this->authorize('export', RiceSeedDistribution::class);

        $query = $this->buildFilteredQuery($request);
        $maximumId = (int) ((clone $query)->max('id') ?? 0);
        $rowCount = $maximumId === 0
            ? 0
            : (clone $query)->where('id', '<=', $maximumId)->count();

        $filename = 'agriculture_fisheries_assistance_'.now()->format('Y-m-d_H-i-s').'.csv';

        /*
         * Recorded before the download starts.
         *
         * This file leaves the system carrying every recipient's date of birth,
         * gender and six eligibility flags — 4Ps, IP, PWD, senior citizen, OFW and
         * agrarian reform beneficiary. Those say things about a household that the
         * household did not choose to publish, and once the file is on someone's
         * laptop the system has no further say in where it goes. The audit trail is
         * the only record that it left at all, so it is written even if the stream
         * is abandoned part-way: an export that began is what matters here, not one
         * that finished.
         *
         * The filters are recorded with it, because "who exported the register" and
         * "who exported the twelve 4Ps recipients in one barangay" are different
         * events and the row count alone cannot tell them apart.
         */
        AuditTrail::record(
            'exported',
            'Assistance distributions',
            $request->user()->name.' exported the agriculture and fisheries assistance register.',
            [
                'metadata' => [
                    'row_count' => $rowCount,
                    'includes_personal_data' => true,
                    'filters' => array_filter($request->only([
                        'q',
                        'municipality_id',
                        'assistance_sector',
                        'input_category',
                        'seed_variety_claimed',
                        'received_from',
                        'received_to',
                        'gender',
                        'kgs_min',
                        'kgs_max',
                        'last_name',
                        'first_name',
                        'middle_name',
                        'ffrs',
                        'farm_location',
                        'farm_area_min',
                        'farm_area_max',
                        'dob_from',
                        'dob_to',
                        'is_arb',
                        'is_4ps',
                        'is_ip',
                        'is_pwd',
                        'is_sc',
                        'is_ofw',
                    ]), fn ($value) => $value !== null && $value !== ''),
                ],
            ]
        );

        $headings = [
            'No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'FFRS No.',
            'Date of Birth',
            'Location of Farm',
            // Recorded on the release itself rather than joined from the farmer:
            // a register should say where the hand-over belonged at the time, and a
            // provincial export otherwise cannot tell two barangays of the same name
            // in different municipalities apart.
            'Municipality',
            'Province',
            'Gender',
            'ARB',
            '4Ps',
            'IP',
            'PWD',
            'SC',
            'OFW',
            'Farm Area (ha)',
            'Assistance Sector',
            'Input Category',
            'Item / Variety',
            'Quantity Released',
            'Unit',
            'Input Notes',
            'Date Received',
        ];

        return response()->streamDownload(function () use (
            $query,
            $headings,
            $maximumId
        ) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);

            $i = 0;

            if ($maximumId > 0) {
                (clone $query)
                    ->where('id', '<=', $maximumId)
                    ->chunkById(1000, function ($rows) use (&$i, $out) {
                        foreach ($rows as $r) {
                            $i++;

                            fputcsv($out, array_map([CsvExport::class, 'value'], [
                                $i,
                                $r->last_name,
                                $r->first_name,
                                $r->middle_name,
                                $r->ffrs,
                                optional($r->date_of_birth)->format('Y-m-d'),
                                $r->farm_location,
                                $r->farm_municipality,
                                $r->farm_province,
                                $r->gender,
                                $r->is_arb ? 'Y' : 'N',
                                $r->is_4ps ? 'Y' : 'N',
                                $r->is_ip ? 'Y' : 'N',
                                $r->is_pwd ? 'Y' : 'N',
                                $r->is_sc ? 'Y' : 'N',
                                $r->is_ofw ? 'Y' : 'N',
                                $r->farm_area_ha,
                                $r->assistanceSectorLabel(),
                                $this->inputCategoryOptions[$r->input_category]
                                    ?? ucfirst(str_replace('_', ' ', $r->input_category ?: 'rice_seed')),
                                $r->seed_variety_claimed,
                                $r->kgs_received,
                                $r->quantity_unit ?: 'kg',
                                $r->input_notes,
                                optional($r->date_received)->format('Y-m-d'),
                            ]));
                        }
                    });
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $q = trim((string) $request->query('q', ''));

        $query = RiceSeedDistribution::query();
        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $request->query('municipality_id')
        );

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('last_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('middle_name', 'like', "%{$q}%")
                    ->orWhere('ffrs', 'like', "%{$q}%")
                    ->orWhere('farm_location', 'like', "%{$q}%")
                    ->orWhere('seed_variety_claimed', 'like', "%{$q}%")
                    ->orWhere('input_notes', 'like', "%{$q}%");
            });
        }

        foreach (['last_name', 'first_name', 'middle_name', 'ffrs', 'farm_location'] as $col) {
            $val = trim((string) $request->query($col, ''));
            if ($val !== '') {
                $query->where($col, 'like', "%{$val}%");
            }
        }

        $gender = $request->query('gender');
        if (in_array($gender, Farmer::GENDERS, true)) {
            $query->where('gender', $gender);
        }

        $seedVariety = trim((string) $request->query(
            'seed_variety_claimed',
            ''
        ));
        if ($seedVariety !== '') {
            $query->where('seed_variety_claimed', 'like', "%{$seedVariety}%");
        }

        $inputCategory = $request->query('input_category');
        if (array_key_exists((string) $inputCategory, $this->inputCategoryOptions)) {
            $query->where('input_category', $inputCategory);
        }

        $assistanceSector = (string) $request->query('assistance_sector', '');
        if ($assistanceSector === 'fisheries') {
            $query->whereIn(
                'input_category',
                RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
            );
        } elseif ($assistanceSector === 'agriculture') {
            $query->where(function (Builder $query) {
                $query->whereNull('input_category')
                    ->orWhereNotIn(
                        'input_category',
                        RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
                    );
            });
        }

        foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $col) {
            $val = $request->query($col);
            if ($val === '1' || $val === '0') {
                $query->where($col, (int) $val);
            }
        }

        $this->applyRange($query, 'farm_area_ha', $request->query('farm_area_min'), $request->query('farm_area_max'));
        $this->applyRange($query, 'kgs_received', $request->query('kgs_min'), $request->query('kgs_max'));

        $this->applyDateRange($query, 'date_of_birth', $request->query('dob_from'), $request->query('dob_to'));
        $this->applyDateRange($query, 'date_received', $request->query('received_from'), $request->query('received_to'));

        return $query;
    }

    private function applyRange(Builder $query, string $column, $min, $max): void
    {
        if ($min !== null && $min !== '' && is_numeric($min)) {
            $query->where($column, '>=', $min);
        }
        if ($max !== null && $max !== '' && is_numeric($max)) {
            $query->where($column, '<=', $max);
        }
    }

    private function kilogramReleases(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('quantity_unit')
                ->orWhere('quantity_unit', '')
                ->orWhere('quantity_unit', 'kg');
        });
    }

    private function applyDateRange(Builder $query, string $column, $from, $to): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate($column, '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function getFarmersForForm(Request $request)
    {
        $query = Farmer::query();
        $this->municipalityAccess->scope($query, $request->user());

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'municipality_id',
                'rsbsa_no',
                'ffrs',
                'last_name',
                'first_name',
                'middle_name',
                'ext_name',
                'date_of_birth',
                'contact_number',
                'gender',
                'farm_location',
                'farm_province',
                'farm_municipality',
                'ecosystem',
                'ecosystem_source',
                'farm_area_ha',
                'is_arb',
                'is_4ps',
                'is_ip',
                'is_pwd',
                'is_sc',
                'is_ofw',
            ]);
    }

    /**
     * Distribution sheets the signed-in account may attach a release to.
     *
     * Municipality scope is applied to the base query before anything is
     * selected, so a provincial account sees only sheets inside its province and a
     * municipal account only its own.
     */
    private function getBatchesForForm(Request $request)
    {
        $query = RiceDistributionBatch::query();
        $this->municipalityAccess->scope($query, $request->user());

        return $query
            ->orderByDesc('planting_year')
            ->orderBy('reference')
            ->limit(200)
            ->get([
                'id',
                'municipality_id',
                'reference',
                'planting_season',
                'planting_year',
                'harvest_season',
                'harvest_year',
                'default_seed_bag_kg',
            ]);
    }

    private function buildDistributionPayload(
        array $validated,
        Farmer $farmer,
        int $municipalityId
    ): array {
        return array_merge(
            $this->buildFarmerSnapshot($farmer),
            [
                'municipality_id' => $municipalityId,
                'farmer_id' => $farmer->id,
                'batch_id' => $this->resolveBatchId($validated, $municipalityId),
                'input_category' => $validated['input_category'],
                'seed_variety_claimed' => $validated['seed_variety_claimed'],
                'claimed_area_ha' => $validated['claimed_area_ha'] ?? null,
                'claimed_seeds_kg' => $validated['claimed_seeds_kg'] ?? null,
                'registered_rice_area_ha' => $validated['registered_rice_area_ha'] ?? null,
                'lot_series' => $this->nullIfEmpty($validated['lot_series'] ?? null),
                'input_notes' => $this->nullIfEmpty($validated['input_notes'] ?? null),
                'crop_establishment' => $validated['crop_establishment'] ?? null,
                'date_of_sowing_label' => $this->nullIfEmpty($validated['date_of_sowing_label'] ?? null),
                'avg_weight_per_bag_kg' => $validated['avg_weight_per_bag_kg'] ?? null,
                'total_production_bags' => $validated['total_production_bags'] ?? null,
                'avg_area_harvested_ha' => $validated['avg_area_harvested_ha'] ?? null,
                'seed_variety_planted' => $this->nullIfEmpty($validated['seed_variety_planted'] ?? null),
                'seed_class' => $validated['seed_class'] ?? null,
                'harvest_season' => $validated['harvest_season'] ?? null,
                'harvest_year' => $validated['harvest_year'] ?? null,
                'seed_bags' => $validated['seed_bags'] ?? null,
                'seed_bag_kg' => $validated['seed_bag_kg'] ?? null,
                // Already derived from bags x bag weight by the form request when
                // both were supplied, so there is only ever one stored total.
                'kgs_received' => $validated['kgs_received'],
                'quantity_unit' => $validated['quantity_unit'],
                'date_received' => $validated['date_received'],
                'consent_status' => $validated['consent_status'],
                'kp_kits_received' => $validated['kp_kits_received'] ?? null,
                'representative_name' => $this->nullIfEmpty($validated['representative_name'] ?? null),
            ]
        );
    }

    private function resolveDistributionMunicipality(
        Request $request,
        array $validated,
        Farmer $farmer
    ): int {
        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $validated['municipality_id'] ?? null
        );

        if ((int) $farmer->municipality_id !== $municipalityId) {
            throw ValidationException::withMessages([
                'farmer_id' => 'The selected beneficiary does not belong to the selected municipality.',
            ]);
        }

        return $municipalityId;
    }

    /**
     * A release may only join a distribution sheet owned by the same municipality.
     *
     * An unavailable sheet and a sheet belonging to another municipality return
     * the same message, so the form cannot be used to discover whether another
     * municipality's sheet exists.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveBatchId(array $validated, int $municipalityId): ?int
    {
        $batchId = $validated['batch_id'] ?? null;

        if ($batchId === null || $batchId === '') {
            return null;
        }

        $available = RiceDistributionBatch::query()
            ->whereKey((int) $batchId)
            ->where('municipality_id', $municipalityId)
            ->exists();

        if (! $available) {
            throw ValidationException::withMessages([
                'batch_id' => 'The selected distribution sheet is unavailable for this municipality.',
            ]);
        }

        // The sheet totals a column printed as "Total seed (kg)" and nothing in this
        // system converts between units, so a release counted in pieces or sacks
        // cannot be added to it. Seed handed out in bags belongs on the sheet through
        // the bag count and bag weight, which state the kilograms outright.
        if (! SeedReleaseQuantity::isKilogramUnit($validated['quantity_unit'] ?? null)) {
            throw ValidationException::withMessages([
                'batch_id' => 'A distribution sheet totals seed in kilograms. Record this release in kilograms, or use the bag count and bag weight, before adding it to a sheet.',
            ]);
        }

        return (int) $batchId;
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function optionsWithStoredValue(array $options, ?string $stored): array
    {
        $stored = $stored === null ? '' : trim($stored);

        if ($stored === '' || in_array($stored, $options, true)) {
            return $options;
        }

        return [...$options, $stored];
    }

    private function buildFarmerSnapshot(Farmer $farmer): array
    {
        return [
            'last_name' => $farmer->last_name,
            'first_name' => $farmer->first_name,
            'middle_name' => $farmer->middle_name,
            'ext_name' => $farmer->ext_name,
            'ffrs' => $farmer->ffrs ?: $farmer->rsbsa_no,
            'date_of_birth' => $farmer->date_of_birth,
            'gender' => $farmer->gender,
            'contact_number' => $farmer->contact_number,

            'farm_location' => $farmer->farm_location,
            'farm_province' => $farmer->farm_province,
            'farm_municipality' => $farmer->farm_municipality,
            'farm_area_ha' => $farmer->farm_area_ha,

            'ecosystem' => $farmer->ecosystem,
            'ecosystem_source' => $farmer->ecosystem_source,

            'is_arb' => (bool) $farmer->is_arb,
            'is_4ps' => (bool) $farmer->is_4ps,
            'is_ip' => (bool) $farmer->is_ip,
            'is_pwd' => (bool) $farmer->is_pwd,
            'is_sc' => (bool) $farmer->is_sc,
            'is_ofw' => (bool) $farmer->is_ofw,
        ];
    }

    /** =========================
     * IMPORT HELPERS
     * ========================= */
    private function makeHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $colLetter => $header) {
            $h = $this->normalizeHeader((string) $header);
            if ($h !== '') {
                $map[$h] = $colLetter;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $h): string
    {
        $h = trim($h);
        $h = preg_replace('/\s+/', ' ', $h);

        return mb_strtoupper($h);
    }

    private function cellStr(array $row, array $headerMap, array $possibleHeaders): string
    {
        foreach ($possibleHeaders as $name) {
            $key = $this->normalizeHeader($name);
            $col = $headerMap[$key] ?? null;
            if ($col) {
                return trim((string) ($row[$col] ?? ''));
            }
        }

        return '';
    }

    private function cellFloat(array $row, array $headerMap, array $possibleHeaders): ?float
    {
        $raw = $this->cellStr($row, $headerMap, $possibleHeaders);
        if ($raw === '') {
            return null;
        }

        $raw = str_replace(',', '', $raw);

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function cellInt(array $row, array $headerMap, array $possibleHeaders): ?int
    {
        $f = $this->cellFloat($row, $headerMap, $possibleHeaders);

        return $f === null ? null : (int) round($f);
    }

    /**
     * Reads a date cell and returns Y-m-d or null.
     */
    private function cellDateYmd(array $row, array $headerMap, array $possibleHeaders): ?string
    {
        foreach ($possibleHeaders as $name) {
            $key = $this->normalizeHeader($name);
            $col = $headerMap[$key] ?? null;
            if (! $col) {
                continue;
            }

            $v = $row[$col] ?? null;
            if ($v === null || $v === '') {
                return null;
            }

            try {
                if ($v instanceof \DateTimeInterface) {
                    return $v->format('Y-m-d');
                }

                if (is_numeric($v)) {
                    return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
                }

                $s = trim((string) $v);

                if (preg_match('~^\d{1,2}/\d{1,2}/\d{2,4}$~', $s)) {
                    $dt = \DateTime::createFromFormat('m/d/Y', $s) ?: \DateTime::createFromFormat('n/j/Y', $s);
                    if (! $dt) {
                        $dt = \DateTime::createFromFormat('m/d/y', $s) ?: \DateTime::createFromFormat('n/j/y', $s);
                    }

                    return $dt ? $dt->format('Y-m-d') : null;
                }

                return (new \DateTime($s))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function nullIfEmpty(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return ($v === '') ? null : $v;
    }

    private function normalizeGender(?string $raw): ?string
    {
        $raw = strtoupper(trim((string) $raw));

        return match ($raw) {
            'MALE', 'M' => 'Male',
            'FEMALE', 'F' => 'Female',
            'OTHER', 'O' => 'Other',
            default => null,
        };
    }

    /** @param  mixed  $value */
}
