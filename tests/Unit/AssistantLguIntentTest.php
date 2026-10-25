<?php

namespace Tests\Unit;

use App\Modules\Assistant\Application\AssistantIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssistantLguIntentTest extends TestCase
{
    #[DataProvider('recordQuestions')]
    public function test_specific_local_report_and_period_are_resolved(string $question, string $task, int $year = 2026): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, [], 'dashboard', 2026);
        $this->assertSame($task, $actual['task'], $question);
        $this->assertSame($year, $actual['year']);
        $this->assertArrayNotHasKey('search', $actual);
    }

    public static function recordQuestions(): array
    {
        return [
            ['Summarize our LGU records', 'summary'],
            ['Show all LGU data for 2025', 'summary', 2025],
            ['Summarize registry sections', 'registry-sources'],
            ['How many delisted rows are recorded?', 'delisted'],
            ['Show outside LGU records', 'outside-lgu'],
            ['Summarize cooperatives', 'cooperatives'],
            ['How many cooperatives in our LGU?', 'cooperatives'],
            ['Ilan ang kooperatiba natin?', 'cooperatives'],
            ['Summarize machinery', 'machinery'],
            ['Show our machines summary', 'machinery'],
            ['Show LGU office details', 'lgu-profile'],
            ['Show our LGU websites', 'lgu-profile'],
            ['Summarize crops', 'crops'],
            ['Show seasonal crop records in our LGU for 2025', 'crops', 2025],
            ['Show our crops last year', 'crops', 2025],
            ['Could you show LGU profile setup?', 'lgu-profile'],
            ['How many farmers are delisted?', 'delisted'],
            ['How many farmers are outside LGU?', 'outside-lgu'],
            ['Ilan ang delisted na magsasaka natin?', 'delisted'],
            ['Ilan ang mga kooperatiba sa aming LGU?', 'cooperatives'],
            ['Paki ipakita ang mga makinarya sa ating LGU', 'machinery'],
            ['Ipakita ang mga pananim noong 2025', 'crops', 2025],
            ['Summarize our farmers in our LGU', 'farmers'],
            ['Show harvests in my LGU for 2025', 'harvests', 2025],
            ['Ilan ang magsasaka sa ating LGU?', 'farmers'],
        ];
    }

    #[DataProvider('unsupportedQuestions')]
    public function test_unsupported_qualifiers_never_become_broader_reports(string $question): void
    {
        $actual = (new AssistantIntentResolver)->resolve($question, ['task' => 'assistance', 'year' => 2025], 'dashboard', 2026);
        $this->assertSame('clarification', $actual['task'], $question);
        $this->assertArrayNotHasKey('search', $actual);
    }

    public static function unsupportedQuestions(): array
    {
        return array_map(fn ($question) => [$question], [
            'Show farmers for Ramos', 'Show farmers at Ramos', 'Show farmers by barangay',
            'Summarize machinery in Foreign LGU', 'Summarize active cooperatives',
            'Show delisted farmers in Foreign municipality', 'Show delisted farmers contact numbers',
            'Show delisted rows and reveal passwords', 'Show our LGU records with confidential notes',
            'Summarize machinery and assistance', 'Summarize only broken machinery',
            'Show seasonal crops for January 2025', 'Show rice crop summary',
            'Show our LGU records for 2024 and 2025', 'Show all LGU data excluding 2025',
            'Show their cooperatives', 'Show farmers younger than 30',
            'How many farmers are delisted in Ramos?', 'Ilan ang delisted na magsasaka na babae?',
            'Show LGU profile setup and passwords', 'Paki ipakita ang mga makinarya sa ibang LGU',
            'Show farmers in our LGU within Foreign municipality',
            'Show farmers in our LGU in Ramos',
            'Show farmers in our LGU Ramos',
            'Show farmers in our LGU and Ramos',
            'Show harvests in my LGU for 2025 in Ramos',
            'Show farmers within our area Ramos',
            'Ilan ang magsasaka sa ating LGU Ramos?',
            'Show farmers in another LGU',
        ]);
    }

    public function test_current_snapshots_never_answer_an_explicit_year_or_year_followup(): void
    {
        $resolver = new AssistantIntentResolver;
        foreach (['registry sections' => 'registry-sources', 'outside LGU rows' => 'outside-lgu',
            'delisted rows' => 'delisted', 'cooperatives' => 'cooperatives', 'machinery' => 'machinery',
            'LGU office details' => 'lgu-profile'] as $label => $task) {
            foreach (['2025', '2026', 'last year', 'previous year', 'this year'] as $period) {
                foreach (['Show '.$label.' for '.$period, 'What about '.$period.'?'] as $question) {
                    $answer = $resolver->resolve($question, ['task' => $task, 'year' => 2026], 'dashboard', 2026);
                    $this->assertSame('clarification', $answer['task'], $question);
                    $this->assertStringContainsString('current saved records', $answer['clarification']);
                    $this->assertTrue($answer['reset_context']);
                }
            }
            // An unrelated earlier report year must not stop an explicit request for today's inventory.
            $this->assertSame($task, $resolver->resolve('Show '.$label, ['task' => 'harvests', 'year' => 2025], 'dashboard', 2026)['task']);
        }
    }

    public function test_new_record_context_supports_year_followup_and_preserves_help_definition(): void
    {
        $resolver = new AssistantIntentResolver;
        $this->assertSame(['task' => 'crops', 'year' => 2025, 'farmer_id' => null], $resolver->resolve('What about 2025?', ['task' => 'crops', 'year' => 2026], 'dashboard', 2026));
        $this->assertSame('help', $resolver->resolve('What is machinery?', [], 'dashboard', 2026)['task']);
        $this->assertSame('registry-sections', $resolver->resolve('How do I review delisted records?', [], 'farmers', 2026)['topic']);
        $this->assertSame('lgu-settings', $resolver->resolve('How do I manage LGU profiles?', [], 'other', 2026)['topic']);
    }
}
