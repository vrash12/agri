<?php

namespace App\Modules\Assistant\Application;

use App\Models\HarvestRecord;
use App\Models\RiceSeedDistribution;

/**
 * Recognizes a record question about exactly one supported thing, so the reply
 * can answer it first instead of returning the whole module report.
 *
 * The vocabulary is fixed: one assistance input category (or the seed /
 * fisheries groups already defined by the model), one harvest commodity, the
 * assistance recipient count, and three current registry gaps. The matched
 * term is removed and the remainder is returned so the caller can run the
 * existing unsupported-subset, location and period guards on what is left; a
 * second term, an extra filter or a location must still clarify rather than
 * widen. This never builds a query and never sends text anywhere.
 */
final class AssistantRecordFocus
{
    public const REGISTRY = ['unmapped', 'missing-ffrs', 'missing-location'];

    // How-to and definition questions are help, wherever the instruction words appear.
    private const WORKFLOW = '/^(?:where|why|saan|bakit|can\s+i|could\s+i|should\s+i|explain|describe|tell\s+me\s+about|teach|guide|walk\s+me|steps?\b|what\s+(?:is|are)\s+(?:a|an|the)?\s*(?:fertili[sz]er|npk|seed))|\b(?:how\s+(?:do|does|can|to|should|would)|show\s+me\s+how|instructions?|step[\s-]+by[\s-]+step|paano|turuan|gabayan|ipaliwanag)\b/iu';

    private const ADVICE = '/\b(?:should\s+(?:i|we)|dapat|recommend(?:ed|ation)?|best|apply|application\s+rate|dose|dosage|per\s+hectare|kada\s+ektarya|price|prices|presyo|magkano)\b/iu';

    private const GIVEN = '/\b(?:give|gave|given|giving|distribut\w*|releas\w*|receiv\w*|provid\w*|hand(?:ed)?\s+out|assistance|ayuda|beneficiar(?:y|ies)|benepisyaryo|nabigyan|binigyan|natanggap|nakatanggap|tumanggap|ipinamigay|naipamigay|pinamigay|ibinigay|binigay|ipinamahagi|naipamahagi)\b/iu';

    private const HARVESTED = '/\b(?:harvest|harvests|harvested|ani|inani|production|produced)\b/iu';

    /**
     * @return array{task:string,focus:string|null,remainder:string}|array{ambiguous:true}|null
     */
    public function resolve(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || preg_match(self::WORKFLOW, $text)) {
            return null;
        }
        if ($registry = $this->registry($text)) {
            return $registry;
        }
        $given = (bool) preg_match(self::GIVEN, $text);
        $harvested = (bool) preg_match(self::HARVESTED, $text);
        if ($given && ! $harvested && ! preg_match(self::ADVICE, $text)) {
            if (($match = $this->firstTerm($text, $this->inputTerms())) !== null) {
                return $this->single($text, $match, 'assistance');
            }
            if (preg_match('/\bbeneficiar(?:y|ies)\b|\bbenepisyaryo\b|\b(?:farmers?|magsasaka)\s+(?:who\s+)?(?:received|got|were\s+given|nabigyan|nakatanggap|tumanggap)(?:\s+(?:any\s+)?(?:assistance|ayuda|help|tulong))?\b|\b(?:ilan|sino)\s+(?:ang\s+)?(?:mga\s+)?(?:nabigyan|nakatanggap)(?:\s+ng\s+(?:ayuda|tulong|assistance))?\b/iu', $text, $people, PREG_OFFSET_CAPTURE)) {
                return ['task' => 'assistance', 'focus' => 'beneficiaries', 'remainder' => $this->without($text, $people[0][1], strlen($people[0][0]))];
            }
        }
        if ($harvested && ! $given && ($match = $this->firstTerm($text, $this->commodityTerms())) !== null) {
            return $this->single($text, $match, 'harvests');
        }

        return null;
    }

    /** Whether a stored or resolved focus belongs to the task and the fixed vocabulary. */
    public static function valid(string $task, string $focus): bool
    {
        return match ($task) {
            'assistance' => in_array($focus, ['beneficiaries', 'seed', 'fisheries'], true)
                || (str_starts_with($focus, 'input:') && isset(self::inputCategories()[substr($focus, 6)])),
            'harvests' => str_starts_with($focus, 'commodity:') && isset(self::commodities()[substr($focus, 10)]),
            'data-quality' => in_array($focus, self::REGISTRY, true),
            default => false,
        };
    }

    /** Lower-case words for a focus, used in static follow-up prompts. */
    public static function phrase(string $focus): string
    {
        return match (true) {
            $focus === 'seed' => 'seeds',
            $focus === 'fisheries' => 'fisheries assistance',
            str_starts_with($focus, 'input:') => mb_strtolower(trim(explode('/', self::inputCategories()[substr($focus, 6)] ?? '')[0])),
            str_starts_with($focus, 'commodity:') => mb_strtolower(trim(explode('/', self::commodities()[substr($focus, 10)] ?? '')[0])),
            default => '',
        };
    }

    /** @return array<string,string> Specific input categories; "other" buckets are not a meaningful subject. */
    private static function inputCategories(): array
    {
        return array_diff_key(RiceSeedDistribution::INPUT_CATEGORY_LABELS, array_flip(['other_input', 'other_fisheries']));
    }

    /** @return array<string,string> */
    private static function commodities(): array
    {
        return array_diff_key(HarvestRecord::COMMODITY_LABELS, ['other' => true]);
    }

    /** @return array{task:string,focus:string|null,remainder:string}|null */
    private function registry(string $text): ?array
    {
        if (! preg_match('/\b(?:farmers?|magsasaka|profiles?)\b/iu', $text)) {
            return null;
        }
        $gap = '(?:without|with\s+no|(?:have|has|had)\s+no|(?:do|does)\s+not\s+have|don[’\']?t\s+have|doesn[’\']?t\s+have|no|missing|lacking|walang|wala\s+pang)';
        $patterns = [
            'unmapped' => '/\bunmapped\b|\b'.$gap.'\s+(?:(?:a|an|any|saved|mapped|plotted|drawn|land|farm|na)\s+)*(?:parcels?|plots?|lupa|mapa|boundar(?:y|ies))\b|\bnot\s+(?:yet\s+)?mapped\b/iu',
            'missing-ffrs' => '/\b'.$gap.'\s+(?:(?:a|an|any|saved|recorded|na)\s+)*ffrs(?:\s+(?:numbers?|no\.?|references?|ids?))?\b/iu',
            'missing-location' => '/\b'.$gap.'\s+(?:(?:a|an|any|saved|recorded|na)\s+)*(?:farm\s+)?locations?\b/iu',
        ];
        foreach ($patterns as $focus => $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                return ['task' => 'data-quality', 'focus' => $focus, 'remainder' => $this->without($text, $match[0][1], strlen($match[0][0]))];
            }
        }
        // "Farmers with saved parcels" is the registry report's lead figure.
        if (preg_match('/\bmapped\s+(?:farmers?|magsasaka)\b|\b(?:with|have|has|having|may)\s+(?:(?:a|an|any|saved|mapped|plotted|drawn|na)\s+)*(?:parcels?|plots?|boundar(?:y|ies))\b/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
            return ['task' => 'farmers', 'focus' => null, 'remainder' => $this->without($text, $match[0][1], strlen($match[0][0]))];
        }

        return null;
    }

    /** @return array{task:string,focus:string,remainder:string}|array{ambiguous:true} */
    private function single(string $text, array $match, string $task): array
    {
        $remainder = $this->without($text, $match['offset'], $match['length']);
        // A second subject would make one total answer only part of the question.
        if ($this->firstTerm($remainder, [...$this->inputTerms(), ...$this->commodityTerms()]) !== null) {
            return ['ambiguous' => true];
        }

        return ['task' => $task, 'focus' => $match['focus'], 'remainder' => $remainder];
    }

    /**
     * Longest term first, so "fish fingerlings" wins over "fish".
     *
     * @param  array<string,string>  $terms  pattern => focus
     * @return array{focus:string,offset:int,length:int}|null
     */
    private function firstTerm(string $text, array $terms): ?array
    {
        uksort($terms, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        foreach ($terms as $pattern => $focus) {
            if (preg_match('/(?<![\p{L}\d-])'.$pattern.'(?![\p{L}\d-])/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
                return ['focus' => $focus, 'offset' => $match[0][1], 'length' => strlen($match[0][0])];
            }
        }

        return null;
    }

    /** @return array<string,string> */
    private function inputTerms(): array
    {
        $terms = [];
        foreach (self::inputCategories() as $key => $label) {
            foreach ([str_replace('_', ' ', $key), ...explode('/', $label)] as $term) {
                $terms[$this->termPattern($term)] = 'input:'.$key;
            }
        }

        // Common spellings for existing categories and the model's own groups.
        return $terms + [
            'fertili[sz]ers?' => 'input:fertilizer', 'pataba' => 'input:fertilizer', 'abono' => 'input:fertilizer',
            'fingerlings?' => 'input:fish_fingerlings', 'semilya' => 'input:fish_fingerlings',
            'seeds?' => 'seed', 'binhi' => 'seed', 'fisheries(?:\s+(?:assistance|inputs?))?' => 'fisheries',
        ];
    }

    /** @return array<string,string> */
    private function commodityTerms(): array
    {
        $terms = [];
        foreach (self::commodities() as $key => $label) {
            foreach ([$key, ...explode('/', $label)] as $term) {
                $terms[$this->termPattern($term)] = 'commodity:'.$key;
            }
        }

        return $terms + ['root\s+crops?' => 'commodity:rootcrop', 'mais' => 'commodity:corn', 'maize' => 'commodity:corn',
            'gulay' => 'commodity:vegetable', 'prutas' => 'commodity:fruit', 'isda' => 'commodity:fish', 'manok' => 'commodity:poultry'];
    }

    private function termPattern(string $term): string
    {
        $term = preg_replace('/s$/i', '', mb_strtolower(trim($term)));

        return str_replace(' ', '\s+', preg_quote($term, '/')).'(?:e?s)?';
    }

    private function without(string $text, int $offset, int $length): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', substr($text, 0, $offset).' '.substr($text, $offset + $length)));
    }
}
