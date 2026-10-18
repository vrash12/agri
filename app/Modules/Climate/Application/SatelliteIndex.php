<?php

namespace App\Modules\Climate\Application;

use InvalidArgumentException;

/** Application-owned formulas and presentation; request text never becomes an evalscript. */
final class SatelliteIndex
{
    public const IDS = ['ndvi', 'ndmi', 'savi', 'evi'];

    public static function definition(string $id): array
    {
        $common = [
            'id' => $id, 'resolution_m' => 10, 'minimum' => -1, 'maximum' => 1,
            'stops' => [-1, 0, 0.2, 0.5, 1],
            'colors' => ['#334da6', '#a67a4d', '#f2d44d', '#80b840', '#0f5226'],
            'legend_low' => 'Less green', 'legend_high' => 'More green',
        ];

        $specific = match ($id) {
            'ndvi' => [
                'name' => 'Plant greenness', 'choice' => 'NDVI · General crop check',
                'formula' => '(B08 − B04) / (B08 + B04)',
                'help' => 'Shows how green the plants look from space. Start here for a general look at the field.',
                'reading_help' => 'Higher numbers usually mean more green plants. A low number can also mean bare soil, water or a recent harvest.',
                'legend_note' => 'More green usually means more green plants. Brown or yellow can be bare soil or a recently harvested field.',
                'next_step' => 'Look at the picture, then check the plants in the field.',
            ],
            'ndmi' => [
                'name' => 'Water signal in plants', 'choice' => 'NDMI · Possible lack of water',
                'resolution_m' => 20, 'formula' => '(B08 − B11) / (B08 + B11)',
                'help' => 'Helps you look for signs that plants may be short of water. Check the field before deciding to water it.',
                'reading_help' => 'Higher numbers usually suggest more water in the plants. A low number alone does not prove drought. Standing water and bare soil can affect it.',
                'legend_low' => 'Lower water signal', 'legend_high' => 'Higher water signal',
                'legend_note' => 'Brown shows a lower water signal. Blue-green shows a higher one. Flooded fields can change the result.',
                'stops' => [-1, -0.25, 0, 0.25, 1],
                'colors' => ['#8c510a', '#dfc27d', '#f5f5dc', '#5ab4ac', '#01665e'],
                'next_step' => 'Check recent rain and irrigation, then look at the plants and soil.',
            ],
            'savi' => [
                'name' => 'Plant cover with bare soil', 'choice' => 'SAVI · Young or widely spaced crops',
                'formula' => '1.5 × (B08 − B04) / (B08 + B04 + 0.5)',
                // With harmonized nonnegative reflectance SAVI lies within ±1.5, not necessarily ±1.
                'minimum' => -1.5, 'maximum' => 1.5,
                'help' => 'Use this when plants are small or far apart. It helps stop bare soil from confusing the picture.',
                'reading_help' => 'Higher numbers usually mean more green plant cover. This does not tell you how fertile the soil is.',
                'legend_note' => 'Use these colours when there is still a lot of bare soil between the plants.',
                'next_step' => 'Look at how much of the ground the plants cover. Check thin or empty spots in the field.',
            ],
            'evi' => [
                'name' => 'Thick, leafy plant cover', 'choice' => 'EVI · Thick, leafy crops',
                'formula' => '2.5 × (B08 − B04) / (B08 + 6 × B04 − 7.5 × B02 + 1)',
                // A numerical safety envelope, not a biological range. Raw EVI can exceed ±1.
                'minimum' => -1.0e9, 'maximum' => 1.0e9,
                'help' => 'Use this when plants have lots of leaves and cover most of the ground. It can show changes that NDVI may miss.',
                'reading_help' => 'Higher numbers usually mean more green plant cover. Compare crops at the same stage of growth.',
                'legend_note' => 'Use these colours for thick, leafy crops. Colour alone does not tell you if a crop is healthy.',
                'next_step' => 'Compare pictures from a similar stage of growth, then check any changes in the field.',
            ],
            default => throw new InvalidArgumentException('Choose NDVI, NDMI, SAVI or EVI.'),
        };

        return array_replace($common, $specific);
    }

    public static function definitions(): array
    {
        return array_combine(self::IDS, array_map(self::definition(...), self::IDS));
    }

    public static function input(string $id): array
    {
        $bands = match ($id) {
            'ndvi', 'savi' => ['B04', 'B08'],
            'ndmi' => ['B08', 'B11'],
            'evi' => ['B02', 'B04', 'B08'],
            'true-color' => ['B02', 'B03', 'B04', 'B08'],
            default => throw new InvalidArgumentException('Unsupported satellite layer.'),
        };

        return ['bands' => [...$bands, 'SCL', 'dataMask'],
            'units' => [...array_fill(0, count($bands), 'REFLECTANCE'), 'DN', 'DN']];
    }

    /** Identical masked values drive statistics and index images. Near-zero divisors are no-data. */
    public static function calculation(string $id): string
    {
        $definition = self::definition($id);
        [$numerator, $denominator] = match ($id) {
            'ndvi' => ['s.B08-s.B04', 's.B08+s.B04'],
            'ndmi' => ['s.B08-s.B11', 's.B08+s.B11'],
            'savi' => ['1.5*(s.B08-s.B04)', 's.B08+s.B04+0.5'],
            'evi' => ['2.5*(s.B08-s.B04)', 's.B08+6*s.B04-7.5*s.B02+1'],
        };
        $bands = json_encode(array_slice(self::input($id)['bands'], 0, -2), JSON_THROW_ON_ERROR);
        $min = json_encode($definition['minimum'], JSON_THROW_ON_ERROR);
        $max = json_encode($definition['maximum'], JSON_THROW_ON_ERROR);

        return 'function indexValue(s) { if (s.dataMask !== 1 || [4,5,6].indexOf(s.SCL) === -1 || !'.$bands.
            '.every(function(b) { return typeof s[b] === "number" && isFinite(s[b]) && s[b] >= 0; })) return null; '.
            'var d = '.$denominator.'; if (!isFinite(d) || Math.abs(d) <= 0.000001) return null; '.
            'var v = ('.$numerator.')/d; return isFinite(v) && v >= '.$min.' && v <= '.$max.' ? v : null; }';
    }
}
