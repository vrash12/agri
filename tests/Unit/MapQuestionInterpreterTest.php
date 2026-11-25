<?php

namespace Tests\Unit;

use App\Models\ParcelCropSeason;
use App\Modules\Land\Application\MapQuestionInterpreter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MapQuestionInterpreterTest extends TestCase
{
    private const CONTEXT = ['year' => 2025, 'season' => 'dry'];

    #[DataProvider('recognized')]
    public function test_recognized_phrasings(string $question, string $intent, ?string $crop, ?int $year, ?string $season, ?string $place): void
    {
        $result = (new MapQuestionInterpreter)->interpret($question, self::CONTEXT, 2026);
        $this->assertSame('ok', $result['status'], $question);
        $this->assertSame([$intent, $crop, $year, $season, $place],
            [$result['intent'], $result['crop'], $result['year'], $result['season'], $result['location_text']], $question);
    }

    public static function recognized(): array
    {
        return [
            ['corn parcels with no harvest this wet season', 'no_harvest', 'corn', 2026, 'wet', null],
            ['Parcels without a boundary', 'no_boundary', null, null, null, null],
            ['farmers without a parcel in San Isidro', 'without_parcel', null, null, null, 'san isidro'],
            ['rice parcels 2026 dry season', 'crop', 'rice', 2026, 'dry', null],
            ['parcels needing a field visit', 'field_visit', null, 2025, 'dry', null],
            ['palay parcels tag-ulan 2024', 'crop', 'rice', 2024, 'wet', null],
            ['mais na walang harvest', 'no_harvest', 'corn', 2025, 'dry', null],
            ['vegetable parcels in Brgy. Poblacion', 'crop', 'vegetables', 2025, 'dry', 'poblacion'],
            ['root crop parcels in san jose 2026 wet season', 'crop', 'root_crops', 2026, 'wet', 'san jose'],
            ['parcels with no crop recorded', 'crop', 'not_recorded', 2025, 'dry', null],
            ['unmapped farmers', 'without_parcel', null, null, null, null],
            ['overdue inspections', 'field_visit', null, 2025, 'dry', null],
            ['mixed crops parcels', 'crop', 'mixed', 2025, 'dry', null],
        ];
    }

    #[DataProvider('unsupported')]
    public function test_unsupported_or_ambiguous_requests_clarify(string $question): void
    {
        $result = (new MapQuestionInterpreter)->interpret($question, self::CONTEXT, 2026);
        $this->assertSame('clarify', $result['status'], $question);
        $this->assertSame(MapQuestionInterpreter::EXAMPLES, $result['examples']);
        $this->assertNotSame('', $result['message']);
    }

    public static function unsupported(): array
    {
        return array_map(fn ($q) => [$q], [
            '', 'delete rice parcels', 'export corn parcels', 'top 10 rice parcels', 'parcels larger than 3 ha',
            'compare rice 2025 vs 2026', 'rice parcels 2025 and 2026', 'rice and corn parcels', 'dry and wet rice parcels',
            'fish parcels', 'livestock farmers', 'parcels with drought damage', 'parcels owned by women',
            'show me the map', 'mango parcels', 'harvested rice parcels', 'rice parcels 1989', 'rice parcels 2030',
            'parcels without a boundary 2026', 'rice parcels needing a field visit', 'rice parcels last season',
            str_repeat('rice ', 41),
        ]);
    }

    public function test_vocabulary_covers_every_parcel_crop_and_never_matches_species_names(): void
    {
        $interpreter = new MapQuestionInterpreter;
        foreach (ParcelCropSeason::CROPS as $code => $label) {
            if ($code === 'not_recorded') {
                continue;
            }
            $this->assertSame([$code], $interpreter->crops(MapQuestionInterpreter::normalize($label)), $label);
        }
        foreach (['mango', 'banana', 'tilapia', 'peanut', 'ampalaya'] as $species) {
            $this->assertSame([], $interpreter->crops($species));
        }
        $this->assertSame('san isidro', MapQuestionInterpreter::place('Brgy. SAN ISIDRO'));
    }
}
