<?php

namespace App\Http\Controllers;

use App\Models\RiceSeedDistribution;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RiceSeedDistributionController extends Controller
{
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

    private array $cropEstablishmentOptions = [
        'Direct',
        'Transplanted',
    ];

    private array $seedClassOptions = [
        'Certified',
        'Not Specified',
    ];

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $baseQuery = $this->buildFilteredQuery($request);

        // Totals (filtered)
        $totalRecords = (clone $baseQuery)->count();
        $totalKgs     = (float) (clone $baseQuery)->sum('kgs_received');

        // Stats
        $latestReceived = (clone $baseQuery)->max('date_received');

        // ===== Charts (based on current filters) =====

        // 1) Top 10 Locations by total kgs
        $topLocations = (clone $baseQuery)
            ->whereNotNull('farm_location')
            ->where('farm_location', '!=', '')
            ->selectRaw("farm_location, SUM(kgs_received) as total")
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
        $seedVarieties = (clone $baseQuery)
            ->whereNotNull('seed_variety_claimed')
            ->where('seed_variety_claimed', '!=', '')
            ->selectRaw("seed_variety_claimed, SUM(kgs_received) as total_kgs")
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('total_kgs')
            ->limit(10)
            ->get();

        // 6) Crop Establishment Methods (Count)
        $cropEst = (clone $baseQuery)
            ->whereNotNull('crop_establishment')
            ->where('crop_establishment', '!=', '')
            ->selectRaw("crop_establishment, COUNT(*) as cnt")
            ->groupBy('crop_establishment')
            ->orderByDesc('cnt')
            ->get();

        // 7) NEW: Top Yielding Planted Varieties (By Production Bags)
        $topYieldingVarieties = (clone $baseQuery)
            ->whereNotNull('seed_variety_planted')
            ->where('seed_variety_planted', '!=', '')
            ->selectRaw("seed_variety_planted, SUM(total_production_bags) as total_bags")
            ->groupBy('seed_variety_planted')
            ->orderByDesc('total_bags')
            ->limit(10)
            ->get();

        // 8) NEW: Seed Class Distribution
        $seedClasses = (clone $baseQuery)
            ->whereNotNull('seed_class')
            ->where('seed_class', '!=', '')
            ->selectRaw("seed_class, COUNT(*) as cnt")
            ->groupBy('seed_class')
            ->orderByDesc('cnt')
            ->get();

        // 9) NEW: Total Farm Area by Municipality
        $areaByMunicipality = (clone $baseQuery)
            ->whereNotNull('farm_municipality')
            ->where('farm_municipality', '!=', '')
            ->selectRaw("farm_municipality, SUM(farm_area_ha) as total_area")
            ->groupBy('farm_municipality')
            ->orderByDesc('total_area')
            ->limit(10)
            ->get();

        $stats = [
            'latestReceived' => $latestReceived,
        ];

        $charts = [
            'toploc_labels'  => $topLocations->pluck('farm_location')->values(),
            'toploc_values'  => $topLocations->pluck('total')->map(fn ($v) => (float) $v)->values(),

            'gender_labels'  => $genderDist->pluck('gender')->values(),
            'gender_values'  => $genderDist->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'elig_labels'    => ['ARB', '4Ps', 'IP', 'PWD', 'SC', 'OFW'],
            'elig_values'    => [
                (int) ($eligCounts['is_arb'] ?? 0),
                (int) ($eligCounts['is_4ps'] ?? 0),
                (int) ($eligCounts['is_ip']  ?? 0),
                (int) ($eligCounts['is_pwd'] ?? 0),
                (int) ($eligCounts['is_sc']  ?? 0),
                (int) ($eligCounts['is_ofw'] ?? 0),
            ],

            'age_labels'     => $ageGroups->pluck('grp')->values(),
            'age_values'     => $ageGroups->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'seed_variety_labels' => $seedVarieties->pluck('seed_variety_claimed')->values(),
            'seed_variety_values' => $seedVarieties->pluck('total_kgs')->map(fn ($v) => (float) $v)->values(),

            'crop_est_labels' => $cropEst->pluck('crop_establishment')->values(),
            'crop_est_values' => $cropEst->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            // DATA FOR NEW CHARTS
            'yield_variety_labels' => $topYieldingVarieties->pluck('seed_variety_planted')->values(),
            'yield_variety_values' => $topYieldingVarieties->pluck('total_bags')->map(fn ($v) => (int) $v)->values(),

            'seed_class_labels' => $seedClasses->pluck('seed_class')->values(),
            'seed_class_values' => $seedClasses->pluck('cnt')->map(fn ($v) => (int) $v)->values(),

            'area_mun_labels' => $areaByMunicipality->pluck('farm_municipality')->values(),
            'area_mun_values' => $areaByMunicipality->pluck('total_area')->map(fn ($v) => (float) $v)->values(),
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
            'stats',
            'charts'
        ));
    }

    /** GET import page */
    public function importForm()
    {
        return view('rice_seed_distributions.import');
    }

    /** (Optional) Keep this if you already referenced showImport somewhere */
    public function showImport()
    {
        return view('rice_seed_distributions.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getSheetByName('NRP DISTRIBUTION') ?? $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->with('error', 'No data rows found in the file.');
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->makeHeaderMap($headerRow);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use (&$created, &$updated, &$skipped, $rows, $headerMap) {
            foreach ($rows as $row) {
                $ffrs = $this->cellStr($row, $headerMap, ['FFRS RSBSA Number']);
                if ($ffrs === '') {
                    $skipped++;
                    continue;
                }

                $farmer = Farmer::where('ffrs', $ffrs)
                    ->orWhere('rsbsa_no', $ffrs)
                    ->first();

                $prov = $this->cellStr($row, $headerMap, ['Farm Address (Province)']);
                $mun  = $this->cellStr($row, $headerMap, ['Farm Address (Municipality)']);
                $fallbackFarmLocation = trim(implode(', ', array_filter([$mun, $prov])));

                $seedVarietyClaimed = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Variety Claimed']));

                $cropEst = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Crop Establishment']));
                if ($cropEst !== null && !in_array($cropEst, $this->cropEstablishmentOptions, true)) {
                    $cropEst = null;
                }

                $seedClass = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Class (Hybrid, Inbred, etc.)']));
                if ($seedClass !== null && !in_array($seedClass, $this->seedClassOptions, true)) {
                    $seedClass = 'Not Specified';
                }

                $claimedArea  = $this->cellFloat($row, $headerMap, ['Claimed Area (ha)']);
                $claimedSeeds = $this->cellFloat($row, $headerMap, ['Claimed seeds (kg)']);
                $lotSeries    = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Lot Series']));
                $sowingLabel  = $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Date of Sowing']));

                $sex = $this->normalizeGender($this->cellStr($row, $headerMap, ['Sex']));
                $dob = $this->cellDateYmd($row, $headerMap, ['Birthdate']);

                $data = [
                    'farmer_id' => $farmer?->id,

                    'last_name'      => $farmer?->last_name ?? $this->cellStr($row, $headerMap, ['Farmer Last Name']),
                    'first_name'     => $farmer?->first_name ?? $this->cellStr($row, $headerMap, ['Farmer First Name']),
                    'middle_name'    => $farmer?->middle_name ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Farmer Middle Name'])),
                    'ext_name'       => $farmer?->ext_name ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Farmer Ext Name'])),
                    'ffrs'           => $farmer?->ffrs ?? $ffrs,
                    'date_of_birth'  => $farmer?->date_of_birth?->format('Y-m-d') ?? $dob,
                    'gender'         => $farmer?->gender ?? $sex,
                    'contact_number' => $farmer?->contact_number ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Contact Number'])),

                    'farm_province'     => $farmer?->farm_province ?? ($prov ?: null),
                    'farm_municipality' => $farmer?->farm_municipality ?? ($mun ?: null),
                    'farm_location'     => $farmer?->farm_location ?? ($fallbackFarmLocation !== '' ? $fallbackFarmLocation : 'UNKNOWN'),

                    'ecosystem'        => $farmer?->ecosystem ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Eco-System'])),
                    'ecosystem_source' => $farmer?->ecosystem_source ?? $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Eco-System Source'])),

                    'farm_area_ha'  => $farmer?->farm_area_ha ?? $claimedArea,
                    'kgs_received'  => $claimedSeeds ?? 0,
                    'date_received' => now()->toDateString(),

                    'seed_variety_claimed' => $seedVarietyClaimed,
                    'claimed_area_ha'      => $claimedArea,
                    'claimed_seeds_kg'     => $claimedSeeds,
                    'lot_series'           => $lotSeries,
                    'crop_establishment'   => $cropEst,
                    'date_of_sowing_label' => $sowingLabel,

                    'avg_weight_per_bag_kg' => $this->cellInt($row, $headerMap, ['Average Weight per Bag (kg) - for all variety(ies)']),
                    'total_production_bags' => $this->cellInt($row, $headerMap, ['Total Production (no. of bags) - for all variety(ies)']),
                    'avg_area_harvested_ha' => $this->cellFloat($row, $headerMap, ['Average Area Harvested (ha)']),
                    'seed_variety_planted'  => $this->nullIfEmpty($this->cellStr($row, $headerMap, ['Seed Variety Planted'])),
                    'seed_class'            => $seedClass,
                ];

                $unique = [
                    'ffrs'                 => $ffrs,
                    'seed_variety_claimed' => $seedVarietyClaimed,
                    'lot_series'           => $lotSeries,
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
        return view('rice_seed_distributions.create', [
            'seedVarietyClaimedOptions' => $this->seedVarietyClaimedOptions,
            'cropEstablishmentOptions'  => $this->cropEstablishmentOptions,
            'seedClassOptions'          => $this->seedClassOptions,
            'farmer_id' => $request->query('farmer_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'farmer_id' => ['nullable', 'exists:farmers,id'],

            'last_name'      => ['required', 'string', 'max:80'],
            'first_name'     => ['required', 'string', 'max:80'],
            'middle_name'    => ['nullable', 'string', 'max:80'],
            'ext_name'       => ['nullable', 'string', 'max:10'],
            'ffrs'           => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth'  => ['nullable', 'date'],
            'gender'         => ['nullable', Rule::in(['Male', 'Female', 'Other'])],

            'farm_location'     => ['required', 'string', 'max:255'],
            'farm_province'     => ['nullable', 'string', 'max:80'],
            'farm_municipality' => ['nullable', 'string', 'max:80'],
            'ecosystem'         => ['nullable', 'string', 'max:60'],
            'ecosystem_source'  => ['nullable', 'string', 'max:60'],

            'is_arb' => ['nullable', 'boolean'],
            'is_4ps' => ['nullable', 'boolean'],
            'is_ip'  => ['nullable', 'boolean'],
            'is_pwd' => ['nullable', 'boolean'],
            'is_sc'  => ['nullable', 'boolean'],
            'is_ofw' => ['nullable', 'boolean'],

            'seed_variety_claimed' => ['nullable', Rule::in($this->seedVarietyClaimedOptions)],
            'claimed_area_ha'       => ['nullable', 'numeric', 'min:0'],
            'claimed_seeds_kg'      => ['nullable', 'numeric', 'min:0'],
            'lot_series'            => ['nullable', 'string'],
            'crop_establishment'    => ['nullable', Rule::in($this->cropEstablishmentOptions)],
            'date_of_sowing_label'  => ['nullable', 'string', 'max:60'],

            'avg_weight_per_bag_kg'  => ['nullable', 'integer', 'min:0'],
            'total_production_bags'  => ['nullable', 'integer', 'min:0'],
            'avg_area_harvested_ha'  => ['nullable', 'numeric', 'min:0'],
            'seed_variety_planted'   => ['nullable', 'string', 'max:200'],
            'seed_class'             => ['nullable', Rule::in($this->seedClassOptions)],

            'farm_area_ha'  => ['nullable', 'numeric', 'min:0'],
            'kgs_received'  => ['required', 'numeric', 'min:0'],
            'date_received' => ['required', 'date'],
        ]);

        foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $k) {
            $data[$k] = (bool) ($data[$k] ?? false);
        }

        RiceSeedDistribution::create($data);

        return redirect()->route('rice-seed-distributions.index')
            ->with('success', 'Recipient record added successfully.');
    }

    public function edit(RiceSeedDistribution $riceSeedDistribution)
    {
        return view('rice_seed_distributions.edit', [
            'record' => $riceSeedDistribution,
            'seedVarietyClaimedOptions' => $this->seedVarietyClaimedOptions,
            'cropEstablishmentOptions'  => $this->cropEstablishmentOptions,
            'seedClassOptions'          => $this->seedClassOptions,
        ]);
    }

    public function update(Request $request, RiceSeedDistribution $riceSeedDistribution)
    {
        $data = $request->validate([
            'farmer_id' => ['nullable', 'exists:farmers,id'],

            'last_name'      => ['required', 'string', 'max:80'],
            'first_name'     => ['required', 'string', 'max:80'],
            'middle_name'    => ['nullable', 'string', 'max:80'],
            'ext_name'       => ['nullable', 'string', 'max:10'],
            'ffrs'           => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth'  => ['nullable', 'date'],
            'gender'         => ['nullable', Rule::in(['Male', 'Female', 'Other'])],

            'farm_location'     => ['required', 'string', 'max:255'],
            'farm_province'     => ['nullable', 'string', 'max:80'],
            'farm_municipality' => ['nullable', 'string', 'max:80'],
            'ecosystem'         => ['nullable', 'string', 'max:60'],
            'ecosystem_source'  => ['nullable', 'string', 'max:60'],

            'is_arb' => ['nullable', 'boolean'],
            'is_4ps' => ['nullable', 'boolean'],
            'is_ip'  => ['nullable', 'boolean'],
            'is_pwd' => ['nullable', 'boolean'],
            'is_sc'  => ['nullable', 'boolean'],
            'is_ofw' => ['nullable', 'boolean'],

            'seed_variety_claimed' => ['nullable', Rule::in($this->seedVarietyClaimedOptions)],
            'claimed_area_ha'       => ['nullable', 'numeric', 'min:0'],
            'claimed_seeds_kg'      => ['nullable', 'numeric', 'min:0'],
            'lot_series'            => ['nullable', 'string'],
            'crop_establishment'    => ['nullable', Rule::in($this->cropEstablishmentOptions)],
            'date_of_sowing_label'  => ['nullable', 'string', 'max:60'],

            'avg_weight_per_bag_kg'  => ['nullable', 'integer', 'min:0'],
            'total_production_bags'  => ['nullable', 'integer', 'min:0'],
            'avg_area_harvested_ha'  => ['nullable', 'numeric', 'min:0'],
            'seed_variety_planted'   => ['nullable', 'string', 'max:200'],
            'seed_class'             => ['nullable', Rule::in($this->seedClassOptions)],

            'farm_area_ha'  => ['nullable', 'numeric', 'min:0'],
            'kgs_received'  => ['required', 'numeric', 'min:0'],
            'date_received' => ['required', 'date'],
        ]);

        foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $k) {
            $data[$k] = (bool) ($data[$k] ?? false);
        }

        $riceSeedDistribution->update($data);

        return redirect()->route('rice-seed-distributions.index')
            ->with('success', 'Recipient record updated successfully.');
    }

    public function destroy(RiceSeedDistribution $riceSeedDistribution)
    {
        $riceSeedDistribution->delete();

        return redirect()->route('rice-seed-distributions.index')
            ->with('success', 'Recipient record deleted.');
    }

    public function export(Request $request)
    {
        $query = $this->buildFilteredQuery($request)
            ->orderBy('last_name')
            ->orderBy('first_name');

        $filename = 'rice_seed_distribution_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headings = [
            'No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'FFRS No.',
            'Date of Birth',
            'Location of Farm',
            'Gender',
            'ARB',
            '4Ps',
            'IP',
            'PWD',
            'SC',
            'OFW',
            'Farm Area (ha)',
            'No. of kgs Received',
            'Date Received',
        ];

        return response()->streamDownload(function () use ($query, $headings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);

            $i = 0;

            $query->chunk(1000, function ($rows) use (&$i, $out) {
                foreach ($rows as $r) {
                    $i++;

                    fputcsv($out, [
                        $i,
                        $r->last_name,
                        $r->first_name,
                        $r->middle_name,
                        $r->ffrs,
                        optional($r->date_of_birth)->format('Y-m-d'),
                        $r->farm_location,
                        $r->gender,
                        $r->is_arb ? 'Y' : 'N',
                        $r->is_4ps ? 'Y' : 'N',
                        $r->is_ip ? 'Y' : 'N',
                        $r->is_pwd ? 'Y' : 'N',
                        $r->is_sc ? 'Y' : 'N',
                        $r->is_ofw ? 'Y' : 'N',
                        $r->farm_area_ha,
                        $r->kgs_received,
                        optional($r->date_received)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $q = trim((string) $request->query('q', ''));

        $query = RiceSeedDistribution::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('last_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('middle_name', 'like', "%{$q}%")
                    ->orWhere('ffrs', 'like', "%{$q}%")
                    ->orWhere('farm_location', 'like', "%{$q}%");
            });
        }

        foreach (['last_name', 'first_name', 'middle_name', 'ffrs', 'farm_location'] as $col) {
            $val = trim((string) $request->query($col, ''));
            if ($val !== '') {
                $query->where($col, 'like', "%{$val}%");
            }
        }

        $gender = $request->query('gender');
        if (in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $query->where('gender', $gender);
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

    private function applyDateRange(Builder $query, string $column, $from, $to): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate($column, '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate($column, '<=', $to);
        }
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
        if ($raw === '') return null;

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
            if (!$col) continue;

            $v = $row[$col] ?? null;
            if ($v === null || $v === '') return null;

            try {
                if ($v instanceof \DateTimeInterface) {
                    return $v->format('Y-m-d');
                }

                if (is_numeric($v)) {
                    return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
                }

                $s = trim((string) $v);

                // If looks like m/d/Y or mm/dd/YYYY
                if (preg_match('~^\d{1,2}/\d{1,2}/\d{2,4}$~', $s)) {
                    $dt = \DateTime::createFromFormat('m/d/Y', $s) ?: \DateTime::createFromFormat('n/j/Y', $s);
                    if (!$dt) {
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
}