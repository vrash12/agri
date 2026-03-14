<?php
// app/Http/Controllers/FarmerController.php

namespace App\Http\Controllers;

use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Models\RiceSeedDistribution;

class FarmerController extends Controller
{
    public function records(Request $request, Farmer $farmer)
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min($perPage, 100));

        // Only records linked to this farmer
        $query = RiceSeedDistribution::query()
            ->where('farmer_id', $farmer->id);

        // Optional simple search within this farmer's records
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('seed_variety_claimed', 'like', "%{$q}%")
                    ->orWhere('lot_series', 'like', "%{$q}%")
                    ->orWhere('date_of_sowing_label', 'like', "%{$q}%")
                    ->orWhere('seed_variety_planted', 'like', "%{$q}%");
            });
        }

        // Optional date range filter
        if ($request->filled('received_from')) {
            $query->whereDate('date_received', '>=', $request->query('received_from'));
        }
        if ($request->filled('received_to')) {
            $query->whereDate('date_received', '<=', $request->query('received_to'));
        }

        // ==========================================
        // STATS GENERATION
        // ==========================================
        $totalRecords = (clone $query)->count();
        $totalKgs     = (float) (clone $query)->sum('kgs_received');

        // 1. Most Claimed Seed Variety
        $topVarietyRow = (clone $query)
            ->select('seed_variety_claimed', DB::raw('count(*) as count'))
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('count')
            ->first();
        $favoriteVariety = $topVarietyRow ? $topVarietyRow->seed_variety_claimed : 'N/A';

        // 2. First and Last Received Dates
        $firstReceived = (clone $query)->min('date_received');
        $lastReceived  = (clone $query)->max('date_received');

        // ==========================================
        // GRAPH DATA AGGREGATION
        // ==========================================
        // Graph 1: Kgs Received Over Time (Line/Bar Chart Data)
        $kgsOverTime = (clone $query)
            ->selectRaw('DATE(date_received) as date, SUM(kgs_received) as total_kgs')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => (float) $item->total_kgs];
            });

        // Graph 2: Variety Claimed Distribution (Pie/Doughnut Chart Data)
        $varietyChartData = (clone $query)
            ->selectRaw('COALESCE(seed_variety_claimed, "Unknown") as variety, SUM(kgs_received) as total_kgs')
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
            'kgsOverTime',
            'varietyChartData'
        ));
    }

    public function index(Request $request)
    {
        $q = $request->query('q');

        // totals (count + sum kgs) based on current filters
        $totals = $this->baseQuery($request, false)
            ->selectRaw('COUNT(farmers.id) as total_farmers, SUM(COALESCE(a.total_kgs,0)) as total_kgs')
            ->first();

        $totalFarmers = (int) ($totals->total_farmers ?? 0);
        $totalKgs     = (float) ($totals->total_kgs ?? 0);

        // ==========================================
        // GLOBAL GRAPH DATA AGGREGATION
        // ==========================================
        // Graph 1: Gender Distribution (Pie Chart)
        $genderStats = $this->baseQuery($request, false)
            ->selectRaw('COALESCE(gender, "Unspecified") as gender_group, COUNT(farmers.id) as count')
            ->groupBy('gender_group')
            ->pluck('count', 'gender_group');

        // Graph 2: Top 10 Locations by Farmer Count (Bar Chart)
        $locationStats = $this->baseQuery($request, false)
            ->selectRaw('farm_location, COUNT(farmers.id) as count')
            ->whereNotNull('farm_location')
            ->where('farm_location', '!=', 'UNKNOWN')
            ->groupBy('farm_location')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'farm_location');

        // IMPORTANT: get ALL rows so DataTables can search/paginate client-side
        $farmers = $this->baseQuery($request, true)
            ->orderBy('farmers.last_name')
            ->orderBy('farmers.first_name')
            ->get();

        return view('farmers.index', compact(
            'farmers', 
            'q', 
            'totalFarmers', 
            'totalKgs',
            'genderStats',
            'locationStats'
        ));
    }

    public function showImport()
    {
        return view('farmers.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);

        $sheetsToRead = ['PARCEL LISTING', 'OUTSIDE LGU'];

        $agg = [];

        foreach ($sheetsToRead as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) continue;

            $rows = $sheet->toArray(null, true, true, true);
            if (count($rows) < 2) continue;

            $headerRow = array_shift($rows);
            $map = [];
            foreach ($headerRow as $col => $name) {
                $name = trim((string) $name);
                if ($name !== '') $map[$name] = $col;
            }

            foreach ($rows as $row) {
                $last  = $this->cellStr($row, $map, 'LAST NAME');
                $first = $this->cellStr($row, $map, 'FIRST NAME');

                if ($last === '' || $first === '') continue;

                $middle = $this->cellStr($row, $map, 'MIDDLE NAME');
                $ext    = $this->cellStr($row, $map, 'EXT NAME');

                $ffrs   = $this->cellStr($row, $map, 'FFRS System Generated No.');
                $rsbsa  = $this->cellStr($row, $map, 'LGU RSBSA Number');

                $dob = $this->cellDateYmd($row, $map, 'BIRTHDATE');

                $genderRaw = strtoupper($this->cellStr($row, $map, 'GENDER'));
                $gender = match ($genderRaw) {
                    'MALE' => 'Male',
                    'FEMALE' => 'Female',
                    default => null,
                };

                $farmLocation = $this->cellStr($row, $map, 'FARMER ADDRESS 1');
                $farmMunicipality = $this->cellStr($row, $map, 'FARMER ADDRESS 2');
                $farmProvince = $this->cellStr($row, $map, 'FARMER ADDRESS 3');

                $isArb = $this->cellYesNo($row, $map, 'ARB');
                $isIp  = $this->cellYesNo($row, $map, 'IF IP');

                $farmerKey = $ffrs !== ''
                    ? 'FFRS|' . $ffrs
                    : 'NOFFRS|' . $last . '|' . $first . '|' . $middle . '|' . $ext . '|' . ($dob ?? '');

                if (!isset($agg[$farmerKey])) {
                    $agg[$farmerKey] = [
                        'ffrs' => $ffrs !== '' ? $ffrs : null,
                        'rsbsa_no' => $rsbsa !== '' ? $rsbsa : null,

                        'last_name' => $last,
                        'first_name' => $first,
                        'middle_name' => $middle !== '' ? $middle : null,
                        'ext_name' => $ext !== '' ? $ext : null,

                        'date_of_birth' => $dob,
                        'gender' => $gender,
                        'contact_number' => null,

                        'farm_location' => $farmLocation !== '' ? $farmLocation : 'UNKNOWN',
                        'farm_municipality' => $farmMunicipality !== '' ? $farmMunicipality : null,
                        'farm_province' => $farmProvince !== '' ? $farmProvince : null,

                        'ecosystem' => null,
                        'ecosystem_source' => null,

                        'is_arb' => $isArb,
                        'is_ip' => $isIp,

                        'is_4ps' => false,
                        'is_pwd' => false,
                        'is_sc'  => false,
                        'is_ofw' => false,

                        'parcels' => [],
                    ];
                } else {
                    $agg[$farmerKey]['is_arb'] = $agg[$farmerKey]['is_arb'] || $isArb;
                    $agg[$farmerKey]['is_ip']  = $agg[$farmerKey]['is_ip']  || $isIp;

                    if (($agg[$farmerKey]['farm_location'] ?? '') === 'UNKNOWN' && $farmLocation !== '') {
                        $agg[$farmerKey]['farm_location'] = $farmLocation;
                    }
                    if (empty($agg[$farmerKey]['farm_municipality']) && $farmMunicipality !== '') {
                        $agg[$farmerKey]['farm_municipality'] = $farmMunicipality;
                    }
                    if (empty($agg[$farmerKey]['farm_province']) && $farmProvince !== '') {
                        $agg[$farmerKey]['farm_province'] = $farmProvince;
                    }
                }

                $parcelNo = $this->cellStr($row, $map, 'PARCEL NO');
                $parcelArea = $this->cellFloat($row, $map, 'PARCEL AREA');

                if ($parcelNo !== '' && $parcelArea !== null) {
                    $prev = $agg[$farmerKey]['parcels'][$parcelNo] ?? 0;
                    $agg[$farmerKey]['parcels'][$parcelNo] = max($prev, $parcelArea);
                }
            }
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (&$created, &$updated, $agg) {
            foreach ($agg as $data) {
                $farmArea = null;
                if (!empty($data['parcels'])) {
                    $farmArea = round(array_sum($data['parcels']), 2);
                }
                unset($data['parcels']);
                $data['farm_area_ha'] = $farmArea;

                if (!empty($data['ffrs'])) {
                    $unique = ['ffrs' => $data['ffrs']];
                } else {
                    $unique = [
                        'last_name' => $data['last_name'],
                        'first_name' => $data['first_name'],
                        'middle_name' => $data['middle_name'],
                        'ext_name' => $data['ext_name'],
                        'date_of_birth' => $data['date_of_birth'],
                    ];
                }

                $existing = Farmer::where($unique)->first();

                if ($existing) {
                    $existing->fill($data)->save();
                    $updated++;
                } else {
                    Farmer::create($data);
                    $created++;
                }
            }
        });

        return redirect()->route('farmers.index')
            ->with('success', "Farmers import done. Created: {$created}, Updated: {$updated}");
    }

    private function baseQuery(Request $request, bool $withSelect)
    {
        $aggSub = DB::table('rice_seed_distributions')
            ->selectRaw('farmer_id, COUNT(*) as records_count, SUM(kgs_received) as total_kgs, MAX(date_received) as last_received')
            ->groupBy('farmer_id');

        $query = Farmer::query()
            ->leftJoinSub($aggSub, 'a', function ($join) {
                $join->on('a.farmer_id', '=', 'farmers.id');
            });

        if ($withSelect) {
            $query->selectRaw('
                farmers.*,
                COALESCE(a.records_count, 0) as records_count,
                COALESCE(a.total_kgs, 0) as total_kgs,
                a.last_received as last_received
            ');
        }

        $this->applyFilters($query, $request);

        return $query;
    }

    private function applyFilters($query, Request $request): void
    {
        $q = $request->query('q');

        $query->when($q, function ($qq) use ($q) {
            $qq->where(function ($sub) use ($q) {
                $sub->where('farmers.last_name', 'like', "%{$q}%")
                    ->orWhere('farmers.first_name', 'like', "%{$q}%")
                    ->orWhere('farmers.middle_name', 'like', "%{$q}%")
                    ->orWhere('farmers.ffrs', 'like', "%{$q}%")
                    ->orWhere('farmers.rsbsa_no', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_location', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_municipality', 'like', "%{$q}%")
                    ->orWhere('farmers.farm_province', 'like', "%{$q}%");
            });
        });
    }

    private function cellStr(array $row, array $map, string $name): string
    {
        $col = $map[$name] ?? null;
        if (!$col) return '';
        return trim((string)($row[$col] ?? ''));
    }

    private function cellYesNo(array $row, array $map, string $name): bool
    {
        $v = strtoupper($this->cellStr($row, $map, $name));
        return in_array($v, ['YES','Y','1','TRUE'], true);
    }

    private function cellFloat(array $row, array $map, string $name): ?float
    {
        $col = $map[$name] ?? null;
        if (!$col) return null;

        $v = $row[$col] ?? null;
        if ($v === null || $v === '') return null;

        return is_numeric($v) ? (float)$v : null;
    }

    private function cellDateYmd(array $row, array $map, string $name): ?string
    {
        $col = $map[$name] ?? null;
        if (!$col) return null;

        $v = $row[$col] ?? null;
        if ($v === null || $v === '') return null;

        try {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            if (is_numeric($v)) return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');
            return (new \DateTime((string)$v))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}