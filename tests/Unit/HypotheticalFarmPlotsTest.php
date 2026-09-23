<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use App\Support\HypotheticalFarmPlots;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HypotheticalFarmPlotsTest extends TestCase
{
    private Container $previousContainer;

    private GeoGeometry $geometry;

    private HypotheticalFarmPlots $plots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository(['geofencing' => ['near_boundary_meters' => 20]]));
        Container::setInstance($container);
        $this->geometry = new GeoGeometry();
        $this->plots = new HypotheticalFarmPlots($this->geometry);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_plots_are_deterministic_open_rings_with_computed_measurements(): void
    {
        $boundary = $this->square(120, 15, .02);
        $plots = $this->plots->generate($boundary, 12);
        $this->assertSame($plots, $this->plots->generate($boundary, 12));
        $this->assertCount(12, $plots);
        $this->assertValidPlots($plots, $boundary);
    }

    public function test_existing_parcels_are_excluded_without_being_changed(): void
    {
        $boundary = $this->square(120, 15, .02);
        $existing = $this->plots->generate($boundary, 12);
        $excluded = array_column($existing, 'polygon_json');
        $before = $excluded;
        $plots = $this->plots->generate($boundary, 12, $excluded);
        $this->assertSame($before, $excluded);
        $this->assertValidPlots(array_merge($existing, $plots), $boundary);
    }

    public function test_holes_enclosed_by_a_candidate_are_excluded(): void
    {
        $boundary = $this->square(120, 15, .02);
        $hole = $this->square(120.01008, 15.00998, .00004);
        $boundary['coordinates'][] = $hole['coordinates'][0];
        $plots = $this->plots->generate($boundary, 12);
        $this->assertValidPlots($plots, $boundary);
        foreach ($plots as $plot) {
            $this->assertFalse($this->geometry->overlaps($hole, $this->geometry->fromLatLngRing($plot['polygon_json'])));
        }
    }

    public function test_multipart_boundaries_keep_plots_on_an_interior_component(): void
    {
        $boundary = ['type' => 'MultiPolygon', 'coordinates' => [
            $this->square(120, 15, .02)['coordinates'],
            $this->square(120.1, 15, .02)['coordinates'],
        ]];
        $this->assertValidPlots($this->plots->generate($boundary, 12), $boundary);
    }

    public function test_region_one_reference_municipalities_can_hold_twelve_plots_each(): void
    {
        foreach (['ilocos_norte' => 'Bacarra', 'ilocos_sur' => 'Narvacan', 'la_union' => 'Bacnotan', 'pangasinan' => 'Asingan'] as $province => $name) {
            $path = dirname(__DIR__, 2).'/database/seeders/data/'.$province.'_municipality_reference_boundaries.geojson';
            $snapshot = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $matches = array_values(array_filter($snapshot['features'], fn ($feature) => $feature['properties']['shapeName'] === $name));
            $this->assertCount(1, $matches);
            $boundary = $matches[0]['geometry'];
            $plots = $this->plots->generate($boundary, 12);
            $this->assertCount(12, $plots);
            $this->assertValidPlots($plots, $boundary);
        }
    }

    /** @dataProvider invalidCounts */
    public function test_invalid_counts_are_rejected(int $count): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->plots->generate($this->square(120, 15, .02), $count);
    }

    public function invalidCounts(): array
    {
        return [[0], [-1], [49]];
    }

    public function test_insufficient_interior_space_fails_without_a_partial_result(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No partial result');
        $this->plots->generate($this->square(120, 15, .0003), 1);
    }

    public function test_an_occupied_boundary_fails_without_reusing_an_existing_plot(): void
    {
        $boundary = $this->square(120, 15, .02);
        $excluded = array_map(fn ($point) => ['lng' => $point[0], 'lat' => $point[1]], array_slice($boundary['coordinates'][0], 0, -1));
        $this->expectException(RuntimeException::class);
        $this->plots->generate($boundary, 1, [$excluded]);
    }

    public function test_malformed_existing_geometry_is_not_silently_ignored(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->plots->generate($this->square(120, 15, .02), 1, [[['lat' => 'unknown', 'lng' => 120]]]);
    }

    public function test_nonfinite_boundary_coordinates_are_rejected(): void
    {
        $boundary = $this->square(120, 15, .02);
        $boundary['coordinates'][0][0][0] = INF;
        $this->expectException(InvalidArgumentException::class);
        $this->plots->generate($boundary, 1);
    }

    public function test_detailed_geometry_is_rejected_before_it_can_be_simplified(): void
    {
        config(['geofencing.simplify_above_vertices' => 50]);
        $ring = [];
        for ($i = 0; $i < 64; $i++) {
            $ring[] = [120 + cos($i * 2 * M_PI / 64) * .02, 15 + sin($i * 2 * M_PI / 64) * .02];
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be simplified');
        $this->plots->generate(['type' => 'Polygon', 'coordinates' => [$ring]], 1);
    }

    public function test_excluded_plot_count_is_bounded(): void
    {
        $ring = [['lat' => 15, 'lng' => 120], ['lat' => 15, 'lng' => 120.01], ['lat' => 15.01, 'lng' => 120]];
        $this->expectException(InvalidArgumentException::class);
        $this->plots->generate($this->square(120, 15, .02), 1, array_fill(0, 129, $ring));
    }

    private function assertValidPlots(array $plots, array $boundary): void
    {
        $geometries = [];
        foreach ($plots as $plot) {
            $this->assertCount(4, $plot['polygon_json']);
            $this->assertNotSame($plot['polygon_json'][0], $plot['polygon_json'][3]);
            $this->assertSame('inside', $this->geometry->classifyParcel($plot['polygon_json'], $boundary));
            $geometry = $this->geometry->fromLatLngRing($plot['polygon_json']);
            $this->assertEqualsWithDelta(.25, $plot['area_ha'], .001);
            $this->assertSame($this->geometry->areaHectares($geometry), $plot['area_ha']);
            $this->assertSame($this->geometry->centroid($geometry), [$plot['centroid_lat'], $plot['centroid_lng']]);
            foreach ($geometries as $other) {
                $this->assertFalse($this->geometry->overlaps($geometry, $other));
            }
            $geometries[] = $geometry;
        }
    }

    private function square(float $lng, float $lat, float $size): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + $size, $lat], [$lng + $size, $lat + $size], [$lng, $lat + $size], [$lng, $lat],
        ]]];
    }
}
