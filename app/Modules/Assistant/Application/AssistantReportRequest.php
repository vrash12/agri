<?php

namespace App\Modules\Assistant\Application;

use App\Models\AntiRabiesVaccination;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\RiceSeedDistribution;

/**
 * Recognizes unsupported report subsets before broad totals can be mistaken for
 * the requested result. Run only after help and literal farmer searches resolve.
 */
final class AssistantReportRequest
{
    public function clarification(string $text): ?string
    {
        $text = preg_replace('/[_\s]+/u', ' ', $text);
        if (preg_match('/\b(?:contact(?:s|\s+(?:details|information|numbers?))?|phone(?:s|\s+numbers?)?|mobile(?:\s+numbers?)?|telephone|emails?|address(?:es)?|residence|birthdays?|birthdates?|birth\s+dates?|date\s+of\s+birth|dob|eligibility|arb|pwd|ip\s+members?|notes?|diagnosis|diagnoses|coordinates?|latitude|longitude|passwords?|credentials?|secrets?|api\s+keys?|access\s+tokens?|private\s+keys?)\b|\b(?:lat|lng)\s*(?:=|:)\s*[-\d]|(?<![\p{L}\d])\.env\b/iu', $text)) {
            return 'Private contact, birth, address, eligibility, note and coordinate fields are not available in this chat; credentials are never disclosed. I can show permitted record summaries. Open an authorized record page for other details your role may view.';
        }
        if ($this->hasNarrowerPeriod($text) || $this->hasMultiplePeriods($text)) {
            return 'I can report one whole year, not a season, month or date range. Open the relevant register for its available period filters, or ask for the complete annual assistance, harvest or Animal Health report.';
        }
        if ($this->hasExcludedRecords($text)) {
            return 'This chat cannot exclude records or reporting years from a total. Open the relevant register for its available filters, or ask for the complete current registry summary or one annual report.';
        }
        if ($this->hasDemographicFilter($text)) {
            return 'This chat cannot apply an age, civil-status or farmer classification filter. Open the farmer registry for its available filters, or ask for the complete current registry summary.';
        }
        if ($this->hasListingOrRanking($text)) {
            return 'This chat provides bounded record summaries, not a complete list or a sorted ranking. Open the relevant register to review and sort its records, or ask for the complete current registry summary or one annual report.';
        }
        if ($this->hasRegistryFilter($text)) {
            return 'This chat cannot filter farmer reports by gender, mapping status or registry follow-up. Open the farmer registry and use its filters, or ask for the complete current registry summary.';
        }
        if ($this->hasAnimalFilter($text)) {
            return 'I can summarize all Animal Health services for one year, but cannot filter by animal species or service type. Open Animal Health and choose its animal or service filters, or ask for the complete annual summary.';
        }
        if ($this->hasCommodityFilter($text)) {
            return 'This chat cannot apply a crop, commodity or assistance-input filter. Open the harvest or assistance register and choose its commodity or category filter, or ask for the annual summary across all recorded categories.';
        }
        if (preg_match('/\b(?:pending|approved|rejected|inactive|delisted|eligible|ineligible|unlinked|unclaimed|consent|eligibility|deceased|dead|died|relocated|transferred|deregistered|removed)\b|\b(?:active|projected|manual|direct)\s+(?:farmers?|records?|harvests?)\b|\b(?:only|without|missing)\s+(?:a\s+)?(?:farmer\s+)?(?:link|links|quantity|classification|year|date|dates)\b|\b(?:more\s+than|less\s+than|greater\s+than|fewer\s+than|larger\s+than|smaller\s+than|at\s+least|at\s+most|minimum|maximum|over|under|above|below)\s+(?:\d|one\b|two\b|three\b|four\b|five\b|six\b|seven\b|eight\b|nine\b|ten\b)|\b(?:quantity|area|hectares?|ha|kgs?|kilograms?)\s*[<>]=?\s*\d/iu', $text)) {
            return 'This chat cannot apply that status, missing-field or quantity condition. Open the relevant register for its available filters, or ask for the complete current registry summary or one annual report.';
        }

        return null;
    }

    private function hasExcludedRecords(string $text): bool
    {
        // A mentioned year is not necessarily the requested year. In particular,
        // "excluding 2025" must never be answered with the 2025 total.
        return (bool) preg_match('/\b(?:except|excluding|exclude|omitting|omit|other\s+than|not\s+including|but\s+not|without\s+counting)\b|\bnot\s+(?:(?:in|for|during|from)\s+)?(?:(?:the\s+)?(?:same|that|this|current|previous|prior|last)\s+year|\d{4})\b|\boutside\s+(?:(?:the\s+)?(?:year|period)\s+)?\d{4}\b/iu', $text);
    }

    private function hasDemographicFilter(string $text): bool
    {
        return (bool) preg_match('/\b(?:age|ages|aged|elderly|seniors?|senior\s+citizens?|younger|older|youth|retired|retirees?|married|unmarried|widowed|widows?|widowers?|divorced|separated|civil\s+status|marital\s+status|organic)\b|\bsingle\s+(?:farmers?|magsasaka)\b|\b(?:farmers?|magsasaka)\b.{0,60}\bsingle\b|\b\d+\s*(?:years?\s+old|year[ -]olds?)\b/iu', $text);
    }

    private function hasMultiplePeriods(string $text): bool
    {
        return (bool) preg_match('/\b(?:all|every|each|multiple|several)\s+(?:reporting\s+)?years?\b|\b(?:lifetime|all[ -]time|yearly|annually|ever)\b|\b(?:by|per|across|over)\s+(?:(?:the|reporting)\s+)?years?\b|\b(?:to\s+date|since\s+(?:the\s+)?(?:beginning|start|registration))\b/iu', $text);
    }

    private function hasListingOrRanking(string $text): bool
    {
        // The available readers aggregate records. A total must not masquerade
        // as the requested complete row listing or ordering.
        return (bool) preg_match('/\b(?:list|lists|listing|listings|rank|ranks|ranking|ranked|sort|sorted|sorting|alphabetical|alphabetically|top|bottom|highest|lowest|largest|smallest|biggest)\b|\b(?:most|least)\s+(?:recent|productive|assisted)\b/iu', $text);
    }

    private function hasNarrowerPeriod(string $text): bool
    {
        return (bool) preg_match('/\b(?:dry|wet|rainy|summer|winter)\s+season\b|\b(?:dry|wet)\s+(?:\d{4}|harvests?|crops?|planting)\b|\b(?:season|seasons)\s*(?:=|:)\s*(?:dry|wet)\b|\b(?:tag[\s-]?(?:init|ulan)|dry[\s-]season|wet[\s-]season)\b|\b(?:january|february|march|april|june|july|august|september|october|november|december|enero|pebrero|marso|abril|mayo|hunyo|hulyo|agosto|setyembre|oktubre|nobyembre|disyembre)\b|\b(?:in|for|during|of)\s+may\b|\bmay\s+\d{1,4}\b|\b(?:jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\.?\s+\d{1,4}\b|\b(?:month|months|monthly|quarter|quarters|quarterly|week|weeks|weekly|today|yesterday|daily|kahapon|ngayon|buwan|linggo)\b|\bq[1-4]\b|\b\d{4}-\d{1,2}(?:-\d{1,2})?\b|\b\d{4}\s*[-–—]\s*\d{4}\b|\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b|\b(?:before|after|since|until|between|mula|hanggang)\s+(?:(?:the\s+)?year\s+)?\d{4}\b|\bdate\s+range\b|\b(?:by|per|each)\s+season\b/iu', $text);
    }

    private function hasRegistryFilter(string $text): bool
    {
        return (bool) preg_match('/\b(?:male|female|men|women|lalaki(?:ng)?|babae(?:ng)?|unmapped)\b|\bmapped\s+(?:farmers?|parcels?|plots?|profiles?|records?)\b|\bfarmers\b.{0,60}\bmapped\b|\b(?:gender|sex)\s*(?:=|:|is)\s*\S|\b(?:gender|sex)\s+(?:other|unspecified)\b|\bunspecified\s+(?:farmers?|gender|sex)\b|\b(?:by|per|each|only)\s+(?:gender|sex)\b|\b(?:farmers|magsasaka|profiles|how\s+many|ilan)\b.{0,80}\b(?:with|without|missing|no|have|has|having)\s+(?:(?:a|any|an|their|saved|plotted|mapped|land)\s+)*(?:parcels?|plots?|boundar(?:y|ies)|maps?|mapping|ffrs|rsbsa)\b|\b(?:missing|incomplete|duplicate|duplicates|unregistered)\s+(?:farmer|farmers|registry|records?|profiles?|ffrs|rsbsa)\b|\b(?:walang|may)\s+(?:lupa|mapa|ffrs|rsbsa)\b/iu', $text);
    }

    private function hasAnimalFilter(string $text): bool
    {
        return $this->matchesTaxonomy($text, AntiRabiesVaccination::ANIMAL_TYPE_LABELS, ['Other'])
            || $this->matchesTaxonomy($text, AntiRabiesVaccination::SERVICE_TYPE_LABELS)
            || (bool) preg_match('/\b(?:aso|pusa|baka|kalabaw|kambing|baboy|manok|itik|kabayo|anti[\s-]?rabies|rabies|vaccines?|vaccinated|dewormed|unvaccinated)\b|\b(?:by|per|each|only)\s+(?:species|animal\s+types?|service\s+types?|breeds?)\b|\b(?:animal|service|pet)\s+type\s*(?:=|:)\s*\S/iu', $text);
    }

    private function hasCommodityFilter(string $text): bool
    {
        return $this->matchesTaxonomy($text, HarvestRecord::COMMODITY_LABELS, ['other'])
            || $this->matchesTaxonomy($text, ParcelCropSeason::CROPS, ['mixed', 'other', 'not_recorded'])
            || $this->matchesTaxonomy($text, RiceSeedDistribution::INPUT_CATEGORY_LABELS, ['other_input', 'other_fisheries'])
            || (bool) preg_match('/\b(?:maize|mais|gulay|prutas|tilapia|bangus|hito|catfish|fingerlings?|fertilisers?|fertilizers?|abono|fisheries|hybrid|inbred|certified\s+seed|nsic|rc\s*\d+)\b|\bagricultur(?:e|al)\s+assistance\b|\bother\s+(?:commodity|crop|farm\s+input|fisheries(?:\s+assistance)?)\b|\b(?:crop|commodity|variety|input\s+category|category)\s*(?:=|:)\s*\S|\b(?:only|specific|selected)\s+(?:crop|commodity|variety|input\s+category|category)\b/iu', $text);
    }

    /** Model constants supply labels only; this does not construct a model/query. */
    private function matchesTaxonomy(string $text, array $labels, array $excludedKeys = []): bool
    {
        $terms = [];
        foreach ($labels as $key => $label) {
            if (in_array($key, $excludedKeys, true)) {
                continue;
            }
            foreach (array_merge([str_replace('_', ' ', $key)], explode('/', $label)) as $term) {
                $term = trim($term);
                // Labels with an existing plural are matched in singular too.
                $term = preg_replace('/s$/i', '', $term);
                $terms[] = str_replace(' ', '\\s+', preg_quote($term, '/')).'(?:s)?';
            }
        }

        return (bool) preg_match('/\b(?:'.implode('|', array_unique($terms)).')\b/iu', $text);
    }
}
