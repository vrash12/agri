<?php

namespace App\Modules\Assistant\Application;

/** Finite local phrasing rules; never infers permissions or sends text to AI. */
final class AssistantUtterance
{
    public static function normalize(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $guide = AssistantGuide::namePattern();
        for ($pass = 0; $pass < 3; $pass++) {
            $previous = $text;
            // A greeting with a task may address a guide. Standalone social
            // messages remain intact so they can preserve conversation context.
            $text = preg_replace('/^(?:(?:hi|hello|hey|kumusta|kamusta)\s+)?'.$guide.'\s*[,!:]\s*(?=\S)/iu', '', $text);
            $text = preg_replace('/^(?:please|paki)\s+|^(?:can|could|would)\s+you\s+(?:please\s+)?/iu', '', $text);
            if ($previous === $text) {
                break;
            }
        }

        return trim($text);
    }

    /**
     * null: not a lookup; []: lookup-shaped but ambiguous/unsupported.
     * One parser protects both routing and literal-name language handling.
     *
     * @return array{search?:string,year?:int}|null
     */
    public static function farmerLookup(string $text): ?array
    {
        $explicit = (bool) preg_match('/^(?:find|search|show(?:\s+me)?|hanapin)\s+(?:ang\s+)?(?:farmer|magsasaka)\s+(.+)$/iu', $text, $match);
        if (! $explicit && ! preg_match('/^(?:who\s+is|sino\s+si|find|search)\s+(.+)$/iu', $text, $match)) {
            return null;
        }
        $name = trim($match[1]);
        if (preg_match('/^(?:summary|summaries|report|records|totals)\b/iu', $name)) {
            return null;
        }
        $year = null;
        // Preserve the existing explicit lookup + reporting-year syntax.
        if ($explicit && preg_match('/\s+(?:(?:for|in|sa)\s+)?((?:19|20|21)\d{2})[.!?\s]*$/u', $name, $period, PREG_OFFSET_CAPTURE)) {
            $year = (int) $period[1][0];
            $name = trim(substr($name, 0, $period[0][1]));
            if ($year > 2100) {
                return [];
            }
        }
        $name = rtrim($name, " \t\n\r!? .");
        if (preg_match('/^(?:AGRI-F|PAIS-FRM)-\d{1,16}$/i', $name)) {
            return ['search' => $name] + ($year === null ? [] : ['year' => $year]);
        }
        // Conjunctions, request qualifiers and protected fields cannot be
        // interpreted as a name or fall through into an office-wide report.
        if (preg_match('/\b(?:and|also|then|at|pati|tsaka|show|summarize|ipakita|ibuod|harvests?|assistance|address(?:es)?|contacts?|birthdays?|birthdates?|phones?|mobile|telephone|emails?|notes?|passwords?|credentials?|coordinates?|latitude|longitude|eligibility|keys?|from|within|for|in|sa|province|municipality|region)\b/iu', $name)) {
            return [];
        }
        $part = "[\p{L}\p{M}]+(?:[’'\x{002D}][\p{L}\p{M}]+)*(?:\.)?";
        if (! preg_match('/^'.$part.'(?:\s+'.$part.'){'.($explicit ? '0' : '1').',7}$/u', $name)) {
            return [];
        }

        return ['search' => $name] + ($year === null ? [] : ['year' => $year]);
    }

    public static function indexComparison(string $text): bool
    {
        $index = '(?:NDVI|NDMI|SAVI|EVI)';
        $join = '(?:\s*,\s*(?:(?:and|at)\s+)?|\s+(?:and|with|versus|vs\.?|compared\s+to|at)\s+)';
        $list = $index.'(?:'.$join.$index.'){1,3}';
        $start = '(?:(?:what\s+is\s+(?:the\s+)?(?:difference\s+between\s+)?|explain\s+|compare\s+|difference\s+between\s+|how\s+do\s+I\s+(?:choose|compare)\s+))?';

        return (bool) preg_match('/^'.$start.$list.'(?:\s+(?:differ|work))?[.!?\s]*$/iu', $text)
            || (bool) preg_match('/^how\s+(?:are|do)\s+'.$list.'\s+(?:differ|different)[.!?\s]*$/iu', $text);
    }
}
