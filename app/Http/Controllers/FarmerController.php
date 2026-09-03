<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class FarmerController extends Controller
{
    public function __construct(
        private ConcurrentWrite $concurrentWrite,
        private MunicipalityAccess $municipalityAccess
    ) {
        $this->middleware('auth')->except(['publicLand']);
    }

    /**
     * Display the seed and farm-input distribution history of one farmer.
     */
    public function records(Request $request, Farmer $farmer)
    {
        $this->authorize('view', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min($perPage, 100));

        $query = RiceSeedDistribution::query()
            ->where('farmer_id', $farmer->id);

        $this->applyMunicipalityScope(
            $query,
            $user,
            'rice_seed_distributions.municipality_id'
        );

        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('seed_variety_claimed', 'like', "%{$q}%")
                    ->orWhere('lot_series', 'like', "%{$q}%")
                    ->orWhere('input_notes', 'like', "%{$q}%")
                    ->orWhere('date_of_sowing_label', 'like', "%{$q}%")
                    ->orWhere('seed_variety_planted', 'like', "%{$q}%");
            });
        }

        $inputCategory = (string) $request->query('input_category', '');
        if (array_key_exists(
            $inputCategory,
            RiceSeedDistribution::INPUT_CATEGORY_LABELS
        )) {
            $query->where('input_category', $inputCategory);
        }

        if ($request->filled('received_from')) {
            $query->whereDate(
                'date_received',
                '>=',
                $request->query('received_from')
            );
        }

        if ($request->filled('received_to')) {
            $query->whereDate(
                'date_received',
                '<=',
                $request->query('received_to')
            );
        }

        $totalRecords = (clone $query)->count();
        $kilogramQuery = (clone $query)->where(function (Builder $query) {
            $query->whereNull('quantity_unit')
                ->orWhere('quantity_unit', '')
                ->orWhere('quantity_unit', 'kg');
        });
        $totalKgs = (float) (clone $kilogramQuery)->sum('kgs_received');

        $topVarietyRow = (clone $query)
            ->select(
                'seed_variety_claimed',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('count')
            ->first();

        $favoriteVariety = $topVarietyRow
            ? $topVarietyRow->seed_variety_claimed
            : 'N/A';

        $firstReceived = (clone $query)->min('date_received');
        $lastReceived = (clone $query)->max('date_received');
        $machineryCount = $farmer->machineries()->count();

        $kgsOverTime = (clone $kilogramQuery)
            ->selectRaw(
                'DATE(date_received) as date,
                 SUM(kgs_received) as total_kgs'
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => (float) $item->total_kgs];
            });

        $varietyChartData = (clone $kilogramQuery)
            ->selectRaw(
                'COALESCE(seed_variety_claimed, "Unknown") as variety,
                 SUM(kgs_received) as total_kgs'
            )
            ->groupBy('variety')
            ->orderByDesc('total_kgs')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->variety => (float) $item->total_kgs];
            });

        $records = $query
            ->orderByDesc('date_received')
            ->paginate($perPage)
            ->withQueryString();

        return view('farmers.records', compact(
            'farmer',
            'records',
            'perPage',
            'totalRecords',
            'totalKgs',
            'q',
            'favoriteVariety',
            'firstReceived',
            'lastReceived',
            'machineryCount',
            'kgsOverTime',
            'varietyChartData'
        ) + [
            'inputCategoryOptions' => RiceSeedDistribution::INPUT_CATEGORY_LABELS,
        ]);
    }

    /**
     * Display the farmer directory.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Farmer::class);
        $q = $request->query('q');
        $user = $this->authenticatedUser($request);
        $municipalities = $this->municipalityOptionsFor($user);
        $selectedMunicipality = $this->resolveWorkspaceMunicipality(
            $request,
            $user,
            $municipalities
        );
        $workspaceMunicipalityId = $selectedMunicipality?->id;

        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(10, min($perPage, 100));

        $totals = $this->baseQuery(
            $request,
            false,
            $workspaceMunicipalityId
        )
            ->selectRaw(
                'COUNT(farmers.id) as total_farmers,
                 SUM(COALESCE(a.total_kgs, 0)) as total_kgs,
                 SUM(CASE WHEN p.farmer_id IS NOT NULL THEN 1 ELSE 0 END) as mapped_farmers,
                 SUM(COALESCE(p.plot_count, 0)) as total_plots,
                 SUM(COALESCE(p.mapped_area_ha, 0)) as mapped_area_ha,
                 SUM(CASE WHEN farmers.ffrs IS NULL OR farmers.ffrs = "" THEN 1 ELSE 0 END) as missing_ffrs,
                 COUNT(DISTINCT CASE
                    WHEN farmers.farm_location IS NOT NULL
                     AND farmers.farm_location != ""
                     AND UPPER(farmers.farm_location) != "UNKNOWN"
                    THEN farmers.farm_location
                 END) as location_count'
            )
            ->first();

        $totalFarmers = (int) ($totals->total_farmers ?? 0);
        $totalKgs = (float) ($totals->total_kgs ?? 0);
        $mappedFarmers = (int) ($totals->mapped_farmers ?? 0);
        $totalPlots = (int) ($totals->total_plots ?? 0);
        $mappedAreaHa = (float) ($totals->mapped_area_ha ?? 0);
        $missingFfrs = (int) ($totals->missing_ffrs ?? 0);
        $locationCount = (int) ($totals->location_count ?? 0);
        $mappingCoverage = $totalFarmers > 0
            ? round(($mappedFarmers / $totalFarmers) * 100, 1)
            : 0.0;

        $genderStats = $this->baseQuery(
            $request,
            false,
            $workspaceMunicipalityId
        )
            ->selectRaw(
                'COALESCE(farmers.gender, "Unspecified") as gender_group,
                 COUNT(farmers.id) as count'
            )
            ->groupBy('gender_group')
            ->pluck('count', 'gender_group');

        $locationStats = $this->baseQuery(
            $request,
            false,
            $workspaceMunicipalityId
        )
            ->selectRaw(
                'farmers.farm_location,
                 COUNT(farmers.id) as count'
            )
            ->whereNotNull('farmers.farm_location')
            ->where('farmers.farm_location', '!=', 'UNKNOWN')
            ->groupBy('farmers.farm_location')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'farmers.farm_location');

        $farmers = $this->baseQuery(
            $request,
            true,
            $workspaceMunicipalityId
        )
            ->orderBy('farmers.last_name')
            ->orderBy('farmers.first_name')
            ->paginate($perPage)
            ->withQueryString();

        // The map always receives the complete municipality workspace. Search,
        // gender, and data-quality filters refine only the registry table.
        $mapFarmers = $this->baseQuery(
            $request,
            true,
            $workspaceMunicipalityId,
            false
        )
            ->orderBy('farmers.last_name')
            ->orderBy('farmers.first_name')
            ->get();
        $mapFarmerCount = $mapFarmers->count();
        $mapMappedFarmerCount = $mapFarmers
            ->where('plot_count', '>', 0)
            ->count();
        $mapPlotCount = (int) $mapFarmers->sum('plot_count');
        $mapAreaHa = (float) $mapFarmers->sum('mapped_area_ha');
        $canChooseMunicipality = $user->isProvincialUser();
        $mapMunicipalityBoundaries = collect();

        // Keep the registry usable during a rolling deployment where the
        // geofence migration may not have run yet. Once available, only active
        // official boundaries within the authenticated map scope are exposed.
        if (Schema::hasTable('municipality_boundaries')) {
            $boundaryQuery = MunicipalityBoundary::query()
                ->active()
                ->with('municipality:id,name,province')
                ->orderBy('municipality_id');

            $this->municipalityAccess->scope($boundaryQuery, $user);

            if ($workspaceMunicipalityId) {
                $boundaryQuery->where('municipality_id', $workspaceMunicipalityId);
            }

            $mapMunicipalityBoundaries = $boundaryQuery
                ->get([
                    'id', 'municipality_id', 'name', 'geojson', 'color',
                    'area_ha', 'centroid_lat', 'centroid_lng',
                ])
                ->map(fn (MunicipalityBoundary $boundary) => [
                    'id' => $boundary->id,
                    'municipality_id' => $boundary->municipality_id,
                    'municipality_name' => $boundary->municipality?->name,
                    'name' => $boundary->name,
                    'geojson' => $boundary->geojson,
                    'color' => $boundary->color ?: '#15803D',
                    'area_ha' => round((float) $boundary->area_ha, 2),
                    'centroid_lat' => (float) $boundary->centroid_lat,
                    'centroid_lng' => (float) $boundary->centroid_lng,
                ])
                ->values();
        }

        return view('farmers.index', compact(
            'farmers',
            'q',
            'perPage',
            'totalFarmers',
            'totalKgs',
            'mappedFarmers',
            'totalPlots',
            'mappedAreaHa',
            'missingFfrs',
            'locationCount',
            'mappingCoverage',
            'genderStats',
            'locationStats',
            'municipalities',
            'selectedMunicipality',
            'mapFarmers',
            'mapFarmerCount',
            'mapMappedFarmerCount',
            'mapPlotCount',
            'mapAreaHa',
            'mapMunicipalityBoundaries',
            'canChooseMunicipality'
        ));
    }

    /**
     * Display one farmer's printable local registry card.
     */
    public function idCard(Request $request, Farmer $farmer)
    {
        $this->authorize('view', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $farmer->loadMissing([
            'municipality:id,name,province',
            'farmPlots:id,farmer_id,name,area_ha',
        ]);

        $scanUrl = route(
            'farmers.public-land',
            ['token' => $farmer->public_map_token]
        );
        $qrCode = QrCode::create($scanUrl)
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->setSize(280)
            ->setMargin(12);
        $qrDataUri = (new SvgWriter())->write($qrCode)->getDataUri();

        return view('farmers.id-card', compact(
            'farmer',
            'scanUrl',
            'qrDataUri'
        ));
    }

    /**
     * Display the read-only interactive parcel map linked from a farmer ID.
     */
    public function publicLand(Request $request, string $token)
    {
        $farmer = Farmer::query()
            ->where('public_map_token', $token)
            ->with([
                'municipality:id,name,province',
                'farmPlots' => function ($query) {
                    $query->orderBy('name')->select([
                        'id',
                        'farmer_id',
                        'name',
                        'polygon_json',
                        'area_ha',
                        'centroid_lat',
                        'centroid_lng',
                        'color',
                    ]);
                },
            ])
            ->firstOrFail();

        $plots = $farmer->farmPlots
            ->map(function ($plot) {
                return [
                    'id' => $plot->id,
                    'name' => $plot->name ?: 'Unnamed parcel',
                    'polygon' => $plot->polygon_json ?: [],
                    'area_ha' => $plot->area_ha !== null
                        ? (float) $plot->area_ha
                        : null,
                    'centroid_lat' => $plot->centroid_lat !== null
                        ? (float) $plot->centroid_lat
                        : null,
                    'centroid_lng' => $plot->centroid_lng !== null
                        ? (float) $plot->centroid_lng
                        : null,
                    'color' => $plot->color ?: '#16834b',
                ];
            })
            ->values();

        $googleMapsApiKey = trim((string) config('services.google_maps.key'));
        $googleMapsMapId = trim((string) config('services.google_maps.map_id'));

        return response()
            ->view('farmers.public-land', compact(
                'farmer',
                'plots',
                'googleMapsApiKey',
                'googleMapsMapId'
            ))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Stream a protected farmer profile photo to an authorized user.
     */
    public function photo(Request $request, Farmer $farmer)
    {
        $this->authorize('view', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $path = $farmer->profile_photo_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return Storage::disk('local')->response(
            $path,
            basename($path),
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Show the create-farmer page.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Farmer::class);
        $user = $this->authenticatedUser($request);

        return view('farmers.create', [
            'record' => new Farmer(),
            'municipalities' => $this->municipalityOptionsFor($user),
            'canChooseMunicipality' => $user->isProvincialUser(),
        ]);
    }

    /**
     * Store a new farmer in the correct municipality.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Farmer::class);
        $data = $this->validatedFarmerData($request);

        $farmer = $this->concurrentWrite->transaction(
            function () use ($data, $request): Farmer {
                $farmer = Farmer::create($data);
                $this->syncProfilePhoto($request, $farmer);

                return $farmer;
            }
        );

        return redirect()
            ->route('farmers.index')
            ->with('success', 'Farmer created successfully.');
    }

    /**
     * Show the edit-farmer page.
     */
    public function edit(Request $request, Farmer $farmer)
    {
        $this->authorize('update', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        return view('farmers.edit', [
            'record' => $farmer,
            'municipalities' => $this->municipalityOptionsFor($user),
            'canChooseMunicipality' => $user->isProvincialUser(),
        ]);
    }

    /**
     * Update an accessible farmer.
     */
    public function update(Request $request, Farmer $farmer)
    {
        $this->authorize('update', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $data = $this->validatedFarmerData($request, $farmer);

        $this->concurrentWrite->execute(
            $farmer,
            $request->input('_record_version'),
            function (Farmer $current) use ($data, $request): void {
                $current->update($data);
                $this->syncProfilePhoto($request, $current);
            }
        );

        return redirect()
            ->route('farmers.index')
            ->with('success', 'Farmer updated successfully.');
    }

    /**
     * Delete an accessible farmer when no dependent records exist.
     */
    public function destroy(Request $request, Farmer $farmer)
    {
        $this->authorize('delete', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $error = $this->concurrentWrite->locked(
            $farmer,
            function (Farmer $current): ?string {
                if ($current->riceSeedDistributions()->exists()) {
                    return 'Cannot delete this farmer because distribution records are linked to them.';
                }

                if ($current->farmPlots()->exists()) {
                    return 'Cannot delete this farmer because farm plots are linked to them.';
                }

                $photoPath = $current->profile_photo_path;
                $current->cooperatives()->detach();
                $current->delete();

                if ($photoPath) {
                    $current->getConnection()->afterCommit(
                        fn () => Storage::disk('local')->delete($photoPath)
                    );
                }

                return null;
            }
        );

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()
            ->route('farmers.index')
            ->with('success', 'Farmer deleted successfully.');
    }

    /**
     * Validate and normalize farmer input.
     */
    private function validatedFarmerData(
        Request $request,
        ?Farmer $farmer = null
    ): array {
        $user = $this->authenticatedUser($request);
        $farmerId = $farmer?->id;

        $rules = [
            'rsbsa_no' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('farmers', 'rsbsa_no')->ignore($farmerId),
            ],
            'ffrs' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('farmers', 'ffrs')->ignore($farmerId),
            ],

            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'ext_name' => ['nullable', 'string', 'max:50'],
            'owner_name' => ['nullable', 'string', 'max:255'],

            'date_of_birth' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
                'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000',
            ],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'gender' => [
                'nullable',
                Rule::in(['Male', 'Female', 'Other', 'Unspecified']),
            ],

            'farm_location' => ['nullable', 'string', 'max:255'],
            'farm_province' => ['nullable', 'string', 'max:255'],
            'farm_municipality' => ['nullable', 'string', 'max:255'],
            'ecosystem' => ['nullable', 'string', 'max:255'],
            'ecosystem_source' => ['nullable', 'string', 'max:255'],

            'farm_area_ha' => ['nullable', 'numeric', 'min:0'],

            'is_arb' => ['nullable', 'boolean'],
            'is_4ps' => ['nullable', 'boolean'],
            'is_ip' => ['nullable', 'boolean'],
            'is_pwd' => ['nullable', 'boolean'],
            'is_sc' => ['nullable', 'boolean'],
            'is_ofw' => ['nullable', 'boolean'],
        ];

        if ($user->isProvincialUser()) {
            $rules['municipality_id'] = [
                'required',
                'integer',
                Rule::exists('municipalities', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ];
        }

        $data = $request->validate($rules);

        unset($data['profile_photo'], $data['remove_profile_photo']);

        $municipality = $this->resolveMunicipalityForWrite(
            $request,
            $user
        );

        $data['municipality_id'] = $municipality->id;
        $data['farm_municipality'] = $municipality->name;
        $data['farm_province'] = $municipality->province ?: 'Tarlac';

        foreach (
            ['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw']
            as $field
        ) {
            $data[$field] = $request->boolean($field);
        }

        foreach ([
            'rsbsa_no',
            'ffrs',
            'middle_name',
            'ext_name',
            'owner_name',
            'contact_number',
            'farm_location',
            'farm_province',
            'farm_municipality',
            'ecosystem',
            'ecosystem_source',
        ] as $field) {
            $data[$field] = $this->nullIfEmpty($data[$field] ?? null);
        }

        return $data;
    }

    /**
     * Store, replace, or remove a farmer photo on the protected local disk.
     */
    private function syncProfilePhoto(
        Request $request,
        Farmer $farmer
    ): void {
        $oldPath = $farmer->profile_photo_path;

        if ($request->hasFile('profile_photo')) {
            $newPath = $request->file('profile_photo')->store(
                'farmer-photos/'.$farmer->municipality_id,
                'local'
            );

            if (! $newPath) {
                abort(500, 'The farmer photo could not be stored.');
            }

            $farmer->forceFill(['profile_photo_path' => $newPath])->save();

            if ($oldPath && $oldPath !== $newPath) {
                $farmer->getConnection()->afterCommit(
                    fn () => Storage::disk('local')->delete($oldPath)
                );
            }

            return;
        }

        if ($request->boolean('remove_profile_photo') && $oldPath) {
            $farmer->forceFill(['profile_photo_path' => null])->save();
            $farmer->getConnection()->afterCommit(
                fn () => Storage::disk('local')->delete($oldPath)
            );

            return;
        }

        $municipalityFolder = 'farmer-photos/'.$farmer->municipality_id.'/';
        if (
            $oldPath
            && ! Str::startsWith($oldPath, $municipalityFolder)
            && Storage::disk('local')->exists($oldPath)
        ) {
            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
            $newPath = $municipalityFolder.Str::uuid()
                .($extension ? '.'.$extension : '');

            if (! Storage::disk('local')->copy($oldPath, $newPath)) {
                abort(500, 'The farmer photo could not be moved safely.');
            }
            $farmer->forceFill(['profile_photo_path' => $newPath])->save();
            $farmer->getConnection()->afterCommit(
                fn () => Storage::disk('local')->delete($oldPath)
            );
        }
    }

    /**
     * Display the Excel import form.
     */
    public function showImport(Request $request)
    {
        $this->authorize('import', Farmer::class);
        $user = $this->authenticatedUser($request);

        return view('farmers.import', [
            'municipalities' => $this->municipalityOptionsFor($user),
            'canChooseMunicipality' => $user->isProvincialUser(),
        ]);
    }

    /**
     * Import farmers into one explicitly controlled municipality.
     */
    public function import(Request $request)
    {
        $this->authorize('import', Farmer::class);
        $user = $this->authenticatedUser($request);

        $rules = [
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ];

        if ($user->isProvincialUser()) {
            $rules['municipality_id'] = [
                'required',
                'integer',
                Rule::exists('municipalities', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ];
        }

        $request->validate($rules);

        $municipality = $this->resolveMunicipalityForWrite(
            $request,
            $user
        );

        $path = $request->file('file')->getRealPath();

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([
            'PARCEL LISTING',
            'OUTSIDE LGU',
        ]);

        $spreadsheet = $reader->load($path);

        $sheetsToRead = [
            'PARCEL LISTING',
            'OUTSIDE LGU',
        ];

        $agg = [];

        foreach ($sheetsToRead as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (! $sheet) {
                continue;
            }

            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                continue;
            }

            $headerRow = array_shift($rows);
            $map = $this->makeHeaderMap($headerRow);

            foreach ($rows as $row) {
                $last = $this->cellStr($row, $map, ['LAST NAME']);
                $first = $this->cellStr($row, $map, ['FIRST NAME']);

                if ($last === '' || $first === '') {
                    continue;
                }

                $middle = $this->nullIfEmpty(
                    $this->cellStr($row, $map, ['MIDDLE NAME'])
                );

                $ext = $this->nullIfEmpty(
                    $this->cellStr($row, $map, ['EXT NAME'])
                );

                $ffrs = $this->nullIfEmpty(
                    $this->cellStr(
                        $row,
                        $map,
                        ['FFRS System Generated No.', 'FFRS']
                    )
                );

                $rsbsa = $this->nullIfEmpty(
                    $this->cellStr(
                        $row,
                        $map,
                        ['LGU RSBSA Number', 'RSBSA NO.', 'RSBSA']
                    )
                );

                $dob = $this->cellDateYmd(
                    $row,
                    $map,
                    ['BIRTHDATE', 'DATE OF BIRTH']
                );

                $genderRaw = strtoupper(
                    $this->cellStr($row, $map, ['GENDER', 'SEX'])
                );

                $gender = match ($genderRaw) {
                    'MALE', 'M' => 'Male',
                    'FEMALE', 'F' => 'Female',
                    default => null,
                };

                $farmLocation = $this->nullIfEmpty(
                    $this->cellStr(
                        $row,
                        $map,
                        ['FARMER ADDRESS 1', 'ADDRESS 1', 'BARANGAY']
                    )
                );

                $ownerName = $this->nullIfEmpty(
                    $this->cellStr(
                        $row,
                        $map,
                        ['OWNER NAME', 'OWNER', 'LAND OWNER']
                    )
                );

                $isArb = $this->cellYesNo($row, $map, ['ARB']);
                $isIp = $this->cellYesNo($row, $map, ['IF IP', 'IP']);

                $farmerKey = $ffrs
                    ? 'FFRS|'.$ffrs
                    : 'NOFFRS|'
                        .mb_strtoupper($last)
                        .'|'
                        .mb_strtoupper($first)
                        .'|'
                        .mb_strtoupper((string) $middle)
                        .'|'
                        .mb_strtoupper((string) $ext)
                        .'|'
                        .($dob ?? '');

                if (! isset($agg[$farmerKey])) {
                    $agg[$farmerKey] = [
                        'municipality_id' => $municipality->id,
                        'ffrs' => $ffrs,
                        'rsbsa_no' => $rsbsa,

                        'last_name' => $last,
                        'first_name' => $first,
                        'middle_name' => $middle,
                        'ext_name' => $ext,
                        'owner_name' => $ownerName,

                        'date_of_birth' => $dob,
                        'gender' => $gender,
                        'contact_number' => null,

                        'farm_location' => $farmLocation ?: 'UNKNOWN',
                        'farm_municipality' => $municipality->name,
                        'farm_province' => $municipality->province ?: 'Tarlac',

                        'ecosystem' => null,
                        'ecosystem_source' => null,

                        'is_arb' => $isArb,
                        'is_ip' => $isIp,
                        'is_4ps' => false,
                        'is_pwd' => false,
                        'is_sc' => false,
                        'is_ofw' => false,

                        'parcels' => [],
                    ];
                } else {
                    $agg[$farmerKey]['is_arb'] =
                        $agg[$farmerKey]['is_arb'] || $isArb;

                    $agg[$farmerKey]['is_ip'] =
                        $agg[$farmerKey]['is_ip'] || $isIp;

                    if (
                        ($agg[$farmerKey]['farm_location'] ?? 'UNKNOWN')
                            === 'UNKNOWN'
                        && $farmLocation
                    ) {
                        $agg[$farmerKey]['farm_location'] = $farmLocation;
                    }

                    if ($ownerName) {
                        if (empty($agg[$farmerKey]['owner_name'])) {
                            $agg[$farmerKey]['owner_name'] = $ownerName;
                        } elseif (
                            stripos(
                                $agg[$farmerKey]['owner_name'],
                                $ownerName
                            ) === false
                        ) {
                            $agg[$farmerKey]['owner_name'] = trim(
                                $agg[$farmerKey]['owner_name']
                                .', '
                                .$ownerName
                            );
                        }
                    }

                    if (
                        empty($agg[$farmerKey]['rsbsa_no'])
                        && $rsbsa
                    ) {
                        $agg[$farmerKey]['rsbsa_no'] = $rsbsa;
                    }
                }

                $parcelNo = $this->nullIfEmpty(
                    $this->cellStr(
                        $row,
                        $map,
                        ['PARCEL NO', 'PARCEL NUMBER']
                    )
                );

                $parcelArea = $this->cellFloat(
                    $row,
                    $map,
                    ['PARCEL AREA']
                );

                if ($parcelNo !== null && $parcelArea !== null) {
                    $previous = $agg[$farmerKey]['parcels'][$parcelNo] ?? 0;

                    $agg[$farmerKey]['parcels'][$parcelNo] = max(
                        $previous,
                        $parcelArea
                    );
                }
            }
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            &$created,
            &$updated,
            $agg,
            $municipality
        ) {
            foreach ($agg as $data) {
                $farmArea = ! empty($data['parcels'])
                    ? round(array_sum($data['parcels']), 2)
                    : null;

                unset($data['parcels']);

                $data['farm_area_ha'] = $farmArea;
                $data['municipality_id'] = $municipality->id;
                $data['farm_municipality'] = $municipality->name;
                $data['farm_province'] = $municipality->province ?: 'Tarlac';

                $existing = Farmer::query()
                    ->where('municipality_id', $municipality->id)
                    ->when(
                        ! empty($data['ffrs']) || ! empty($data['rsbsa_no']),
                        function ($query) use ($data) {
                            $query->where(function ($sub) use ($data) {
                                if (! empty($data['ffrs'])) {
                                    $sub->orWhere('ffrs', $data['ffrs']);
                                }

                                if (! empty($data['rsbsa_no'])) {
                                    $sub->orWhere(
                                        'rsbsa_no',
                                        $data['rsbsa_no']
                                    );
                                }
                            });
                        }
                    )
                    ->first();

                if (
                    ! $existing
                    && empty($data['ffrs'])
                    && empty($data['rsbsa_no'])
                ) {
                    $existing = Farmer::query()
                        ->where('municipality_id', $municipality->id)
                        ->where([
                            'last_name' => $data['last_name'],
                            'first_name' => $data['first_name'],
                            'middle_name' => $data['middle_name'],
                            'ext_name' => $data['ext_name'],
                            'date_of_birth' => $data['date_of_birth'],
                        ])
                        ->first();
                }

                if ($existing) {
                    $existing->fill($data)->save();
                    $updated++;
                } else {
                    Farmer::create($data);
                    $created++;
                }
            }
        });

        return redirect()
            ->route('farmers.index')
            ->with(
                'success',
                "{$municipality->name} farmers import completed. "
                ."Created: {$created}, Updated: {$updated}"
            );
    }

    /**
     * Build the municipality-aware farmer listing query.
     */
    private function baseQuery(
        Request $request,
        bool $withSelect,
        ?int $workspaceMunicipalityId,
        bool $applyRegistryFilters = true
    ): Builder {
        $user = $this->authenticatedUser($request);

        $aggSub = DB::table('rice_seed_distributions')
            ->selectRaw(
                'farmer_id,
                 COUNT(*) as records_count,
                 SUM(CASE WHEN quantity_unit IS NULL OR quantity_unit = \'\' OR quantity_unit = \'kg\' THEN kgs_received ELSE 0 END) as total_kgs,
                 MAX(date_received) as last_received'
            );

        if ($workspaceMunicipalityId !== null) {
            $aggSub->where(
                'rice_seed_distributions.municipality_id',
                $workspaceMunicipalityId
            );
        }

        $aggSub->groupBy('farmer_id');

        $plotAggSub = DB::table('farm_plots')
            ->selectRaw(
                'farmer_id,
                 COUNT(*) as plot_count,
                 SUM(COALESCE(area_ha, 0)) as mapped_area_ha'
            )
            ->groupBy('farmer_id');

        $query = Farmer::query()
            ->leftJoinSub($aggSub, 'a', function ($join) {
                $join->on('a.farmer_id', '=', 'farmers.id');
            })
            ->leftJoinSub($plotAggSub, 'p', function ($join) {
                $join->on('p.farmer_id', '=', 'farmers.id');
            });

        if ($workspaceMunicipalityId !== null) {
            $query->where(
                'farmers.municipality_id',
                $workspaceMunicipalityId
            );
        } else {
            $this->applyMunicipalityScope(
                $query,
                $user,
                'farmers.municipality_id'
            );
        }

        if ($withSelect) {
            $query->selectRaw(
                'farmers.*,
                 COALESCE(a.records_count, 0) as records_count,
                 COALESCE(a.total_kgs, 0) as total_kgs,
                 a.last_received as last_received,
                 COALESCE(p.plot_count, 0) as plot_count,
                 COALESCE(p.mapped_area_ha, 0) as mapped_area_ha'
            );
        }

        if ($applyRegistryFilters) {
            $this->applyFilters($query, $request);
        }

        return $query;
    }

    /**
     * Apply the farmer search filters.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $registryId = null;
            if (preg_match('/^PAIS-FRM-(\d+)$/i', $q, $matches) === 1) {
                $registryId = (int) $matches[1];
            }

            $query->where(function ($sub) use ($q, $registryId) {
                $sub->where('farmers.last_name', 'like', "%{$q}%")
                    ->orWhere('farmers.first_name', 'like', "%{$q}%")
                    ->orWhere('farmers.middle_name', 'like', "%{$q}%")
                    ->orWhere('farmers.owner_name', 'like', "%{$q}%")
                    ->orWhere('farmers.ffrs', 'like', "%{$q}%")
                    ->orWhere('farmers.rsbsa_no', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_location', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_municipality', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_province', 'like', "%{$q}%");

                if ($registryId !== null) {
                    $sub->orWhere('farmers.id', $registryId);
                }
            });
        }

        $gender = (string) $request->query('gender', '');
        if (in_array($gender, ['Male', 'Female', 'Other', 'Unspecified'], true)) {
            if ($gender === 'Unspecified') {
                $query->where(function ($sub) {
                    $sub->whereNull('farmers.gender')
                        ->orWhere('farmers.gender', '')
                        ->orWhere('farmers.gender', 'Unspecified');
                });
            } else {
                $query->where('farmers.gender', $gender);
            }
        }

        $mapping = (string) $request->query('mapping', '');
        if ($mapping === 'mapped') {
            $query->whereNotNull('p.farmer_id');
        } elseif ($mapping === 'unmapped') {
            $query->whereNull('p.farmer_id');
        }

        $quality = (string) $request->query('quality', '');
        if ($quality === 'missing_ffrs') {
            $query->where(function ($sub) {
                $sub->whereNull('farmers.ffrs')
                    ->orWhere('farmers.ffrs', '');
            });
        } elseif ($quality === 'missing_location') {
            $query->where(function ($sub) {
                $sub->whereNull('farmers.farm_location')
                    ->orWhere('farmers.farm_location', '')
                    ->orWhereRaw('UPPER(farmers.farm_location) = ?', ['UNKNOWN']);
            });
        }
    }

    /**
     * Return one accessible farmer's map information.
     */
    public function mapCard(Request $request, Farmer $farmer)
    {
        $this->authorize('view', $farmer);
        $user = $this->authenticatedUser($request);
        $this->ensureFarmerIsAccessible($farmer, $user);

        $distributionQuery = RiceSeedDistribution::query()
            ->where('farmer_id', $farmer->id);

        $this->applyMunicipalityScope(
            $distributionQuery,
            $user,
            'rice_seed_distributions.municipality_id'
        );

        $recordsCount = (int) (clone $distributionQuery)->count();
        $totalKgs = (float) (clone $distributionQuery)
            ->where(function (Builder $query) {
                $query->whereNull('quantity_unit')
                    ->orWhere('quantity_unit', '')
                    ->orWhere('quantity_unit', 'kg');
            })
            ->sum('kgs_received');
        $lastReceived = (clone $distributionQuery)->max('date_received');

        return response()->json([
            'id' => $farmer->id,
            'registry_id' => $farmer->registry_id,
            'profile_photo_url' => $farmer->profile_photo_path
                ? route('farmers.photo', $farmer)
                : null,
            'municipality_id' => $farmer->municipality_id,

            'last_name' => $farmer->last_name,
            'first_name' => $farmer->first_name,
            'middle_name' => $farmer->middle_name,
            'ext_name' => $farmer->ext_name,
            'owner_name' => $farmer->owner_name,

            'ffrs' => $farmer->ffrs,
            'rsbsa_no' => $farmer->rsbsa_no,
            'date_of_birth' => $farmer->date_of_birth,
            'gender' => $farmer->gender,

            'location' => $farmer->farm_location,
            'farm_location' => $farmer->farm_location,
            'farm_municipality' => $farmer->farm_municipality,
            'farm_province' => $farmer->farm_province,
            'farm_area_ha' => $farmer->farm_area_ha,

            'records_count' => $recordsCount,
            'total_kgs' => $totalKgs,
            'last_received' => $lastReceived,
        ]);
    }

    /**
     * Apply municipality filtering to an Eloquent query.
     */
    private function applyMunicipalityScope(
        Builder $query,
        User $user,
        string $column = 'municipality_id'
    ): Builder {
        if ($user->isProvincialUser()) {
            return $query;
        }

        if (! $user->municipality_id) {
            abort(403, 'Your account is not assigned to a municipality.');
        }

        return $query->where($column, $user->municipality_id);
    }

    /**
     * Prevent a municipal user from opening another municipality's farmer.
     */
    private function ensureFarmerIsAccessible(
        Farmer $farmer,
        User $user
    ): void {
        if ($user->isProvincialUser()) {
            return;
        }

        if (
            ! $user->municipality_id
            || (int) $farmer->municipality_id
                !== (int) $user->municipality_id
        ) {
            abort(403, 'You cannot access farmers from another municipality.');
        }
    }

    /**
     * Resolve the municipality to use for create, update, and import actions.
     */
    private function resolveMunicipalityForWrite(
        Request $request,
        User $user
    ): Municipality {
        if ($user->isProvincialUser()) {
            return Municipality::query()
                ->whereKey((int) $request->input('municipality_id'))
                ->where('is_active', true)
                ->firstOrFail();
        }

        if (! $user->municipality_id) {
            abort(403, 'Your account is not assigned to a municipality.');
        }

        return Municipality::query()
            ->whereKey($user->municipality_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Return the active municipality choices visible to this account.
     */
    private function municipalityOptionsFor(User $user)
    {
        return $this->municipalityAccess->choices($user);
    }

    /**
     * Resolve the one municipality shared by the registry and parcel map.
     */
    private function resolveWorkspaceMunicipality(
        Request $request,
        User $user,
        Collection $municipalities
    ): ?Municipality {
        if (! $user->canAccessAllMunicipalities()) {
            $municipality = $municipalities->first();

            if (! $municipality instanceof Municipality) {
                abort(403, 'Your account is not assigned to an active municipality.');
            }

            return $municipality;
        }

        if (! $request->filled('municipality_id')) {
            return null;
        }

        $municipalityId = filter_var(
            $request->query('municipality_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $municipality = $municipalityId
            ? $municipalities->firstWhere('id', (int) $municipalityId)
            : null;

        if (! $municipality instanceof Municipality) {
            throw ValidationException::withMessages([
                'municipality_id' => 'Please select an active municipality.',
            ]);
        }

        return $municipality;
    }

    /**
     * Return the authenticated user or stop the request.
     */
    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function makeHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $column => $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $map[$name] = $column;
            }
        }

        return $map;
    }

    private function cellStr(
        array $row,
        array $map,
        array $possibleHeaders
    ): string {
        foreach ($possibleHeaders as $name) {
            $column = $map[$name] ?? null;

            if ($column) {
                return trim((string) ($row[$column] ?? ''));
            }
        }

        return '';
    }

    private function cellYesNo(
        array $row,
        array $map,
        array $possibleHeaders
    ): bool {
        $value = strtoupper(
            $this->cellStr($row, $map, $possibleHeaders)
        );

        return in_array(
            $value,
            ['YES', 'Y', '1', 'TRUE'],
            true
        );
    }

    private function cellFloat(
        array $row,
        array $map,
        array $possibleHeaders
    ): ?float {
        foreach ($possibleHeaders as $name) {
            $column = $map[$name] ?? null;

            if (! $column) {
                continue;
            }

            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                return null;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }

            $clean = str_replace(',', '', (string) $value);

            return is_numeric($clean)
                ? (float) $clean
                : null;
        }

        return null;
    }

    private function cellDateYmd(
        array $row,
        array $map,
        array $possibleHeaders
    ): ?string {
        foreach ($possibleHeaders as $name) {
            $column = $map[$name] ?? null;

            if (! $column) {
                continue;
            }

            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                return null;
            }

            try {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }

                if (is_numeric($value)) {
                    return ExcelDate::excelToDateTimeObject(
                        (float) $value
                    )->format('Y-m-d');
                }

                return (new \DateTime((string) $value))
                    ->format('Y-m-d');
            } catch (\Throwable $exception) {
                return null;
            }
        }

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
