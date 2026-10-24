<?php

namespace App\Modules\Assistant\Application;

/** Local agriculture routing; neither personal wording nor chat history leaves the application. */
final class AssistantAgriculture
{
    public const TOPICS = [
        'agriculture-basics' => ['module' => 'agriculture', 'question' => 'What is agriculture, what are common farming activities and why does it matter?'],
        'soil-health' => ['module' => 'agriculture', 'question' => 'What is soil health and how do organic matter, compost, soil cover and soil testing support crops?'],
        'crop-rotation' => ['module' => 'agriculture', 'question' => 'What is crop rotation, how does it differ from intercropping, and how should a farmer plan it?'],
        'crop-nutrition' => ['module' => 'agriculture', 'question' => 'What do nitrogen, phosphorus and potassium do for crops, and why should fertilizer choice and rate depend on local soil and crop conditions?'],
        'seed-selection' => ['module' => 'agriculture', 'question' => 'What makes good quality planting seed, and what should farmers check when choosing and storing seed?'],
        'crop-establishment' => ['module' => 'agriculture', 'question' => 'What general steps should a farmer consider before planting rice, corn or vegetables, without assuming a local planting calendar or rate?'],
        'irrigation' => ['module' => 'agriculture', 'question' => 'What is irrigation and how should crop stage, soil, drainage, rainfall and water availability guide watering?'],
        'pest-management' => ['module' => 'agriculture', 'question' => 'What is integrated pest management, and how should farmers investigate crop damage or yellow leaves without assuming a diagnosis or pesticide treatment?'],
        'livestock-care' => ['module' => 'agriculture', 'question' => 'What general husbandry and biosecurity practices support farm livestock and poultry, and when is veterinary assessment needed?'],
        'aquaculture' => ['module' => 'agriculture', 'question' => 'What is aquaculture and what general water, feed, stocking and observation practices support fish farming?'],
    ];

    public static function refusal(string $language): string
    {
        return $language === 'fil'
            ? 'Para sa agrikultura at AgriLGU ang gabay na ito. Hindi ako makakasagot ng tanong na walang kaugnayan dito. Maaari kang magtanong tungkol sa pananim, lupa, patubig, peste, alagang hayop, palaisdaan o mga feature ng sistema.'
            : 'I can help with agriculture and AgriLGU. I cannot answer unrelated questions. Ask about crops, soil, irrigation, pests, farm animals, fish farming or the system’s features.';
    }

    public static function requestsInstructionOverride(string $text): bool
    {
        // Instruction clauses are different from literal farmer names such as
        // "Find farmer Ignore Santos". Run this before lookup/report routing.
        return (bool) preg_match('/(?:^|[;.!?]|\b(?:and|also|then|at|pati|tsaka)\s+)\s*(?:ignore|disregard|override|forget|bypass|do\s+not\s+follow|balewalain|huwag\s+sundin)\b.{0,80}\b(?:rules|instructions|prompt|restrictions|messages|conversation\s+history|chat\s+history|panuntunan|tagubilin|bilin|mensahe|usapan)\b/iu', $text)
            || (bool) preg_match('/^(?:act\s+as|you\s+are\s+now|enable|activate)\s+(?:an?\s+)?(?:unrestricted|unfiltered|developer\s+mode|jailbreak)\b/iu', $text);
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $text, int $year, string $language): ?array
    {
        // Reject unrelated/mixed requests before broad workflow keywords can
        // turn a farming noun into an unrelated answer. This is not a classifier
        // guarantee: only reviewed canonical topics can reach the provider.
        if (self::requestsInstructionOverride($text)
            || preg_match('/\b(?:ignore|override|forget|bypass)\b.{0,50}\b(?:rules|instructions|prompt|restrictions)\b/iu', $text)
            || preg_match('/\b(?:movies?|celebrit(?:y|ies)|basketball|football|nba|romance|dating|horoscope|astrology|cryptocurrency|bitcoin|video\s+games?|election|president\s+of|capital\s+of)\b|\b(?:write|generate|debug)\b.{0,35}\b(?:code|python|javascript|poem|song|essay)\b|\b(?:write|compose|draft|generate|tell(?:\s+me)?|give(?:\s+me)?)\b.{0,40}\b(?:poem|song|essay|story|love\s+letter|joke)\b|\b(?:solve|calculate|compute|evaluate)\b.{0,35}(?:\d\s*(?:[+*\/=\x{00d7}\x{00f7}]|\b(?:plus|minus|times|divided)\b)|\b(?:equation|arithmetic|square\s+root)\b)/iu', $text)
            || preg_match('/\b(?:software|computer|laptop|desktop|smartphone|gaming\s+pc|windows|macos|android|ios)\b.{0,40}\b(?:maintenance|backups?|settings|repair|troubleshoot)\b|\b(?:backups?|settings|repair|troubleshoot)\b.{0,40}\b(?:computer|laptop|desktop|smartphone|gaming\s+pc|windows|macos|android|ios)\b/iu', $text)) {
            return $this->clarify(self::refusal($language), $year);
        }
        if (! preg_match('/\b(?:record|log|track|inventory|e?agrilgu|workbook|spreadsheet|excel|magtala|itala)\b/iu', $text)
            && preg_match('/\b(?:repair|fix|replace|troubleshoot|overhaul|maintain|maintenance)\b.{0,40}\b(?:engine|gearbox|transmission|fuel\s+pump|carburetor|spark\s+plug|hydraulic\s+pump)\b|\b(?:engine|gearbox|transmission|fuel\s+pump|carburetor|spark\s+plug|hydraulic\s+pump)\b.{0,40}\b(?:repair|repairs|maintenance|overhaul)\b/iu', $text)) {
            return $this->clarify($language === 'fil'
                ? 'Maipapaliwanag ko ang machinery inventory at maintenance records sa AgriLGU, pero wala akong sinuring gabay sa pagkumpuni ng makina. Kumonsulta sa kwalipikadong agricultural machinery technician para sa aktuwal na pagkumpuni.'
                : 'I can explain machinery inventory and maintenance records in AgriLGU, but I do not have reviewed mechanical repair instructions. Ask a qualified agricultural machinery technician for engine or repair work.', $year);
        }
        // Existing record and software requests retain their policies, query
        // scope and exact workflow. Ordinary farm advice has no database path.
        if (preg_match('/\b(?:e?agrilgu|system|assistant|dashboard|records?|recorded|registry|register|registration|import|upload|export|download|workbook|spreadsheet|excel|draw|drawing|plotting|map|mapping|boundary|boundaries|parcel|parcels|plots?|mapa|hangganan|assistance|release|releases|distribution|reports?|summary|summarize|count|listed|selected|his|her|their|niya|kanyang|account|login|password|qr|portal|id|audit|backup|ndvi|ndmi|savi|evi|satellite|pagasa|forecast|readiness|climate\s+risk|magtala|itala|ipakita|buod|hanapin|gumuhit)\b|\b(?:how\s+many|ilan|ilang|who\s+received)\b/iu', $text)) {
            return null;
        }
        if (preg_match('/^(?:seeds?|binhi|lupa)[.!?\s]*$/iu', $text)) {
            return null;
        }
        $topic = match (true) {
            (bool) preg_match('/\b(?:crop\s+rotation|rotating\s+crops?|intercropping|crop\s+diversification|pagpapalit(?:-palit)?\s+ng\s+pananim)\b/iu', $text) => 'crop-rotation',
            (bool) preg_match('/\b(?:pests?|insects?|weeds?|ipm|integrated\s+pest|pesticides?|crop\s+disease|yellow(?:ing)?\s+(?:leaves|leaf)|leaves\s+(?:turning\s+)?yellow|naninilaw|peste|damo|sakit\s+ng\s+(?:halaman|palay|pananim))\b/iu', $text) => 'pest-management',
            (bool) preg_match('/\b(?:aquaculture|fish\s+farm(?:ing)?|fish\s+pond|fishpond|fish\s+feed|fingerlings?|tilapia|hito|bangus|catfish|palaisdaan|pag-aalaga\s+ng\s+isda)\b/iu', $text) => 'aquaculture',
            (bool) preg_match('/\b(?:livestock|poultry|cattle|cows?|goats?|pigs?|chickens?|carabao|farm\s+animals?|biosecurity|husbandry|baka|baboy|manok|kambing|kalabaw)\b/iu', $text) => 'livestock-care',
            (bool) preg_match('/\b(?:fertili[sz]ers?|nitrogen|phosphorus|potassium|npk|crop\s+nutri(?:tion|ents)|abono|pataba)\b/iu', $text) => 'crop-nutrition',
            (bool) preg_match('/\b(?:irrigation|watering|water\s+(?:management|conservation)|drainage|drought|patubig|pagdidilig|dilig)\b|\b(?:water|tubig)\b.*\b(?:rice|corn|crops?|plants?|palay|mais|pananim)\b/iu', $text) => 'irrigation',
            (bool) preg_match('/\b(?:soil|compost|mulch|organic\s+matter|lupa)\b/iu', $text) => 'soil-health',
            (bool) preg_match('/\b(?:seed\s+(?:quality|selection|storage)|good\s+(?:quality\s+)?seeds?|germination|germinate|binhi)\b/iu', $text) => 'seed-selection',
            (bool) preg_match('/\b(?:plant(?:ing)?|grow(?:ing)?|cultivate|cultivation|magtanim|pagtatanim|palaguin)\b.*\b(?:rice|corn|maize|vegetables?|crops?|palay|mais|gulay|pananim)\b|\b(?:rice|corn|maize|vegetables?|palay|mais|gulay)\s+(?:farming|cultivation|planting)\b/iu', $text) => 'crop-establishment',
            (bool) preg_match('/\b(?:agriculture|farming|agricultural|agrikultura|pagsasaka|sakahan)\b/iu', $text) => 'agriculture-basics',
            default => null,
        };
        if ($topic === null) {
            return null;
        }
        if (preg_match('/\b(?:today|tomorrow|latest|current\s+price|price|prices|market\s+rate|dosage|dose|dosages|mixing\s+ratio|how\s+much|how\s+many\s+(?:bags|kg|kilograms)|ilang\s+sako|magkano|gamot|diagnose|diagnosis|cure|prescribe|sick|dying)\b/iu', $text)) {
            return $this->clarify($language === 'fil'
                ? 'Pangkalahatang gabay sa agrikultura ang maibibigay ko. Wala akong kasalukuyang presyo, pagsusuri ng iyong bukid o hayop, o batayan para sa eksaktong dosis. Kumonsulta sa agriculture office o beterinaryo para sa tiyak na rekomendasyon; maaari kong ipaliwanag muna ang pangkalahatang paksa.'
                : 'I can explain general agriculture principles. I do not have live prices, an assessment of your field or animals, or evidence for an exact dose. Ask your agriculture office or veterinarian for a specific recommendation, or ask me to explain the general topic.', $year);
        }

        return ['task' => 'help', 'topic' => $topic, 'year' => $year, 'language' => $language,
            'style' => preg_match('/\b(?:steps|step.by.step|numbered|list|hakbang|isa.isa)\b/iu', $text) ? 'steps' : 'plain'];
    }

    private function clarify(string $answer, int $year): array
    {
        return ['task' => 'clarification', 'year' => $year, 'clarification' => $answer, 'reset_context' => true];
    }
}
