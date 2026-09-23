<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use InvalidArgumentException;
use Tests\TestCase;

class GeoGeometryTest extends TestCase
{
    private GeoGeometry $geometry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geometry = app(GeoGeometry::class);
    }

    public function test_it_normalizes_and_measures_a_valid_polygon(): void
    {
        $boundary = $this->geometry->prepare($this->square(120.50, 15.40, 0.01));

        $this->assertSame('Polygon', $boundary['type']);
        $this->assertCount(5, $boundary['coordinates'][0]);
        $this->assertSame(4, $this->geometry->vertexCount($boundary));
        $this->assertGreaterThan(100.0, $this->geometry->areaHectares($boundary));
        $this->assertLessThan(130.0, $this->geometry->areaHectares($boundary));
    }

    public function test_label_positions_avoid_holes_and_water_between_islands(): void
    {
        $outer = [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]];
        $hole = [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]];
        $point = $this->geometry->labelPosition(['type' => 'Polygon', 'coordinates' => [$outer, $hole]]);
        $this->assertNotNull($point);
        $this->assertTrue($point['lat'] < 4 || $point['lat'] > 6 || $point['lng'] < 4 || $point['lng'] > 6);
        $other = array_map(fn ($point) => [$point[0] + 30, $point[1]], $outer);
        $point = $this->geometry->labelPosition(['type' => 'MultiPolygon', 'coordinates' => [[$outer], [$other]]]);
        $this->assertNotNull($point);
        $this->assertTrue($point['lng'] < 10 || $point['lng'] > 30);
    }

    public function test_every_region_two_reference_has_a_finite_label_position(): void
    {
        $count = 0;
        foreach (['batanes_municipality_reference_boundaries', 'cagayan_municipality_reference_boundaries', 'isabela_municipality_reference_boundaries', 'nueva_vizcaya_municipality_reference_boundaries', 'quirino_municipality_reference_boundaries', 'santiago_city_reference_boundary'] as $source) {
            $data = json_decode(file_get_contents(database_path('seeders/data/'.$source.'.geojson')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($data['features'] as $feature) {
                $position = $this->geometry->labelPosition($feature['geometry']);
                $this->assertNotNull($position);
                $this->assertTrue(is_finite($position['lat']) && is_finite($position['lng']));
                $count++;
            }
        }
        $this->assertSame(93, $count);
    }

    public function test_it_rejects_a_self_intersecting_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('self-intersecting');

        $this->geometry->prepare([
            'type' => 'Polygon',
            'coordinates' => [[
                [120.50, 15.40],
                [120.51, 15.41],
                [120.50, 15.41],
                [120.51, 15.40],
                [120.50, 15.40],
            ]],
        ]);
    }

    public function test_it_accepts_a_narrow_parcel_with_dense_survey_vertices(): void
    {
        // Two diagonal survey edges about one metre apart remain disjoint even
        // when their short segments have overlapping longitude/latitude bounds.
        $corners = [[120.60, 15.60], [120.601, 15.601], [120.60099, 15.60101], [120.59999, 15.60001]];
        $points = [];
        foreach ($corners as $index => $start) {
            $end = $corners[($index + 1) % count($corners)];
            $steps = $index % 2 === 0 ? 100 : 1;
            for ($step = 0; $step < $steps; $step++) {
                $points[] = [
                    'lng' => $start[0] + ($end[0] - $start[0]) * $step / $steps,
                    'lat' => $start[1] + ($end[1] - $start[1]) * $step / $steps,
                ];
            }
        }

        $parcel = $this->geometry->fromLatLngRing($points);

        $this->assertSame(count($points), $this->geometry->vertexCount($parcel));
        $this->assertGreaterThan(0.01, $this->geometry->areaHectares($parcel));
        $this->assertLessThan(0.04, $this->geometry->areaHectares($parcel));
    }

    public function test_it_still_rejects_crossing_edges_at_survey_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('self-intersecting');

        $this->geometry->fromLatLngRing([
            ['lng' => 120.60, 'lat' => 15.60],
            ['lng' => 120.60002, 'lat' => 15.60002],
            ['lng' => 120.60, 'lat' => 15.60002],
            ['lng' => 120.60002, 'lat' => 15.60],
        ]);
    }

    public function test_short_disjoint_edges_are_not_mistaken_for_a_crossing(): void
    {
        $parcel = $this->geometry->fromLatLngRing([
            ['lng' => 120.60, 'lat' => 15.60],
            ['lng' => 120.6001, 'lat' => 15.60],
            ['lng' => 120.60011, 'lat' => 15.600011],
            ['lng' => 120.60001, 'lat' => 15.600009],
        ]);

        $this->assertSame(4, $this->geometry->vertexCount($parcel));
        $this->assertGreaterThan(0.0008, $this->geometry->areaHectares($parcel));
        $this->assertLessThan(0.0015, $this->geometry->areaHectares($parcel));
    }

    public function test_it_classifies_inside_partial_and_outside_parcels(): void
    {
        $boundary = $this->geometry->prepare($this->square(120.50, 15.40, 0.02));

        $inside = $this->latLngSquare(120.505, 15.405, 0.002);
        $partial = $this->latLngSquare(120.519, 15.409, 0.003);
        $outside = $this->latLngSquare(120.53, 15.43, 0.002);

        $this->assertSame('inside', $this->geometry->classifyParcel($inside, $boundary));
        $this->assertSame('partial', $this->geometry->classifyParcel($partial, $boundary));
        $this->assertSame('outside', $this->geometry->classifyParcel($outside, $boundary));
    }

    public function test_it_detects_boundary_overlap_without_confusing_separate_areas(): void
    {
        $first = $this->geometry->prepare($this->square(120.50, 15.40, 0.02));
        $overlap = $this->geometry->prepare($this->square(120.51, 15.41, 0.02));
        $separate = $this->geometry->prepare($this->square(120.60, 15.50, 0.01));

        $this->assertTrue($this->geometry->overlaps($first, $overlap));
        $this->assertFalse($this->geometry->overlaps($first, $separate));
    }

    public function test_neighboring_boundaries_may_share_an_edge_without_overlapping(): void
    {
        $first = $this->geometry->prepare($this->square(120.50, 15.40, 0.02));
        $neighbor = $this->geometry->prepare($this->square(120.52, 15.40, 0.02));
        $identical = $this->geometry->prepare($this->square(120.50, 15.40, 0.02));

        $this->assertFalse($this->geometry->overlaps($first, $neighbor));
        $this->assertTrue($this->geometry->overlaps($first, $identical));
    }

    public function test_a_concave_boundary_does_not_overlap_the_neighbour_in_its_notch(): void
    {
        $concave = $this->geometry->prepare($this->notchedBoundary());
        $neighbour = $this->geometry->prepare([
            'type' => 'Polygon',
            'coordinates' => [[[1, 1], [3, 1], [3, 4], [1, 4], [1, 1]]],
        ]);

        $this->assertFalse($this->geometry->overlaps($concave, $neighbour));
        $this->assertFalse($this->geometry->overlaps($neighbour, $concave));
        $this->assertTrue($this->geometry->overlaps($concave, $concave));
        $this->assertTrue($this->geometry->overlaps($concave, $this->square(0.2, 2, 0.3)));
        $this->assertTrue($this->geometry->overlaps($concave, $this->square(0.8, 2, 0.5)));
    }

    public function test_a_hole_is_not_filled_by_the_centroid_overlap_check(): void
    {
        $outer = $this->square(0, 0, 4);
        $island = $this->square(1, 1, 2);
        $outer['coordinates'][] = $island['coordinates'][0];
        $outer = $this->geometry->prepare($outer);
        $island = $this->geometry->prepare($island);

        $this->assertFalse($this->geometry->overlaps($outer, $island));
        $this->assertFalse($this->geometry->overlaps($island, $outer));
        $this->assertTrue($this->geometry->overlaps($outer, $outer));
        $this->assertTrue($this->geometry->overlaps($outer, $this->square(0.1, 0.1, 0.3)));
    }

    public function test_concave_multipolygon_parts_keep_real_overlap_detection(): void
    {
        $multi = $this->geometry->prepare([
            'type' => 'MultiPolygon',
            'coordinates' => [$this->notchedBoundary()['coordinates'], $this->square(6, 0, 2)['coordinates']],
        ]);

        $this->assertTrue($this->geometry->overlaps($multi, $multi));
        $this->assertTrue($this->geometry->overlaps($multi, $this->square(6.2, 0.2, 0.3)));
        $this->assertFalse($this->geometry->overlaps($multi, $this->square(1.2, 1.2, 1.5)));
    }

    public function test_multipolygon_parts_may_touch_at_a_single_point(): void
    {
        foreach ([
            $this->square(1, 1, 1)['coordinates'],
            [[[0.5, 1], [0, 2], [1, 2], [0.5, 1]]],
        ] as $neighbor) {
            $multi = $this->geometry->prepare(['type' => 'MultiPolygon', 'coordinates' => [
                $this->square(0, 0, 1)['coordinates'], $neighbor,
            ]]);
            $this->assertCount(2, $multi['coordinates']);
        }
    }

    /** @dataProvider invalidMultipolygonParts */
    public function test_multipolygon_parts_still_reject_shared_lines_and_overlapping_interiors(array $second): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parts of a MultiPolygon');
        $this->geometry->prepare(['type' => 'MultiPolygon', 'coordinates' => [
            $this->square(0, 0, 2)['coordinates'], $second,
        ]]);
    }

    public static function invalidMultipolygonParts(): array
    {
        return [
            'shared edge' => [[[[2, 0], [3, 0], [3, 2], [2, 2], [2, 0]]]],
            'partial shared edge' => [[[[2, 0.5], [3, 0.5], [3, 1.5], [2, 1.5], [2, 0.5]]]],
            'identical' => [[[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]],
            'contained' => [[[[0.5, 0.5], [1, 0.5], [1, 1], [0.5, 1], [0.5, 0.5]]]],
            'crossing' => [[[[1, -1], [3, -1], [3, 1], [1, 1], [1, -1]]]],
            'crossing through vertices' => [[[[-1, 1], [1, -1], [3, 1], [1, 3], [-1, 1]]]],
        ];
    }

    public function test_a_multipolygon_island_can_touch_a_hole_at_one_point_but_not_share_its_edge(): void
    {
        $outer = $this->square(0, 0, 6)['coordinates'];
        $outer[] = $this->square(1, 1, 4)['coordinates'][0];
        $island = [[[3, 1], [4, 3], [3, 4], [2, 3], [3, 1]]];
        $this->assertCount(2, $this->geometry->prepare(['type' => 'MultiPolygon', 'coordinates' => [$outer, $island]])['coordinates']);

        $this->expectException(InvalidArgumentException::class);
        $this->geometry->prepare(['type' => 'MultiPolygon', 'coordinates' => [$outer, $this->square(2, 1, 2)['coordinates']]]);
    }

    /** @return array<string, mixed> */
    private function notchedBoundary(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[[0, 0], [4, 0], [4, 4], [3, 4], [3, 1], [1, 1], [1, 4], [0, 4], [0, 0]]],
        ];
    }

    /** @return array<string, mixed> */
    private function square(float $lng, float $lat, float $size): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$lng, $lat],
                [$lng + $size, $lat],
                [$lng + $size, $lat + $size],
                [$lng, $lat + $size],
                [$lng, $lat],
            ]],
        ];
    }

    /** @return array<int, array{lat:float,lng:float}> */
    private function latLngSquare(float $lng, float $lat, float $size): array
    {
        return [
            ['lat' => $lat, 'lng' => $lng],
            ['lat' => $lat, 'lng' => $lng + $size],
            ['lat' => $lat + $size, 'lng' => $lng + $size],
            ['lat' => $lat + $size, 'lng' => $lng],
        ];
    }
}
