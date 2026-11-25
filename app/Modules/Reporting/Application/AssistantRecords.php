<?php

namespace App\Modules\Reporting\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Modules\Assistant\Contracts\OfficeRecordReader;
use App\Modules\Farmers\Application\AssistantDataQuality;
use App\Modules\Farmers\Application\FarmerDataQuality;
use App\Modules\Farmers\Application\FarmerIdentifier;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Private, read-only answers. Nothing from this service goes to an AI provider.
 *
 * Each reply opens with plain sentences that answer the question with its key
 * figures, area and period, then a short breakdown and at most one note. The
 * wording is application-owned; units are never combined, missing values are
 * never shown as zero, and current registry figures are kept apart from yearly
 * records. Detailed limitations travel in the evidence disclosure.
 */
final class AssistantRecords implements OfficeRecordReader
{
    private const GROUPS = 10;

    public function __construct(private MunicipalityAccess $access, private AssistantDataQuality $quality, private AssistantOperationalReports $operations) {}

    /** @return array{answer:string,source:string,report:string,year:int,actions:array,links:array} */
    public function answer(User $user, string $question, int $year, ?int $farmerId = null): array
    {
        Gate::forUser($user)->authorize('use', OfficeRecordReader::class);
        if (! config('assistant.enabled')) {
            throw new AssistantUnavailable('The Assistant is not available yet. Ask your administrator to enable it.');
        }
        preg_match_all('/\b(?:19|20|21)\d{2}\b/', $question, $years);
        $requestedYears = array_unique($years[0]);
        if (count($requestedYears) > 1 || ($requestedYears && (int) reset($requestedYears) > 2100)) {
            return $this->result('Choose one reporting year between 1900 and 2100, then ask again.', 'guidance', $year);
        }
        $year = $requestedYears ? (int) reset($requestedYears) : $year;
        $topic = $this->topic($question, $farmerId);

        return $this->answerTask($user, $topic, $year, $question, $farmerId);
    }

    /** Resolves an application-owned task; raw text is used only for local name lookup. */
    public function answerTask(User $user, string $topic, int $year, string $search = '', ?int $farmerId = null, ?string $focus = null): array
    {
        Gate::forUser($user)->authorize('use', OfficeRecordReader::class);
        if (! config('assistant.enabled')) {
            throw new AssistantUnavailable('The Assistant is not available yet. Ask your administrator to enable it.');
        }
        if ($year < 1900 || $year > 2100) {
            return $this->result('Choose one reporting year between 1900 and 2100, then ask again.', 'guidance', $year);
        }
        if ($user->isProvincialVeterinaryOffice() && ! in_array($topic, ['summary', 'animal-health', 'guidance'], true)) {
            abort(403, 'Your veterinary account can view Animal Health reports only.');
        }
        // The focus is re-validated here against the model vocabularies; it is
        // never user text and an unknown key fails closed.
        abort_unless($focus === null || $this->validFocus($topic, $focus), 422, 'Choose a supported report.');
        try {
            $result = match ($topic) {
                'farmer-search' => $this->farmer($user, 'Find farmer '.$search, $year, null),
                'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests' => $this->farmer($user, $search, $year, $farmerId, $topic),
                'farmers' => $this->result($this->farmerSummary($user), 'farmers', $year, links: [$this->link('Open farmer registry', 'farmers.index')]),
                'assistance' => $this->result($this->assistance($user, $year, $focus), 'assistance', $year, links: [$this->link('Open assistance register', 'rice-seed-distributions.index')]),
                'harvests' => $this->result($this->harvests($user, $year, $focus), 'harvests', $year, links: [$this->link('Open harvest register', 'harvest-records.index')]),
                'animal-health' => $this->result($this->animals($user, $year), 'animal-health', $year, links: [$this->link('Open Animal Health', 'anti-rabies-vaccinations.index')]),
                'data-quality' => $this->qualityResult($user, $year, $focus),
                'registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile', 'crops', 'barangays' => $this->result(
                    $this->operations->answer($user, $topic, $year), $topic, $year, links: $this->reportLinks($topic)
                ),
                'summary' => $user->isProvincialVeterinaryOffice()
                    ? $this->result($this->animals($user, $year), 'animal-health', $year)
                    : $this->result($this->overview($user, $year), 'summary', $year),
                default => $this->result('I can answer questions about your farmers, farm-location (barangay) breakdowns, imported workbook sections, seasonal crops, assistance, harvests, Animal Health, cooperatives, machinery and LGU profile setup — for example “How many farmers do we have?” or “Show assistance for '.$year.'”. To find one person, type “Find farmer” followed by a name or AgriLGU ID. Reports cover your account’s permitted area and one reporting year.', 'guidance', $year),
            };

            return $result + ['evidence' => $this->evidence($user, $result['report'], $year)];
        } catch (QueryException $exception) {
            // SQL errors can contain protected values. Never log the SQL or bindings.
            Log::warning('Assistant records could not be read.', ['exception_type' => $exception::class]);
            throw new AssistantUnavailable('The records report could not load right now. Nothing was changed. Try again shortly.');
        }
    }

    private function topic(string $question, ?int $farmerId): string
    {
        if ($farmerId || preg_match('/\b(?:AGRI-F|PAIS-FRM)-\d+|^(?:find|search|show|hanapin)\s+(?:farmer|magsasaka)\s+\S/iu', trim($question))) {
            return 'farmer';
        }

        return match (true) {
            AssistantDataQuality::matches($question) => 'data-quality',
            (bool) preg_match('/\bdelisted\b/iu', $question) => 'delisted',
            (bool) preg_match('/\boutside[\s-]+lgu\b/iu', $question) => 'outside-lgu',
            (bool) preg_match('/\b(?:workbook|source rows|registry sections)\b/iu', $question) => 'registry-sources',
            (bool) preg_match('/\b(?:cooperatives?|memberships?)\b/iu', $question) => 'cooperatives',
            (bool) preg_match('/\b(?:machinery|equipment|inventory)\b/iu', $question) => 'machinery',
            (bool) preg_match('/\b(?:lgu profiles?|office profiles?|website setup)\b/iu', $question) => 'lgu-profile',
            (bool) preg_match('/\b(?:seasonal crops?|crop records?)\b/iu', $question) => 'crops',
            (bool) preg_match('/\b(harvest|harvests|production|ani)\b/iu', $question) => 'harvests',
            (bool) preg_match('/\b(assistance|seed|seeds|distribution|releases|binhi|ayuda)\b/iu', $question) => 'assistance',
            (bool) preg_match('/\b(animal|animals|vaccination|deworming|hayop)\b/iu', $question) => 'animal-health',
            (bool) preg_match('/\b(farmer|farmers|parcel|parcels|mapping|magsasaka|lupa)\b/iu', $question) => 'farmers',
            (bool) preg_match('/\b(summary|summarize|overview|report|reports|buod)\b/iu', $question) => 'summary',
            default => 'guidance',
        };
    }

    /** @return array{total:int,mapped:int,plots:int,area:float,missing_ffrs:int} */
    private function farmerFigures(User $user): array
    {
        $farmers = $this->query(Farmer::class, $user);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        $plots = FarmPlot::query()->whereIn('farmer_id', (clone $farmers)->select('farmers.id'));

        return [
            'total' => (clone $farmers)->count(),
            'mapped' => (clone $plots)->distinct()->count('farmer_id'),
            'plots' => (clone $plots)->count(),
            'area' => (float) (clone $plots)->sum('area_ha'),
            'missing_ffrs' => FarmerDataQuality::missingFfrs(clone $farmers)->count(),
        ];
    }

    private function farmerSummary(User $user): string
    {
        $figures = $this->farmerFigures($user);
        if ($figures['total'] === 0) {
            return RecordReplyText::areaHas($user).' no registered farmers in AgriLGU yet.';
        }
        $mapped = $figures['mapped'];
        $lead = RecordReplyText::areaHas($user).' '.RecordReplyText::count($figures['total'], 'registered farmer').'. '.match (true) {
            $figures['total'] === 1 => $mapped === 1 ? 'That farmer has a saved parcel boundary.' : 'That farmer has no saved parcel boundary yet.',
            $mapped === 0 => 'None of them has a saved parcel boundary yet.',
            $mapped === $figures['total'] => 'All of them have a saved parcel boundary.',
            default => RecordReplyText::number($mapped).' of them ('.RecordReplyText::percent($mapped, $figures['total']).') '.($mapped === 1 ? 'has' : 'have').' a saved parcel boundary.',
        };

        return RecordReplyText::compose($lead, [
            'Saved parcels: '.RecordReplyText::number($figures['plots']).', covering '.RecordReplyText::hectares($figures['area']).' of mapped area',
            'Farmers without a saved parcel: '.RecordReplyText::number($figures['total'] - $mapped),
            'Farmers without an FFRS reference: '.RecordReplyText::number($figures['missing_ffrs']),
        ], note: 'These are today’s registry figures, not a past-year snapshot, and saved boundaries are planning records, not proof of ownership.');
    }

    /**
     * Receipt-year releases. Only a valid same-municipality link represents a
     * known recipient; quantities stay grouped by input and unit.
     *
     * @param  list<string>|null  $categories
     * @return array{records:int,people:int,unlinked:int,groups:Collection,more:bool,incomplete:int,undated:int}
     */
    private function assistanceFigures(User $user, int $year, ?Farmer $farmer = null, ?array $categories = null): array
    {
        $base = $this->query(RiceSeedDistribution::class, $user);
        if ($farmer) {
            $base->where('farmer_id', $farmer->id)->where('municipality_id', $farmer->municipality_id);
        }
        if ($categories !== null) {
            $base->whereIn('input_category', $categories);
        }
        $dated = (clone $base)->where('date_received', '>=', "{$year}-01-01")->where('date_received', '<', ($year + 1).'-01-01');
        $linked = (clone $dated)->whereHas('farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'rice_seed_distributions.municipality_id'));
        $rows = (clone $dated)->whereNotNull('kgs_received')
            ->whereIn('input_category', array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))
            ->where(fn (Builder $q) => $q->whereIn('quantity_unit', array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))->orWhereNull('quantity_unit')->orWhere('quantity_unit', ''))
            ->selectRaw("input_category, COALESCE(NULLIF(quantity_unit, ''), 'kg') as unit, SUM(kgs_received) as quantity")
            ->groupBy('input_category', 'unit')->orderBy('input_category')->orderBy('unit')->limit(self::GROUPS + 1)->get();
        $records = (clone $dated)->count();

        return [
            'records' => $records,
            'people' => (clone $linked)->distinct()->count('farmer_id'),
            'unlinked' => $records - (clone $linked)->count(),
            'groups' => $rows->take(self::GROUPS)->map(fn ($row) => [
                'label' => RiceSeedDistribution::INPUT_CATEGORY_LABELS[$row->input_category],
                'quantity' => (float) $row->quantity, 'unit' => RiceSeedDistribution::QUANTITY_UNIT_LABELS[$row->unit],
            ]),
            'more' => $rows->count() > self::GROUPS,
            'incomplete' => (clone $dated)->where(fn (Builder $q) => $q->whereNull('kgs_received')->orWhereNull('input_category')->orWhereNotIn('input_category', array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('quantity_unit')->where('quantity_unit', '!=', '')->whereNotIn('quantity_unit', array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))))->count(),
            'undated' => (clone $base)->whereNull('date_received')->count(),
        ];
    }

    private function assistance(User $user, int $year, ?string $focus = null): string
    {
        $area = RecordReplyText::area($user);
        $subject = $this->assistanceSubject($focus);
        $figures = $this->assistanceFigures($user, $year, null, $subject['categories']);
        $records = $figures['records'];
        $people = RecordReplyText::count($figures['people'], 'farmer');
        $details = [
            $figures['unlinked'] > 0 ? RecordReplyText::count($figures['unlinked'], 'release has', 'releases have').' no farmer linked in '.$area.', so '
                .($figures['unlinked'] === 1 ? 'its recipient isn’t' : 'their recipients aren’t').' counted among the farmers.' : '',
            $figures['incomplete'] > 0 ? RecordReplyText::count($figures['incomplete'], 'release is', 'releases are').' missing a quantity, input type or unit and '.($figures['incomplete'] === 1 ? 'needs' : 'need').' review.' : '',
            $figures['undated'] > 0 ? RecordReplyText::count($figures['undated'], 'release has', 'releases have').' no received date, so '.($figures['undated'] === 1 ? 'it isn’t' : 'they aren’t').' counted in any year.' : '',
            $figures['more'] ? 'Showing the first 10 quantity groups; open the assistance register for the full breakdown.' : '',
        ];
        $countsNotNames = 'I give counts here, not names; the assistance register lists each recipient.';
        if ($focus === 'beneficiaries') {
            $lead = $records === 0
                ? "I found no assistance releases received in {$year} for {$area}, so no farmers are counted as recipients."
                : RecordReplyText::sentence($people).' in '.$area.' received assistance in '.$year.', through '.RecordReplyText::count($records, 'release').'.';

            return RecordReplyText::compose($lead, details: $details, note: $countsNotNames);
        }
        if ($subject['categories'] !== null) {
            $groups = $figures['groups'];
            $recipients = $figures['people'] > 0 ? ' to '.$people : '';
            $lead = match (true) {
                $records === 0 => "I found no {$subject['adjective']} releases received in {$year} for {$area}.",
                $groups->isEmpty() => "In {$year}, {$area} recorded ".RecordReplyText::count($records, $subject['adjective'].' release').$recipients.', but none has a usable quantity yet.',
                $groups->count() === 1 => "In {$year}, {$area} gave out ".$this->amount($groups->first()['quantity'], $groups->first()['unit']).' of '.mb_strtolower(trim(explode('/', $groups->first()['label'])[0]))
                    .' in '.RecordReplyText::count($records, 'release').$recipients.'.',
                default => "In {$year}, {$area} gave out {$subject['name']} in ".RecordReplyText::count($records, 'release').$recipients.'.',
            };
            $items = $groups->count() > 1 ? $groups->map(fn (array $group) => $group['label'].': '.$this->amount($group['quantity'], $group['unit']))->values()->all() : [];

            return RecordReplyText::compose($lead, $items, 'Totals by input and unit:', $details, $countsNotNames);
        }
        if ($records === 0) {
            return RecordReplyText::compose("I found no assistance releases received in {$year} for {$area}.", details: $details);
        }

        return RecordReplyText::compose(
            "In {$year}, {$area} recorded ".RecordReplyText::count($records, 'assistance release').($figures['people'] > 0 ? ', reaching '.$people : '').'.',
            $figures['groups']->map(fn (array $group) => $group['label'].': '.$this->amount($group['quantity'], $group['unit']))->values()->all(),
            'What was released:', $details,
            'Each input and unit is totaled separately (blank legacy units count as kg), and releases are not harvests.',
        );
    }

    /** @return array{categories:list<string>|null,name:string,adjective:string} */
    private function assistanceSubject(?string $focus): array
    {
        $labels = RiceSeedDistribution::INPUT_CATEGORY_LABELS;

        return match (true) {
            $focus === 'seed' => ['categories' => array_values(array_filter(array_keys($labels), fn (string $key) => str_ends_with($key, '_seed'))), 'name' => 'seeds', 'adjective' => 'seed'],
            $focus === 'fisheries' => ['categories' => RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES, 'name' => 'fisheries assistance', 'adjective' => 'fisheries assistance'],
            is_string($focus) && str_starts_with($focus, 'input:') => ['categories' => [substr($focus, 6)],
                'name' => $name = mb_strtolower(trim(explode('/', $labels[substr($focus, 6)])[0])), 'adjective' => $name],
            default => ['categories' => null, 'name' => 'assistance', 'adjective' => 'assistance'],
        };
    }

    /** @return array{records:int,groups:Collection,more:bool,incomplete:int,undated:int} */
    private function harvestFigures(User $user, int $year, ?Farmer $farmer = null, ?string $commodity = null): array
    {
        $base = $this->query(HarvestRecord::class, $user);
        if ($farmer) {
            $base->where('farmer_id', $farmer->id)->where('municipality_id', $farmer->municipality_id);
        }
        if ($commodity !== null) {
            $base->where('commodity', $commodity);
        }
        $period = (clone $base)->where('harvest_year', $year);
        $complete = (clone $period)->whereNotNull('quantity')->whereIn('commodity', array_keys(HarvestRecord::COMMODITY_LABELS))->whereIn('quantity_unit', array_keys(HarvestRecord::QUANTITY_UNIT_LABELS));
        $rows = (clone $complete)->selectRaw('commodity, quantity_unit, SUM(quantity) as quantity')->groupBy('commodity', 'quantity_unit')->orderBy('commodity')->orderBy('quantity_unit')->limit(self::GROUPS + 1)->get();
        $records = (clone $period)->count();

        return [
            'records' => $records,
            'groups' => $rows->take(self::GROUPS)->map(fn ($row) => [
                'label' => HarvestRecord::COMMODITY_LABELS[$row->commodity], 'quantity' => (float) $row->quantity, 'unit' => $this->harvestUnit($row->quantity_unit),
            ]),
            'more' => $rows->count() > self::GROUPS,
            'incomplete' => $records - (clone $complete)->count(),
            'undated' => (clone $base)->whereNull('harvest_year')->count(),
        ];
    }

    private function harvests(User $user, int $year, ?string $focus = null): string
    {
        $area = RecordReplyText::area($user);
        $commodity = is_string($focus) && str_starts_with($focus, 'commodity:') ? substr($focus, 10) : null;
        $figures = $this->harvestFigures($user, $year, null, $commodity);
        $records = $figures['records'];
        $groups = $figures['groups'];
        $details = [
            $figures['incomplete'] > 0 ? RecordReplyText::count($figures['incomplete'], 'record is', 'records are').' missing a quantity, crop type or unit, so '.($figures['incomplete'] === 1 ? 'it isn’t' : 'they aren’t').' in the totals.' : '',
            $figures['undated'] > 0 ? RecordReplyText::count($figures['undated'], 'record has', 'records have').' no harvest year, so '.($figures['undated'] === 1 ? 'it isn’t' : 'they aren’t').' counted in any year.' : '',
            $figures['more'] ? 'Showing the first 10 commodity/unit groups; open the harvest register for the full breakdown.' : '',
        ];
        $note = 'Units are kept separate and never converted, and a missing quantity is not a zero harvest.';
        if ($commodity !== null) {
            $name = mb_strtolower(trim(explode('/', HarvestRecord::COMMODITY_LABELS[$commodity])[0]));
            $lead = match (true) {
                $records === 0 => "I found no {$name} harvest records for {$area} in {$year}.",
                $groups->isEmpty() => "In {$year}, {$area} has ".RecordReplyText::count($records, $name.' harvest record').', but none has a usable quantity yet.',
                $groups->count() === 1 => "In {$year}, {$area} harvested ".$this->amount($groups->first()['quantity'], $groups->first()['unit'])." of {$name}, from ".RecordReplyText::count($records, 'harvest record').'.',
                default => "In {$year}, {$area} recorded ".RecordReplyText::count($records, $name.' harvest record').', saved in more than one unit, so the totals stay separate.',
            };
            $items = $groups->count() > 1 ? $groups->map(fn (array $group) => $this->amount($group['quantity'], $group['unit']))->values()->all() : [];

            return RecordReplyText::compose($lead, $items, details: $details, note: $note);
        }
        if ($records === 0) {
            return RecordReplyText::compose("I found no harvest records for {$area} with {$year} as the harvest year.", details: $details);
        }
        $crops = $groups->pluck('label')->unique()->map(fn (string $label) => mb_strtolower($label))->values()->all();

        return RecordReplyText::compose(
            "In {$year}, {$area} recorded ".RecordReplyText::count($records, 'harvest record').($crops === [] ? '.' : ', covering '.RecordReplyText::join($crops).'.'),
            $groups->map(fn (array $group) => $group['label'].': '.$this->amount($group['quantity'], $group['unit']))->values()->all(),
            'What was harvested:', $details, $note,
        );
    }

    /** @return array{records:int,animals:int,undated:int} */
    private function animalFigures(User $user, int $year): array
    {
        $base = $this->query(AntiRabiesVaccination::class, $user);
        $period = (clone $base)->where('vaccination_year', $year);

        return [
            'records' => (clone $period)->count(),
            // Existing one-animal fallback for missing or zero legacy counts.
            'animals' => (int) (clone $period)->selectRaw('COALESCE(SUM(CASE WHEN animal_count IS NULL OR animal_count < 1 THEN 1 ELSE animal_count END), 0) as total')->value('total'),
            'undated' => (clone $base)->whereNull('vaccination_year')->count(),
        ];
    }

    private function animals(User $user, int $year): string
    {
        $figures = $this->animalFigures($user, $year);
        $area = RecordReplyText::area($user);
        $details = [$figures['undated'] > 0 ? RecordReplyText::count($figures['undated'], 'record has', 'records have').' no service year, so '.($figures['undated'] === 1 ? 'it isn’t' : 'they aren’t').' counted in any year.' : ''];
        if ($figures['records'] === 0) {
            return RecordReplyText::compose("I found no Animal Health services recorded for {$area} in {$year}.", details: $details);
        }

        return RecordReplyText::compose(
            "In {$year}, {$area} recorded ".RecordReplyText::count($figures['records'], 'Animal Health service record').', covering '.RecordReplyText::count($figures['animals'], 'animal service').' in total.',
            details: $details,
            note: 'An animal served on two visits is counted twice, so this is not the number of unique animals, and it is not medical advice.',
        );
    }

    private function farmer(User $user, string $question, int $year, ?int $farmerId, string $detail = 'farmer'): array
    {
        $base = $this->query(Farmer::class, $user);
        if (! $farmerId && preg_match('/\b(?:AGRI-F|PAIS-FRM)-\d{1,16}\b/i', $question, $match)) {
            $farmerId = FarmerIdentifier::parse($match[0]);
        }
        $area = RecordReplyText::area($user);
        if (! $farmerId) {
            $search = trim(preg_replace('/^(?:find|search|show|hanapin)\s+(?:farmer|magsasaka)\s+/iu', '', trim($question)));
            if (mb_strlen($search) < 2 || mb_strlen($search) > 80) {
                return $this->result('Type “Find farmer” followed by 2–80 characters of a name or a complete AgriLGU ID, for example “Find farmer Juan Dela Cruz”.', 'farmer-search', $year);
            }
            $words = preg_split('/\s+/u', $search, 6);
            foreach ($words as $word) {
                // Literal LIKE tokens; wildcard-only input cannot enumerate records.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
                $base->where(fn (Builder $q) => $q->whereRaw("first_name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("middle_name LIKE ? ESCAPE '!'", [$term]));
            }
            $matches = $base->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(7)->get(['id', 'first_name', 'last_name', 'middle_name', 'municipality_id']);
            $actions = [];
            foreach ($matches->take(6) as $match) {
                Gate::forUser($user)->authorize('view', $match);
                $actions[] = ['label' => $this->name($match).' · '.$match->agri_gov_id, 'farmer_id' => $match->id];
            }

            return $this->result(match (true) {
                $matches->isEmpty() => "I couldn’t find a farmer with that name or ID in {$area}. Check the spelling, or use their complete AgriLGU ID.",
                $matches->count() === 1 => "I found 1 matching farmer in {$area}. Choose them below to see their summary.",
                $matches->count() > 6 => "I found more than six matching farmers in {$area}. Showing the first six matches; use a fuller name to narrow the search.",
                default => 'I found '.$matches->count()." matching farmers in {$area}. Choose one below to see their summary.",
            }, 'farmer-search', $year, $actions);
        }
        $farmer = $base->whereKey($farmerId)->first(['id', 'municipality_id', 'first_name', 'middle_name', 'last_name', 'farm_area_ha']);
        abort_unless($farmer, 404, 'That farmer is unavailable in your permitted area.');
        Gate::forUser($user)->authorize('view', $farmer);
        $who = $this->name($farmer).' ('.$farmer->agri_gov_id.')';
        $links = [['label' => 'Open farmer record', 'url' => route('farmers.records', $farmer)]];
        if ($detail === 'farmer-assistance') {
            $assistance = $this->assistanceFigures($user, $year, $farmer);

            return $this->result(RecordReplyText::compose($assistance['records'] === 0
                ? "I found no assistance received by {$who} in {$year}."
                : "In {$year}, {$who} received ".RecordReplyText::count($assistance['records'], 'assistance release').'.',
                $this->groupLines($assistance['groups']), 'What was released:', [
                    $assistance['incomplete'] > 0 ? RecordReplyText::count($assistance['incomplete'], 'release is', 'releases are').' missing a quantity, input type or unit.' : '',
                    $assistance['more'] ? 'Showing the first 10 quantity groups; open the farmer record for the full breakdown.' : '',
                ]), $detail, $year, links: $links) + ['_farmer_id' => $farmer->id];
        }
        if ($detail === 'farmer-harvests') {
            $harvests = $this->harvestFigures($user, $year, $farmer);

            return $this->result(RecordReplyText::compose($harvests['records'] === 0
                ? "I found no harvest records for {$who} in {$year}."
                : "In {$year}, {$who} has ".RecordReplyText::count($harvests['records'], 'harvest record').'.',
                $this->groupLines($harvests['groups']), 'What was harvested:', [
                    $harvests['incomplete'] > 0 ? RecordReplyText::count($harvests['incomplete'], 'record is', 'records are').' missing a quantity, crop type or unit.' : '',
                    $harvests['more'] ? 'Showing the first 10 commodity/unit groups; open the farmer record for the full breakdown.' : '',
                ], 'Units are kept separate and never converted.'), $detail, $year, links: $links) + ['_farmer_id' => $farmer->id];
        }
        $office = $farmer->municipality()->first(['id', 'name', 'province']);
        $plots = FarmPlot::query()->where('farmer_id', $farmer->id);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        $crops = $this->query(ParcelCropSeason::class, $user)->where('municipality_id', $farmer->municipality_id)->whereIn('farm_plot_id', (clone $plots)->select('id'))->where('crop_year', $year)
            ->whereIn('crop', array_keys(ParcelCropSeason::CROPS))->select('crop')->distinct()->limit(9)->get();
        $plotCount = (clone $plots)->count();
        $lead = $who.' is registered in '.($office ? mb_substr($office->name.', '.$office->province, 0, 180) : 'no assigned office').'. '
            .'They have '.RecordReplyText::count($plotCount, 'saved parcel').($plotCount > 0 ? ' covering '.RecordReplyText::hectares((float) (clone $plots)->sum('area_ha')) : '')
            .($farmer->farm_area_ha === null ? ', and no declared farm area is recorded.' : ', and a declared farm area of '.RecordReplyText::hectares((float) $farmer->farm_area_ha).'.');
        $items = ["Crops recorded for {$year}: ".($crops->isEmpty() ? 'none classified yet' : $crops->map(fn ($row) => ParcelCropSeason::CROPS[$row->crop])->implode(', '))];
        if ($detail !== 'farmer-parcels') {
            $assistance = $this->assistanceFigures($user, $year, $farmer);
            $harvests = $this->harvestFigures($user, $year, $farmer);
            $items[] = "Assistance received in {$year}: ".($assistance['records'] === 0 ? 'none recorded'
                : RecordReplyText::count($assistance['records'], 'release').($assistance['groups']->isEmpty() ? '' : ' — '.implode(', ', $this->groupLines($assistance['groups']))));
            $items[] = "Harvests in {$year}: ".($harvests['records'] === 0 ? 'none recorded'
                : RecordReplyText::count($harvests['records'], 'record').($harvests['groups']->isEmpty() ? '' : ' — '.implode(', ', $this->groupLines($harvests['groups']))));
        }

        return $this->result(RecordReplyText::compose($lead, $items, note: 'Saved boundaries are planning records, not proof of ownership; quantities keep their own units.'), $detail, $year, links: $links) + ['_farmer_id' => $farmer->id];
    }

    /** @return list<string> */
    private function groupLines(Collection $groups): array
    {
        return $groups->map(fn (array $group) => $group['label'].' '.$this->amount($group['quantity'], $group['unit']))->values()->all();
    }

    private function qualityResult(User $user, int $year, ?string $focus = null): array
    {
        $figures = $this->quality->figures($user);
        $links = $this->quality->links();
        $total = $figures['total'];
        $area = RecordReplyText::areaPossessive($user);
        $focused = ['unmapped' => ['without_parcels', 'no saved parcel yet', 'a saved parcel'],
            'missing-ffrs' => ['missing_ffrs', 'no FFRS reference recorded', 'an FFRS reference'],
            'missing-location' => ['missing_location', 'no farm location recorded', 'a farm location']];
        if (isset($focused[$focus])) {
            [$key, $gap, $present] = $focused[$focus];
            $count = $figures[$key];
            $lead = match (true) {
                $total === 0 => RecordReplyText::sentence($area).' registry has no farmer profiles yet, so there is nothing to check.',
                $count === 0 => 'All '.RecordReplyText::count($total, 'registered farmer').' in '.RecordReplyText::area($user).' have '.$present.'.',
                default => RecordReplyText::number($count).' of '.$area.' '.RecordReplyText::count($total, 'registered farmer').' ('.RecordReplyText::percent($count, $total).') '.($count === 1 ? 'has' : 'have').' '.$gap.'.',
            };
            // The matching review queue comes first; the others stay available.
            $ordered = [$links[$key], ...array_values(array_diff_key($links, [$key => true]))];

            return $this->result(RecordReplyText::compose($lead, note: 'This is a review list, not a fraud finding; a blank field is not a recorded “no”. Use the review link below to see them in the registry.'),
                'data-quality', $year, links: $ordered);
        }
        $gaps = array_filter([
            $figures['without_parcels'] > 0 ? RecordReplyText::number($figures['without_parcels']).' without a saved parcel' : '',
            $figures['missing_ffrs'] > 0 ? RecordReplyText::number($figures['missing_ffrs']).' without an FFRS reference' : '',
            $figures['missing_location'] > 0 ? RecordReplyText::number($figures['missing_location']).' without a farm location' : '',
        ]);
        $lead = match (true) {
            $total === 0 => RecordReplyText::sentence($area).' registry has no farmer profiles yet, so there is nothing to check.',
            $gaps === [] => 'Every farmer profile in '.$area.' registry ('.RecordReplyText::number($total).') has a saved parcel, an FFRS reference and a farm location.',
            default => 'Here is what needs attention in '.$area.' registry of '.RecordReplyText::count($total, 'farmer profile').': '.RecordReplyText::join($gaps).'.',
        };

        return $this->result(RecordReplyText::compose($lead, $total === 0 ? [] : [
            'Farmers without a saved parcel: '.RecordReplyText::number($figures['without_parcels']),
            'Farmers without an FFRS reference: '.RecordReplyText::number($figures['missing_ffrs']),
            'Farmers without a farm location: '.RecordReplyText::number($figures['missing_location']),
            'Saved plots without a drawn boundary: '.RecordReplyText::number($figures['plots_without_geometry']),
        ], note: 'These are review lists, not fraud findings; a blank field is not a recorded “no”.'), 'data-quality', $year, links: array_values($links));
    }

    private function overview(User $user, int $year): string
    {
        $farmers = $this->farmerFigures($user);
        $assistance = $this->assistanceFigures($user, $year);
        $harvests = $this->harvestFigures($user, $year);
        $animals = $this->animalFigures($user, $year);
        $items = [
            'Farmers: '.RecordReplyText::number($farmers['total']).' registered, '.RecordReplyText::number($farmers['mapped']).' with a saved parcel ('.RecordReplyText::percent($farmers['mapped'], $farmers['total']).'), '
                .RecordReplyText::number($farmers['missing_ffrs']).' without an FFRS reference.',
            "Assistance in {$year}: ".RecordReplyText::count($assistance['records'], 'release').' to '.RecordReplyText::count($assistance['people'], 'farmer').'.',
            "Harvests in {$year}: ".RecordReplyText::count($harvests['records'], 'record').($harvests['incomplete'] > 0 ? ' ('.RecordReplyText::number($harvests['incomplete']).' without a usable quantity)' : '').'.',
            "Animal Health in {$year}: ".RecordReplyText::count($animals['records'], 'service record').', covering '.RecordReplyText::count($animals['animals'], 'animal service').'.',
        ];
        foreach (['registry-sources', 'crops', 'cooperatives', 'machinery'] as $topic) {
            // Each dedicated report supplies its own breakdown and caveats.
            $items[] = $this->operations->overviewLine($user, $topic, $year);
        }

        return RecordReplyText::compose(
            'Here’s '.RecordReplyText::area($user).' at a glance: '.RecordReplyText::count($farmers['total'], 'registered farmer').' today, with '
                .RecordReplyText::count($assistance['records'], 'assistance release').' and '.RecordReplyText::count($harvests['records'], 'harvest record')." recorded for {$year}.",
            $items,
            note: "Farmer, workbook, cooperative and machinery figures are current; the rest are for {$year}. Ask about any one for its details — LGU profile setup is available separately.",
        );
    }

    /** Fixed vocabularies only; mirrors the Assistant's focus recognizer without importing it. */
    private function validFocus(string $topic, string $focus): bool
    {
        return match ($topic) {
            'assistance' => in_array($focus, ['beneficiaries', 'seed', 'fisheries'], true)
                || (str_starts_with($focus, 'input:') && isset(RiceSeedDistribution::INPUT_CATEGORY_LABELS[substr($focus, 6)]) && ! str_starts_with(substr($focus, 6), 'other_')),
            'harvests' => str_starts_with($focus, 'commodity:') && isset(HarvestRecord::COMMODITY_LABELS[substr($focus, 10)]) && $focus !== 'commodity:other',
            'data-quality' => in_array($focus, ['unmapped', 'missing-ffrs', 'missing-location'], true),
            default => false,
        };
    }

    /** "6 sacks", "1 sack", "35 kg". */
    private function amount(float $quantity, string $unit): string
    {
        $text = RecordReplyText::quantity($quantity);
        if ($text === '1' && ! in_array($unit, ['kg', 'g', 'L', 'mL'], true)) {
            $unit = match ($unit) {
                'boxes' => 'box', 'metric tons' => 'metric ton', default => rtrim($unit, 's'),
            };
        }

        return $text.' '.$unit;
    }

    private function harvestUnit(string $unit): string
    {
        return match ($unit) {
            'kg' => 'kg', 'ton' => 'metric tons',
            default => mb_strtolower(HarvestRecord::QUANTITY_UNIT_LABELS[$unit]),
        };
    }

    /** All source links and descriptions are application-owned, never generated by a provider. */
    private function reportLinks(string $report): array
    {
        return match ($report) {
            'registry-sources' => [
                ['label' => 'Open Outside LGU rows', 'url' => route('farmers.registry-sources.index', 'outside_lgu')],
                ['label' => 'Open Delisted rows', 'url' => route('farmers.registry-sources.index', 'delisted')],
            ],
            'outside-lgu', 'delisted' => [['label' => $report === 'outside-lgu' ? 'Open Outside LGU rows' : 'Open Delisted rows', 'url' => route('farmers.registry-sources.index', $report === 'outside-lgu' ? 'outside_lgu' : 'delisted')]],
            'cooperatives' => [$this->link('Open cooperatives', 'farmers-cooperatives.index')],
            'machinery' => [$this->link('Open machinery inventory', 'machinery-inventory.index')],
            'lgu-profile' => [$this->link('Open LGU profiles', 'settings.lgu.index')],
            'crops' => [$this->link('Open farmers and parcel records', 'farmers.index')],
            'barangays' => [
                $this->link('Open farmer registry', 'farmers.index'),
                ['label' => 'Review missing locations', 'url' => route('farmers.index', ['quality' => 'missing_location'])],
                $this->link('Open assistance register', 'rice-seed-distributions.index'),
                $this->link('Open harvest register', 'harvest-records.index'),
            ],
            default => [],
        };
    }

    private function evidence(User $user, string $report, int $year): array
    {
        $catalog = [
            'farmers' => ['Farmer registry and saved parcels', 'farmers.index', 'Current records; mapped areas are saved planning values.'],
            'assistance' => ['Assistance register', 'rice-seed-distributions.index', "Received dates in {$year}; categories and units stay separate."],
            'harvests' => ['Harvest register', 'harvest-records.index', "Stated harvest year {$year}; missing quantities are not zero."],
            'animal-health' => ['Animal Health register', 'anti-rabies-vaccinations.index', "Recorded service year {$year}; repeat animal visits may count again."],
            'registry-sources' => ['Retained farmer workbook', 'farmers.index', 'Imported source rows; repeated parcel/commodity rows are not distinct farmers.'],
            'cooperatives' => ['Cooperative membership register', 'farmers-cooperatives.index', 'Current cooperatives; only valid same-LGU farmer membership links count.'],
            'machinery' => ['Machinery inventory', 'machinery-inventory.index', 'Current saved availability and maintenance records.'],
            'lgu-profile' => ['LGU profile and website settings', 'settings.lgu.index', 'Active office configuration; page publication does not verify DNS/HTTPS.'],
            'crops' => ['Seasonal parcel crop records', 'farmers.index', "Stated crop year {$year}; valid same-LGU parcel-season records, not planted hectares."],
            'barangays' => ['Farmer registry farm locations', 'farmers.index', 'Current free-text farm location on each profile, grouped per municipality; not an official barangay list.'],
        ];
        $topics = match ($report) {
            'summary' => ['farmers', 'assistance', 'harvests', 'animal-health', 'registry-sources', 'crops', 'cooperatives', 'machinery'],
            'barangays' => ['barangays', 'assistance', 'harvests'],
            'farmer' => ['farmers', 'crops', 'assistance', 'harvests'],
            'farmer-parcels' => ['farmers', 'crops'],
            'farmer-assistance' => ['farmers', 'assistance'],
            'farmer-harvests' => ['farmers', 'harvests'],
            'farmer-search', 'data-quality' => ['farmers'],
            'outside-lgu', 'delisted' => ['registry-sources'],
            default => isset($catalog[$report]) ? [$report] : [],
        };
        $sources = [];
        $tables = [
            'farmers' => ['farmers', 'farm_plots'], 'assistance' => ['rice_seed_distributions'],
            'harvests' => ['harvest_records'], 'animal-health' => ['anti_rabies_vaccinations'],
            'registry-sources' => ['farmer_registry_source_rows'], 'cooperatives' => ['farmers_cooperatives', 'cooperative_farmer'],
            'machinery' => ['agricultural_machineries'], 'lgu-profile' => ['municipality_profiles', 'municipality_websites'],
            'crops' => ['parcel_crop_seasons'], 'barangays' => ['farmers', 'farm_plots'],
        ];
        foreach ($topics as $topic) {
            [$title, $route, $detail] = $catalog[$topic];
            $url = route($route);
            if (in_array($report, ['outside-lgu', 'delisted'], true)) {
                $url = $this->reportLinks($report)[0]['url'];
                $detail = ($report === 'outside-lgu' ? 'Outside LGU rows only. ' : 'Delisted rows only. ').$detail;
            }
            foreach ($tables[$topic] as $table) {
                if (! Schema::hasTable($table)) {
                    $detail = 'This module is wholly or partly unavailable in this installation. The reply identifies which count could not be established.';
                    break;
                }
            }
            $sources[] = ['title' => $title, 'url' => $url, 'detail' => $detail];
        }
        $period = match ($report) {
            'assistance', 'farmer-assistance' => "Received in {$year}",
            'harvests', 'farmer-harvests' => "Stated harvest year {$year}",
            'animal-health' => "Recorded service year {$year}",
            'crops' => "Stated crop year {$year}",
            'barangays' => "Current registry locations plus {$year} releases and harvest records",
            'summary', 'farmer', 'farmer-parcels' => "Current registry/setup plus explicitly labeled {$year} records",
            default => 'Current saved records; not a historical snapshot',
        };

        return [
            'checked_at' => now('UTC')->toIso8601String(), 'scope' => mb_substr($user->scopeLabel(), 0, 180), 'period' => $period,
            'sources' => $sources,
            'limitations' => ['Read directly from the authorized local registers for this reply; private record values are not sent to an AI provider.',
                'Counts describe saved records, not every event in the LGU. Missing or unavailable records cannot establish zero real-world activity.',
                'Separate queries may reflect ongoing staff edits; this is not a certified, transaction-frozen statistical report.'],
        ];
    }

    /** @param class-string<Model> $model */
    private function query(string $model, User $user): Builder
    {
        Gate::forUser($user)->authorize('viewAny', $model);

        return $this->access->scope($model::query(), $user);
    }

    private function name(Farmer $farmer): string
    {
        return mb_substr(trim(implode(' ', array_filter([$farmer->first_name, $farmer->middle_name, $farmer->last_name]))) ?: 'Farmer', 0, 150);
    }

    private function link(string $label, string $route): array
    {
        return ['label' => $label, 'url' => route($route)];
    }

    private function result(string $answer, string $report, int $year, array $actions = [], array $links = []): array
    {
        return ['answer' => $answer, 'source' => 'records', 'report' => $report, 'year' => $year, 'actions' => $actions, 'links' => $links];
    }
}
