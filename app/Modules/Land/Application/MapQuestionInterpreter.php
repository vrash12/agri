<?php

namespace App\Modules\Land\Application;

use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;

/**
 * Turns a short typed map question into one allowlisted map filter.
 *
 * Pure and local: fixed vocabularies and bounded patterns only, no database,
 * provider or model call. Anything it does not recognise, or recognises as an
 * unsupported qualifier, becomes a clarification rather than a wider result.
 */
final class MapQuestionInterpreter
{
    public const MAX_LENGTH = 200;

    public const INTENTS = ['crop', 'no_harvest', 'no_boundary', 'without_parcel', 'field_visit'];

    public const EXAMPLES = [
        'corn parcels with no harvest this wet season',
        'parcels without a boundary',
        'farmers without a parcel in San Isidro',
        'rice parcels 2026 dry season',
        'parcels needing a field visit',
    ];

    /** Category words only; a specific species never widens to its whole category. */
    private const CROP_ALIASES = [
        'rice' => ['palay'],
        'corn' => ['maize', 'mais'],
        'vegetables' => ['vegetable', 'veggies', 'gulay'],
        'root_crops' => ['root crop', 'rootcrop', 'rootcrops'],
        'fruit' => ['fruits', 'prutas'],
        'legumes' => ['legume', 'pulses'],
        'mixed' => ['mixed crop', 'intercrop', 'intercropped'],
        'other' => ['other crop', 'other crops'],
    ];

    /** Harvest commodities that are never a parcel crop classification. */
    private const NON_PARCEL_COMMODITIES = ['fish', 'livestock', 'poultry'];

    private const UNSUPPORTED = [
        'write' => '/\b(?:delete|remove|update|edit|change|assign|create|save|import|upload|rename|recolou?r)\b/u',
        'export' => '/\b(?:export|download|print)\b/u',
        'measure' => '/\b(?:more|less|greater|fewer|bigger|smaller|larger|over|under|above|below)\s+than\b|\b(?:at\s+least|at\s+most)\b|\b\d+(?:\.\d+)?\s*(?:ha|hectares?|sqm|square)\b/u',
        'rank' => '/\b(?:largest|biggest|smallest|top|most|least|highest|lowest|rank(?:ed|ing)?|best|worst)\b/u',
        'compare' => '/\b(?:compare[ds]?|comparison|versus|vs|trend|between)\b/u',
        'diagnosis' => '/\b(?:disease[sd]?|pests?|infest\w*|drought|flood(?:ed|ing)?|damaged?|sick|yield|stress(?:ed)?)\b/u',
        'person' => '/\b(?:women|woman|female|men|male|gender|age|senior|youth|pwd|4ps|beneficiar\w*|assistance|subsid\w*|seeds?|fertili[sz]er\w*|names?|named|owned\s+by)\b/u',
    ];

    /**
     * @param  array{year:int,season:string}  $context  the map's applied period
     * @param  list<string>  $knownPlaces  normalized names the executor may match within scope
     * @return array<string, mixed>
     */
    public function interpret(string $question, array $context, int $currentYear): array
    {
        $text = self::normalize($question);
        if ($text === '') {
            return $this->clarify('Type a question about parcels or farmers on this map.');
        }
        if (mb_strlen($question) > self::MAX_LENGTH) {
            return $this->clarify('Keep the question under '.self::MAX_LENGTH.' characters.');
        }

        foreach (self::UNSUPPORTED as $kind => $pattern) {
            if (preg_match($pattern, $text)) {
                return $this->clarify(match ($kind) {
                    'write' => 'The map question box only reads saved records. Use the parcel and crop tools to change records.',
                    'export' => 'Use Export parcels or Download municipality map for files. The question box only highlights parcels.',
                    'measure' => 'Area or quantity limits are not supported yet. Ask about crops, harvests, boundaries or field visits.',
                    'rank' => 'Rankings are not supported. Ask for a group of parcels instead, such as rice parcels this dry season.',
                    'compare' => 'Comparisons are not supported. Ask about one year and one season at a time.',
                    'diagnosis' => 'The map cannot diagnose crop problems. Try “parcels needing a field visit” to plan an inspection.',
                    default => 'Questions about people, assistance or names are not supported here. Use the registry or the Assistant.',
                });
            }
        }
        foreach (self::NON_PARCEL_COMMODITIES as $commodity) {
            if (preg_match('/\b'.$commodity.'\b/u', $text)) {
                return $this->clarify(HarvestRecord::COMMODITY_LABELS[$commodity].' is recorded as a harvest commodity, not a parcel crop. Ask about a parcel crop such as rice or corn.');
            }
        }

        $years = $this->years($text, $currentYear);
        if ($years === null) {
            return $this->clarify('Use one year between 1990 and '.($currentYear + 1).'.');
        }
        if (count($years) > 1) {
            return $this->clarify('Ask about one year at a time.');
        }
        $seasons = $this->seasons($text);
        if (count($seasons) > 1) {
            return $this->clarify('Ask about one season at a time: dry or wet.');
        }
        if (preg_match('/\b(?:last|previous|next)\s+season\b/u', $text)) {
            return $this->clarify('Name the season and year, for example “2026 dry season”.');
        }
        $crops = $this->crops($text);
        if (count($crops) > 1) {
            return $this->clarify('Ask about one crop at a time.');
        }

        $intent = match (true) {
            (bool) preg_match('/\bfarmers?\b/u', $text) && (bool) preg_match('/\b(?:without|no|missing|walang|lacking)\s+(?:a\s+|any\s+)?(?:saved\s+)?(?:parcels?|plots?|land|boundar(?:y|ies))\b|\bunmapped\b|\bnot\s+mapped\b/u', $text) => 'without_parcel',
            (bool) preg_match('/\b(?:without|no|missing|walang|lacking)\s+(?:a\s+|any\s+)?(?:boundar(?:y|ies)|geometry|polygons?|outlines?)\b|\bunmapped\b|\bnot\s+(?:mapped|plotted|drawn)\b/u', $text) => 'no_boundary',
            (bool) preg_match('/\b(?:field\s*visits?|visits?|inspections?|inspect|field\s*checks?|follow[\s-]?ups?|overdue|priority|falling\s+(?:ndvi|greenness|readings?))\b/u', $text) => 'field_visit',
            (bool) preg_match('/\b(?:no|without|missing|walang)\s+(?:recorded\s+)?harvests?\b|\bnot\s+(?:yet\s+)?harvested\b|\bunharvested\b|\bharvests?\s+(?:not|never)\s+recorded\b/u', $text) => 'no_harvest',
            (bool) preg_match('/\b(?:no|without|missing)\s+(?:a\s+)?crop\s+records?\b|\bno\s+crops?\s+recorded\b|\bunrecorded\s+crops?\b/u', $text) => 'not_recorded',
            (bool) preg_match('/\bharvest/u', $text) => 'harvested',
            $crops !== [] => 'crop',
            default => null,
        };
        if ($intent === null) {
            return $this->clarify('I could not match that to a map filter.');
        }
        if ($intent === 'harvested') {
            return $this->clarify('I can find parcels with no harvest recorded, not parcels with a harvest. Try “corn parcels with no harvest this wet season”.');
        }
        if ($intent === 'not_recorded') {
            $intent = 'crop';
            $crops = ['not_recorded'];
        }
        if (in_array($intent, ['no_boundary', 'without_parcel'], true) && ($crops !== [] || $seasons !== [] || $years !== [])) {
            return $this->clarify('Boundary and parcel-coverage checks describe current records, not a crop season. Remove the crop, year or season.');
        }
        if ($intent === 'field_visit' && $crops !== []) {
            return $this->clarify('The field-visit layer covers every loaded parcel. Remove the crop, or ask for crop parcels with no harvest instead.');
        }

        // “this wet season” / “this year” mean the calendar year; otherwise the
        // map's applied period is the default, and the answer always names it.
        $year = $years[0] ?? (preg_match('/\b(?:this|current)\s+(?:(?:wet|dry|rainy)\s+)?(?:season|year)\b/u', $text)
            ? $currentYear : (int) $context['year']);

        return [
            'status' => 'ok',
            'intent' => $intent,
            'crop' => $crops[0] ?? null,
            'year' => in_array($intent, ['no_boundary', 'without_parcel'], true) ? null : $year,
            'season' => in_array($intent, ['no_boundary', 'without_parcel'], true) ? null : ($seasons[0] ?? $context['season']),
            'location_text' => $this->locationText($text),
            'normalized' => $text,
        ];
    }

    /** Lowercase, accent-folded text with punctuation removed. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '');
        $value = strtr($value, ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $value = preg_replace('/[^a-z0-9\s-]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** Normalized place name: barangay prefixes removed so “Brgy. San Isidro” matches “San Isidro”. */
    public static function place(string $value): string
    {
        return trim(preg_replace('/^(?:barangay|brgy|bgy)\s+/u', '', self::normalize($value)) ?? '');
    }

    /** @return list<string> */
    public function crops(string $text): array
    {
        $found = [];
        foreach (ParcelCropSeason::CROPS as $code => $label) {
            if ($code === 'not_recorded') {
                continue;
            }
            $words = array_merge([str_replace('_', ' ', $code)], self::CROP_ALIASES[$code] ?? [], array_map(
                fn (string $part) => self::normalize($part), explode('/', $label)));
            foreach (array_unique($words) as $word) {
                if ($code === 'other' && $word === 'other') {
                    continue;
                }
                if ($word !== '' && preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                    $found[$code] = true;
                }
            }
        }
        if (isset($found['mixed']) && count($found) > 1 && preg_match('/\bmixed\s+crops?\b/u', $text)) {
            $found = ['mixed' => true];
        }

        return array_keys($found);
    }

    /** @return list<string> */
    private function seasons(string $text): array
    {
        $found = [];
        if (preg_match('/\b(?:wet|rainy|tag-?ulan)\b/u', $text)) {
            $found[] = 'wet';
        }
        if (preg_match('/\b(?:dry|tag-?araw)\b/u', $text)) {
            $found[] = 'dry';
        }

        return $found;
    }

    /** @return list<int>|null null when a year is outside the supported range */
    private function years(string $text, int $currentYear): ?array
    {
        preg_match_all('/\b(\d{4})\b/u', $text, $matches);
        $years = array_values(array_unique(array_map('intval', $matches[1])));
        if (preg_match('/\blast\s+year\b/u', $text)) {
            $years[] = $currentYear - 1;
        }
        foreach ($years as $year) {
            if ($year < 1990 || $year > $currentYear + 1) {
                return null;
            }
        }

        return array_values(array_unique($years));
    }

    /** The place phrase after “in/at/sa”, minus period and crop words; matched later only within scope. */
    private function locationText(string $text): ?string
    {
        if (! preg_match('/\b(?:in|at|sa|from)\s+(?:barangay\s+|brgy\s+|bgy\s+)?([a-z0-9 -]{2,60})$/u', $text, $match)
            && ! preg_match('/\b(?:in|at|sa|from)\s+(?:barangay\s+|brgy\s+|bgy\s+)?([a-z0-9 -]{2,60}?)\s+(?:with|without|this|for|during|\d{4})\b/u', $text, $match)) {
            return null;
        }
        $stop = '/\b(?:the|this|that|current|my|our|view|map|area|scope|season|seasons|year|wet|dry|rainy|tag-?ulan|tag-?araw|\d{4}|with|without|no|harvest|harvests|recorded|yet|parcels?|plots?|farmers?|for|during|of|and)\b/u';
        $place = trim(preg_replace('/\s+/u', ' ', preg_replace($stop, ' ', $match[1]) ?? '') ?? '');

        return $place === '' ? null : $place;
    }

    /** @return array<string, mixed> */
    private function clarify(string $message): array
    {
        return ['status' => 'clarify', 'message' => $message, 'examples' => self::EXAMPLES];
    }
}
