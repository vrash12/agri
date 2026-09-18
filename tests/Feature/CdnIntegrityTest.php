<?php

namespace Tests\Feature;

use App\Support\Cdn;
use RuntimeException;
use Tests\TestCase;

/**
 * Third-party code that runs inside this application.
 *
 * Every script and stylesheet fetched from someone else's server executes in a
 * session that can read the farmer register. Two things have to hold for that to be
 * acceptable: the URL names an exact version, and the tag carries the hash of the
 * file that was reviewed. Neither is much use alone — an unversioned URL serves
 * whatever is current, and a pinned URL with no hash still trusts whoever is serving
 * it today.
 *
 * These tests fail if a new CDN reference is added without going through
 * `config/cdn.php`, which is the only way this stays true as the application grows.
 */
class CdnIntegrityTest extends TestCase
{
    /** Paths a CDN reference could legitimately appear in. */
    private const SCANNED = ['resources/views', 'public/js'];

    /**
     * Hosts that serve static third-party code.
     *
     * This list started with three entries and missed jQuery and DataTables, which
     * load from the main layout and therefore run on every authenticated page — the
     * most valuable thing on the list to have hashed. Add a host here when one is
     * introduced; the point of the check is lost if the list only names what was
     * already known about.
     */
    private const CDN_HOSTS = [
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'unpkg.com',
        'code.jquery.com',
        'cdn.datatables.net',
        'stackpath.bootstrapcdn.com',
        'maxcdn.bootstrapcdn.com',
        'ajax.googleapis.com',
    ];

    /**
     * Origins that cannot carry a hash, and why.
     *
     * The Google Maps bootstrap negotiates its own version at load time and the
     * Fonts stylesheet is generated per browser, so neither has a stable body to
     * hash. Both are named in the Content-Security-Policy instead, which is the only
     * control available for them.
     */
    private const UNHASHABLE = ['maps.googleapis.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];

    public function test_every_pinned_asset_names_an_exact_version_and_carries_a_hash(): void
    {
        $assets = Cdn::all();

        $this->assertNotEmpty($assets, 'No CDN assets are pinned; the table should not be empty.');

        foreach ($assets as $name => $asset) {
            $this->assertStringStartsWith('https://', $asset['url'], "{$name} must be fetched over HTTPS.");
            $this->assertStringStartsWith('sha384-', $asset['integrity'], "{$name} has no sha384 hash.");

            // A base64 sha384 digest is 64 characters.
            $this->assertSame(
                64,
                strlen(substr($asset['integrity'], 7)),
                "{$name} has a malformed sha384 digest."
            );

            // Three conventions in use: jsDelivr pins with `@version`, cdnjs and
            // DataTables with a version path segment, and code.jquery.com puts the
            // version in the filename.
            $versioned = (bool) preg_match('~@\d+\.\d+|/\d+\.\d+(\.\d+)?/|-\d+\.\d+\.\d+\.~', $asset['url']);
            $this->assertTrue($versioned, "{$name} does not name an exact version: {$asset['url']}");
        }
    }

    public function test_no_view_or_script_reaches_a_cdn_without_going_through_the_pinned_table(): void
    {
        $offenders = [];

        foreach ($this->scannedFiles() as $file) {
            $contents = file_get_contents($file);

            foreach (preg_split('/\R/', $contents) as $number => $line) {
                foreach (self::CDN_HOSTS as $host) {
                    if (! str_contains($line, $host)) {
                        continue;
                    }

                    // The pinned table itself, and lines that read from it, are the
                    // supported way in.
                    if (str_contains($line, 'Cdn::') || str_contains($line, '__cdn')) {
                        continue;
                    }

                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).':'.($number + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A CDN URL is loaded directly instead of through config/cdn.php:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_a_rendered_tag_carries_the_hash_and_the_cross_origin_attribute(): void
    {
        $script = (string) Cdn::script('chart_js');

        $this->assertStringContainsString('integrity="sha384-', $script);
        // Without crossorigin the browser cannot verify a cross-origin resource, and
        // the integrity attribute is ignored rather than enforced.
        $this->assertStringContainsString('crossorigin="anonymous"', $script);
        $this->assertStringContainsString('chart.js@4.4.3', $script);

        $style = (string) Cdn::style('tom_select_css');
        $this->assertStringContainsString('rel="stylesheet"', $style);
        $this->assertStringContainsString('integrity="sha384-', $style);
        $this->assertStringContainsString('crossorigin="anonymous"', $style);
    }

    public function test_asking_for_an_unpinned_asset_fails_loudly(): void
    {
        // Rendering an empty src would leave a page subtly broken in an office
        // instead of failing on a test run.
        $this->expectException(RuntimeException::class);
        Cdn::script('not_pinned_anywhere');
    }

    public function test_the_only_unhashed_origins_are_the_ones_that_cannot_be_hashed(): void
    {
        // A record of the exceptions, so that "we hash our third-party code" stays an
        // accurate statement rather than one with quiet gaps behind it.
        $found = [];

        foreach ($this->scannedFiles() as $file) {
            foreach (self::UNHASHABLE as $host) {
                if (str_contains((string) file_get_contents($file), $host)) {
                    $found[$host] = true;
                }
            }
        }

        $this->assertSame(
            ['maps.googleapis.com', 'fonts.googleapis.com', 'fonts.gstatic.com'],
            array_values(array_intersect(self::UNHASHABLE, array_keys($found))),
            'The set of origins loaded without an integrity hash changed. Either hash the new one or record why it cannot be hashed.'
        );
    }

    public function test_the_libraries_on_the_main_layout_are_hashed(): void
    {
        // These run on every authenticated page, so they are the highest-value
        // targets in the application and the ones most worth pinning.
        $layout = (string) file_get_contents(base_path('resources/views/layouts/app.blade.php'));

        foreach (['jquery_js', 'datatables_js', 'datatables_css', 'tom_select_js', 'tom_select_css'] as $name) {
            $this->assertStringContainsString(
                'Cdn::'.(str_ends_with($name, '_css') ? 'style' : 'script')."('{$name}')",
                $layout,
                "The layout does not load {$name} through the pinned table."
            );
        }
    }

    public function test_the_chart_library_is_pinned_to_one_version_everywhere(): void
    {
        // Three separate loaders fetch Chart.js. They drifted to different versions
        // once before; one pinned entry is what stops that recurring.
        $chart = Cdn::asset('chart_js');
        $matches = [];

        foreach ($this->scannedFiles() as $file) {
            if (preg_match_all('~chart\.js@(\d+\.\d+\.\d+)~', (string) file_get_contents($file), $found)) {
                $matches = array_merge($matches, $found[1]);
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique(array_diff($matches, ['4.4.3']))),
            'A Chart.js version other than the pinned one appears in the codebase.'
        );
        $this->assertStringContainsString('4.4.3', $chart['url']);
    }

    /**
     * @return array<int, string>
     */
    private function scannedFiles(): array
    {
        $files = [];

        foreach (self::SCANNED as $relative) {
            $directory = base_path($relative);

            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
