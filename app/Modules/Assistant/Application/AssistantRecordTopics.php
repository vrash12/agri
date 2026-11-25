<?php

namespace App\Modules\Assistant\Application;

/** Exact local report phrases. Extra qualifiers must not become broader totals. */
final class AssistantRecordTopics
{
    public const CURRENT_TASKS = ['registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile'];

    public const TASKS = [...self::CURRENT_TASKS, 'crops', 'barangays'];

    public function resolve(string $text): ?string
    {
        $text = mb_strtolower(trim($text, " \t\n\r\0\x0B.!?"));
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($this->barangayBreakdown($text)) {
            return 'barangays';
        }
        // Whole-sentence availability questions; the machinery reply leads
        // with the available count, so no status filter is implied.
        if (in_array($text, ['machinery available', 'available machinery', 'machines available', 'available machines', 'equipment available', 'available equipment',
            'what machinery is available', 'what equipment is available', 'which machinery is available', 'what machines are available',
            'is there available machinery', 'is there any available machinery', 'do we have available machinery', 'what machinery do we have',
            'how many machines are available', 'how much machinery is available', 'may available na makinarya', 'ano ang available na makinarya'], true)) {
            return 'machinery';
        }
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
            'cooperatives', 'cooperative records', 'cooperative memberships', 'cooperative members', 'kooperatiba', 'cooperatives and members', 'cooperatives and their members', 'cooperatives and memberships', 'members of cooperatives', 'cooperative member farmers' => 'cooperatives',
            'machinery', 'machines', 'equipment', 'machinery inventory', 'equipment inventory', 'machinery records', 'makinarya' => 'machinery',
            'lgu profile', 'lgu profiles', 'lgu profile setup', 'lgu office details', 'office details', 'office profile', 'office profiles', 'lgu website', 'lgu websites' => 'lgu-profile',
            'crops', 'crop records', 'seasonal crops', 'seasonal crop records', 'pananim', 'mga pananim' => 'crops',
            default => null,
        };
    }

    /**
     * A whole-area grouping request only. The remainder after removing one
     * request verb, account-relative area and one period must be an exact
     * phrase; a named barangay, filter, ranking or extra subject stays
     * unrecognized so the resolver can clarify instead of widening a total.
     */
    private function barangayBreakdown(string $text): bool
    {
        if (! preg_match('/\b(?:barangays?|brgys?|farm[ -]locations?|locations?)\b/u', $text)) {
            return false;
        }
        $area = '(?:in|for|within|sa) (?:our|my|the assigned|ating|aming) (?:lgu|office|area)';
        $period = '(?:(?:for|in|sa|noong) )?(?:\d{4}|(?:the )?(?:same|that|last|previous|prior|this|current) year|(?:parehong|nakaraang|ngayong) taon|taong iyon)';
        $text = preg_replace('/^(?:show(?: me)?|summarize|count|give me|how many|what about|ipakita|ibuod|bilangin|ilan)(?: ang)? /u', '', $text);
        $text = preg_replace('/^(?:(?:our|my|the|a|current|registered|recorded|ating|aming|mga) )+/u', '', $text);
        $text = preg_replace('/ (?:natin|namin)\b/u', '', $text);
        $text = preg_replace('/ '.$area.'$/u', '', $text);
        $text = preg_replace('/ '.$period.'$/u', '', $text);
        $text = preg_replace('/ '.$area.'$/u', '', $text);
        $text = preg_replace('/ (?:are (?:there|recorded)|do we have)$/u', '', $text);

        // Subjects are the report's own columns. Commodity, demographic and
        // mapping-status words are deliberately absent: they are filters.
        $subject = '(?:farmers?|farmer (?:counts?|registry|records|profiles)|records|lgu records|lgu data|data|assistance(?: releases| records)?|releases|beneficiaries|harvests?|harvest records)';
        $filipino = '(?:magsasaka|ayuda|ani|benepisyaryo|records)';
        $place = '(?:barangays?|brgys?\.?|farm[ -]locations?|locations?)';
        $grouping = '(?:by|per|for each|in each|across)';
        $summary = '(?:breakdown|summary|overview|report|counts?|totals?)';

        return (bool) preg_match('/^(?:'
            .'(?:barangay|brgy\.?|farm[ -]location)(?:[ -]level)? '.$summary.'(?: (?:of|for) '.$subject.')?'
            .'|farm[ -]locations?'
            .'|(?:'.$subject.' )?'.$summary.' '.$grouping.' '.$place
            .'|(?:'.$subject.' )?'.$grouping.' '.$place.'(?: '.$summary.')?'
            .'|(?:(?:bilang|buod) ng )?(?:'.$filipino.' )?(?:sa |ng )?(?:bawat|kada) (?:barangay|brgy\.?)'
            .'|(?:'.$filipino.' )?(?:ayon|batay) sa (?:barangay|brgy\.?)'
            .')$/u', $text);
    }

    public function mentionsExtendedRecords(string $text): bool
    {
        return (bool) preg_match('/\b(?:cooperatives?|memberships?|machinery|machines?|equipment|makinarya|kooperatiba|delisted|outside[ -]lgu|registry\s+(?:sections?|sources?)|workbook\s+sections?|lgu\s+(?:records|data|profiles?|websites?|office)|office\s+(?:profiles?|details))\b/iu', $text);
    }
}
