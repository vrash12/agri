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
