<?php

namespace App\Http\Controllers;

use App\Models\AntiRabiesVaccination;
use App\Models\AgriculturalMachinery;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate municipal account
        |--------------------------------------------------------------------------
        */

        if ($user->isMunicipalUser() && ! $user->municipality_id) {
            abort(403, 'Your account is not assigned to a municipality.');
        }

        $currentYear = (int) now()->year;

        /*
        |--------------------------------------------------------------------------
        | Municipality-scoped base queries
        |--------------------------------------------------------------------------
        |
        | Provincial roles:
        | - super_admin
        | - provincial_staff
        |
        | These users can see all municipalities.
        |
        | Municipal roles:
        | - municipal_head
        | - municipal_staff
        |
        | These users can only see their assigned municipality.
        |
        */

        $farmerQuery = $this->scopeByMunicipality(
            Farmer::query(),
            $user
        );

        $distributionQuery = $this->scopeByMunicipality(
            RiceSeedDistribution::query(),
            $user
        );

        $vaccinationQuery = $this->scopeByMunicipality(
            AntiRabiesVaccination::query(),
            $user
        );

        $backupQuery = $this->scopeByMunicipality(
            BackupFile::query(),
            $user
        );

        $cooperativeQuery = $this->scopeByMunicipality(
            FarmersCooperative::query(),
            $user
        );

        $machineryQuery = $this->scopeByMunicipality(
            AgriculturalMachinery::query(),
            $user
        );

        $farmPlotQuery = FarmPlot::query()
            ->whereHas('farmer', function (Builder $query) use ($user) {
                $this->scopeByMunicipality($query, $user);
            });

        $userQuery = $this->scopeByMunicipality(
            User::query(),
            $user
        );

        /*
        |--------------------------------------------------------------------------
        | Main statistics
        |--------------------------------------------------------------------------
        */

        $totalFarmers = (clone $farmerQuery)->count();

        $totalDistributionRecords = (clone $distributionQuery)->count();

        $totalKgsDistributed = (float) $this->kilogramReleases(clone $distributionQuery)
            ->sum('kgs_received');

        $totalFisheriesReleases = (clone $distributionQuery)
            ->whereIn(
                'input_category',
                RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
            )
            ->count();

        $totalFingerlingsReleased = (float) (clone $distributionQuery)
            ->where('input_category', 'fish_fingerlings')
            ->where('quantity_unit', 'piece')
            ->sum('kgs_received');

        $totalVaccinations = (clone $vaccinationQuery)->count();

        $totalBackupFiles = $user->isSuperAdmin()
            ? 0
            : (clone $backupQuery)->count();

        $totalCooperatives = (clone $cooperativeQuery)->count();

        $totalMachineries = (clone $machineryQuery)->count();

        $availableMachineries = (clone $machineryQuery)
            ->where('availability_status', 'available')
            ->count();

        $machineriesNeedingAttention = (clone $machineryQuery)
            ->needsMaintenanceAttention()
            ->count();

        $totalFarmPlots = (clone $farmPlotQuery)->count();

        $mappedFarmers = (clone $farmPlotQuery)
            ->distinct()
            ->count('farmer_id');

        $totalMappedArea = (float) (clone $farmPlotQuery)
            ->sum('area_ha');

        $unmappedFarmers = max($totalFarmers - $mappedFarmers, 0);

        $mappingCoverage = $totalFarmers > 0
            ? round(($mappedFarmers / $totalFarmers) * 100, 1)
            : 0.0;

        $farmersMissingFfrs = (clone $farmerQuery)
            ->where(function (Builder $query) {
                $query->whereNull('ffrs')
                    ->orWhere('ffrs', '');
            })
            ->count();

        $farmersMissingLocation = (clone $farmerQuery)
            ->where(function (Builder $query) {
                $query->whereNull('farm_location')
                    ->orWhere('farm_location', '')
                    ->orWhere('farm_location', 'UNKNOWN');
            })
            ->count();

        $totalAdmins = (clone $userQuery)
            ->whereIn('role', [
                User::ROLE_MUNICIPAL_HEAD,
                User::ROLE_MUNICIPAL_STAFF,
            ])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Latest activity
        |--------------------------------------------------------------------------
        */

        $latestBackupAt = $user->isSuperAdmin()
            ? null
            : (clone $backupQuery)->max('created_at');

        $latestPlotAt = (clone $farmPlotQuery)
            ->max('created_at');

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyDistributionRecords = (clone $distributionQuery)
            ->whereBetween('date_received', [$monthStart, $monthEnd])
            ->count();

        $monthlyKgsDistributed = (float) $this->kilogramReleases(clone $distributionQuery)
            ->whereBetween('date_received', [$monthStart, $monthEnd])
            ->sum('kgs_received');

        $monthlyFisheriesReleases = (clone $distributionQuery)
            ->whereIn(
                'input_category',
                RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
            )
            ->whereBetween('date_received', [$monthStart, $monthEnd])
            ->count();

        $monthlyVaccinations = (clone $vaccinationQuery)
            ->whereBetween('vaccination_date', [$monthStart, $monthEnd])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Monthly rice-distribution chart
        |--------------------------------------------------------------------------
        */

        $monthLabels = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];

        $riceMonthlyRaw = $this->kilogramReleases(clone $distributionQuery)
            ->whereYear('date_received', $currentYear)
            ->selectRaw(
                'MONTH(date_received) as month_number,
                 SUM(kgs_received) as total'
            )
            ->groupBy('month_number')
            ->orderBy('month_number')
            ->pluck('total', 'month_number');

        $riceMonthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $riceMonthlyData[] = (float) (
                $riceMonthlyRaw[$month] ?? 0
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Top seed varieties chart
        |--------------------------------------------------------------------------
        */

        $topSeedVarieties = $this->kilogramReleases(clone $distributionQuery)
            ->where(function (Builder $query) {
                $query->whereNull('input_category')
                    ->orWhere('input_category', 'rice_seed');
            })
            ->whereNotNull('seed_variety_claimed')
            ->where('seed_variety_claimed', '!=', '')
            ->selectRaw(
                'seed_variety_claimed,
                 SUM(kgs_received) as total_kgs'
            )
            ->groupBy('seed_variety_claimed')
            ->orderByDesc('total_kgs')
            ->limit(10)
            ->get();

        $charts = [
            'months' => $monthLabels,
            'rice_monthly' => $riceMonthlyData,

            'seed_variety_labels' => $topSeedVarieties
                ->pluck('seed_variety_claimed')
                ->values(),

            'seed_variety_values' => $topSeedVarieties
                ->pluck('total_kgs')
                ->map(fn ($value) => (float) $value)
                ->values(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Recent rice distributions
        |--------------------------------------------------------------------------
        */

        $recentRecipients = (clone $distributionQuery)
            ->orderByDesc('date_received')
            ->orderByDesc('id')
            ->take(5)
            ->get([
                'id',
                'municipality_id',
                'ffrs',
                'last_name',
                'first_name',
                'input_category',
                'seed_variety_claimed',
                'kgs_received',
                'quantity_unit',
                'date_received',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Recent vaccination records
        |--------------------------------------------------------------------------
        */

        $recentVaccinations = (clone $vaccinationQuery)
            ->orderByDesc('vaccination_date')
            ->orderByDesc('id')
            ->take(5)
            ->get([
                'id',
                'municipality_id',
                'owner_name',
                'pet_name',
                'barangay',
                'vaccination_date',
            ]);

        $recentPlots = (clone $farmPlotQuery)
            ->with('farmer:id,first_name,last_name,municipality_id')
            ->orderByDesc('created_at')
            ->take(5)
            ->get([
                'id',
                'farmer_id',
                'name',
                'area_ha',
                'created_at',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Final dashboard statistics
        |--------------------------------------------------------------------------
        */

        $stats = [
            'total_farmers' => $totalFarmers,

            'total_distribution_records' => $totalDistributionRecords,

            'total_kgs_distributed' => $totalKgsDistributed,

            'total_fisheries_releases' => $totalFisheriesReleases,

            'total_fingerlings_released' => $totalFingerlingsReleased,

            'total_vaccinations' => $totalVaccinations,

            'total_backup_files' => $totalBackupFiles,

            'total_cooperatives' => $totalCooperatives,

            'total_machineries' => $totalMachineries,

            'available_machineries' => $availableMachineries,

            'machineries_needing_attention' => $machineriesNeedingAttention,

            'total_farm_plots' => $totalFarmPlots,

            'mapped_farmers' => $mappedFarmers,

            'unmapped_farmers' => $unmappedFarmers,

            'total_mapped_area' => $totalMappedArea,

            'mapping_coverage' => $mappingCoverage,

            'farmers_missing_ffrs' => $farmersMissingFfrs,

            'farmers_missing_location' => $farmersMissingLocation,

            'monthly_distribution_records' => $monthlyDistributionRecords,

            'monthly_kgs_distributed' => $monthlyKgsDistributed,

            'monthly_fisheries_releases' => $monthlyFisheriesReleases,

            'monthly_vaccinations' => $monthlyVaccinations,

            'total_admins' => $totalAdmins,

            'latest_backup_at' => $latestBackupAt,

            'latest_plot_at' => $latestPlotAt,
        ];

        $municipalityStats = collect();
        $provinceOverview = [];

        if ($user->isSuperAdmin()) {
            [$municipalityStats, $provinceOverview] =
                $this->buildMunicipalityOverview();
        }

        return view('dashboard', compact(
            'stats',
            'charts',
            'recentRecipients',
            'recentVaccinations',
            'recentPlots',
            'municipalityStats',
            'provinceOverview',
            'currentYear'
        ));
    }

    /**
     * Build the province-level municipality comparison shown to super admins.
     *
     * The aggregates are calculated in grouped queries so the dashboard does
     * not issue one query per municipality.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array<string, int>}
     */
    private function buildMunicipalityOverview(): array
    {
        $municipalities = Municipality::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'province']);

        if ($municipalities->isEmpty()) {
            return [collect(), [
                'active_municipalities' => 0,
                'municipalities_with_head' => 0,
                'municipal_accounts' => 0,
                'mapped_municipalities' => 0,
                'municipalities_needing_attention' => 0,
                'unassigned_records' => 0,
            ]];
        }

        $municipalityIds = $municipalities->pluck('id');

        $farmerStats = Farmer::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->select('municipality_id')
            ->selectRaw('COUNT(*) as total_farmers')
            ->selectRaw(
                "SUM(CASE WHEN ffrs IS NULL OR ffrs = '' THEN 1 ELSE 0 END) as missing_ffrs"
            )
            ->selectRaw(
                "SUM(CASE WHEN farm_location IS NULL OR farm_location = '' OR UPPER(farm_location) = 'UNKNOWN' THEN 1 ELSE 0 END) as missing_location"
            )
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $plotStats = FarmPlot::query()
            ->join('farmers', 'farmers.id', '=', 'farm_plots.farmer_id')
            ->whereIn('farmers.municipality_id', $municipalityIds)
            ->select('farmers.municipality_id')
            ->selectRaw('COUNT(farm_plots.id) as total_plots')
            ->selectRaw('COUNT(DISTINCT farm_plots.farmer_id) as mapped_farmers')
            ->selectRaw('COALESCE(SUM(farm_plots.area_ha), 0) as mapped_area')
            ->groupBy('farmers.municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $distributionStats = RiceSeedDistribution::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->select('municipality_id')
            ->selectRaw('COUNT(*) as distribution_records')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN quantity_unit IS NULL OR quantity_unit = '' OR quantity_unit = 'kg' THEN kgs_received ELSE 0 END), 0) as total_kgs"
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN input_category IN ('.implode(',', array_fill(0, count(RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES), '?')).') THEN 1 ELSE 0 END), 0) as fisheries_records',
                RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN input_category = 'fish_fingerlings' AND quantity_unit = 'piece' THEN kgs_received ELSE 0 END), 0) as fingerlings_released"
            )
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $vaccinationStats = AntiRabiesVaccination::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->select('municipality_id')
            ->selectRaw('COUNT(*) as vaccinations')
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $cooperativeStats = FarmersCooperative::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->select('municipality_id')
            ->selectRaw('COUNT(*) as cooperatives')
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $machineryStats = AgriculturalMachinery::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->select('municipality_id')
            ->selectRaw('COUNT(*) as total_machinery')
            ->selectRaw(
                "SUM(CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END) as available_machinery"
            )
            ->selectRaw(
                "SUM(CASE WHEN availability_status = 'maintenance' OR condition_status IN ('needs_repair', 'unserviceable') OR (next_maintenance_date IS NOT NULL AND next_maintenance_date <= ?) THEN 1 ELSE 0 END) as machinery_attention",
                [now()->addDays(30)->toDateString()]
            )
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $staffStats = User::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->where('is_active', true)
            ->whereIn('role', User::MUNICIPAL_ROLES)
            ->select('municipality_id')
            ->selectRaw(
                'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as municipal_heads',
                [User::ROLE_MUNICIPAL_HEAD]
            )
            ->selectRaw(
                'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as municipal_staff',
                [User::ROLE_MUNICIPAL_STAFF]
            )
            ->groupBy('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        $municipalityStats = $municipalities->map(function (
            Municipality $municipality
        ) use (
            $farmerStats,
            $plotStats,
            $distributionStats,
            $vaccinationStats,
            $cooperativeStats,
            $machineryStats,
            $staffStats
        ) {
            $farmers = $farmerStats->get($municipality->id);
            $plots = $plotStats->get($municipality->id);
            $distributions = $distributionStats->get($municipality->id);
            $vaccinations = $vaccinationStats->get($municipality->id);
            $cooperatives = $cooperativeStats->get($municipality->id);
            $machinery = $machineryStats->get($municipality->id);
            $staff = $staffStats->get($municipality->id);

            $totalFarmers = (int) ($farmers->total_farmers ?? 0);
            $mappedFarmers = (int) ($plots->mapped_farmers ?? 0);
            $municipalHeads = (int) ($staff->municipal_heads ?? 0);
            $mappingCoverage = $totalFarmers > 0
                ? round(($mappedFarmers / $totalFarmers) * 100, 1)
                : 0.0;

            if ($municipalHeads === 0) {
                $status = 'missing_head';
                $statusLabel = 'Head needed';
                $attentionPriority = 4;
            } elseif ($totalFarmers === 0) {
                $status = 'no_records';
                $statusLabel = 'No farmer records';
                $attentionPriority = 3;
            } elseif ($mappingCoverage < 50) {
                $status = 'needs_mapping';
                $statusLabel = 'Mapping behind';
                $attentionPriority = 2;
            } else {
                $status = 'operational';
                $statusLabel = 'On track';
                $attentionPriority = 1;
            }

            return [
                'id' => (int) $municipality->id,
                'name' => $municipality->name,
                'total_farmers' => $totalFarmers,
                'mapped_farmers' => $mappedFarmers,
                'unmapped_farmers' => max($totalFarmers - $mappedFarmers, 0),
                'mapping_coverage' => $mappingCoverage,
                'total_plots' => (int) ($plots->total_plots ?? 0),
                'mapped_area' => (float) ($plots->mapped_area ?? 0),
                'distribution_records' => (int) ($distributions->distribution_records ?? 0),
                'total_kgs' => (float) ($distributions->total_kgs ?? 0),
                'fisheries_records' => (int) ($distributions->fisheries_records ?? 0),
                'fingerlings_released' => (float) ($distributions->fingerlings_released ?? 0),
                'vaccinations' => (int) ($vaccinations->vaccinations ?? 0),
                'cooperatives' => (int) ($cooperatives->cooperatives ?? 0),
                'total_machinery' => (int) ($machinery->total_machinery ?? 0),
                'available_machinery' => (int) ($machinery->available_machinery ?? 0),
                'machinery_attention' => (int) ($machinery->machinery_attention ?? 0),
                'municipal_heads' => $municipalHeads,
                'municipal_staff' => (int) ($staff->municipal_staff ?? 0),
                'missing_ffrs' => (int) ($farmers->missing_ffrs ?? 0),
                'missing_location' => (int) ($farmers->missing_location ?? 0),
                'status' => $status,
                'status_label' => $statusLabel,
                'attention_priority' => $attentionPriority,
            ];
        });

        $unassignedRecords = Farmer::query()->whereNull('municipality_id')->count()
            + RiceSeedDistribution::query()->whereNull('municipality_id')->count()
            + AntiRabiesVaccination::query()->whereNull('municipality_id')->count()
            + FarmersCooperative::query()->whereNull('municipality_id')->count()
            + AgriculturalMachinery::query()->whereNull('municipality_id')->count();

        return [$municipalityStats, [
            'active_municipalities' => $municipalityStats->count(),
            'municipalities_with_head' => $municipalityStats
                ->where('municipal_heads', '>', 0)
                ->count(),
            'municipal_accounts' => $municipalityStats
                ->sum(fn (array $item) =>
                    $item['municipal_heads'] + $item['municipal_staff']),
            'mapped_municipalities' => $municipalityStats
                ->where('mapped_farmers', '>', 0)
                ->count(),
            'municipalities_needing_attention' => $municipalityStats
                ->where('status', '!=', 'operational')
                ->count(),
            'unassigned_records' => $unassignedRecords,
        ]];
    }

    /**
     * Restrict a model query based on the logged-in user's municipality.
     *
     * Super admins and provincial staff can see all municipalities.
     * Municipal heads and municipal staff can only see their municipality.
     */
    private function scopeByMunicipality(
        Builder $query,
        User $user
    ): Builder {
        if ($user->isProvincialUser()) {
            return $query;
        }

        if (! $user->municipality_id) {
            abort(403, 'Your account is not assigned to a municipality.');
        }

        return $query->where(
            $query->getModel()->qualifyColumn('municipality_id'),
            $user->municipality_id
        );
    }

    private function kilogramReleases(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('quantity_unit')
                ->orWhere('quantity_unit', '')
                ->orWhere('quantity_unit', 'kg');
        });
    }
}
