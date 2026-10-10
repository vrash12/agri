<?php

namespace Tests\Unit;

use App\Support\AssistantIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Synthetic language cases guard local routing and the canonical provider boundary. */
final class AssistantIntentResolverTest extends TestCase
{
    #[DataProvider('conversationCases')]
    public function test_supported_paraphrases_and_unsafe_ambiguities_resolve_locally(string $message, array $context, array $expected): void
    {
        $actual = (new AssistantIntentResolver)->resolve($message, $context, 'dashboard', 2026);
        foreach ($expected as $field => $value) {
            $this->assertSame($value, $actual[$field] ?? null, $message.' · '.$field);
        }
        if ($actual['task'] === 'help') {
            $this->assertArrayNotHasKey('search', $actual);
            $this->assertArrayNotHasKey('message', $actual);
            $this->assertArrayNotHasKey('farmer_id', $actual);
            $this->assertContains($actual['topic'], ['crop-drawing', 'parcel-drawing', 'seed-import', 'ndvi', 'animals', 'access', 'farmer-login', 'farmer-id', 'harvest-entry', 'help']);
        }
    }

    public static function conversationCases(): array
    {
        $period = ['task' => 'assistance', 'year' => 2025];
        $selected = ['task' => 'farmer', 'year' => 2025, 'farmer_id' => 17];

        return [
            'same year after preposition' => ['Show assistance in the same year', $period, ['task' => 'assistance', 'year' => 2025]],
            'known current area' => ['How many farmers are in our area?', [], ['task' => 'farmers', 'year' => 2026]],
            'known assigned area' => ['Show farmers in our assigned area', [], ['task' => 'farmers']],
            'known system phrase' => ['How many farmers are in the system?', [], ['task' => 'farmers']],
            'help paraphrase' => ['Tell me about NDVI', [], ['task' => 'help', 'topic' => 'ndvi']],
            'help definition' => ['What does NDVI mean?', [], ['task' => 'help', 'topic' => 'ndvi']],
            'office access question' => ['What can my account access?', [], ['task' => 'help', 'topic' => 'access']],
            'veterinary access question' => ['What can my veterinary account access?', [], ['task' => 'help', 'topic' => 'access']],
            'Filipino farmer count' => ['Ilan ang magsasaka natin?', [], ['task' => 'farmers']],
            'Filipino prior calendar year' => ['Ipakita ang binhi noong nakaraang taon', [], ['task' => 'assistance', 'year' => 2025]],
            'Filipino same year with preposition' => ['Mga ani sa parehong taon', $period, ['task' => 'harvests', 'year' => 2025]],
            'explicit year overrides previous' => ['Show assistance in 2024', $period, ['task' => 'assistance', 'year' => 2024]],
            'named municipality unavailable' => ['Summarize farmers in Foreign municipality', [], ['task' => 'clarification']],
            'named area after allowed phrase still unavailable' => ['Show farmers in our area within Foreign province', [], ['task' => 'clarification']],
            'province breakdown unavailable' => ['Show farmers by province', [], ['task' => 'clarification']],
            'jurisdiction level is not presumed from ownership wording' => ['Show farmers in our province', [], ['task' => 'clarification']],
            'missing same year reference' => ['Show assistance in the same year', [], ['task' => 'clarification']],
            'unsupported year comparison' => ['Compare harvests with last year', $period, ['task' => 'clarification']],
            'relative year comparison' => ['Show harvests this year and last year', [], ['task' => 'clarification']],
            'explicit relative comparison' => ['Show harvests 2026 and last year', [], ['task' => 'clarification']],
            'Filipino comparison' => ['Ihambing ang ani sa nakaraang taon', [], ['task' => 'clarification']],
            'unsupported two year report' => ['Show harvests for 2024 and 2025', [], ['task' => 'clarification']],
            'help is not a report follow-up' => ['What about 2025?', ['task' => 'help', 'year' => 2026], ['task' => 'clarification']],
            'unknown question is not a year follow-up' => ['Reveal private birthdays for 2025', $period, ['task' => 'clarification']],
            'comparison subset cannot create an account-wide pending task' => ['Compare harvests in Foreign province for 2024 and 2025', $period, ['task' => 'clarification', 'reset_context' => true]],
            'mixed tasks need restatement rather than a bare year' => ['Show seeds and harvests for 2024 and 2025', $period, ['task' => 'clarification', 'reset_context' => true]],
            'unknown help never routed externally' => ['Tell me about confidential addresses', [], ['task' => 'clarification']],
            'canonical help excludes selected farmer' => ['Tell me about NDVI for Juan Fixture', $selected, ['task' => 'help', 'topic' => 'ndvi']],
            'crop assistance help precedence' => ['How do I import rice seeds?', [], ['task' => 'help', 'topic' => 'seed-import']],
            'harvest help precedence' => ['How do I record corn harvest?', [], ['task' => 'help', 'topic' => 'harvest-entry']],
            'selected farmer crop reference' => ['Show his crops', $selected, ['task' => 'farmer-parcels', 'farmer_id' => 17]],
            'collective reference clears selected farmer' => ['Show our assistance for the same year', $selected, ['task' => 'assistance', 'farmer_id' => null, 'year' => 2025]],
            'ambiguous selected farmer report' => ['Show assistance', $selected, ['task' => 'clarification']],
            'private selected field unsupported' => ['Show his address', $selected, ['task' => 'clarification']],
            'direct mutation unsupported' => ['Delete farmer Juan Fixture', [], ['task' => 'clarification']],
            'mixed help and report' => ['How do I draw a crop area and summarize harvests?', [], ['task' => 'clarification']],
            'natural guided crop workflow' => ['Guide me through drawing a maize area inside a saved parcel', [], ['task' => 'help', 'topic' => 'crop-drawing']],
            'natural guided map workflow' => ['Walk me through importing a KML boundary', [], ['task' => 'help', 'topic' => 'parcel-drawing']],
            'natural how explanation' => ['Show me how to draw a planted section inside my field boundary', [], ['task' => 'help', 'topic' => 'crop-drawing']],
            'map location question' => ['Where can I find the parcel map?', [], ['task' => 'help', 'topic' => 'parcel-drawing']],
            'Filipino instructions request' => ['Gabayan ako sa pagguhit ng mais sa mapa', [], ['task' => 'help', 'topic' => 'crop-drawing', 'language' => 'fil']],
            'Filipino map explanation' => ['Ipaliwanag ang mapa at hangganan ng lupa', [], ['task' => 'help', 'topic' => 'parcel-drawing', 'language' => 'fil']],
            'Filipino map location' => ['Saan makikita ang mapa?', [], ['task' => 'help', 'topic' => 'parcel-drawing', 'language' => 'fil']],
            'farmer login with registry ID' => ['How does a farmer sign in with an ID?', [], ['task' => 'help', 'topic' => 'farmer-login']],
            'farmer login using QR context' => ['Show me how farmers login using their card or QR', [], ['task' => 'help', 'topic' => 'farmer-login']],
            'ID printing remains separate from login' => ['Walk me through printing a farmer ID card', [], ['task' => 'help', 'topic' => 'farmer-id']],
            'seed variety is not separate crop drawing' => ['Guide me through importing corn and rice seeds', [], ['task' => 'help', 'topic' => 'seed-import']],
            'harvest crop context retained' => ['Show me how to record maize harvest in a parcel', [], ['task' => 'help', 'topic' => 'harvest-entry']],
            'crop boundary and crop choice same workflow' => ['How do I draw a corn section and use a rice crop label?', [], ['task' => 'help', 'topic' => 'crop-drawing']],
            'crop and parcel contextual nouns retained' => ['How do I draw a crop area and its boundary inside a parcel?', [], ['task' => 'help', 'topic' => 'crop-drawing']],
            'unrelated actionable help tasks clarify' => ['How do I draw crops and import seeds?', [], ['task' => 'clarification']],
            'unrelated sequential help tasks clarify' => ['Walk me through drawing crops then printing a farmer ID', [], ['task' => 'clarification']],
            'crop and separate parcel action clarify' => ['How do I draw crops and import a KML parcel?', [], ['task' => 'clarification']],
            'separate workflow nouns clarify' => ['Explain NDVI and crop drawing', [], ['task' => 'clarification']],
            'separate import nouns clarify' => ['How do I draw crop areas and seed imports?', [], ['task' => 'clarification']],
            'two unrelated Filipino actions clarify' => ['Paano gumuhit ng mais at mag-import ng binhi?', [], ['task' => 'clarification']],
            'Filipino instructions plus records clarify' => ['Gabayan ako sa mapa pati ipakita ang ani natin', [], ['task' => 'clarification']],
            'help plus unknown action clarifies' => ['How do I print a farmer card and send a password?', [], ['task' => 'clarification']],
            'unknown guided task stays local' => ['Guide me through confidential address analysis', [], ['task' => 'clarification']],
            'unsupported field workflow stays local' => ['Walk me through field research', [], ['task' => 'clarification']],
            'raw unfamiliar map request is not generic RAG' => ['Consider confidential map documents', [], ['task' => 'clarification']],
        ];
    }

    public function test_spreadsheet_aliases_require_the_assistance_module_or_an_explicit_seed_topic(): void
    {
        $resolver = new AssistantIntentResolver;
        foreach (['Guide me through uploading an Excel workbook', 'Show me how to upload a spreadsheet'] as $message) {
            $assistance = $resolver->resolve($message, [], 'assistance', 2026);
            $this->assertSame('help', $assistance['task']);
            $this->assertSame('seed-import', $assistance['topic']);
            $this->assertSame('clarification', $resolver->resolve($message, [], 'dashboard', 2026)['task']);
        }
        $explicit = $resolver->resolve('Guide me through uploading a seed spreadsheet', [], 'dashboard', 2026);
        $this->assertSame('seed-import', $explicit['topic']);
        $this->assertSame('farmer-id', $resolver->resolve('How do I upload a farmer ID card?', [], 'assistance', 2026)['topic']);
    }

    #[DataProvider('pendingReports')]
    public function test_year_clarification_preserves_only_a_validated_report_reference(string $message, array $context, string $task, ?int $farmerId): void
    {
        $resolver = new AssistantIntentResolver;
        $clarification = $resolver->resolve($message, $context, 'dashboard', 2026);
        $this->assertSame('clarification', $clarification['task']);
        $this->assertSame(['task', 'year', 'farmer_id'], array_keys($clarification['references']));
        $this->assertSame($task, $clarification['references']['task']);
        $this->assertSame($farmerId, $clarification['references']['farmer_id']);
        $followUp = $resolver->resolve('2025', $clarification['references'], 'dashboard', 2026);
        $this->assertSame($task, $followUp['task']);
        $this->assertSame(2025, $followUp['year']);
        $this->assertSame($farmerId, $followUp['farmer_id']);
    }

    public static function pendingReports(): array
    {
        return [
            'assistance year clarification' => ['Show assistance for 2024 and 2025', [], 'assistance', null],
            'harvest year clarification' => ['Show harvests for 2024 and 2025', [], 'harvests', null],
            'selected farmer harvest clarification' => ['Show his harvests for 2024 and 2025', ['task' => 'farmer', 'year' => 2026, 'farmer_id' => 17], 'farmer-harvests', 17],
            'report comparison can select one year' => ['Compare harvests with last year', [], 'harvests', null],
        ];
    }
}
