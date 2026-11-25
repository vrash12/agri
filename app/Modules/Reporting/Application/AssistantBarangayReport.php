<?php

namespace App\Modules\Reporting\Application;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Modules\Farmers\Application\FarmerDataQuality;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Farm-location (barangay) breakdown for the Assistant.
 *
 * Groups use the free-text `farmers.farm_location` together with the numeric
 * `municipality_id`, so equal place names in different towns never merge.
 * Every figure is a scoped SQL aggregate over a constant number of queries.
 * Only one recorded spelling of each grouped location (collation-dependent
 * MIN) and counts leave the database; no farmer name, identifier, contact,
 * residence address or geometry is selected, and nothing here reaches an AI
 * provider.
 */
final class AssistantBarangayReport
{
    private const SHOWN = 10;

    /**
     * Trimmed, case-insensitive grouping key. Blank text and the importer's
     * UNKNOWN sentinel share the "not recorded" bucket, as in FarmerDataQuality.
     * Application-owned SQL only; no request value is interpolated.
     */
    private const KEY = "CASE WHEN TRIM(COALESCE(farmers.farm_location, '')) = ''"
        ." OR UPPER(TRIM(farmers.farm_location)) = '".FarmerDataQuality::UNKNOWN_LOCATION."'"
        ." THEN '' ELSE UPPER(TRIM(farmers.farm_location)) END";

    private const MAPPED = 'CASE WHEN EXISTS (SELECT 1 FROM farm_plots WHERE farm_plots.farmer_id = farmers.id) THEN 1 ELSE 0 END';

    public function __construct(private MunicipalityAccess $access) {}

    public function answer(User $user, int $year): string
    {
        foreach ([Farmer::class, FarmPlot::class, RiceSeedDistribution::class, HarvestRecord::class] as $model) {
            Gate::forUser($user)->authorize('viewAny', $model);
        }
        $farmers = $this->access->scope(Farmer::query(), $user);
        $totals = (clone $farmers)->toBase()->selectRaw('COUNT(*) as registered, '
            .'SUM(CASE WHEN '.self::KEY." = '' THEN 1 ELSE 0 END) as unrecorded, "
            .'SUM(CASE WHEN '.self::KEY." = '' THEN ".self::MAPPED.' ELSE 0 END) as unrecorded_mapped')->first();
        $recorded = (clone $farmers)->whereRaw(self::KEY." <> ''")
            ->selectRaw('farmers.municipality_id as municipality_id, '.self::KEY.' as location_key')
            ->groupBy('farmers.municipality_id', 'location_key');
        $locationCount = $recorded->toBase()->newQuery()->fromSub(clone $recorded, 'locations')->count();
        $top = (clone $recorded)->selectRaw('MIN(TRIM(farmers.farm_location)) as label, COUNT(*) as registered, SUM('.self::MAPPED.') as mapped')
            ->orderByDesc('registered')->orderBy('location_key')->orderBy('farmers.municipality_id')
            ->limit(self::SHOWN)->toBase()->get();

        $releases = Schema::hasTable('rice_seed_distributions') ? $this->linked(
            $this->access->scope(RiceSeedDistribution::query(), $user)
                ->where('rice_seed_distributions.date_received', '>=', "{$year}-01-01")
                ->where('rice_seed_distributions.date_received', '<', ($year + 1).'-01-01'),
            'rice_seed_distributions', $top, true) : null;
        $harvests = Schema::hasTable('harvest_records') ? $this->linked(
            $this->access->scope(HarvestRecord::query(), $user)->where('harvest_records.harvest_year', $year),
            'harvest_records', $top, false) : null;
        $offices = $user->canAccessAllMunicipalities() ? $this->offices($user, $top) : null;

        $registered = (int) ($totals->registered ?? 0);
        $unrecorded = (int) ($totals->unrecorded ?? 0);
        $area = RecordReplyText::area($user);
        if ($registered === 0) {
            return RecordReplyText::compose(RecordReplyText::sentence($area).' has no registered farmers yet, so there are no farm locations to break down.');
        }
        $lead = RecordReplyText::sentence(RecordReplyText::areaPossessive($user)).' '.RecordReplyText::count($registered, 'registered farmer').' '
            .($registered === 1 ? 'is' : 'are').' spread across '.RecordReplyText::count($locationCount, 'recorded farm location')
            .($offices === null ? '' : ' (each municipality counted separately)').'.';
        if ($top->isNotEmpty()) {
            $first = $top->first();
            $lead .= ' '.$this->label((string) $first->label).' has the most, with '.RecordReplyText::count((int) $first->registered, 'farmer').'.';
        }
        $items = [];
        foreach ($top as $row) {
            $key = $this->groupKey($row);
            $label = $this->label((string) $row->label);
            if ($offices !== null) {
                $label .= ' ('.mb_substr($offices[(string) $row->municipality_id] ?? 'Unassigned office', 0, 60).')';
            }
            $release = $releases['groups'][$key] ?? null;
            $items[] = $this->line($label, (int) $row->registered, (int) $row->mapped,
                $releases ? [(int) ($release->records ?? 0), (int) ($release->people ?? 0)] : null,
                $harvests ? (int) ($harvests['groups'][$key]->records ?? 0) : null, $year);
        }
        $details = [];
        if ($unrecorded > 0) {
            $details[] = $this->line('No farm location recorded', $unrecorded, (int) $totals->unrecorded_mapped,
                $releases ? [(int) $releases['totals']->unrecorded, (int) $releases['totals']->unrecorded_people] : null,
                $harvests ? (int) $harvests['totals']->unrecorded : null, $year);
        }
        if ($locationCount > $top->count()) {
            $remaining = $locationCount - $top->count();
            $details[] = $remaining.' more '.($remaining === 1 ? 'location is' : 'locations are').' not shown. Open the farmer registry to review every location.';
        }
        if ($releases === null) {
            $details[] = 'Assistance releases are unavailable in this installation, so no release count can be shown.';
        } elseif (($unplaced = (int) $releases['totals']->dated - (int) $releases['totals']->linked) > 0) {
            $details[] = RecordReplyText::count($unplaced, 'release', 'releases')." received in {$year} ".($unplaced === 1 ? 'has' : 'have').' no farmer linked in the same municipality, so '.($unplaced === 1 ? 'it isn’t' : 'they aren’t').' placed by location.';
        }
        if ($harvests === null) {
            $details[] = 'Harvest records are unavailable in this installation, so no harvest count can be shown.';
        } elseif (($unplaced = (int) $harvests['totals']->dated - (int) $harvests['totals']->linked) > 0) {
            $details[] = RecordReplyText::count($unplaced, 'harvest record')." for {$year} ".($unplaced === 1 ? 'has' : 'have').' no farmer linked in the same municipality, so '.($unplaced === 1 ? 'it isn’t' : 'they aren’t').' placed by location.';
        }

        return RecordReplyText::compose($lead, $items, "By farm location, with {$year} assistance and harvests:", $details,
            'Farm location is the free-text place saved on each farmer profile, not an official barangay list, so different spellings can appear separately. '
            ."Registry counts are current; releases use {$year} receipt dates and harvests the stated {$year} harvest year.");
    }

    /**
     * Records linked to an owning farmer in the same municipality. Rows with a
     * missing or cross-municipality link are counted only as "not placed".
     *
     * @return array{totals:object,groups:array<string,object>}
     */
    private function linked(Builder $period, string $table, Collection $top, bool $people): array
    {
        $join = fn (JoinClause $join) => $join->on('farmers.id', '=', "{$table}.farmer_id")
            ->on('farmers.municipality_id', '=', "{$table}.municipality_id");
        $totals = (clone $period)->toBase()->leftJoin('farmers', $join)->selectRaw('COUNT(*) as dated, COUNT(farmers.id) as linked, '
            .'SUM(CASE WHEN farmers.id IS NOT NULL AND '.self::KEY." = '' THEN 1 ELSE 0 END) as unrecorded, "
            .'COUNT(DISTINCT CASE WHEN farmers.id IS NOT NULL AND '.self::KEY." = '' THEN {$table}.farmer_id END) as unrecorded_people")->first();
        $groups = [];
        if ($top->isNotEmpty()) {
            $rows = (clone $period)->toBase()->join('farmers', $join)
                ->where(function ($query) use ($top): void {
                    // Only the displayed locations are aggregated, bounding the result.
                    foreach ($top as $row) {
                        $query->orWhere(function ($query) use ($row): void {
                            $query->where('farmers.municipality_id', $row->municipality_id)->whereRaw(self::KEY.' = ?', [$row->location_key]);
                        });
                    }
                })
                ->selectRaw('farmers.municipality_id as municipality_id, '.self::KEY.' as location_key, COUNT(*) as records'
                    .($people ? ", COUNT(DISTINCT {$table}.farmer_id) as people" : ''))
                ->groupBy('farmers.municipality_id', 'location_key')->get();
            foreach ($rows as $row) {
                $groups[$this->groupKey($row)] = $row;
            }
        }

        return ['totals' => $totals, 'groups' => $groups];
    }

    /** @return array<string,string> Names for the displayed rows only, within the account scope. */
    private function offices(User $user, Collection $top): array
    {
        $ids = $top->pluck('municipality_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return $this->access->scopeMunicipalities(Municipality::query(), $user)->whereIn('municipalities.id', $ids)
            ->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])->all();
    }

    /** @param array{0:int,1:int}|null $releases */
    private function line(string $label, int $farmers, int $mapped, ?array $releases, ?int $harvests, int $year): string
    {
        $activity = [];
        if ($releases !== null) {
            $activity[] = $releases[0] === 0 ? 'no releases' : RecordReplyText::count($releases[0], 'release').' to '.RecordReplyText::count($releases[1], 'farmer');
        }
        if ($harvests !== null) {
            $activity[] = $harvests === 0 ? 'no harvest records' : RecordReplyText::count($harvests, 'harvest record');
        }

        return $label.': '.RecordReplyText::count($farmers, 'farmer').', '.RecordReplyText::number($mapped).' with a saved parcel'
            .($activity === [] ? '' : " · {$year}: ".implode(', ', $activity));
    }

    private function groupKey(object $row): string
    {
        return $row->municipality_id.'|'.$row->location_key;
    }

    /** Free text may hold control characters or long imports; keep one bounded line. */
    private function label(string $text): string
    {
        $text = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $text));
        if ($text === '') {
            return 'Location text needing review';
        }

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 79).'…' : $text;
    }
}
