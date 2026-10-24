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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Private, read-only answers. Nothing from this service goes to an AI provider. */
final class AssistantRecords implements OfficeRecordReader
{
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
    public function answerTask(User $user, string $topic, int $year, string $search = '', ?int $farmerId = null): array
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
        try {
            $result = match ($topic) {
                'farmer-search' => $this->farmer($user, 'Find farmer '.$search, $year, null),
                'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests' => $this->farmer($user, $search, $year, $farmerId, $topic),
                'farmers' => $this->result($this->farmerSummary($user), 'farmers', $year, links: [$this->link('Open farmer registry', 'farmers.index')]),
                'assistance' => $this->result($this->assistance($user, $year), 'assistance', $year, links: [$this->link('Open assistance register', 'rice-seed-distributions.index')]),
                'harvests' => $this->result($this->harvests($user, $year), 'harvests', $year, links: [$this->link('Open harvest register', 'harvest-records.index')]),
                'animal-health' => $this->result($this->animals($user, $year), 'animal-health', $year, links: [$this->link('Open Animal Health', 'anti-rabies-vaccinations.index')]),
                'data-quality' => $this->qualityResult($user, $year),
                'registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile', 'crops' => $this->result(
                    $this->operations->answer($user, $topic, $year), $topic, $year, links: $this->reportLinks($topic)
                ),
                'summary' => $user->isProvincialVeterinaryOffice()
                    ? $this->result($this->animals($user, $year), 'animal-health', $year)
                    : $this->result($this->overview($user, $year), 'summary', $year),
                default => $this->result('I can summarize your farmer registry, retained workbook sections, seasonal crops, assistance, harvests, Animal Health, cooperatives, machinery and LGU profile setup. To find one person, type “Find farmer” followed by a name or AgriLGU ID. Reports use your account’s permitted area and one reporting year.', 'guidance', $year),
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

    private function qualityResult(User $user, int $year): array
    {
        $summary = $this->quality->summary($user);

        return $this->result($summary['answer'], 'data-quality', $year, links: $summary['links']);
    }

    private function overview(User $user, int $year): string
    {
        $parts = [$this->farmerSummary($user), $this->assistance($user, $year), $this->harvests($user, $year), $this->animals($user, $year)];
        foreach (['registry-sources', 'crops', 'cooperatives', 'machinery'] as $topic) {
            // The overview presents only the leading labeled aggregates; each
            // dedicated report supplies its bounded breakdown and caveats.
            $parts[] = implode("\n", array_slice(explode("\n", $this->operations->answer($user, $topic, $year)), 0, 5));
        }
        $parts[] = 'Workbook figures count rows, not people. Crop figures count parcel-season records, not hectares or production. Memberships require same-LGU farmer links; machinery counts reflect saved inventory status. Ask for an individual module report for its breakdown and limitations. LGU profile setup is available separately.';

        return implode("\n\n", $parts);
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
        ];
        $topics = match ($report) {
            'summary' => ['farmers', 'assistance', 'harvests', 'animal-health', 'registry-sources', 'crops', 'cooperatives', 'machinery'],
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
            'crops' => ['parcel_crop_seasons'],
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
