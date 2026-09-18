<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * The one place a third-party script or stylesheet is allowed to enter a page.
 *
 * Views ask for an asset by name; the URL and its hash come from `config/cdn.php`.
 * Nothing is rendered without a hash, so a resource cannot be added without also
 * recording which exact file was reviewed.
 *
 * Requesting a name that is not in the table throws rather than rendering an empty
 * `src`. A missing chart library is a visible bug on a test run; a silently empty
 * script tag is one that reaches an office.
 */
final class Cdn
{
    /**
     * A `<script>` tag with its integrity hash.
     */
    public static function script(string $name): HtmlString
    {
        $asset = self::asset($name);

        return new HtmlString(sprintf(
            '<script src="%s" integrity="%s" crossorigin="anonymous" referrerpolicy="no-referrer"></script>',
            e($asset['url']),
            e($asset['integrity'])
        ));
    }

    /**
     * A `<link rel="stylesheet">` tag with its integrity hash.
     */
    public static function style(string $name): HtmlString
    {
        $asset = self::asset($name);

        return new HtmlString(sprintf(
            '<link rel="stylesheet" href="%s" integrity="%s" crossorigin="anonymous" referrerpolicy="no-referrer">',
            e($asset['url']),
            e($asset['integrity'])
        ));
    }

    /**
     * URL and hash for a script the page injects itself.
     *
     * Some libraries are fetched only when a panel is first opened, so the tag is
     * built in JavaScript rather than rendered here. Those injectors still have to
     * set `integrity` and `crossOrigin` on the element, and this is where they get
     * the values: the pins stay in one table whether the tag is written by Blade or
     * by a script.
     *
     * @return array{url: string, integrity: string}
     */
    public static function asset(string $name): array
    {
        $asset = config("cdn.assets.{$name}");

        if (! is_array($asset) || ! isset($asset['url'], $asset['integrity'])) {
            throw new RuntimeException(
                "The CDN asset [{$name}] is not pinned in config/cdn.php. Add its URL and sha384 hash before using it."
            );
        }

        return $asset;
    }

    /**
     * Every pinned asset, for handing to a script that injects its own tags.
     *
     * @return array<string, array{url: string, integrity: string}>
     */
    public static function all(): array
    {
        return (array) config('cdn.assets', []);
    }
}
