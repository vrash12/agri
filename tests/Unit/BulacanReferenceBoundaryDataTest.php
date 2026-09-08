<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class BulacanReferenceBoundaryDataTest extends TestCase
{
    public function test_the_pinned_bulacan_province_boundary_is_valid(): void
    {
        $path = database_path('seeders/data/bulacan_reference_boundary.geojson');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $this->assertSame(
            '41c71c536b79c4ff7d85b88efa9a5cef41bf732926c6cf30b2f09f9025122d33',
            hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents))
        );

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('41af8f1', $document['source']['commit']);
        $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);

        $feature = collect($document['features'])->firstWhere('properties.shapeName', 'Bulacan');
        $this->assertIsArray($feature);
        $this->assertSame('2640588B70472166839855', $feature['properties']['shapeID']);
        $this->assertSame('ADM2', $feature['properties']['shapeType']);

        $geometry = app(GeoGeometry::class)->prepare($feature['geometry']);
        $areaDifference = abs(app(GeoGeometry::class)->areaHectares($geometry) - 278369.0) / 278369.0;

        $this->assertLessThan(0.03, $areaDifference);
    }
}
