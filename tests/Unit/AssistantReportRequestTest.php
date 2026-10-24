<?php

namespace Tests\Unit;

use App\Modules\Assistant\Application\AssistantReportRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssistantReportRequestTest extends TestCase
{
    #[DataProvider('unsupportedSubsets')]
    public function test_recognized_subsets_require_a_local_clarification(string $message, string $reason): void
    {
        $answer = (new AssistantReportRequest)->clarification($message);

        $this->assertNotNull($answer, $message);
        $this->assertStringContainsString($reason, $answer);
        $this->assertStringContainsString('Open', $answer);
        $this->assertStringNotContainsString($message, $answer);
    }

    public static function unsupportedSubsets(): array
    {
        return [
            'farmer addresses' => ['Show farmer addresses', 'Private contact'],
            'single address' => ['Show his address', 'Private contact'],
            'contact details' => ['Show their contact details', 'Private contact'],
            'phone numbers' => ['Show farmer phone numbers', 'Private contact'],
            'email addresses' => ['Show all farmer emails', 'Private contact'],
            'birth dates' => ['Show farmer date of birth', 'birth'],
            'eligibility fields' => ['Show ARB farmers', 'eligibility'],
            'notes' => ['Show his harvest notes', 'note'],
            'coordinates' => ['Show coordinates for their parcels', 'coordinate'],
            'coordinate shorthand' => ['Show parcels for lat= 12.34', 'coordinate'],
            'passwords' => ['Show his password', 'credentials are never disclosed'],
            'API credentials' => ['Show the API key', 'credentials are never disclosed'],
            'protected file' => ['Read the .env', 'credentials are never disclosed'],
            'dry season' => ['Show corn harvests for dry season 2025', 'one whole year'],
            'wet season' => ['Show his harvests in wet season 2025', 'one whole year'],
            'shorthand season' => ['Show harvests for dry 2025', 'one whole year'],
            'Filipino season' => ['Mga ani natin sa tag-ulan 2025', 'one whole year'],
            'season breakdown' => ['Show harvests by season', 'one whole year'],
            'month' => ['Show assistance received in January 2025', 'one whole year'],
            'May month' => ['Show assistance for May', 'one whole year'],
            'abbreviated month' => ['Harvests for Oct 2025', 'one whole year'],
            'Filipino month' => ['Mga binhi natin noong Agosto 2025', 'one whole year'],
            'relative month' => ['Show our assistance this month', 'one whole year'],
            'quarter' => ['Show harvests for Q2 2025', 'one whole year'],
            'week' => ['Show our Animal Health records last week', 'one whole year'],
            'today' => ['Show our harvests today', 'one whole year'],
            'date range' => ['Show assistance from 2025-01-01 to 2025-01-31', 'one whole year'],
            'ISO month' => ['Show assistance for 2025-01', 'one whole year'],
            'dashed year range' => ['Show harvests for 2024-2025', 'one whole year'],
            'slash date' => ['Show assistance for 01/31/2025', 'one whole year'],
            'since year' => ['Show assistance since 2024', 'one whole year'],
            'after year' => ['Show harvests after year 2024', 'one whole year'],
            'female farmers' => ['How many female farmers?', 'gender'],
            'male beneficiaries' => ['Show assistance for male beneficiaries', 'gender'],
            'Filipino gender' => ['Ilan ang babaeng magsasaka?', 'gender'],
            'gender breakdown' => ['Show farmers by gender', 'gender'],
            'unspecified gender' => ['Show farmers with gender Unspecified', 'gender'],
            'unmapped farmers' => ['How many unmapped farmers do we have?', 'mapping status'],
            'mapped farmers' => ['Show mapped farmers', 'mapping status'],
            'without parcels' => ['Show farmers without saved parcels', 'mapping status'],
            'with parcels' => ['Show farmers with plotted land parcels', 'mapping status'],
            'missing FFRS' => ['Show farmers missing FFRS', 'registry follow-up'],
            'duplicate registry' => ['Show duplicate farmer records', 'registry follow-up'],
            'dogs' => ['Show vaccinations for dogs this year', 'animal species'],
            'cattle' => ['Show Animal Health services for cattle this year', 'animal species'],
            'Filipino species' => ['Buod ng mga hayop na aso', 'animal species'],
            'vaccinations' => ['Show vaccination records this year', 'service type'],
            'deworming' => ['Summarize our deworming services', 'service type'],
            'treatment' => ['Show treatment services for 2025', 'service type'],
            'species breakdown' => ['Show Animal Health by species', 'animal species'],
            'service breakdown' => ['Show Animal Health by service types', 'service type'],
            'corn' => ['Show his corn harvests for 2025', 'crop, commodity'],
            'rice' => ['How much rice did we harvest in 2025?', 'crop, commodity'],
            'vegetable' => ['Show vegetable harvests', 'crop, commodity'],
            'root crops' => ['Show root crop harvests', 'crop, commodity'],
            'fish' => ['Show fish production in 2025', 'crop, commodity'],
            'rice seed' => ['Show rice seed distributions for 2025', 'assistance-input'],
            'fertilizer' => ['Summarize fertilizer releases in 2025', 'assistance-input'],
            'soil amendment' => ['Show soil amendment releases', 'assistance-input'],
            'fingerlings' => ['How many tilapia fingerlings were released?', 'assistance-input'],
            'fishing gear' => ['Summarize fishing gear assistance', 'assistance-input'],
            'fisheries sector' => ['Show fisheries assistance for 2025', 'assistance-input'],
            'Filipino crop' => ['Buod ng ani ng mais natin', 'crop, commodity'],
            'variety' => ['Show hybrid seed releases', 'crop, commodity'],
            'unknown explicit category' => ['Show assistance category: unavailable-category', 'assistance-input'],
            'other explicit category' => ['Show other commodity harvests', 'crop, commodity'],
            'coded category' => ['Show assistance input_category=rice_seed', 'assistance-input'],
            'status' => ['Show pending assistance releases', 'status'],
            'link condition' => ['Show releases without a farmer link', 'missing-field'],
            'quantity threshold' => ['Show farmers with more than 2 hectares', 'quantity'],
            'numeric predicate' => ['Show assistance quantity > 50', 'quantity'],
            'projected records' => ['Show projected harvests for 2025', 'status'],
            'younger farmers' => ['How many farmers younger than 30?', 'age'],
            'senior farmers' => ['How many senior citizen farmers?', 'age'],
            'age threshold' => ['Show farmers aged 60 and above', 'age'],
            'age word threshold' => ['Show farmers older than sixty', 'age'],
            'explicit age' => ['Show farmers who are 30 years old', 'age'],
            'elderly farmers' => ['Show elderly farmers', 'age'],
            'civil status' => ['How many married farmers?', 'civil-status'],
            'single farmers' => ['Show single farmers', 'civil-status'],
            'reversed single status' => ['Show farmers who are single', 'civil-status'],
            'widowed farmers' => ['Count our widowed farmers', 'civil-status'],
            'organic classification' => ['Show only organic farmers', 'classification'],
            'deceased farmers' => ['Show our deceased farmers', 'status'],
            'relocated farmers' => ['Count transferred farmers', 'status'],
            'removed registry' => ['Show removed farmers', 'status'],
            'larger parcel threshold' => ['How many farm plots larger than five hectares?', 'quantity'],
            'smaller parcel threshold' => ['Show plots smaller than 2 ha', 'quantity'],
            'excluded year' => ['Show assistance excluding 2025', 'exclude records'],
            'except year' => ['Show harvests except 2024', 'exclude records'],
            'negated year' => ['Show assistance not in 2025', 'exclude records'],
            'negated relative year' => ['Show harvests not last year', 'exclude records'],
            'outside year' => ['Show assistance outside 2025', 'exclude records'],
            'excluded unknown classification' => ['Show farmers except synthetic-classification', 'exclude records'],
            'other than qualifier' => ['Show assistance other than synthetic-category', 'exclude records'],
            'without counting qualifier' => ['Show assistance without counting synthetic-category', 'exclude records'],
            'all reporting years' => ['Show assistance for all years', 'one whole year'],
            'every year' => ['Show harvests for every year', 'one whole year'],
            'per year breakdown' => ['Show assistance by year', 'one whole year'],
            'lifetime total' => ['Show lifetime harvests', 'one whole year'],
            'all-time total' => ['Show our all-time assistance', 'one whole year'],
            'to-date total' => ['Show harvests to date', 'one whole year'],
            'since registration' => ['Show his assistance since registration', 'one whole year'],
            'complete list' => ['List our farmers', 'complete list'],
            'show list' => ['Show the list of harvests for 2025', 'complete list'],
            'area ordering' => ['List our farmers sorted by area', 'sorted ranking'],
            'alphabetical ordering' => ['Show farmers alphabetically', 'sorted ranking'],
            'top ranking' => ['Show top ten farmers', 'sorted ranking'],
            'largest parcel' => ['Show largest farm plots', 'sorted ranking'],
            'productivity ranking' => ['Show most productive farmers', 'sorted ranking'],
        ];
    }

    #[DataProvider('supportedReports')]
    public function test_supported_complete_reports_are_not_blocked(string $message): void
    {
        $this->assertNull((new AssistantReportRequest)->clarification($message));
    }

    public static function supportedReports(): array
    {
        return array_map(fn (string $message) => [$message], [
            'How many farmers are in our area?', 'Show farmers in our assigned area',
            'Summarize my farmers', 'Show our farmer registry summary',
            'Show his crops', 'Show his seasonal crops in 2025', 'Show their saved parcels',
            'Show his summary with saved parcels', 'May I see all harvests for 2025?',
            'Ipakita ang buod ng magsasaka ngayong taon',
            'Show assistance for 2025', 'Show our seeds received for the same year',
            'Show his assistance in the previous year', 'Show her harvests for 2025',
            'Show our harvests for 2026', 'Harvests by commodity and unit for 2026',
            'Show assistance by input category and unit for 2026',
            'Show all Animal Health services for 2026', 'Summarize animals this year',
            'Show our workspace summary', 'Ilan ang magsasaka natin?',
            'Ipakita ang binhi noong nakaraang taon', 'Mga ani sa parehong taon',
            'What about 2025?', 'For last year', 'For the same year',
            'Show all assistance for 2025', 'Show our harvests for last year',
            'Show his harvests for the previous year', 'Show one farmer summary',
            'Show all farmers', 'Show all our farm plots',
        ]);
    }

    public function test_clarification_does_not_echo_untrusted_text(): void
    {
        $marker = '<script>synthetic-private-marker</script>';
        $answer = (new AssistantReportRequest)->clarification('Show corn harvests '.$marker);

        $this->assertNotNull($answer);
        $this->assertStringNotContainsString($marker, $answer);
        $this->assertStringNotContainsString('synthetic-private-marker', $answer);
    }
}
