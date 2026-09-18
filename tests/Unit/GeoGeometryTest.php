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
