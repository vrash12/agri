<?php

namespace App\Support;

/** Bounded task routing. Unknown text is never submitted to an external model. */
final class AssistantIntentResolver
{
    /** @return array{task:string,year:int,farmer_id?:int|null,search?:string,topic?:string,language?:string,clarification?:string,reset_context?:bool,references?:array{task:string,year:int,farmer_id:int|null}} */
    public function resolve(string $message, array $context, string $module, int $currentYear): array
    {
        $text = trim($message);
        $year = (int) ($context['year'] ?? $currentYear);
        $language = preg_match('/\b(paano|saan|gabayan|turuan|ipaliwanag|hakbang|magsasaka|binhi|ayuda|buod|hanapin|hayop|taon|natin|niya)\b/iu', $text) ? 'fil' : 'en';
        if (preg_match('/\b(compare|compared|comparison|versus|vs|trend|trends|ihambing|kumpara|year[\s-]+(?:over|on)[\s-]+year)\b/iu', $text)
            || preg_match('/\b(?:this\s+year|ngayong\s+taon)\b.+\b(?:last\s+year|previous\s+year|nakaraang\s+taon)\b|\b(?:last\s+year|previous\s+year|nakaraang\s+taon)\b.+\b(?:this\s+year|ngayong\s+taon)\b/iu', $text)
            || preg_match('/\b\d{4}\b\s+(?:and|with|at)\s+(?:last\s+year|previous\s+year|nakaraang\s+taon)\b/iu', $text)) {
            return $this->yearClarification($text, $context, $year, comparison: true);
        }
        $workflow = (bool) preg_match('/\b(how\s+(?:do|does|can|to|should)|where\s+(?:do|can|is)|can\s+I|I\s+want\s+to|steps|instructions|guide\s+me|walk\s+me\s+through|show\s+me\s+how|paano|saan|gabayan|turuan|ipaliwanag|hakbang|help\s+(?:me\s+)?(?:use|draw|import|with)|tell\s+me\s+about|explain|what\s+can\s+(?:(?:my|our|the)\s+(?:veterinary\s+)?account|I\s+access)|what\s+(?:is|does)\s+(?:ndvi|the\s+assistant))\b/iu', $text);
        if ($workflow) {
            if (preg_match('/\b(?:and|also|then|at|pati|tsaka)\s+(?:summarize|show|report|count|buod|ibuod|ipakita|bilangin)\b/iu', $text)) {
                return $this->clarify('Would you like system instructions or a records report first? I can handle one task at a time.', $year);
            }
            if ($this->hasMixedHelpWorkflows($text, $module)) {
                return $this->clarify('Which system task would you like instructions for first? Ask about one workflow at a time, such as drawing crops or importing seed releases.', $year);
            }
            $topic = $this->helpTopic($text, $module);

            return $topic ? ['task' => 'help', 'topic' => $topic, 'year' => $year, 'language' => $language]
                : $this->clarify('Which system task do you need help with: drawing parcels or crops, seed imports, harvest entry, farmer IDs, sign-in, NDVI, or Animal Health?', $year);
        }
        if (preg_match('/^(?:please\s+)?(?:delete|remove|update|edit|change|create|reset|issue|approve|assign|send|burahin|baguhin)\b/iu', $text)) {
            return $this->clarify('This chat reads records and explains system steps. It cannot change records, issue accounts, send messages or approve requests. Use the authorized module for that action.', $year);
        }
        if ($this->requestsDifferentScope($text)) {
            return $this->clarify('I can summarize your account’s permitted area. A separate province or municipality filter is not available in this chat yet. Use the register’s location filters for that subset, or ask about your current area.', $year) + ['reset_context' => true];
        }
        preg_match_all('/(?<![\p{L}\d-])\d{4}(?![\p{L}\d-])/u', $text, $matches);
        $years = array_values(array_unique($matches[0]));
        if (count($years) > 1 || ($years && ((int) $years[0] < 1900 || (int) $years[0] > 2100))) {
            return $this->yearClarification($text, $context, $year);
        }
        if ($years) {
            $year = (int) $years[0];
        } elseif (preg_match('/\b(last\s+year|nakaraang\s+taon|noong\s+nakaraang\s+taon)\b/iu', $text)) {
            $year = $currentYear - 1;
        } elseif (preg_match('/\b(previous\s+year|prior\s+year|taon\s+bago\s+iyon)\b/iu', $text)) {
            $year = (int) ($context['year'] ?? $currentYear) - 1;
        } elseif (preg_match('/\b(this\s+year|current\s+year|ngayong\s+taon)\b/iu', $text)) {
            $year = $currentYear;
        } elseif (preg_match('/\b(same\s+year|that\s+year|parehong\s+taon|taong\s+iyon)\b/iu', $text) && ! isset($context['year'])) {
            return $this->yearClarification($text, $context, $year);
        }
        if ($year < 1900 || $year > 2100) {
            return $this->clarify('Choose one reporting year between 1900 and 2100.', $currentYear);
        }
        if (preg_match('/\b(?:AGRI-F|PAIS-FRM)-\d{1,16}\b/i', $text, $id)) {
            return ['task' => 'farmer-search', 'search' => $id[0], 'year' => $year];
        }
        if (preg_match('/^(?:find|search|show|hanapin)\s+(?:ang\s+)?(?:farmer|magsasaka)\s+(.+)$/iu', $text, $search)
            && ! preg_match('/^(?:summary|summaries|report|records|totals)$/iu', trim($search[1]))) {
            $name = preg_replace('/\s+(?:(?:for|in|sa)\s+)?\d{4}\s*$/u', '', trim($search[1]));

            return ['task' => 'farmer-search', 'search' => trim($name), 'year' => $year];
        }
        $personal = $this->personalReference($text);
        $collective = $this->collectiveReference($text);
        if ($personal && $collective) {
            return $this->clarify('Do you mean the selected farmer or your whole office area?', $year);
        }
        $tasks = $this->recordTasks($text);
        if ($personal && preg_match('/\b(crop|crops|pananim)\b/iu', $text) && ! in_array('farmers', $tasks, true)) {
            $tasks[] = 'farmers';
        }
        if (! $personal && ! $tasks && preg_match('/\b(crop|crops|pananim)\b/iu', $text)) {
            return $this->clarify('I can show seasonal crop records for a selected farmer. Find the farmer first, then ask for their crops and reporting year.', $year);
        }
        if ($personal) {
            if (empty($context['farmer_id'])) {
                return $this->clarify('Which farmer do you mean? Type “Find farmer” followed by their name or AgriLGU ID, then choose the matching record.', $year);
            }
            if (in_array('animal-health', $tasks, true) || (! $tasks && ! preg_match('/\b(summary|profile|record|records|info|information|details|buod)\b/iu', $text))) {
                return $this->clarify('For the selected farmer, I can show their recorded summary, saved parcels and crops, assistance or harvests. Which would you like?', $year);
            }
            $task = count($tasks) > 1 ? 'farmer' : match ($tasks[0] ?? '') {
                'assistance' => 'farmer-assistance', 'harvests' => 'farmer-harvests',
                'farmers' => preg_match('/\b(parcel|parcels|mapping|lupa|crop|crops)\b/iu', $text) ? 'farmer-parcels' : 'farmer',
                default => 'farmer',
            };

            return ['task' => $task, 'year' => $year, 'farmer_id' => (int) $context['farmer_id']];
        }
        if (count($tasks) > 1) {
            return $this->clarify('I can show one report at a time, or a workspace summary covering farmers, assistance and harvests. Which would you like?', $year);
        }
        if ($tasks) {
            if (! $collective && ! empty($context['farmer_id']) && in_array($tasks[0], ['assistance', 'harvests'], true)) {
                return $this->clarify('Do you mean the selected farmer’s records or the report for your whole office area? Say “his assistance” or “our assistance”, for example.', $year);
            }

            return ['task' => $tasks[0], 'year' => $year, 'farmer_id' => null];
        }
        if (preg_match('/\b(summary|summarize|overview|workspace|buod)\b/iu', $text)) {
            return ['task' => 'summary', 'year' => $year, 'farmer_id' => null];
        }
        if ($this->periodFollowUp($text) && isset($context['task']) && $context['task'] !== 'help') {
            if ($context['task'] === 'farmer-search') {
                return $this->clarify('Choose a farmer from the search matches first, then ask for that year.', $year);
            }

            return ['task' => $context['task'], 'year' => $year, 'farmer_id' => $context['farmer_id'] ?? null];
        }
        if (preg_match('/^(?:hello|hi|help|kumusta|tulong)[.!?\s]*$/iu', $text)) {
            return $this->clarify('Ask about your farmers, seed assistance, harvests or Animal Health, or ask how to use a system feature. For example: “How many farmers do we have?” or “How do I draw a corn area?”', $year);
        }

        return $this->clarify('I can answer supported system questions and read permitted records. Try “Summarize my farmers”, “Show assistance for 2025”, “Find farmer Juan”, or ask how to use a feature.', $year);
    }

    private function helpTopic(string $text, string $module): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(ndvi|satellite)\b/iu', $text) => 'ndvi',
            (bool) preg_match('/\b(harvest|harvests|ani)\b/iu', $text) => 'harvest-entry',
            (bool) preg_match('/\b(animal|animals|vaccination|vaccinations|deworming|dog|dogs|cow|cows|hayop)\b/iu', $text) => 'animals',
            (bool) preg_match('/\b(kml|kmz)\b/iu', $text) => 'parcel-drawing',
            (bool) preg_match('/\b(seed|seeds|binhi|nrp)\b/iu', $text) => 'seed-import',
            (bool) preg_match('/\b(farmer|farmers|portal|magsasaka)\b/iu', $text) && (bool) preg_match('/\b(login|log\s*in|sign\s*in|password)\b/iu', $text) => 'farmer-login',
            (bool) preg_match('/\b(card|cards|qr|signature|id)\b/iu', $text) => 'farmer-id',
            (bool) preg_match('/\b(access|permission|permissions|role|roles|account|accounts|login|sign\s*in)\b/iu', $text) => 'access',
            (bool) preg_match('/\b(crop|crops|corn|maize|rice|planted|planting|mais|palay|pananim)\b/iu', $text) => 'crop-drawing',
            (bool) preg_match('/\b(parcel|parcels|plot|plots|boundary|boundaries|map|mapping|field\s+boundary|lupa|mapa|hangganan)\b/iu', $text) => 'parcel-drawing',
            $module === 'assistance' && (bool) preg_match('/\b(import|upload|workbook|spreadsheet|excel)\b/iu', $text) => 'seed-import',
            (bool) preg_match('/\b(system|assistant|agrilgu)\b/iu', $text) => 'help',
            default => null,
        };
    }

    private function hasMixedHelpWorkflows(string $text, string $module): bool
    {
        // Distinct action clauses need a choice. Crop/parcel nouns in one
        // instruction remain context: "draw corn inside the parcel" is one task.
        $clauses = preg_split('/\s+(?:and(?:\s+also)?|also|then|as\s+well\s+as|at|pati|tsaka|pagkatapos)\s+/iu', $text);
        $firstTopic = $this->helpTopic($clauses[0], $module);
        foreach (array_slice($clauses, 1) as $clause) {
            if (! preg_match('/^(?:(?:I|to|how\s+(?:do|can|to)|paano)\s+)?(?:draw|drawing|import|importing|upload|record|recording|print|printing|export|open|use|explain|login|log\s*in|sign\s*in|delete|reset|send|gumuhit|mag-import|mag-upload|magtala|i-print|buksan|ipaliwanag)\b|^(?:seed|parcel|crop|farmer\s+(?:id|card)|animal(?:\s+health)?)\s+(?:import|imports|drawing|entry|recording|printing|services)\b/iu', $clause)) {
                continue;
            }
            $nextTopic = $this->helpTopic($clause, $module);
            if ($nextTopic === null || $nextTopic !== $firstTopic) {
                return true;
            }
        }

        return false;
    }

    private function requestsDifferentScope(string $text): bool
    {
        // Remove only known account-area and time references. A remaining named
        // location is not silently replaced with account-wide report totals.
        $text = preg_replace('/\b(?:my|our|own|current|assigned|permitted|ating|aming)\s+(?:(?:current|assigned|permitted)\s+)?(?:office|area|scope|system)\b/iu', '', $text);
        $text = preg_replace('/\b(?:in|sa)\s+(?:the\s+)?(?:system|records|register)\b/iu', '', $text);
        $text = preg_replace('/\b(?:in|for|during|from|sa|noong|ng)\s+(?:the\s+)?(?:same|that|this|current|previous|prior|last)\s+year\b/iu', '', $text);
        $text = preg_replace('/\b(?:sa|noong|ng)\s+(?:parehong|nakaraang|kasalukuyang)\s+taon\b|\b(?:sa|noong|ng)\s+(?:ngayong\s+taon|taong\s+iyon)\b/iu', '', $text);

        return (bool) preg_match('/\b(?:province|provinces|municipality|municipalities|region|regions)\b|\b(?:in|within|from|sa)\s+[\p{L}]/iu', $text);
    }

    private function clarify(string $message, int $year): array
    {
        return ['task' => 'clarification', 'year' => $year, 'clarification' => $message];
    }

    private function yearClarification(string $text, array $context, int $year, bool $comparison = false): array
    {
        if ($this->requestsDifferentScope($text)) {
            return $this->clarify('A separate province or municipality filter is not available in this chat yet. Use the register’s location filters for that subset, or ask about your account’s current area and one reporting year.', $year) + ['reset_context' => true];
        }
        $references = $this->pendingReport($text, $context, $year);
        $prefix = $comparison ? 'Year comparisons are not available in this chat yet. ' : '';
        if ($references === null) {
            return $this->clarify($prefix.'Restate one report and one valid reporting year, for example “Show assistance for 2025”.', $year) + ['reset_context' => true];
        }

        return $this->clarify($prefix.'Choose one reporting year between 1900 and 2100. Which year should I use?', $year)
            + ['references' => $references];
    }

    /** Only minimal application-owned references survive a year clarification. */
    private function pendingReport(string $text, array $context, int $year): ?array
    {
        $tasks = $this->recordTasks($text);
        $personal = $this->personalReference($text);
        $collective = $this->collectiveReference($text);
        if (count($tasks) > 1 || ($personal && $collective)) {
            return null;
        }
        if ($personal) {
            if (empty($context['farmer_id'])) {
                return null;
            }
            $task = match ($tasks[0] ?? '') {
                'assistance' => 'farmer-assistance', 'harvests' => 'farmer-harvests',
                'farmers' => preg_match('/\b(parcel|parcels|mapping|lupa|crop|crops)\b/iu', $text) ? 'farmer-parcels' : 'farmer',
                default => null,
            };

            return $task ? ['task' => $task, 'year' => $year, 'farmer_id' => (int) $context['farmer_id']] : null;
        }
        if ($tasks) {
            if (! in_array($tasks[0], ['assistance', 'harvests', 'animal-health'], true)
                || (! $collective && ! empty($context['farmer_id']) && in_array($tasks[0], ['assistance', 'harvests'], true))) {
                return null;
            }

            return ['task' => $tasks[0], 'year' => $year, 'farmer_id' => null];
        }
        if (preg_match('/\b(summary|summarize|overview|workspace|buod)\b/iu', $text)) {
            return ['task' => 'summary', 'year' => $year, 'farmer_id' => null];
        }
        $task = $context['task'] ?? null;
        if ($this->periodFollowUp($text) && in_array($task, ['assistance', 'harvests', 'animal-health', 'summary', 'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests'], true)) {
            return ['task' => $task, 'year' => $year, 'farmer_id' => $context['farmer_id'] ?? null];
        }

        return null;
    }

    /** @return list<string> */
    private function recordTasks(string $text): array
    {
        $tasks = [];
        foreach (['assistance' => '/\b(assistance|seed|seeds|distribution|distributions|release|releases|binhi|ayuda)\b/iu',
            'harvests' => '/\b(harvest|harvests|production|ani)\b/iu',
            'animal-health' => '/\b(animal|animals|vaccination|vaccinations|deworming|hayop|veterinary)\b/iu',
            'farmers' => '/\b(farmer|farmers|parcel|parcels|mapping|magsasaka|lupa)\b/iu'] as $task => $pattern) {
            if (preg_match($pattern, $text)) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    private function personalReference(string $text): bool
    {
        return (bool) preg_match('/\b(his|her|their|this\s+farmer|that\s+farmer|selected\s+farmer|niya|kaniya|kanya|kanyang|siyang)\b/iu', $text);
    }

    private function collectiveReference(string $text): bool
    {
        return (bool) preg_match('/\b(our|my\s+(?:farmers|assistance|harvests)|all\s+farmers|natin|ating|aming)\b/iu', $text);
    }

    private function periodFollowUp(string $text): bool
    {
        $period = '(?:\d{4}|(?:the\s+)?(?:same|that|last|previous|prior|this|current)\s+year|(?:parehong|nakaraang|ngayong)\s+taon|taong\s+iyon)';

        return (bool) preg_match('/^(?:(?:(?:what|how)\s+about|and|for|in|sa|noong)\s+)?'.$period.'(?:\s*(?:and|or|at|,)\s*'.$period.')*[.!?\s]*$/iu', trim($text));
    }
}
