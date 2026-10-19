<?php

namespace Tests\Unit;

use App\Modules\Assistant\Application\AssistantIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Local paraphrases must preserve literal lookup and report boundaries. */
final class AssistantNaturalLanguageTest extends TestCase
{
    #[DataProvider('farmerLookups')]
    public function test_natural_farmer_questions_keep_the_exact_name(string $question, string $name): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, ['task' => 'farmer', 'year' => 2026, 'farmer_id' => 17], 'farmers', 2026);

        $this->assertSame('farmer-search', $actual['task']);
        $this->assertSame($name, $actual['search']);
        $this->assertSame(2026, $actual['year']);
        $this->assertEmpty($actual['farmer_id'] ?? null);
        $this->assertArrayNotHasKey('references', $actual);
        $this->assertArrayNotHasKey('message', $actual);
    }

    public static function farmerLookups(): array
    {
        return [
            'polite explicit lookup' => ['Please find farmer Juan Dela Cruz', 'Juan Dela Cruz'],
            'Filipino polite lookup' => ['Paki hanapin ang magsasaka Juan Dela Cruz', 'Juan Dela Cruz'],
            'identity question with middle initial' => ['Who is Dennish C. Pascua?', 'Dennish C. Pascua'],
            'Filipino identity question' => ['Sino si Juan Fixture?', 'Juan Fixture'],
            'show a named farmer' => ['Show me farmer Juan Fixture', 'Juan Fixture'],
            'find a person without farmer keyword' => ['Find Juan Fixture', 'Juan Fixture'],
            'crop word is a literal first name' => ['Who is Corn Reyes?', 'Corn Reyes'],
            'month word is a literal first name' => ['Find farmer May Reyes', 'May Reyes'],
            'language word is a literal first name' => ['Who is Filipino Reyes?', 'Filipino Reyes'],
            'straight apostrophe is preserved' => ["Who is Maria O'Malley?", "Maria O'Malley"],
            'curly apostrophe is preserved' => ['Who is Maria O’Malley?', 'Maria O’Malley'],
            'hyphen and accents are preserved' => ['Show me farmer Ana-María Núñez', 'Ana-María Núñez'],
            'Unicode multiword name is preserved' => ['Sino si José de la Cruz?', 'José de la Cruz'],
        ];
    }

    #[DataProvider('ambiguousLookups')]
    public function test_lookup_qualifiers_cannot_fall_back_to_a_broader_report(string $question): void
    {
        $context = ['task' => 'farmer', 'year' => 2026, 'farmer_id' => 17, 'pending_scope' => 'harvests'];
        $actual = (new AssistantIntentResolver)->resolve($question, $context, 'farmers', 2026);

        $this->assertSame('clarification', $actual['task']);
        $this->assertTrue($actual['reset_context'] ?? false);
        $this->assertArrayNotHasKey('search', $actual);
        $this->assertArrayNotHasKey('references', $actual);
        $this->assertArrayNotHasKey('farmer_id', $actual);
    }

    public static function ambiguousLookups(): array
    {
        return array_map(fn (string $question): array => [$question], [
            'Who is Juan Fixture and show harvests?',
            'Who is Juan Fixture and summarize assistance?',
            'Show me farmer Juan Fixture and his address',
            'Who is Juan Fixture? Show his phone number.',
            'Who is Juan Fixture from Foreign municipality?',
            'Show me farmer Juan Fixture in Foreign province',
            'Who is Juan Fixture for 2025?',
            'Who is Juan Fixture for 2025 and 2026?',
            'Sino si Juan Fixture at ipakita ang ani niya?',
        ]);
    }

    #[DataProvider('ordinaryReports')]
    public function test_normal_reports_keep_their_existing_task(string $question, string $task): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, [], 'dashboard', 2026);

        $this->assertSame($task, $actual['task']);
        $this->assertArrayNotHasKey('search', $actual);
        $this->assertSame(2026, $actual['year']);
    }

    public static function ordinaryReports(): array
    {
        return [
            ['Show farmers', 'farmers'],
            ['Show assistance for 2026', 'assistance'],
            ['Show harvests for 2026', 'harvests'],
            ['Summarize my farmers', 'farmers'],
            ['Show our workspace summary', 'summary'],
        ];
    }

    #[DataProvider('mutations')]
    public function test_wrapped_and_direct_mutations_stay_local(string $question): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, [], 'farmers', 2026);

        $this->assertSame('clarification', $actual['task']);
        $this->assertArrayNotHasKey('search', $actual);
        $this->assertArrayNotHasKey('references', $actual);
        $this->assertArrayNotHasKey('farmer_id', $actual);
    }

    public static function mutations(): array
    {
        return array_map(fn (string $question): array => [$question], [
            'Can you delete farmer Juan Fixture?',
            'Could you update farmer Juan Fixture?',
            'Would you approve assistance for Juan Fixture?',
            'Can you add a farmer?',
            'Could you save this harvest?',
            'Would you unlock farmer Juan Fixture?',
            'Record harvest for Juan Fixture',
            'Add farmer Juan Fixture',
            'Save harvest for Juan Fixture',
            'Unlock farmer Juan Fixture',
        ]);
    }

    #[DataProvider('mutationInstructions')]
    public function test_instructions_for_authorized_actions_remain_reviewed_help(string $question, string $topic): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, [], 'farmers', 2026);

        $this->assertSame('help', $actual['task']);
        $this->assertSame($topic, $actual['topic']);
        $this->assertArrayNotHasKey('search', $actual);
        $this->assertArrayNotHasKey('farmer_id', $actual);
    }

    public static function mutationInstructions(): array
    {
        return [
            ['How do I add a farmer profile?', 'farmer-registry'],
            ['How do I record a harvest?', 'harvest-entry'],
            ['How do I draw a crop section?', 'crop-drawing'],
        ];
    }
}
