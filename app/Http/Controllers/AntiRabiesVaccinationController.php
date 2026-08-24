<?php

namespace App\Http\Controllers;

use App\Models\AntiRabiesVaccination;
use App\Support\ConcurrentWrite;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AntiRabiesVaccinationController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AntiRabiesVaccination::class);

        $q        = trim((string) $request->query('q', ''));
        $barangay = trim((string) $request->query('barangay', ''));
        $petType  = trim((string) $request->query('pet_type', ''));
        $serviceType = trim((string) $request->query('service_type', ''));
        $perPage  = (int) $request->query('per_page', 20);

        // Optional (even if you removed Year filter from the view, this doesn't hurt)
        $year     = trim((string) $request->query('year', ''));
        $yearInt  = ($year !== '' && ctype_digit($year)) ? (int) $year : null;

        // Base query for BOTH list + stats/charts
        $base = $this->scopedQuery(
            $request,
            $request->query('municipality_id')
        );

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('owner_name', 'like', "%{$q}%")
                  ->orWhere('pet_name', 'like', "%{$q}%")
                  ->orWhere('pet_breed', 'like', "%{$q}%")
                  ->orWhere('service_name', 'like', "%{$q}%")
                  ->orWhere('diagnosis', 'like', "%{$q}%")
                  ->orWhere('treatment_notes', 'like', "%{$q}%");
            });
        }

        if ($barangay !== '') {
            $base->where('barangay', $barangay);
        }

        if ($petType !== '' && array_key_exists($petType, AntiRabiesVaccination::ANIMAL_TYPE_LABELS)) {
            $base->where('pet_type', $petType);
        }

        if ($serviceType !== '' && array_key_exists($serviceType, AntiRabiesVaccination::SERVICE_TYPE_LABELS)) {
            $base->where('service_type', $serviceType);
        }

        // If you ever add Year filter back, it filters by vaccination_date year
        if ($yearInt !== null) {
            $base->whereYear('vaccination_date', $yearInt);
        }

        // -----------------------------
        // LIST (paginated)
        // -----------------------------
        $records = (clone $base)
            ->orderByDesc('vaccination_date')
            ->orderByDesc('created_at')
            ->paginate(max(5, min($perPage, 200)))
            ->withQueryString();

        // -----------------------------
        // FILTER DROPDOWN OPTIONS
        // -----------------------------
        $petTypeOptions = AntiRabiesVaccination::ANIMAL_TYPE_LABELS;
        $serviceTypeOptions = AntiRabiesVaccination::SERVICE_TYPE_LABELS;

        $barangayOptions = $this->scopedQuery(
            $request,
            $request->query('municipality_id')
        )
            ->select('barangay')
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        // If you still want a Year dropdown in the future:
        $yearOptions = $this->scopedQuery(
            $request,
            $request->query('municipality_id')
        )
            ->selectRaw('YEAR(vaccination_date) AS y')
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y');

        // Owner name suggestions for datalist/search field
        $ownerNameOptions = $this->scopedQuery(
            $request,
            $request->query('municipality_id')
        )
            ->whereNotNull('owner_name')
            ->where('owner_name', '!=', '')
            ->distinct()
            ->orderBy('owner_name')
            ->limit(500)
            ->pluck('owner_name');

        // -----------------------------
        // KPIs (FILTERED)
        // -----------------------------
        $totalServices = (clone $base)->count();

        $animalsServed = (int) (clone $base)
            ->sum('animal_count');

        $uniqueOwners = (clone $base)
            ->distinct()
            ->count('owner_name');

        $uniquePets = (int) (clone $base)
            ->selectRaw("COUNT(DISTINCT CONCAT(owner_name,'|',pet_type,'|',IFNULL(pet_name,''),'|',IFNULL(pet_breed,''),'|',IFNULL(pet_color,''))) AS c")
            ->value('c');

        $latestServiceDate = (clone $base)->max('vaccination_date');

        $currentMonthServices = (clone $base)
            ->whereYear('vaccination_date', LocalTime::now()->year)
            ->whereMonth('vaccination_date', LocalTime::now()->month)
            ->count();

        // -----------------------------
        // chartYear for monthly chart
        // -----------------------------
        if ($yearInt !== null) {
            $chartYear = $yearInt;
        } else {
            $chartYear = $latestServiceDate
                ? (int) date('Y', strtotime($latestServiceDate))
                : (int) LocalTime::now()->year;
        }

        // -----------------------------
        // CHARTS (FILTERED)
        // -----------------------------

        // 1) Vaccinations by Year (from vaccination_date)
        $byYear = (clone $base)
            ->selectRaw('YEAR(vaccination_date) AS y, COUNT(*) AS c')
            ->groupBy('y')
            ->orderBy('y')
            ->get();

        $yearChartLabels = $byYear->pluck('y')->map(fn ($v) => (string) $v)->all();
        $yearChartData   = $byYear->pluck('c')->map(fn ($v) => (int) $v)->all();

        // 2) Animal species breakdown
        $byPetType = (clone $base)
            ->selectRaw('pet_type AS t, SUM(animal_count) AS c')
            ->groupBy('t')
            ->orderByDesc('c')
            ->get()
            ->keyBy('t');

        $petTypeChartLabels = collect(AntiRabiesVaccination::ANIMAL_TYPE_LABELS)
            ->filter(fn ($label, $type) => isset($byPetType[$type]))
            ->values()
            ->all();
        $petTypeChartData = collect(AntiRabiesVaccination::ANIMAL_TYPE_LABELS)
            ->filter(fn ($label, $type) => isset($byPetType[$type]))
            ->keys()
            ->map(fn ($type) => (int) ($byPetType[$type]->c ?? 0))
            ->all();

        $byServiceType = (clone $base)
            ->selectRaw("COALESCE(NULLIF(service_type,''),'vaccination') AS service, COUNT(*) AS c")
            ->groupBy('service')
            ->orderByDesc('c')
            ->get()
            ->keyBy('service');

        $serviceTypeChartLabels = collect(AntiRabiesVaccination::SERVICE_TYPE_LABELS)
            ->values()
            ->all();
        $serviceTypeChartData = collect(AntiRabiesVaccination::SERVICE_TYPE_LABELS)
            ->keys()
            ->map(fn ($type) => (int) ($byServiceType[$type]->c ?? 0))
            ->all();

        // 3) Top 10 Barangays
        $byBarangay = (clone $base)
            ->selectRaw('barangay AS b, COUNT(*) AS c')
            ->groupBy('b')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        $barangayChartLabels = $byBarangay->pluck('b')->map(fn ($v) => (string) $v)->all();
        $barangayChartData   = $byBarangay->pluck('c')->map(fn ($v) => (int) $v)->all();

        // 4) Top 10 Breeds
        $byBreed = (clone $base)
            ->selectRaw('pet_breed AS br, COUNT(*) AS c')
            ->groupBy('br')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        $breedChartLabels = $byBreed->pluck('br')->map(fn ($v) => (string) $v)->all();
        $breedChartData   = $byBreed->pluck('c')->map(fn ($v) => (int) $v)->all();

        // 5) Monthly vaccinations for chartYear
        $monthlyBase = (clone $base)->whereYear('vaccination_date', $chartYear);

        $monthly = $monthlyBase
            ->selectRaw('MONTH(vaccination_date) AS m, COUNT(*) AS c')
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('c', 'm');

        $monthlyChartLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthlyChartData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyChartData[] = (int) ($monthly[$m] ?? 0);
        }

        // 6) Owner Age Groups
        $ageGroups = (clone $base)
            ->whereNotNull('birthday')
            ->selectRaw("
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) < 18 THEN '0-17'
                    WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 18 AND 29 THEN '18-29'
                    WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 30 AND 44 THEN '30-44'
                    WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 45 AND 59 THEN '45-59'
                    ELSE '60+'
                END AS grp,
                COUNT(*) AS c
            ")
            ->groupBy('grp')
            ->get()
            ->keyBy('grp');

        $ageChartLabels = ['0-17','18-29','30-44','45-59','60+'];
        $ageChartData = array_map(fn ($g) => (int) ($ageGroups[$g]->c ?? 0), $ageChartLabels);

        return view('anti_rabies_vaccinations.index', compact(
            'records',
            'barangayOptions',
            'petTypeOptions',
            'ownerNameOptions',
            'q',
            'barangay',
            'petType',
            'serviceType',
            'perPage',

            // (keep available; harmless even if you removed Year filter from view)
            'yearOptions',
            'year',
            'chartYear',

            // KPIs
            'totalServices',
            'animalsServed',
            'uniqueOwners',
            'uniquePets',
            'latestServiceDate',
            'currentMonthServices',

            // charts
            'yearChartLabels',
            'yearChartData',
            'petTypeChartLabels',
            'petTypeChartData',
            'serviceTypeChartLabels',
            'serviceTypeChartData',
            'barangayChartLabels',
            'barangayChartData',
            'breedChartLabels',
            'breedChartData',
            'monthlyChartLabels',
            'monthlyChartData',
            'ageChartLabels',
            'ageChartData',
        ) + [
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id'),
            'serviceTypeOptions' => $serviceTypeOptions,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', AntiRabiesVaccination::class);

        $selectedMunicipalityId = $request->query('municipality_id')
            ?? $request->user()->municipality_id;
        $ownerNameOptions = $this->ownerNameOptions(
            $request,
            $selectedMunicipalityId
        );

        return view('anti_rabies_vaccinations.create', [
            'record' => null,
            'ownerNameOptions' => $ownerNameOptions,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $selectedMunicipalityId,
            'serviceTypeOptions' => AntiRabiesVaccination::SERVICE_TYPE_LABELS,
            'animalTypeOptions' => AntiRabiesVaccination::ANIMAL_TYPE_LABELS,
            'defaultServiceType' => array_key_exists(
                (string) $request->query('service_type'),
                AntiRabiesVaccination::SERVICE_TYPE_LABELS
            ) ? $request->query('service_type') : 'vaccination',
        ]);
    }

    private function ownerNameOptions(
        Request $request,
        mixed $municipalityId
    ) {
        $query = AntiRabiesVaccination::query();

        if (
            $request->user()->canAccessAllMunicipalities()
            && ($municipalityId === null || $municipalityId === '')
        ) {
            return collect();
        }

        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $municipalityId
        );

        return $query
            ->whereNotNull('owner_name')
            ->where('owner_name', '!=', '')
            ->distinct()
            ->orderBy('owner_name')
            ->limit(500)
            ->pluck('owner_name');
    }

    public function store(Request $request)
    {
        $this->authorize('create', AntiRabiesVaccination::class);
        $data = $this->validateData($request);
        $data['municipality_id'] = $this->municipalityAccess
            ->resolveForWrite(
                $request->user(),
                $data['municipality_id'] ?? null
            );

        // The legacy column name remains the canonical service date for compatibility.
        if (Schema::hasColumn('anti_rabies_vaccinations', 'vaccination_year')) {
            $data['vaccination_year'] = (int) date('Y', strtotime($data['vaccination_date']));
        }

        $this->concurrentWrite->transaction(
            fn () => AntiRabiesVaccination::create($data)
        );

        return redirect()
            ->route('anti-rabies-vaccinations.index')
            ->with('success', 'Animal-health service recorded successfully.');
    }

    public function edit(
        Request $request,
        AntiRabiesVaccination $antiRabiesVaccination
    )
    {
        $this->authorize('update', $antiRabiesVaccination);
        $ownerNameOptions = $this->ownerNameOptions(
            $request,
            $antiRabiesVaccination->municipality_id
        );

        return view('anti_rabies_vaccinations.edit', [
            'record' => $antiRabiesVaccination,
            'ownerNameOptions' => $ownerNameOptions,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $antiRabiesVaccination
                ->municipality_id,
            'serviceTypeOptions' => AntiRabiesVaccination::SERVICE_TYPE_LABELS,
            'animalTypeOptions' => AntiRabiesVaccination::ANIMAL_TYPE_LABELS,
            'defaultServiceType' => $antiRabiesVaccination->service_type ?: 'vaccination',
        ]);
    }

    public function update(Request $request, AntiRabiesVaccination $antiRabiesVaccination)
    {
        $this->authorize('update', $antiRabiesVaccination);
        $data = $this->validateData($request);
        $data['municipality_id'] = $this->municipalityAccess
            ->resolveForWrite(
                $request->user(),
                $data['municipality_id'] ?? null
            );

        // The legacy column name remains the canonical service date for compatibility.
        if (Schema::hasColumn('anti_rabies_vaccinations', 'vaccination_year')) {
            $data['vaccination_year'] = (int) date('Y', strtotime($data['vaccination_date']));
        }

        $this->concurrentWrite->execute(
            $antiRabiesVaccination,
            $request->input('_record_version'),
            fn (AntiRabiesVaccination $current) => $current->update($data)
        );

        return redirect()
            ->route('anti-rabies-vaccinations.index')
            ->with('success', 'Animal-health service updated successfully.');
    }

    public function destroy(AntiRabiesVaccination $antiRabiesVaccination)
    {
        $this->authorize('delete', $antiRabiesVaccination);
        $this->concurrentWrite->locked(
            $antiRabiesVaccination,
            fn (AntiRabiesVaccination $current) => $current->delete()
        );

        return back()->with('success', 'Animal-health service deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        // Preserve compatibility with the original anti-rabies form and any
        // existing integrations that predate generalized animal-health fields.
        $request->merge([
            'service_type' => $request->input('service_type', 'vaccination'),
            'service_name' => $request->input('service_name', 'Anti-rabies vaccine'),
            'animal_count' => $request->input('animal_count', 1),
        ]);

        return $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            // Owner
            'owner_name' => ['required', 'string', 'max:120'],
            'barangay'   => ['required', 'string', 'max:120'],
            'birthday'   => ['nullable', 'date', 'before_or_equal:today'],

            // Animal or livestock group
            'pet_type'   => ['required', Rule::in(array_keys(AntiRabiesVaccination::ANIMAL_TYPE_LABELS))],
            'pet_breed'  => ['nullable', 'string', 'max:120'],
            'pet_name'   => ['nullable', 'string', 'max:120'],
            'pet_color'  => ['nullable', 'string', 'max:80'],

            // Service details
            'service_type' => ['required', Rule::in(array_keys(AntiRabiesVaccination::SERVICE_TYPE_LABELS))],
            'service_name' => ['required', 'string', 'max:150'],
            'animal_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            'dosage' => ['nullable', 'string', 'max:120'],
            'administration_route' => ['nullable', 'string', 'max:60'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'treatment_notes' => ['nullable', 'string', 'max:3000'],
            'administered_by' => ['nullable', 'string', 'max:120'],

            // Historical date column used for every service type
            'vaccination_date' => ['required', 'date', 'before_or_equal:today'],
            'next_service_date' => ['nullable', 'date', 'after_or_equal:vaccination_date'],
        ]);
    }

    public function ownerLookup(Request $request)
    {
        $this->authorize('viewAny', AntiRabiesVaccination::class);

        $name = trim((string) $request->query('name', ''));
        if ($name === '') {
            return response()->json(['exists' => false, 'pets' => []]);
        }

        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $request->query('municipality_id')
        );

        $records = AntiRabiesVaccination::query()
            ->where('municipality_id', $municipalityId)
            ->where('owner_name', $name)
            ->orderByDesc('vaccination_date')
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['exists' => false, 'pets' => []]);
        }

        $latest = $records->first();

        $pets = $records
            ->map(function ($r) {
                $lastDate = optional($r->vaccination_date)->format('Y-m-d');
                $lastYear = $r->vaccination_date ? (int) date('Y', strtotime($r->vaccination_date)) : null;

                return [
                    'pet_type' => $r->pet_type,
                    'pet_name' => $r->pet_name,
                    'pet_breed' => $r->pet_breed,
                    'pet_color' => $r->pet_color,
                    'last_service_date' => $lastDate,
                    'last_service_year' => $lastYear,
                    'last_service_type' => $r->serviceTypeLabel(),
                ];
            })
            ->unique(function ($p) {
                return strtolower(trim($p['pet_type'].'|'.$p['pet_name'].'|'.$p['pet_breed'].'|'.($p['pet_color'] ?? '')));
            })
            ->values();

        return response()->json([
            'exists' => true,
            'owner' => [
                'owner_name' => $latest->owner_name,
                'barangay' => $latest->barangay,
                'birthday' => optional($latest->birthday)->format('Y-m-d'),
            ],
            'pets' => $pets,
        ]);
    }

    private function scopedQuery(
        Request $request,
        mixed $municipalityId = null
    ): Builder {
        $query = AntiRabiesVaccination::query();
        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $municipalityId
        );

        return $query;
    }
}
