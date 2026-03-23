<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\User;
use App\Models\BackupFile;
use App\Models\RiceSeedDistribution;
use App\Models\AntiRabiesVaccination;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $currentYear = (int) now()->year;

        $totalFarmers             = Farmer::count();
        $totalDistributionRecords = RiceSeedDistribution::count();
        $totalKgsDistributed      = (float) RiceSeedDistribution::sum('kgs_received');
        $totalVaccinations        = AntiRabiesVaccination::count();
        $totalBackupFiles         = BackupFile::count();
        $totalAdmins              = User::where('role', 'admin')->count();

        $latestDistributionDate = RiceSeedDistribution::max('date_received');
        $latestVaccinationDate  = AntiRabiesVaccination::max('vaccination_date');
        $latestBackupAt         = BackupFile::max('created_at');

        $avgKgsPerRecipient = $totalDistributionRecords > 0
            ? ($totalKgsDistributed / $totalDistributionRecords)
            : 0;

        $avgFarmArea = (float) Farmer::whereNotNull('farm_area_ha')->avg('farm_area_ha');

        $topSeedVariety = RiceSeedDistribution::query()
            ->whereNotNull('seed_variety_claimed')
            ->where('seed_variety_claimed', '!=', '')
            ->selectRaw('seed_variety_claimed, SUM(kgs_received) as total_kgs')
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('total_kgs')
            ->first();

        $topFarmLocation = Farmer::query()
            ->whereNotNull('farm_location')
            ->where('farm_location', '!=', '')
            ->selectRaw('farm_location, COUNT(*) as total_count')
            ->groupBy('farm_location')
            ->orderByDesc('total_count')
            ->first();

        $topVaccinationBarangay = AntiRabiesVaccination::query()
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->selectRaw('barangay, COUNT(*) as total_count')
            ->groupBy('barangay')
            ->orderByDesc('total_count')
            ->first();

        $highlights = [
            'avg_kgs_per_recipient' => $avgKgsPerRecipient,
            'avg_farm_area'         => $avgFarmArea,
            'top_seed_variety'      => $topSeedVariety,
            'top_farm_location'     => $topFarmLocation,
            'top_vacc_barangay'     => $topVaccinationBarangay,
        ];

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $riceMonthlyRaw = RiceSeedDistribution::query()
            ->whereYear('date_received', $currentYear)
            ->selectRaw('MONTH(date_received) as m, SUM(kgs_received) as total')
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('total', 'm');

        $riceMonthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $riceMonthlyData[] = (float) ($riceMonthlyRaw[$m] ?? 0);
        }

        $topLocations = RiceSeedDistribution::query()
            ->whereNotNull('farm_location')
            ->where('farm_location', '!=', '')
            ->selectRaw('farm_location, SUM(kgs_received) as total_kgs')
            ->groupBy('farm_location')
            ->orderByDesc('total_kgs')
            ->limit(10)
            ->get();

        $topSeedVarieties = RiceSeedDistribution::query()
            ->whereNotNull('seed_variety_claimed')
            ->where('seed_variety_claimed', '!=', '')
            ->selectRaw('seed_variety_claimed, SUM(kgs_received) as total_kgs')
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('total_kgs')
            ->limit(10)
            ->get();

        $vaccinationsByBarangay = AntiRabiesVaccination::query()
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->selectRaw('barangay, COUNT(*) as total_count')
            ->groupBy('barangay')
            ->orderByDesc('total_count')
            ->limit(10)
            ->get();

        $farmersByGender = Farmer::query()
            ->selectRaw("COALESCE(NULLIF(gender,''), 'Unspecified') as gender_group, COUNT(*) as total_count")
            ->groupBy('gender_group')
            ->orderByDesc('total_count')
            ->get();

        $charts = [
            'months' => $monthLabels,
            'rice_monthly' => $riceMonthlyData,

            'top_locations_labels' => $topLocations->pluck('farm_location')->values(),
            'top_locations_values' => $topLocations->pluck('total_kgs')->map(fn ($v) => (float) $v)->values(),

            'seed_variety_labels' => $topSeedVarieties->pluck('seed_variety_claimed')->values(),
            'seed_variety_values' => $topSeedVarieties->pluck('total_kgs')->map(fn ($v) => (float) $v)->values(),

            'vacc_barangay_labels' => $vaccinationsByBarangay->pluck('barangay')->values(),
            'vacc_barangay_values' => $vaccinationsByBarangay->pluck('total_count')->map(fn ($v) => (int) $v)->values(),

            'gender_labels' => $farmersByGender->pluck('gender_group')->values(),
            'gender_values' => $farmersByGender->pluck('total_count')->map(fn ($v) => (int) $v)->values(),
        ];

        $recentRecipients = RiceSeedDistribution::query()
            ->orderByDesc('date_received')
            ->orderByDesc('id')
            ->take(5)
            ->get([
                'last_name',
                'first_name',
                'kgs_received',
                'date_received',
            ]);

        $recentVaccinations = AntiRabiesVaccination::query()
            ->orderByDesc('vaccination_date')
            ->orderByDesc('id')
            ->take(5)
            ->get([
                'owner_name',
                'pet_name',
                'barangay',
                'vaccination_date',
            ]);

        $recentBackups = BackupFile::query()
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->take(5)
            ->get([
                'id',
                'original_name',
                'folder',
                'size',
                'uploaded_by',
                'created_at',
            ]);

        $stats = [
            'total_farmers'              => $totalFarmers,
            'total_distribution_records' => $totalDistributionRecords,
            'total_kgs_distributed'      => $totalKgsDistributed,
            'total_vaccinations'         => $totalVaccinations,
            'total_backup_files'         => $totalBackupFiles,
            'total_admins'               => $totalAdmins,
            'latest_distribution_date'   => $latestDistributionDate,
            'latest_vaccination_date'    => $latestVaccinationDate,
            'latest_backup_at'           => $latestBackupAt,
        ];

        return view('dashboard', compact(
            'stats',
            'highlights',
            'charts',
            'recentRecipients',
            'recentVaccinations',
            'recentBackups',
            'currentYear'
        ));
    }
}