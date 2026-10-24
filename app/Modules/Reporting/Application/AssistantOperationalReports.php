<?php

namespace App\Modules\Reporting\Application;

use App\Models\AgriculturalMachinery;
use App\Models\Farmer;
use App\Models\FarmerRegistrySourceRow;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityProfile;
use App\Models\MunicipalityWebsite;
use App\Models\ParcelCropSeason;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Fixed, local aggregates. User text never chooses SQL, columns or relationships. */
final class AssistantOperationalReports
{
    public function __construct(private MunicipalityAccess $access) {}

    public function answer(User $user, string $topic, int $year): string
    {
        return match ($topic) {
            'registry-sources', 'outside-lgu', 'delisted' => $this->registry($user, $topic),
            'cooperatives' => $this->cooperatives($user),
            'machinery' => $this->machinery($user),
            'lgu-profile' => $this->profiles($user),
            'crops' => $this->crops($user, $year),
            default => throw new \InvalidArgumentException('Unknown operational report.'),
        };
    }

    private function registry(User $user, string $topic): string
    {
        // Workbook sections have the registry's read permission, not a new role.
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        if (! Schema::hasTable('farmer_registry_source_rows')) {
            return 'Retained workbook rows are unavailable in this installation. No row count can be established.';
        }
        $base = $this->access->scope(FarmerRegistrySourceRow::query(), $user);
        $labels = ['active' => 'Active source rows', 'outside_lgu' => 'Outside LGU rows', 'delisted' => 'Delisted rows'];
        $status = match ($topic) {
            'outside-lgu' => 'outside_lgu', 'delisted' => 'delisted', default => null,
        };
        if ($status !== null) {
            $base->where('record_status', $status);
            $labels = array_intersect_key($labels, [$status => true]);
        }
        $groups = (clone $base)->whereIn('record_status', array_keys($labels))
            ->selectRaw('record_status, COUNT(*) as row_count')->groupBy('record_status')->get()->keyBy('record_status');
        $lines = ['Retained farmer workbook · current source rows', 'Retained rows in this report: '.(clone $base)->count()];
        foreach ($labels as $key => $label) {
            $lines[] = $label.': '.(int) ($groups->get($key)?->row_count ?? 0);
        }
        if ($status === null) {
            $lines[] = 'Rows with an unrecognized status: '.(clone $base)->where(fn (Builder $q) => $q->whereNull('record_status')->orWhereNotIn('record_status', array_keys($labels)))->count();
        }
        if ($status !== 'delisted') {
            $outside = (clone $base)->where('record_status', 'outside_lgu');
            $linked = (clone $outside)->whereHas('farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'farmer_registry_source_rows.municipality_id'));
            $lines[] = 'Outside LGU rows with valid local farmer links: '.(clone $linked)->count();
            $lines[] = 'Distinct linked farmers in Outside LGU rows: '.(clone $linked)->distinct()->count('farmer_id');
            $lines[] = 'Outside LGU rows without valid local farmer links: '.((clone $outside)->count() - (clone $linked)->count());
        }
        $lines[] = 'These are retained workbook rows, not a count of people. Repeated parcel/commodity rows remain separate. Delisted rows do not establish an active farmer; an outside address does not change LGU ownership. Only imported source rows can be counted.';

        return implode("\n", $lines);
    }

    private function cooperatives(User $user): string
    {
        Gate::forUser($user)->authorize('viewAny', FarmersCooperative::class);
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        if (! Schema::hasTable('farmers_cooperatives') || ! Schema::hasTable('cooperative_farmer')) {
            return 'Cooperative membership records are unavailable in this installation. No membership count can be established.';
        }
        $base = $this->access->scope(FarmersCooperative::query(), $user);
        $members = $this->access->scope(Farmer::query(), $user)
            ->whereHas('cooperatives', fn (Builder $q) => $q->whereColumn('farmers_cooperatives.municipality_id', 'farmers.municipality_id'));
        $withMembers = (clone $base)->whereHas('farmers', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'farmers_cooperatives.municipality_id'));
        $total = (clone $base)->count();

        return "Cooperatives · current records\nCooperatives: {$total}\n"
            .'Cooperatives with valid local members: '.(clone $withMembers)->count()."\n"
            .'Cooperatives without valid local members: '.($total - (clone $withMembers)->count())."\n"
            .'Distinct member farmers: '.(clone $members)->count()."\n"
            .'A farmer in several cooperatives counts once. Cross-municipality or missing farmer links are excluded; membership is not assistance eligibility.';
    }

    private function machinery(User $user): string
    {
        Gate::forUser($user)->authorize('viewAny', AgriculturalMachinery::class);
        if (! Schema::hasTable('agricultural_machineries')) {
            return 'Machinery inventory is unavailable in this installation. No asset count can be established.';
        }
        $base = $this->access->scope(AgriculturalMachinery::query(), $user);
        $groups = (clone $base)->whereIn('availability_status', array_keys(AgriculturalMachinery::AVAILABILITY_STATUSES))
            ->selectRaw('availability_status, COUNT(*) as row_count')->groupBy('availability_status')->get()->keyBy('availability_status');
        $lines = ['Machinery · current inventory', 'Asset records: '.(clone $base)->count()];
        foreach (AgriculturalMachinery::AVAILABILITY_STATUSES as $key => $label) {
            $lines[] = $label.': '.(int) ($groups->get($key)?->row_count ?? 0);
        }
        $lines[] = 'Availability needing review: '.(clone $base)->where(fn (Builder $q) => $q->whereNull('availability_status')->orWhereNotIn('availability_status', array_keys(AgriculturalMachinery::AVAILABILITY_STATUSES)))->count();
        $lines[] = 'Maintenance attention: '.(clone $base)->needsMaintenanceAttention()->count();
        $lines[] = 'Maintenance attention means under maintenance, repair/unserviceable condition, or a due date within 30 days (including overdue dates), using the inventory rule. Availability reflects saved status, not a reservation or live equipment check.';

        return implode("\n", $lines);
    }

    private function profiles(User $user): string
    {
        Gate::forUser($user)->authorize('viewAny', MunicipalityProfile::class);
        if (! Schema::hasTable('municipality_profiles')) {
            return 'LGU office profiles are unavailable in this installation. No setup count can be established.';
        }
        $offices = $this->access->scopeMunicipalities(Municipality::query()->active(), $user)
            ->whereHas('supervisingProvince', fn (Builder $q) => $q->active());
        $profiles = $this->access->scope(MunicipalityProfile::query(), $user)->whereIn('municipality_id', (clone $offices)->select('municipalities.id'));
        $lines = ['LGU profile setup · current active offices', 'Active offices in your scope: '.(clone $offices)->count(),
            'Offices with saved profiles: '.(clone $profiles)->count(),
            'Offices awaiting a profile: '.((clone $offices)->count() - (clone $profiles)->count()),
            'Profiles with an LGU logo: '.(clone $profiles)->whereNotNull('logo_path')->where('logo_path', '!=', '')->count(),
            'Profiles with a printed signatory pair: '.(clone $profiles)->whereNotNull('signatory_name')->where('signatory_name', '!=', '')->whereNotNull('signatory_title')->where('signatory_title', '!=', '')->count()];
        if (Schema::hasTable('municipality_websites')) {
            // Website view permission delegates to the office profile policy.
            // Only approved publication counts are read after that authorization.
            $websites = $this->access->scope(MunicipalityWebsite::query(), $user);
            $lines[] = 'Published LGU pages: '.(clone $websites)->publiclyVisible()->count();
        } else {
            $lines[] = 'Public website settings: unavailable in this installation.';
        }
        $lines[] = 'A published page does not prove that its subdomain DNS or HTTPS is ready. Profile counts use active LGUs with active supervising provinces. Open LGU settings for authorized office details and corrections.';

        return implode("\n", $lines);
    }

    private function crops(User $user, int $year): string
    {
        Gate::forUser($user)->authorize('viewAny', ParcelCropSeason::class);
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        if (! Schema::hasTable('parcel_crop_seasons')) {
            return 'Seasonal crop records are unavailable in this installation. No crop count can be established.';
        }
        $base = $this->access->scope(ParcelCropSeason::query(), $user)->where('crop_year', $year);
        // A stored municipality alone cannot validate a corrupt cross-LGU plot link.
        $valid = (clone $base)->whereHas('plot.farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'parcel_crop_seasons.municipality_id'));
        $groups = (clone $valid)->whereIn('crop', array_keys(ParcelCropSeason::CROPS))
            ->selectRaw('crop, COUNT(*) as row_count')->groupBy('crop')->get()->keyBy('crop');
        $lines = ["Seasonal crop records · stated crop year {$year}", 'Valid parcel-season records: '.(clone $valid)->count(),
            'Distinct mapped parcels with crop records: '.(clone $valid)->distinct()->count('farm_plot_id'),
            'Records excluded for missing or cross-LGU parcel links: '.((clone $base)->count() - (clone $valid)->count())];
        foreach (ParcelCropSeason::CROPS as $key => $label) {
            if ($groups->has($key)) {
                $lines[] = $label.': '.(int) $groups->get($key)->row_count.' parcel-season records';
            }
        }
        $lines[] = 'Unrecognized crop classification: '.(clone $valid)->where(fn (Builder $q) => $q->whereNull('crop')->orWhereNotIn('crop', array_keys(ParcelCropSeason::CROPS)))->count();
        $lines[] = 'A parcel may appear in both seasons. These counts use saved parcel-season classifications, not individual planted sections, planted hectares, yield or harvested production. Missing crop records do not establish fallow land.';

        return implode("\n", $lines);
    }
}
