<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class BaguioReferenceBoundaryDataTest extends TestCase
{
    public function test_the_pinned_city_geometry_has_verified_identity_attribution_and_plausible_area(): void
    {
        $contents = file_get_contents(database_path('seeders/data/baguio_reference_boundary.geojson'));
        $this->assertSame('103e72b2ce486c5275c9d310ae2cf62f7696fadb0d8305ee151ba0340de69529',
            hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)));
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('9469f09', $document['source']['commit']);
        $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);
        $this->assertSame('1430300000', $document['source']['psgc_code']);
        $this->assertSame('141102000', $document['source']['legacy_psgc_code']);
        $this->assertCount(1, $document['features']);
        $feature = $document['features'][0];
        $this->assertSame('Baguio City', $feature['properties']['shapeName']);
        $this->assertSame('30758251B18922588133033', $feature['properties']['shapeID']);
        $this->assertSame('ADM3', $feature['properties']['shapeType']);
        $this->assertSame('PHL', $feature['properties']['shapeGroup']);
        $geometryService = app(GeoGeometry::class);
        $geometry = $geometryService->prepare($feature['geometry']);
        $this->assertSame(33, $geometryService->vertexCount($geometry));
        $this->assertLessThan(0.03, abs($geometryService->areaHectares($geometry) - 5808.582396) / 5808.582396);
    }
}
