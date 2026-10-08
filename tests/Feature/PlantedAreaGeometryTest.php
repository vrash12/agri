<?php

namespace Tests\Feature;

use App\Support\GeoGeometry;
use Tests\TestCase;

class PlantedAreaGeometryTest extends TestCase
{
    public function test_exact_inclusive_containment_and_adjacent_boundaries(): void
    {
        $geo = new GeoGeometry;
        $parent = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]]], false);
        $left = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [2, 0], [2, 4], [0, 4], [0, 0]]]], false);
        $right = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[2, 0], [4, 0], [4, 4], [2, 4], [2, 0]]]], false);
        $this->assertTrue($geo->containsPolygon($parent, $parent));
        $this->assertTrue($geo->containsPolygon($parent, $left));
        $this->assertFalse($geo->overlaps($left, $right));
    }

    public function test_edge_escaping_a_concave_parcel_at_vertices_is_not_contained(): void
    {
        $geo = new GeoGeometry;
        $parent = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [3, 4], [3, 1], [1, 1], [1, 4], [0, 4], [0, 0]]]], false);
        $triangle = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 4], [4, 4], [2, 0], [0, 4]]]], false);
        $this->assertFalse($geo->containsPolygon($parent, $triangle));
    }

    public function test_planted_polygon_cannot_swallow_a_boundary_hole(): void
    {
        $geo = new GeoGeometry;
        $parent = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]], [[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]]]], false);
        $area = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[.5, .5], [3, .5], [3, 3], [.5, 3], [.5, .5]]]], false);
        $this->assertFalse($geo->containsPolygon($parent, $area));
    }
}
