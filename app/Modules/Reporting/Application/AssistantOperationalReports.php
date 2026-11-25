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
    public function __construct(private MunicipalityAccess $access, private AssistantBarangayReport $barangays) {}

    public function answer(User $user, string $topic, int $year): string
    {
        return match ($topic) {
            'registry-sources', 'outside-lgu', 'delisted' => $this->registry($user, $topic),
            'cooperatives' => $this->cooperatives($user),
            'machinery' => $this->machinery($user),
            'lgu-profile' => $this->profiles($user),
            'crops' => $this->crops($user, $year),
            'barangays' => $this->barangays->answer($user, $year),
            default => throw new \InvalidArgumentException('Unknown operational report.'),
        };
    }

    /** One plain overview line per module, from the same authorized aggregates as the full reports. */
    public function overviewLine(User $user, string $topic, int $year): string
    {
        return match ($topic) {
            'registry-sources' => ($rows = $this->registryFigures($user, 'registry-sources')) === null
                ? 'Imported workbook rows: unavailable in this installation.'
                : 'Imported workbook rows: '.RecordReplyText::count($rows['total'], 'retained row').' ('.RecordReplyText::join([
                    RecordReplyText::number($rows['active']).' active', RecordReplyText::number($rows['outside_lgu']).' Outside LGU', RecordReplyText::number($rows['delisted']).' Delisted',
                ]).').',
            'crops' => ($crops = $this->cropFigures($user, $year)) === null
                ? "Seasonal crops in {$year}: unavailable in this installation."
                : "Seasonal crops in {$year}: ".RecordReplyText::count($crops['valid'], 'parcel-season record').' on '.RecordReplyText::count($crops['parcels'], 'mapped parcel').'.',
            'cooperatives' => ($coops = $this->cooperativeFigures($user)) === null
                ? 'Cooperatives: unavailable in this installation.'
                : 'Cooperatives: '.RecordReplyText::number($coops['total']).', with '.RecordReplyText::count($coops['members'], 'member farmer').'.',
            'machinery' => ($machines = $this->machineryFigures($user)) === null
                ? 'Machinery: unavailable in this installation.'
                : 'Machinery: '.RecordReplyText::count($machines['total'], 'asset').', '.RecordReplyText::number($machines['statuses']['available'] ?? 0).' available now.',
            default => throw new \InvalidArgumentException('Unknown overview line.'),
        };
    }

    private function registry(User $user, string $topic): string
    {
        $rows = $this->registryFigures($user, $topic);
        if ($rows === null) {
            return 'Imported farmer workbook rows are unavailable in this installation, so I can’t count them.';
        }
        $area = RecordReplyText::areaPossessive($user);
        $note = 'Workbook rows are imported lines, not people — one farmer can appear on several rows.';
        if ($topic === 'delisted') {
            return RecordReplyText::compose(RecordReplyText::sentence($area).' imported farmer workbook has '.RecordReplyText::count($rows['delisted'], 'Delisted row').'.',
                note: 'A Delisted row is kept for reference; it doesn’t mean the person has an active registration.');
        }
        $outside = RecordReplyText::count($rows['outside_lgu'], 'Outside LGU row');
        $links = RecordReplyText::number($rows['outside_linked']).' '.($rows['outside_linked'] === 1 ? 'is' : 'are').' linked to a farmer registered here ('
            .RecordReplyText::count($rows['outside_people'], 'distinct farmer').'), and '.RecordReplyText::number($rows['outside_unlinked']).' '.($rows['outside_unlinked'] === 1 ? 'is' : 'are').' not linked.';
        if ($topic === 'outside-lgu') {
            return RecordReplyText::compose(RecordReplyText::sentence($area).' imported farmer workbook has '.$outside.'.'.($rows['outside_lgu'] > 0 ? ' Of those, '.$links : ''),
                note: 'An outside address doesn’t change which LGU owns the record.');
        }

        return RecordReplyText::compose(
            RecordReplyText::sentence($area).' imported farmer workbook has '.RecordReplyText::count($rows['total'], 'retained row').': '
                .RecordReplyText::join([RecordReplyText::number($rows['active']).' active', RecordReplyText::number($rows['outside_lgu']).' Outside LGU', RecordReplyText::number($rows['delisted']).' Delisted']).'.',
            details: [
                $rows['unrecognized'] > 0 ? RecordReplyText::count($rows['unrecognized'], 'row has', 'rows have').' a status I don’t recognize and needs review.' : '',
                $rows['outside_lgu'] > 0 ? 'Of the '.$outside.', '.$links : '',
            ],
            note: $note.' Delisted rows don’t establish an active farmer.',
        );
    }

    /** @return array{total:int,active:int,outside_lgu:int,delisted:int,unrecognized:int,outside_linked:int,outside_people:int,outside_unlinked:int}|null */
    private function registryFigures(User $user, string $topic): ?array
    {
        // Workbook sections have the registry's read permission, not a new role.
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        if (! Schema::hasTable('farmer_registry_source_rows')) {
            return null;
        }
        $base = $this->access->scope(FarmerRegistrySourceRow::query(), $user);
        $known = ['active', 'outside_lgu', 'delisted'];
        $status = match ($topic) {
            'outside-lgu' => 'outside_lgu', 'delisted' => 'delisted', default => null,
        };
        if ($status !== null) {
            $base->where('record_status', $status);
        }
        $groups = (clone $base)->whereIn('record_status', $known)
            ->selectRaw('record_status, COUNT(*) as row_count')->groupBy('record_status')->get()->keyBy('record_status');
        $outside = (clone $base)->where('record_status', 'outside_lgu');
        $linked = (clone $outside)->whereHas('farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'farmer_registry_source_rows.municipality_id'));
        $outsideCount = (int) ($groups->get('outside_lgu')?->row_count ?? 0);
        $linkedCount = $status === 'delisted' ? 0 : (clone $linked)->count();

        return [
            'total' => (clone $base)->count(),
            'active' => (int) ($groups->get('active')?->row_count ?? 0),
            'outside_lgu' => $outsideCount,
            'delisted' => (int) ($groups->get('delisted')?->row_count ?? 0),
            'unrecognized' => $status === null ? (clone $base)->where(fn (Builder $q) => $q->whereNull('record_status')->orWhereNotIn('record_status', $known))->count() : 0,
            'outside_linked' => $linkedCount,
            'outside_people' => $status === 'delisted' ? 0 : (clone $linked)->distinct()->count('farmer_id'),
            'outside_unlinked' => $outsideCount - $linkedCount,
        ];
    }

    private function cooperatives(User $user): string
    {
        $figures = $this->cooperativeFigures($user);
        if ($figures === null) {
            return 'Cooperative membership records are unavailable in this installation, so I can’t count them.';
        }
        $without = $figures['total'] - $figures['with_members'];

        return RecordReplyText::compose(
            RecordReplyText::areaHas($user).' '.RecordReplyText::count($figures['total'], 'cooperative').' on record, with '.RecordReplyText::count($figures['members'], 'member farmer').' in total.'
                .($figures['total'] > 0 ? ' '.RecordReplyText::count($figures['with_members'], 'cooperative has', 'cooperatives have').' at least one member registered here; '
                    .RecordReplyText::number($without).' '.($without === 1 ? 'has' : 'have').' none yet.' : ''),
            note: 'A farmer in two cooperatives is counted once, members must be registered in the same LGU, and membership doesn’t decide assistance eligibility.',
        );
    }

    /** @return array{total:int,with_members:int,members:int}|null */
    private function cooperativeFigures(User $user): ?array
    {
        Gate::forUser($user)->authorize('viewAny', FarmersCooperative::class);
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        if (! Schema::hasTable('farmers_cooperatives') || ! Schema::hasTable('cooperative_farmer')) {
            return null;
        }
        $base = $this->access->scope(FarmersCooperative::query(), $user);
        $members = $this->access->scope(Farmer::query(), $user)
            ->whereHas('cooperatives', fn (Builder $q) => $q->whereColumn('farmers_cooperatives.municipality_id', 'farmers.municipality_id'));

        return [
            'total' => (clone $base)->count(),
            'with_members' => (clone $base)->whereHas('farmers', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'farmers_cooperatives.municipality_id'))->count(),
            'members' => (clone $members)->count(),
        ];
    }

    private function machinery(User $user): string
    {
        $figures = $this->machineryFigures($user);
        if ($figures === null) {
            return 'The machinery inventory is unavailable in this installation, so I can’t count assets.';
        }
        $parts = [];
        foreach (AgriculturalMachinery::AVAILABILITY_STATUSES as $key => $label) {
            if (($figures['statuses'][$key] ?? 0) > 0 || $key === 'available') {
                $parts[] = RecordReplyText::number($figures['statuses'][$key] ?? 0).' '.mb_strtolower($label);
            }
        }
        $lead = RecordReplyText::areaHas($user).' '.RecordReplyText::count($figures['total'], 'machinery asset').' on record'
            .($figures['total'] > 0 ? ': '.RecordReplyText::join($parts).'.' : '.');

        return RecordReplyText::compose($lead, details: [
            $figures['attention'] > 0 ? RecordReplyText::count($figures['attention'], 'asset needs', 'assets need').' maintenance attention (under maintenance, in repair or unserviceable condition, or due for maintenance within 30 days, including overdue).' : '',
            $figures['review'] > 0 ? RecordReplyText::count($figures['review'], 'asset has', 'assets have').' an availability status that needs review.' : '',
        ], note: 'Availability is the saved inventory status, not a live check or a reservation.');
    }

    /** @return array{total:int,statuses:array<string,int>,review:int,attention:int}|null */
    private function machineryFigures(User $user): ?array
    {
        Gate::forUser($user)->authorize('viewAny', AgriculturalMachinery::class);
        if (! Schema::hasTable('agricultural_machineries')) {
            return null;
        }
        $base = $this->access->scope(AgriculturalMachinery::query(), $user);
        $groups = (clone $base)->whereIn('availability_status', array_keys(AgriculturalMachinery::AVAILABILITY_STATUSES))
            ->selectRaw('availability_status, COUNT(*) as row_count')->groupBy('availability_status')->get()->keyBy('availability_status');
        $statuses = [];
        foreach (array_keys(AgriculturalMachinery::AVAILABILITY_STATUSES) as $key) {
            $statuses[$key] = (int) ($groups->get($key)?->row_count ?? 0);
        }

        return [
            'total' => (clone $base)->count(),
            'statuses' => $statuses,
            'review' => (clone $base)->where(fn (Builder $q) => $q->whereNull('availability_status')->orWhereNotIn('availability_status', array_keys(AgriculturalMachinery::AVAILABILITY_STATUSES)))->count(),
            'attention' => (clone $base)->needsMaintenanceAttention()->count(),
        ];
    }

    private function profiles(User $user): string
    {
        Gate::forUser($user)->authorize('viewAny', MunicipalityProfile::class);
        if (! Schema::hasTable('municipality_profiles')) {
            return 'LGU office profiles are unavailable in this installation, so I can’t count profile setup.';
        }
        $offices = $this->access->scopeMunicipalities(Municipality::query()->active(), $user)
            ->whereHas('supervisingProvince', fn (Builder $q) => $q->active());
        $profiles = $this->access->scope(MunicipalityProfile::query(), $user)->whereIn('municipality_id', (clone $offices)->select('municipalities.id'));
        $officeCount = (clone $offices)->count();
        $profileCount = (clone $profiles)->count();
        $awaiting = $officeCount - $profileCount;
        $items = [
            'Profiles with an LGU logo: '.RecordReplyText::number((clone $profiles)->whereNotNull('logo_path')->where('logo_path', '!=', '')->count()),
            'Profiles with a printed signatory name and title: '.RecordReplyText::number((clone $profiles)->whereNotNull('signatory_name')->where('signatory_name', '!=', '')->whereNotNull('signatory_title')->where('signatory_title', '!=', '')->count()),
        ];
        if (Schema::hasTable('municipality_websites')) {
            // Website view permission delegates to the office profile policy.
            // Only approved publication counts are read after that authorization.
            $items[] = 'Published LGU pages: '.RecordReplyText::number($this->access->scope(MunicipalityWebsite::query(), $user)->publiclyVisible()->count());
        } else {
            $items[] = 'Public website settings: unavailable in this installation';
        }

        return RecordReplyText::compose(
            'Your scope has '.RecordReplyText::count($officeCount, 'active LGU office').'. '.match (true) {
                $officeCount === 0 => 'There is no office profile to set up.',
                $officeCount === 1 => $profileCount === 1 ? 'It has a saved LGU profile.' : 'It doesn’t have a saved LGU profile yet.',
                $profileCount === 0 => 'None of them has a saved LGU profile yet.',
                $awaiting === 0 => 'All of them have a saved LGU profile.',
                default => RecordReplyText::number($profileCount).' '.($profileCount === 1 ? 'has' : 'have').' a saved LGU profile, and '.RecordReplyText::number($awaiting).' '.($awaiting === 1 ? 'is' : 'are').' still waiting for one.',
            },
            $items,
            note: 'A published page doesn’t confirm that its subdomain DNS or HTTPS is ready; open LGU settings for office details and corrections.',
        );
    }

    private function crops(User $user, int $year): string
    {
        $figures = $this->cropFigures($user, $year);
        if ($figures === null) {
            return 'Seasonal crop records are unavailable in this installation, so I can’t count them.';
        }
        if ($figures['valid'] === 0) {
            return RecordReplyText::compose('I found no seasonal crop records for '.RecordReplyText::area($user)." in {$year}.", details: [
                $figures['excluded'] > 0 ? RecordReplyText::count($figures['excluded'], 'record was', 'records were').' left out because the parcel link is missing or belongs to another LGU.' : '',
            ], note: 'Missing crop records don’t mean the land was left fallow.');
        }
        $items = [];
        foreach (ParcelCropSeason::CROPS as $key => $label) {
            if (($figures['crops'][$key] ?? 0) > 0) {
                $items[] = $label.': '.RecordReplyText::count($figures['crops'][$key], 'parcel-season');
            }
        }

        return RecordReplyText::compose(
            "In {$year}, ".RecordReplyText::area($user).' has '.RecordReplyText::count($figures['valid'], 'seasonal crop record').' on '.RecordReplyText::count($figures['parcels'], 'mapped parcel').'.',
            $items, 'By crop:', [
                $figures['unrecognized'] > 0 ? RecordReplyText::count($figures['unrecognized'], 'record has', 'records have').' a crop type I don’t recognize.' : '',
                $figures['excluded'] > 0 ? RecordReplyText::count($figures['excluded'], 'record was', 'records were').' left out because the parcel link is missing or belongs to another LGU.' : '',
            ],
            'A parcel can appear once per season; these are parcel-season entries, not individual planted sections, planted hectares or harvests.',
        );
    }

    /** @return array{valid:int,parcels:int,excluded:int,unrecognized:int,crops:array<string,int>}|null */
    private function cropFigures(User $user, int $year): ?array
    {
        Gate::forUser($user)->authorize('viewAny', ParcelCropSeason::class);
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        if (! Schema::hasTable('parcel_crop_seasons')) {
            return null;
        }
        $base = $this->access->scope(ParcelCropSeason::query(), $user)->where('crop_year', $year);
        // A stored municipality alone cannot validate a corrupt cross-LGU plot link.
        $valid = (clone $base)->whereHas('plot.farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'parcel_crop_seasons.municipality_id'));
        $groups = (clone $valid)->whereIn('crop', array_keys(ParcelCropSeason::CROPS))
            ->selectRaw('crop, COUNT(*) as row_count')->groupBy('crop')->get()->keyBy('crop');
        $validCount = (clone $valid)->count();

        return [
            'valid' => $validCount,
            'parcels' => (clone $valid)->distinct()->count('farm_plot_id'),
            'excluded' => (clone $base)->count() - $validCount,
            'unrecognized' => (clone $valid)->where(fn (Builder $q) => $q->whereNull('crop')->orWhereNotIn('crop', array_keys(ParcelCropSeason::CROPS)))->count(),
            'crops' => $groups->map(fn ($row) => (int) $row->row_count)->all(),
        ];
    }
}
