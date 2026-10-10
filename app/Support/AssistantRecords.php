<?php

namespace App\Support;

use App\Exceptions\AssistantUnavailable;
use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** Private, read-only answers. Nothing from this service goes to an AI provider. */
final class AssistantRecords
{
    public function __construct(private MunicipalityAccess $access) {}

    /** @return array{answer:string,source:string,report:string,year:int,actions:array,links:array} */
    public function answer(User $user, string $question, int $year, ?int $farmerId = null): array
    {
        Gate::forUser($user)->authorize('use', OfficeAssistant::class);
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
    public function answerTask(User $user, string $topic, int $year, string $search = '', ?int $farmerId = null): array
    {
        Gate::forUser($user)->authorize('use', OfficeAssistant::class);
        if (! config('assistant.enabled')) {
            throw new AssistantUnavailable('The Assistant is not available yet. Ask your administrator to enable it.');
        }
        if ($user->isProvincialVeterinaryOffice() && ! in_array($topic, ['summary', 'animal-health', 'guidance'], true)) {
            abort(403, 'Your veterinary account can view Animal Health reports only.');
        }
        try {
            return match ($topic) {
                'farmer-search' => $this->farmer($user, 'Find farmer '.$search, $year, null),
                'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests' => $this->farmer($user, $search, $year, $farmerId, $topic),
                'farmers' => $this->result($this->farmerSummary($user), 'farmers', $year, links: [$this->link('Open farmer registry', 'farmers.index')]),
                'assistance' => $this->result($this->assistance($user, $year), 'assistance', $year, links: [$this->link('Open assistance register', 'rice-seed-distributions.index')]),
                'harvests' => $this->result($this->harvests($user, $year), 'harvests', $year, links: [$this->link('Open harvest register', 'harvest-records.index')]),
                'animal-health' => $this->result($this->animals($user, $year), 'animal-health', $year, links: [$this->link('Open Animal Health', 'anti-rabies-vaccinations.index')]),
                'summary' => $user->isProvincialVeterinaryOffice()
                    ? $this->result($this->animals($user, $year), 'animal-health', $year)
                    : $this->result($this->farmerSummary($user)."\n\n".$this->assistance($user, $year)."\n\n".$this->harvests($user, $year), 'summary', $year),
                default => $this->result('I can show your farmer summary, assistance report, harvest report or Animal Health summary. To find one person, type “Find farmer” followed by a name or AgriLGU ID. Reports use your account’s permitted area and one reporting year.', 'guidance', $year),
            };
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
            (bool) preg_match('/\b(harvest|harvests|production|ani)\b/iu', $question) => 'harvests',
            (bool) preg_match('/\b(assistance|seed|seeds|distribution|releases|binhi|ayuda)\b/iu', $question) => 'assistance',
            (bool) preg_match('/\b(animal|animals|vaccination|deworming|hayop)\b/iu', $question) => 'animal-health',
            (bool) preg_match('/\b(farmer|farmers|parcel|parcels|mapping|magsasaka|lupa)\b/iu', $question) => 'farmers',
            (bool) preg_match('/\b(summary|summarize|overview|report|reports|buod)\b/iu', $question) => 'summary',
            default => 'guidance',
        };
    }

    private function farmerSummary(User $user): string
    {
        $farmers = $this->query(Farmer::class, $user);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        $total = (clone $farmers)->count();
        $plots = FarmPlot::query()->whereIn('farmer_id', (clone $farmers)->select('farmers.id'));
        $mapped = (clone $plots)->distinct()->count('farmer_id');
        $missing = FarmerDataQuality::missingFfrs(clone $farmers)->count();

        return "Farmer registry · current records\n".$this->scopeLabel($user)."\n"
            ."Registered farmers: {$total}\nFarmers with saved parcels: {$mapped}\n"
            .'Mapping coverage: '.($total ? number_format($mapped / $total * 100, 1).'%' : 'No farmers recorded')."\n"
            .'Saved parcels: '.(clone $plots)->count()."\nMapped area: ".number_format((float) (clone $plots)->sum('area_ha'), 4)." ha\n"
            ."Missing FFRS reference: {$missing}\nRegistry/mapping figures are current, not a historical snapshot. Saved boundaries are planning records, not proof of ownership.";
    }

    private function assistance(User $user, int $year, ?Farmer $farmer = null): string
    {
        $base = $this->query(RiceSeedDistribution::class, $user);
        if ($farmer) {
            $base->where('farmer_id', $farmer->id)->where('municipality_id', $farmer->municipality_id);
        }
        $dated = (clone $base)->where('date_received', '>=', "{$year}-01-01")->where('date_received', '<', ($year + 1).'-01-01');
        // Only a valid same-municipality link represents a known beneficiary.
        $linked = (clone $dated)->whereHas('farmer', fn (Builder $q) => $q->whereColumn('farmers.municipality_id', 'rice_seed_distributions.municipality_id'));
        $rows = (clone $dated)->whereNotNull('kgs_received')
            ->whereIn('input_category', array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))
            ->where(fn (Builder $q) => $q->whereIn('quantity_unit', array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))->orWhereNull('quantity_unit')->orWhere('quantity_unit', ''))
            ->selectRaw("input_category, COALESCE(NULLIF(quantity_unit, ''), 'kg') as unit, SUM(kgs_received) as quantity")
            ->groupBy('input_category', 'unit')->orderBy('input_category')->orderBy('unit')->limit(11)->get();
        $lines = ["Assistance · received in {$year}", $this->scopeLabel($user), 'Release records: '.(clone $dated)->count(),
            'Unique linked beneficiaries: '.(clone $linked)->distinct()->count('farmer_id'),
            'Releases without a valid same-municipality farmer link: '.((clone $dated)->count() - (clone $linked)->count())];
        foreach ($rows->take(10) as $row) {
            $lines[] = RiceSeedDistribution::INPUT_CATEGORY_LABELS[$row->input_category].': '.number_format((float) $row->quantity, 3).' '.RiceSeedDistribution::QUANTITY_UNIT_LABELS[$row->unit];
        }
        if ($rows->count() > 10) {
            $lines[] = 'Showing the first 10 quantity groups; open the register for the full breakdown.';
        }
        $incomplete = (clone $dated)->where(fn (Builder $q) => $q->whereNull('kgs_received')->orWhereNull('input_category')->orWhereNotIn('input_category', array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))
            ->orWhere(fn (Builder $q) => $q->whereNotNull('quantity_unit')->where('quantity_unit', '!=', '')->whereNotIn('quantity_unit', array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))))->count();
        $lines[] = "Quantity/category needing review: {$incomplete}";
        $lines[] = 'Undated releases excluded from this year: '.(clone $base)->whereNull('date_received')->count();
        $lines[] = 'Quantities stay separate by input and unit. Legacy blank release units mean kg. Releases are not harvest production.';

        return implode("\n", $lines);
    }

    private function harvests(User $user, int $year, ?Farmer $farmer = null): string
    {
        $base = $this->query(HarvestRecord::class, $user);
        if ($farmer) {
            $base->where('farmer_id', $farmer->id)->where('municipality_id', $farmer->municipality_id);
        }
        $period = (clone $base)->where('harvest_year', $year);
        $complete = (clone $period)->whereNotNull('quantity')->whereIn('commodity', array_keys(HarvestRecord::COMMODITY_LABELS))->whereIn('quantity_unit', array_keys(HarvestRecord::QUANTITY_UNIT_LABELS));
        $rows = (clone $complete)->selectRaw('commodity, quantity_unit, SUM(quantity) as quantity')->groupBy('commodity', 'quantity_unit')->orderBy('commodity')->orderBy('quantity_unit')->limit(11)->get();
        $lines = ["Harvests · stated crop year {$year}", $this->scopeLabel($user), 'Harvest records: '.(clone $period)->count()];
        foreach ($rows->take(10) as $row) {
            $lines[] = HarvestRecord::COMMODITY_LABELS[$row->commodity].': '.number_format((float) $row->quantity, 3).' '.HarvestRecord::QUANTITY_UNIT_LABELS[$row->quantity_unit];
        }
        if ($rows->count() > 10) {
            $lines[] = 'Showing the first 10 commodity/unit groups; open the register for the full breakdown.';
        }
        $lines[] = 'Records with incomplete quantity/classification: '.((clone $period)->count() - (clone $complete)->count());
        $lines[] = 'Records with no stated harvest year excluded: '.(clone $base)->whereNull('harvest_year')->count();
        $lines[] = 'Units and commodities are not combined or converted. A missing value is not a recorded zero. No crop-section linkage or yield estimate is inferred.';

        return implode("\n", $lines);
    }

    private function animals(User $user, int $year): string
    {
        $base = $this->query(AntiRabiesVaccination::class, $user);
        $period = (clone $base)->where('vaccination_year', $year);

        return "Animal Health · recorded service year {$year}\n".$this->scopeLabel($user)."\nService records: ".(clone $period)->count()
            ."\nAnimal service instances: ".(int) (clone $period)->selectRaw('COALESCE(SUM(CASE WHEN animal_count IS NULL OR animal_count < 1 THEN 1 ELSE animal_count END), 0) as total')->value('total')
            ."\nRecords missing a service year: ".(clone $base)->whereNull('vaccination_year')->count()
            ."\nRepeat visits can count the same animal more than once. These are recorded services, not the number of unique animals or medical advice.";
    }

    private function farmer(User $user, string $question, int $year, ?int $farmerId, string $detail = 'farmer'): array
    {
        $base = $this->query(Farmer::class, $user);
        if (! $farmerId && preg_match('/\b(?:AGRI-F|PAIS-FRM)-\d{1,16}\b/i', $question, $match)) {
            $farmerId = FarmerIdentifier::parse($match[0]);
        }
        if (! $farmerId) {
            $search = trim(preg_replace('/^(?:find|search|show|hanapin)\s+(?:farmer|magsasaka)\s+/iu', '', trim($question)));
            if (mb_strlen($search) < 2 || mb_strlen($search) > 80) {
                return $this->result('Type “Find farmer” followed by 2–80 characters of a name or a complete AgriLGU ID.', 'farmer-search', $year);
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

            return $this->result($matches->isEmpty() ? 'No matching farmer is available in your permitted area. Check the name or use a complete AgriLGU ID.'
                : 'Choose a farmer below to see their recorded summary.'.($matches->count() > 6 ? ' Showing the first six matches; use a fuller name to narrow the search.' : ''), 'farmer-search', $year, $actions);
        }
        $farmer = $base->whereKey($farmerId)->first(['id', 'municipality_id', 'first_name', 'middle_name', 'last_name', 'farm_area_ha']);
        abort_unless($farmer, 404, 'That farmer is unavailable in your permitted area.');
        Gate::forUser($user)->authorize('view', $farmer);
        $heading = $this->name($farmer).' · '.$farmer->agri_gov_id."\n".$this->scopeLabel($user);
        if (in_array($detail, ['farmer-assistance', 'farmer-harvests'], true)) {
            $text = $heading."\n\n".($detail === 'farmer-assistance' ? $this->assistance($user, $year, $farmer) : $this->harvests($user, $year, $farmer));

            return $this->result($text, $detail, $year, links: [['label' => 'Open farmer record', 'url' => route('farmers.records', $farmer)]]) + ['_farmer_id' => $farmer->id];
        }
        $office = $farmer->municipality()->first(['id', 'name', 'province']);
        $plots = FarmPlot::query()->where('farmer_id', $farmer->id);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);
        $crops = $this->query(ParcelCropSeason::class, $user)->where('municipality_id', $farmer->municipality_id)->whereIn('farm_plot_id', (clone $plots)->select('id'))->where('crop_year', $year)
            ->whereIn('crop', array_keys(ParcelCropSeason::CROPS))->select('crop')->distinct()->limit(9)->get();
        $text = $heading
            ."\nRegistry office: ".($office ? mb_substr($office->name.', '.$office->province, 0, 180) : 'Not assigned')
            ."\nDeclared farm area: ".($farmer->farm_area_ha === null ? 'Not recorded' : number_format((float) $farmer->farm_area_ha, 2).' ha')
            ."\nSaved parcels: ".(clone $plots)->count()."\nMapped area: ".number_format((float) (clone $plots)->sum('area_ha'), 4).' ha'
            ."\nRecorded crops in {$year}: ".($crops->isEmpty() ? 'No classified seasonal crops recorded' : $crops->map(fn ($row) => ParcelCropSeason::CROPS[$row->crop])->implode(', '));
        if ($detail !== 'farmer-parcels') {
            $text .= "\n\n".$this->assistance($user, $year, $farmer)."\n\n".$this->harvests($user, $year, $farmer);
        }

        return $this->result($text, $detail, $year, links: [['label' => 'Open farmer record', 'url' => route('farmers.records', $farmer)]]) + ['_farmer_id' => $farmer->id];
    }

    /** @param class-string<Model> $model */
    private function query(string $model, User $user): Builder
    {
        Gate::forUser($user)->authorize('viewAny', $model);

        return $this->access->scope($model::query(), $user);
    }

    private function scopeLabel(User $user): string
    {
        return 'Scope: '.mb_substr($user->scopeLabel(), 0, 180);
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
