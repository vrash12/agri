<?php

namespace Tests\Unit;

use App\Modules\Assistant\Application\AssistantIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Farm-location breakdown routing stays exact; subsets and lookups keep their paths. */
final class AssistantBarangayIntentTest extends TestCase
{
    #[DataProvider('breakdownQuestions')]
    public function test_whole_area_breakdown_phrasings_resolve_locally(string $question, int $year = 2026): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, [], 'dashboard', 2026);
        $this->assertSame(['task' => 'barangays', 'year' => $year, 'farmer_id' => null], $actual, $question);
    }

    public static function breakdownQuestions(): array
    {
        return [
            ['Barangay breakdown'], ['barangay breakdown for 2025', 2025], ['Brgy breakdown'], ['Breakdown by barangay'],
            ['Give me a barangay breakdown'], ['Can you show the barangay-level summary?'], ['Barangay summary of farmers'],
            ['Show farmers by barangay'], ['Show farmers per barangay'], ['Farmers per barangay'], ['How many farmers per barangay?'],
            ['Farmers in each barangay'], ['Farmers per brgy'], ['Per barangay'], ['By barangay'],
            ['Show farm locations'], ['Farm locations'], ['Farmers by farm location'], ['Show our farm locations for 2024', 2024],
            ['Assistance by barangay 2026'], ['Assistance by barangay 2025', 2025], ['Harvests per barangay last year', 2025],
            ['Show our LGU records by barangay for 2024', 2024], ['Count farmers by barangay in our LGU'], ['What about farmers per barangay?'],
            ['Ilan ang magsasaka sa bawat barangay?'], ['Ilan ang magsasaka natin sa bawat barangay?'], ['Ipakita ang mga magsasaka kada barangay'],
            ['Bilang ng magsasaka bawat barangay'], ['Ayuda sa bawat barangay ngayong taon'], ['Ani bawat barangay noong 2025', 2025],
            ['Magsasaka ayon sa barangay'], ['Bawat barangay'],
        ];
    }

    #[DataProvider('farmerLookups')]
    public function test_farmer_name_lookups_are_not_hijacked(string $question, string $search): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, ['task' => 'barangays', 'year' => 2026], 'farmers', 2026);
        $this->assertSame('farmer-search', $actual['task'], $question);
        $this->assertSame($search, $actual['search']);
    }

    public static function farmerLookups(): array
    {
        return [
            ['Find farmer Barangay', 'Barangay'], ['Find farmer Juan Brgy', 'Juan Brgy'], ['Show farmer Brgy Santos', 'Brgy Santos'],
            ['Who is Maria Barangay?', 'Maria Barangay'], ['Hanapin ang magsasaka Location', 'Location'],
        ];
    }

    #[DataProvider('existingTopics')]
    public function test_existing_topics_keep_their_routes(string $question, string $task): void
    {
        $this->assertSame($task, (new AssistantIntentResolver)->resolve($question, [], 'farmers', 2026)['task'], $question);
    }

    public static function existingTopics(): array
    {
        return [
            ['Summarize our farmers', 'farmers'], ['Show assistance for 2025', 'assistance'], ['Show harvests in my LGU for 2025', 'harvests'],
            ['Summarize crops', 'crops'], ['Summarize registry sections', 'registry-sources'], ['What needs attention in our registry?', 'data-quality'],
            ['How do I use the farmer registry?', 'help'], ['Show farmers by province', 'clarification'], ['Summarize our workspace', 'summary'],
        ];
    }

    #[DataProvider('unsupportedSubsets')]
    public function test_barangay_subsets_and_filters_clarify_instead_of_widening(string $question): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, ['task' => 'barangays', 'year' => 2025], 'farmers', 2026);
        $this->assertSame('clarification', $actual['task'], $question);
        $this->assertTrue($actual['reset_context'] ?? false, $question);
        $this->assertArrayNotHasKey('search', $actual);
    }

    public static function unsupportedSubsets(): array
    {
        return array_map(fn (string $question) => [$question], [
            'Show farmers in Barangay San Isidro', 'How many farmers in barangay Poblacion?', 'Farmers per barangay in Ramos',
            'Top barangays by farmers', 'Rank barangays by assistance', 'List farmers per barangay', 'Female farmers per barangay',
            'Rice harvests by barangay', 'Farmers per barangay excluding Poblacion', 'Assistance by barangay for January 2026',
            'Farmers per barangay for 2025 and 2026', 'Compare assistance by barangay 2025 and 2026',
            'Show farmers per barangay with contact numbers', 'Farmers per barangay and machinery', 'Ilan ang babae sa bawat barangay?',
        ]);
    }

    public function test_named_barangay_clarification_points_to_the_supported_breakdown(): void
    {
        foreach (['Show farmers in Barangay San Isidro', 'Compare assistance by barangay 2025 and 2026'] as $question) {
            $actual = (new AssistantIntentResolver)->resolve($question, [], 'farmers', 2026);
            $this->assertStringContainsString('farm-location (barangay) breakdown', $actual['clarification'], $question);
        }
    }

    public function test_breakdown_is_annual_with_year_followups_and_clears_a_selected_farmer(): void
    {
        $resolver = new AssistantIntentResolver;
        $this->assertSame(['task' => 'barangays', 'year' => 2025, 'farmer_id' => null], $resolver->resolve('What about 2025?', ['task' => 'barangays', 'year' => 2026], 'farmers', 2026));
        $this->assertSame(['task' => 'barangays', 'year' => 2024, 'farmer_id' => null], $resolver->resolve('Show farmers per barangay for 2024', ['task' => 'machinery', 'year' => 2026], 'farmers', 2026));
        $this->assertSame(['task' => 'barangays', 'year' => 2025, 'farmer_id' => null], $resolver->resolve('Assistance by barangay 2025', ['task' => 'farmer', 'year' => 2026, 'farmer_id' => 17], 'farmers', 2026));
        // A bare grouping follow-up keeps the previous report year.
        $this->assertSame(['task' => 'barangays', 'year' => 2024, 'farmer_id' => null], $resolver->resolve('By barangay', ['task' => 'assistance', 'year' => 2024], 'assistance', 2026));
    }
}
