<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Modules\Land\Application\PlantedCropAreas;
use App\Support\GeoGeometry;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlantedAreaGeometryTest extends TestCase
{
    public function test_near_closed_visual_marks_keep_points_and_enforce_enclosure_overlap(): void
    {
        $plot = new FarmPlot;
        $plot->polygon_json = [
            ['lat' => 15, 'lng' => 120], ['lat' => 15, 'lng' => 120.002],
            ['lat' => 15.002, 'lng' => 120.002], ['lat' => 15.002, 'lng' => 120],
        ];
        $path = [
            ['lat' => 15.0002, 'lng' => 120.0002], ['lat' => 15.0002, 'lng' => 120.0009],
            ['lat' => 15.0009, 'lng' => 120.0009], ['lat' => 15.0009, 'lng' => 120.0002],
            ['lat' => 15.000199, 'lng' => 120.000201],
        ];
        $mark = ['type' => 'line', 'crop' => 'corn', 'path' => $path];
        $service = new PlantedCropAreas(new GeoGeometry);
        $saved = $service->prepare($plot, [$mark]);
        $this->assertSame($path, $saved[0]['path']);
        $this->assertArrayNotHasKey('area_ha', $saved[0]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Crop sections cannot cover the same land');
        $service->prepare($plot, [$mark, $mark]);
    }

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

    public function test_freehand_crop_line_stays_inside_and_rejects_a_concave_excursion(): void
    {
        $geo = new GeoGeometry;
        $parent = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]]], false);
        $this->assertTrue($geo->containsPath($parent, [[.5, .5], [3.5, 3.5]]));

        $concave = $geo->prepare(['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [3, 4], [3, 1], [1, 1], [1, 4], [0, 4], [0, 0]]]], false);
        $this->assertFalse($geo->containsPath($concave, [[.5, .5], [3.5, 3.5]]));
    }
}
