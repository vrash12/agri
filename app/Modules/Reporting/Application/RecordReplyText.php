<?php

namespace App\Modules\Reporting\Application;

use App\Models\User;

/**
 * Plain-language phrasing for local record replies.
 *
 * Replies open with one or two sentences that answer the question, followed by
 * an optional numbered breakdown and at most one short note. The browser's
 * answer renderer turns blank-line paragraphs and "1. ..." lines into text-only
 * paragraphs and lists. Everything here is application-owned wording around
 * counts that were already authorized and scoped; nothing reaches a provider.
 */
final class RecordReplyText
{
    /** The account's own area in words, e.g. "Sample Town" or "your supervised provinces". */
    public static function area(User $user): string
    {
        if ($user->isSystemOwner()) {
            return 'your supervised provinces';
        }
        $label = trim($user->scopeLabel());

        return $label === '' || str_ends_with($label, 'not assigned') ? 'your area' : mb_substr($label, 0, 120);
    }

    /** "Sample Town has" / "Your supervised provinces have". */
    public static function areaHas(User $user): string
    {
        return self::sentence(self::area($user)).($user->isSystemOwner() ? ' have' : ' has');
    }

    /** "Sample Town’s" / "your supervised provinces’". */
    public static function areaPossessive(User $user): string
    {
        $area = self::area($user);

        return $area.(str_ends_with($area, 's') ? '’' : '’s');
    }

    public static function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    public static function number(int $value): string
    {
        return number_format($value);
    }

    /** "1 farmer" / "1,234 farmers". */
    public static function count(int $value, string $singular, ?string $plural = null): string
    {
        return self::number($value).' '.($value === 1 ? $singular : ($plural ?? $singular.'s'));
    }

    /** Recorded quantity without trailing zeros: 15, 2.5, 1,200.125. */
    public static function quantity(float $value): string
    {
        $text = number_format($value, 3);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /** Area in hectares with up to four decimals, as saved. */
    public static function hectares(float $value): string
    {
        $text = number_format($value, 4);

        return (str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text).' ha';
    }

    public static function percent(int $part, int $total): string
    {
        return $total > 0 ? number_format($part / $total * 100, 1).'%' : '0%';
    }

    /** "a, b and c". */
    public static function join(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }
        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }

    /**
     * Lead paragraph, optional titled numbered list, optional extra lines and
     * one optional note. Empty parts are omitted.
     *
     * @param  list<string>  $items
     * @param  list<string>  $details
     */
    public static function compose(string $lead, array $items = [], ?string $title = null, array $details = [], ?string $note = null): string
    {
        $blocks = [trim($lead)];
        if ($items !== []) {
            $list = [];
            foreach (array_values($items) as $index => $item) {
                $list[] = ($index + 1).'. '.$item;
            }
            $blocks[] = ($title === null ? '' : $title."\n").implode("\n", $list);
        }
        $details = array_values(array_filter($details, fn ($line) => $line !== ''));
        if ($details !== []) {
            $blocks[] = implode("\n", $details);
        }
        if ($note !== null && $note !== '') {
            $blocks[] = 'Note: '.$note;
        }

        return implode("\n\n", $blocks);
    }
}
