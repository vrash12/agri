<?php

namespace App\Support;

/** Bounded task routing. Unknown text is never submitted to an external model. */
final class AssistantIntentResolver
{
    /** @return array{task:string,year:int,farmer_id?:int|null,search?:string,topic?:string,style?:string,kind?:string,language?:string,clarification?:string,reset_context?:bool,references?:array{task:string,year:int,farmer_id:int|null}} */
    public function resolve(string $message, array $context, string $module, int $currentYear): array
    {
        $text = trim($message);
        $year = (int) ($context['year'] ?? $currentYear);
        $language = preg_match('/\b(paano|saan|gabayan|turuan|ipaliwanag|hakbang|magsasaka|binhi|ayuda|buod|hanapin|hayop|taon|natin|niya|sino|ano|anong|ikaw|gamit|kumusta|kamusta|salamat|magandang|paki|gawing|paikliin|halimbawa|dagdag|tulong)\b/iu', $text) ? 'fil' : 'en';
        if ($kind = $this->identityKind($text)) {
            return ['task' => 'identity', 'kind' => $kind, 'year' => $year, 'language' => $language];
        }
        if ($style = $this->helpFollowUpStyle($text)) {
            if (($context['task'] ?? null) === 'identity') {
                return ['task' => 'identity', 'kind' => 'capabilities', 'year' => $year, 'language' => $language];
            }
            if (($context['task'] ?? null) === 'help' && is_string($context['help_topic'] ?? null) && isset(OfficeAssistant::TOPICS[$context['help_topic']])) {
                return ['task' => 'help', 'topic' => $context['help_topic'], 'style' => $style, 'year' => $year, 'language' => $language];
            }

            // A record summary cannot become provider history through a vague
            // request to explain, shorten or give an example of the last reply.
            return $this->clarify('Which system task should I explain? Ask about a feature such as drawing crops, seed imports or Animal Health. I can only reuse an earlier system-help topic.', $year);
        }
        if (preg_match('/^(?:how\s+(?:do|can)\s+I\s+use|explain|help\s+me\s+(?:use|understand)|what\s+can\s+I\s+do\s+(?:on|in)|paano\s+gamitin(?:\s+ang)?|ipaliwanag)\s+(?:this\s+page|this\s+screen|pahinang\s+ito|page\s+na\s+ito)[.!?\s]*$/iu', $text)
            || preg_match('/^(?:what\s+can\s+I\s+do\s+here|help\s+me\s+with\s+this\s+page|explain\s+this\s+feature)[.!?\s]*$/iu', $text)) {
            $topic = $this->pageTopic($module);

            return ['task' => 'help', 'topic' => $topic, 'year' => $year, 'language' => $language];
        }
        if (preg_match('/\b(compare|compared|comparison|versus|vs|trend|trends|ihambing|kumpara|year[\s-]+(?:over|on)[\s-]+year)\b/iu', $text)
            || preg_match('/\b(?:this\s+year|ngayong\s+taon)\b.+\b(?:last\s+year|previous\s+year|nakaraang\s+taon)\b|\b(?:last\s+year|previous\s+year|nakaraang\s+taon)\b.+\b(?:this\s+year|ngayong\s+taon)\b/iu', $text)
            || preg_match('/\b\d{4}\b\s+(?:and|with|at)\s+(?:last\s+year|previous\s+year|nakaraang\s+taon)\b/iu', $text)) {
            return $this->yearClarification($text, $context, $year, comparison: true);
        }
        $workflow = (bool) preg_match('/\b(how\s+(?:do|does|can|to|should)|where\s+(?:do|can|is)|can\s+I|I\s+want\s+to|steps|instructions|guide\s+me|walk\s+me\s+through|show\s+me\s+how|paano|saan|gabayan|turuan|ipaliwanag|hakbang|help\s+(?:me\s+)?(?:use|draw|import|with)|tell\s+me\s+about|explain|what\s+can\s+(?:(?:my|our|the)\s+(?:veterinary\s+)?account|I\s+access)|what\s+(?:is|does)\s+(?:ndvi|the\s+assistant))\b/iu', $text)
            || (bool) preg_match('/^(?:what\s+(?:is|does|are)|how\s+(?:is|are))\s+(?:the\s+)?(?:dashboard|weather|cooperative|cooperatives|machinery|equipment|backup|backups|audit|geofence|geofences|agrilgu|rag|privacy|farmer\s+registry|data\s+quality|harvest|harvests|assistance|animal\s+health|rice\s+releases?|distribution\s+sheets?)\b/iu', $text)
            || (bool) preg_match('/^(?:do\s+you\s+(?:send|share)|is\s+(?:farmer\s+)?data\s+(?:sent|shared)|are\s+farmer\s+records\s+(?:sent|shared))\b.+\b(?:openai|ai|provider)\b/iu', $text)
            || (bool) preg_match('/^(?:please\s+)?(?:describe|can\s+you\s+(?:explain|describe)|help\s+me\s+understand)\b/iu', $text)
            || (bool) preg_match('/^(?:does|do|is|are|can)\b.+\b(?:support|supported|available|implemented|feature|work|allowed)\b/iu', $text)
            || (bool) preg_match('/^(?:dashboard|weather|cooperatives?|machinery|backups?|audit\s+trail|geofences?|agrilgu|rag|assistant\s+privacy)[.!?\s]*$/iu', $text);
        if ($workflow) {
            if (preg_match('/\b(?:and|also|then|at|pati|tsaka)\s+(?:summarize|show|report|count|buod|ibuod|ipakita|bilangin)\b/iu', $text)) {
                return $this->clarify('Would you like system instructions or a records report first? I can handle one task at a time.', $year);
            }
            if ($this->hasMixedHelpWorkflows($text, $module)) {
                return $this->clarify('Which system task would you like instructions for first? Ask about one workflow at a time, such as drawing crops or importing seed releases.', $year);
            }
            $topic = $this->helpTopic($text, $module);

            return $topic ? ['task' => 'help', 'topic' => $topic, 'year' => $year, 'language' => $language]
                : $this->clarify('Which AgriLGU feature do you need help with? I can explain the dashboard, registry, parcel/crop maps, assistance, harvests, Animal Health, cooperatives, machinery or authorized office tools. Ask about one feature, or say “How do I use this page?”.', $year);
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
        if ($this->periodFollowUp($text) && in_array($context['task'] ?? null, ['farmers', 'assistance', 'harvests', 'animal-health', 'summary', 'farmer-search', 'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests'], true)) {
            if ($context['task'] === 'farmer-search') {
                return $this->clarify('Choose a farmer from the search matches first, then ask for that year.', $year);
            }

            return ['task' => $context['task'], 'year' => $year, 'farmer_id' => $context['farmer_id'] ?? null];
        }

        return $this->clarify('I help with AgriLGU features and permitted office records. Ask “Who are you?”, “How do I use this page?”, “Summarize my farmers” or name a feature you want explained. I cannot inspect server files or answer unrelated general questions.', $year);
    }

    private function identityKind(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B.!?");
        $text = preg_replace('/^(?:please|paki)\s+|\s+(?:please|po)$/iu', '', $text);

        return match (true) {
            (bool) preg_match('/^(?:who\s+(?:are\s+you|am\s+I\s+talking\s+to|is\s+(?:this|the)\s+(?:ai|assistant))|what\s+(?:are\s+you|is\s+your\s+name)|what(?:’s|\x27s)\s+your\s+name|introduce\s+yourself|sino\s+(?:ka|ikaw|ang\s+(?:ai|assistant))|ano(?:ng)?\s+(?:ang\s+)?pangalan\s+mo)$/iu', $text) => 'identity',
            (bool) preg_match('/^(?:(?:what|which)\s+(?:ai|model|technology|framework)(?:\s+model)?\s+(?:are\s+you(?:\s+(?:using|built\s+on))?|do\s+you\s+use|is\s+(?:used|this)|does\s+(?:this|the\s+assistant)\s+use)|are\s+you\s+(?:chatgpt|an?\s+ai)|anong\s+(?:ai|model|technology|framework)\s+(?:ang\s+)?(?:gamit\s+mo|ginagamit\s+mo|ginamit)|ano\s+ang\s+(?:ai|model)\s+(?:na\s+)?gamit\s+mo)$/iu', $text) => 'technology',
            (bool) preg_match('/^(?:hello|hi|hey|how\s+are\s+you|good\s+(?:morning|afternoon|evening)|kumusta(?:\s+ka)?|kamusta(?:\s+ka)?|magandang\s+(?:araw|umaga|hapon|gabi))$/iu', $text) => 'greeting',
            (bool) preg_match('/^(?:thanks(?:\s+(?:a\s+lot|so\s+much))?|thank\s+you(?:\s+(?:very\s+much|so\s+much))?|salamat(?:\s+(?:po|nang\s+marami))?|maraming\s+salamat)$/iu', $text) => 'thanks',
            (bool) preg_match('/^(?:what\s+can\s+(?:you|the\s+assistant)\s+do|how\s+can\s+you\s+help(?:\s+me)?|what\s+(?:are\s+your\s+(?:features|capabilities)|can\s+you\s+help\s+(?:me\s+)?with)|ano\s+(?:ang\s+)?(?:magagawa\s+mo|kaya\s+mong\s+gawin|maitutulong\s+mo)|help|tulong)$/iu', $text) => 'capabilities',
            default => null,
        };
    }

    private function helpFollowUpStyle(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B.!?");
        $text = preg_replace('/^(?:please|paki)\s+|\s+(?:please|po)$/iu', '', $text);

        return match (true) {
            (bool) preg_match('/^(?:explain\s+(?:more|further)|tell\s+me\s+more|more\s+details|ipaliwanag\s+pa|dagdag\s+na\s+paliwanag)$/iu', $text) => 'more',
            (bool) preg_match('/^(?:make\s+(?:it|that)\s+(?:shorter|brief)|shorten\s+(?:it|that)|keep\s+it\s+short|brief(?:ly)?|paikliin(?:\s+(?:ito|mo))?|gawing\s+maikli)$/iu', $text) => 'brief',
            (bool) preg_match('/^(?:(?:give|show)\s+(?:me\s+)?(?:an?\s+)?example|for\s+example|example|magbigay\s+(?:ng\s+)?halimbawa|halimbawa)$/iu', $text) => 'example',
            (bool) preg_match('/^(?:(?:explain\s+(?:it\s+)?|show\s+(?:me\s+)?(?:the\s+)?)?step(?:\s+|-)by(?:\s+|-)step|(?:give|show)\s+(?:me\s+)?(?:the\s+)?steps|isa(?:\s+|-)isahin\s+(?:ang\s+)?hakbang)$/iu', $text) => 'steps',
            (bool) preg_match('/^(?:explain\s+(?:it\s+)?(?:simply|in\s+simple\s+terms)|make\s+(?:it|that)\s+(?:simpler|easier\s+to\s+understand)|simplify(?:\s+(?:it|that))?|ipaliwanag\s+nang\s+simple|gawing\s+simple)$/iu', $text) => 'plain',
            default => null,
        };
    }

    /** A UI module is a relevance hint only when the user asks about this page. */
    private function pageTopic(string $module): string
    {
        return match ($module) {
            'dashboard' => 'dashboard-overview', 'farmers' => 'farmer-registry', 'crops' => 'crop-drawing', 'assistance' => 'assistance-management',
            'satellite' => 'ndvi', 'coverage' => 'assistance-coverage',
            'harvests' => 'harvest-entry', 'animal-health' => 'animals', 'accounts' => 'access',
            'cooperatives' => 'cooperatives', 'machinery' => 'machinery', 'geofences' => 'geofences', 'backups' => 'backups', 'audit' => 'audit',
            default => 'help',
        };
    }

    private function helpTopic(string $text, string $module): ?string
    {
        return match (true) {
            (bool) preg_match('/\bprivacy\b|\b(?:send|share|sent|shared)\b.+\b(?:openai|ai|provider)\b|\b(?:openai|provider)\b.+\b(?:record|records|data|conversation)\b|\b(?:protect|protected|protecting)\s+(?:farmer\s+)?(?:data|records)\b/iu', $text) => 'assistant-privacy',
            (bool) preg_match('/\b(rag|retrieval|ai\s+(?:technology|technologies|sdk)|laravel\s+ai|language\s+model|openai)\b/iu', $text) => 'ai-technology',
            (bool) preg_match('/\b(dashboard|dashboard\s+charts)\b/iu', $text) => 'dashboard-overview',
            (bool) preg_match('/\b(weather|forecast|forecasts|pagasa|panahon)\b/iu', $text) => 'weather',
            (bool) preg_match('/\b(cooperative|cooperatives|co-op|kooperatiba|membership)\b/iu', $text) => 'cooperatives',
            (bool) preg_match('/\b(machinery|equipment|tractor|tractors|maintenance|makinarya)\b/iu', $text) => 'machinery',
            (bool) preg_match('/\b(backup|backups|backup\s+folder)\b/iu', $text) => 'backups',
            (bool) preg_match('/\b(audit|audit\s+trail|activity\s+history)\b/iu', $text) => 'audit',
            (bool) preg_match('/\b(geofence|geofences|municipality\s+boundar(?:y|ies)|administrative\s+boundar(?:y|ies))\b/iu', $text) => 'geofences',
            (bool) preg_match('/\b(data\s+quality|duplicate|duplicates|missing\s+(?:fields|information|details)|incomplete\s+(?:farmer|record|records))\b/iu', $text) => 'data-quality',
            (bool) preg_match('/\b(ndvi|satellite)\b/iu', $text) => 'ndvi',
            (bool) preg_match('/\b(?:assistance|release|releases|beneficiar(?:y|ies))\s+(?:coverage|map)\b|\bcoverage\s+map\b/iu', $text) => 'assistance-coverage',
            (bool) preg_match('/\b(harvest|harvests|ani)\b/iu', $text) => 'harvest-entry',
            (bool) preg_match('/\b(animal|animals|vaccination|vaccinations|deworming|dog|dogs|cow|cows|hayop)\b/iu', $text) => 'animals',
            (bool) preg_match('/\b(kml|kmz)\b/iu', $text) => 'parcel-drawing',
            (bool) preg_match('/\b(import(?:ing)?|upload(?:ing)?|workbook|spreadsheet|excel)\b/iu', $text) && (bool) preg_match('/\b(seed|seeds|binhi|nrp|release|releases|assistance)\b/iu', $text) => 'seed-import',
            (bool) preg_match('/\b(fisheries|fish|fingerlings|tilapia|hito|catfish|bangus|feed|gear|fertilizer|fertiliser|abono|soil\s+amendment|seed|seeds|binhi|nrp|assistance|release|releases|distribution\s+sheets?)\b/iu', $text) => 'assistance-management',
            (bool) preg_match('/\b(farmer|farmers|portal|magsasaka)\b/iu', $text) && (bool) preg_match('/\b(login|log\s*in|sign\s*in|password)\b/iu', $text) => 'farmer-login',
            (bool) preg_match('/\b(card|cards|qr|signature|id)\b/iu', $text) => 'farmer-id',
            (bool) preg_match('/\b(access|permission|permissions|role|roles|account|accounts|login|sign\s*in)\b/iu', $text) => 'access',
            (bool) preg_match('/\b(farmer|farmers|magsasaka)\b/iu', $text) && (bool) preg_match('/\b(registry|profile|profiles|register|registration|import|upload|workbook|spreadsheet|excel)\b/iu', $text) => 'farmer-registry',
            (bool) preg_match('/\b(crop|crops|corn|maize|rice|planted|planting|mais|palay|pananim)\b/iu', $text) => 'crop-drawing',
            (bool) preg_match('/\b(parcel|parcels|plot|plots|boundary|boundaries|map|mapping|field\s+boundary|lupa|mapa|hangganan)\b/iu', $text) => 'parcel-drawing',
            $module === 'assistance' && (bool) preg_match('/\b(import|upload|workbook|spreadsheet|excel)\b/iu', $text) => 'seed-import',
            (bool) preg_match('/\bagrilgu\b|\b(?:system|platform)\s+(?:overview|features|modules)\b/iu', $text) => 'system-overview',
            (bool) preg_match('/\b(system|assistant)\b/iu', $text) => 'help',
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
            if (! preg_match('/^(?:(?:I|to|how\s+(?:do|can|to)|paano)\s+)?(?:draw|drawing|import|importing|upload|record|recording|print|printing|export|open|use|explain|login|log\s*in|sign\s*in|delete|reset|send|add|register|review|check|view|download|maintain|restore|gumuhit|mag-import|mag-upload|magtala|i-print|buksan|ipaliwanag)\b|^(?:seed|parcel|crop|farmer\s+(?:id|card)|animal(?:\s+health)?)\s+(?:import|imports|drawing|entry|recording|printing|services)\b|^(?:dashboard(?:\s+reports?)?|weather(?:\s+forecasts?)?|cooperatives?|machinery|equipment|backups?|audit(?:\s+trail)?|geofences?|data\s+quality|farmer\s+registry|rag|privacy)[.!?\s]*$/iu', $clause)) {
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
