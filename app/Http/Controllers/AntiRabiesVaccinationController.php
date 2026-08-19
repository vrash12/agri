<?php

namespace App\Http\Controllers;

use App\Models\AntiRabiesVaccination;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AntiRabiesVaccinationController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AntiRabiesVaccination::class);

        $q        = trim((string) $request->query('q', ''));
        $barangay = trim((string) $request->query('barangay', ''));
        $petType  = trim((string) $request->query('pet_type', '')); // Dog/Cat
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
                  ->orWhere('pet_breed', 'like', "%{$q}%");
            });
        }

        if ($barangay !== '') {
            $base->where('barangay', $barangay);
        }

        if ($petType !== '' && in_array($petType, ['Dog', 'Cat'], true)) {
            $base->where('pet_type', $petType);
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
        $petTypeOptions = ['Dog', 'Cat'];

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
        $totalVaccinations = (clone $base)->count();

        $uniqueOwners = (clone $base)
            ->distinct()
            ->count('owner_name');

        $uniquePets = (int) (clone $base)
            ->selectRaw("COUNT(DISTINCT CONCAT(owner_name,'|',pet_type,'|',pet_name,'|',pet_breed,'|',IFNULL(pet_color,''))) AS c")
            ->value('c');

        $latestVaccinationDate = (clone $base)->max('vaccination_date');

        $currentMonthVaccinations = (clone $base)
            ->whereYear('vaccination_date', now()->year)
            ->whereMonth('vaccination_date', now()->month)
            ->count();

        // -----------------------------
        // chartYear for monthly chart
        // -----------------------------
        if ($yearInt !== null) {
            $chartYear = $yearInt;
        } else {
            $chartYear = $latestVaccinationDate
                ? (int) date('Y', strtotime($latestVaccinationDate))
                : (int) now()->year;
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

        // 2) Pet Type Breakdown (Dog vs Cat)
        $byPetType = (clone $base)
            ->selectRaw('pet_type AS t, COUNT(*) AS c')
            ->groupBy('t')
            ->orderBy('t')
            ->get()
            ->keyBy('t');

        $petTypeChartLabels = ['Dog', 'Cat'];
        $petTypeChartData   = [
            (int) ($byPetType['Dog']->c ?? 0),
            (int) ($byPetType['Cat']->c ?? 0),
        ];

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
            'perPage',

            // (keep available; harmless even if you removed Year filter from view)
            'yearOptions',
            'year',
            'chartYear',

            // KPIs
            'totalVaccinations',
            'uniqueOwners',
            'uniquePets',
            'latestVaccinationDate',
            'currentMonthVaccinations',

            // charts
            'yearChartLabels',
            'yearChartData',
            'petTypeChartLabels',
            'petTypeChartData',
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

        // ✅ If the column still exists in DB, auto-fill it from vaccination_date
        if (Schema::hasColumn('anti_rabies_vaccinations', 'vaccination_year')) {
            $data['vaccination_year'] = (int) date('Y', strtotime($data['vaccination_date']));
        }

        AntiRabiesVaccination::create($data);

        return redirect()
            ->route('anti-rabies-vaccinations.index')
            ->with('success', 'Anti-rabies vaccination record added successfully.');
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

        // ✅ If the column still exists in DB, auto-fill it from vaccination_date
        if (Schema::hasColumn('anti_rabies_vaccinations', 'vaccination_year')) {
            $data['vaccination_year'] = (int) date('Y', strtotime($data['vaccination_date']));
        }

        $antiRabiesVaccination->update($data);

        return redirect()
            ->route('anti-rabies-vaccinations.index')
            ->with('success', 'Anti-rabies vaccination record updated successfully.');
    }

    public function destroy(AntiRabiesVaccination $antiRabiesVaccination)
    {
        $this->authorize('delete', $antiRabiesVaccination);
        $antiRabiesVaccination->delete();

        return back()->with('success', 'Record deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            // Owner
            'owner_name' => ['required', 'string', 'max:120'],
            'barangay'   => ['required', 'string', 'max:120'],
            'birthday'   => ['required', 'date'],

            // Pet
            'pet_type'   => ['required', 'in:Dog,Cat'],
            'pet_breed'  => ['required', 'string', 'max:120'],
            'pet_name'   => ['required', 'string', 'max:120'],
            'pet_color'  => ['nullable', 'string', 'max:80'],

            // Vaccination
            'vaccination_date' => ['required', 'date'],
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
                    'last_vaccination_date' => $lastDate,
                    'last_vaccination_year' => $lastYear,
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
