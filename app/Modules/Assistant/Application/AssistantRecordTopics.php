<?php

namespace App\Modules\Assistant\Application;

/** Exact local report phrases. Extra qualifiers must not become broader totals. */
final class AssistantRecordTopics
{
    public const CURRENT_TASKS = ['registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile'];

    public const TASKS = [...self::CURRENT_TASKS, 'crops'];

    public function resolve(string $text): ?string
    {
        $text = mb_strtolower(trim($text, " \t\n\r\0\x0B.!?"));
        $text = preg_replace('/\s+/u', ' ', $text);
        if (! preg_match('/^(?:show|summarize|count|give me|how many|what about|ipakita|ibuod|bilangin|ilan)\b|^what (?:is|are) (?:our|my)\b/u', $text)) {
            return null;
        }
        // Only account-relative area phrases are removable, never a named LGU.
        $text = preg_replace('/\s+(?:in|for|within|sa) (?:our|my|the assigned|ating|aming) (?:lgu|office|area)$/u', '', $text);
        $text = preg_replace('/\s+(?:(?:for|in|sa|noong) )?(?:\d{4}|(?:the )?(?:same|that|last|previous|prior|this|current) year|(?:parehong|nakaraang|ngayong) taon|taong iyon)$/u', '', $text);
        $text = preg_replace('/\s+(?:in|for|within|sa) (?:our|my|the assigned|ating|aming) (?:lgu|office|area)$/u', '', $text);
        $text = preg_replace('/^(?:show(?: me)?|summarize|count|give me|how many|what about|what (?:is|are)|ipakita|ibuod|bilangin|ilan(?: ang)?) /u', '', $text);
        $text = preg_replace('/^(?:(?:our|my|the|current|recorded|ang|ating|aming|mga) )+/u', '', $text);
        $text = preg_replace('/ (?:are (?:there|recorded)|do we have|natin)$/u', '', $text);
        $text = preg_replace('/ (?:summary|overview|counts|count|totals|report)$/u', '', $text);

        return match ($text) {
            'lgu records', 'lgu data', 'all lgu records', 'all lgu data', 'office records', 'office data', 'all office records', 'all office data' => 'summary',
            'registry sections', 'registry sources', 'registry source rows', 'workbook sections', 'retained workbook rows' => 'registry-sources',
            'outside lgu', 'outside-lgu', 'outside lgu rows', 'outside-lgu rows', 'outside lgu farmers', 'outside-lgu farmers', 'outside lgu records', 'outside-lgu records', 'farmers are outside lgu' => 'outside-lgu',
            'delisted', 'delisted rows', 'delisted farmers', 'delisted records', 'farmers are delisted', 'delisted na magsasaka' => 'delisted',
            'cooperatives', 'cooperative records', 'cooperative memberships', 'cooperative members', 'kooperatiba' => 'cooperatives',
            'machinery', 'machines', 'equipment', 'machinery inventory', 'equipment inventory', 'machinery records', 'makinarya' => 'machinery',
            'lgu profile', 'lgu profiles', 'lgu profile setup', 'lgu office details', 'office details', 'office profile', 'office profiles', 'lgu website', 'lgu websites' => 'lgu-profile',
            'crops', 'crop records', 'seasonal crops', 'seasonal crop records', 'pananim', 'mga pananim' => 'crops',
            default => null,
        };
    }

    public function mentionsExtendedRecords(string $text): bool
    {
        return (bool) preg_match('/\b(?:cooperatives?|memberships?|machinery|machines?|equipment|makinarya|kooperatiba|delisted|outside[ -]lgu|registry\s+(?:sections?|sources?)|workbook\s+sections?|lgu\s+(?:records|data|profiles?|websites?|office)|office\s+(?:profiles?|details))\b/iu', $text);
    }
}
