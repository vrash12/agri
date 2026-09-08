<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class BenguetReferenceBoundaryDataTest extends TestCase
{
    public function test_pinned_municipalities_have_verified_identity_attribution_and_plausible_area(): void
    {
        $contents = file_get_contents(database_path('seeders/data/benguet_reference_boundaries.geojson'));
        $this->assertSame('6b089afa0c6d70ad949eb5deb7ce10b7a8264596510f0f3087b2065ee3bad288',
            hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)));
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('FeatureCollection', $document['type']);
        $this->assertSame('9469f09', $document['source']['commit']);
        $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);
        $this->assertSame(2020, $document['source']['boundary_year']);
        $this->assertCount(3, $document['features']);
        $expected = [
            'Atok' => ['30758251B63625349740918', '1401101000', '141101000', 17910.3503],
            'La Trinidad' => ['30758251B70129135029645', '1401110000', '141110000', 7109.2414],
            'Tublay' => ['30758251B5924892224435', '1401114000', '141114000', 7671.2429],
        ];
        $this->assertSame(array_keys($expected), array_column(array_column($document['features'], 'properties'), 'shapeName'));
        $geometryService = app(GeoGeometry::class);

        foreach ($document['features'] as $feature) {
            $properties = $feature['properties'];
            [$shapeId, $psgc, $legacyPsgc, $area] = $expected[$properties['shapeName']];
            $this->assertSame($shapeId, $properties['shapeID']);
            $this->assertSame($psgc, $properties['psgc_code']);
            $this->assertSame($legacyPsgc, $properties['legacy_psgc_code']);
            $this->assertSame('Benguet', $properties['province']);
            $this->assertSame('ADM3', $properties['shapeType']);
            $this->assertSame('PHL', $properties['shapeGroup']);
            $geometry = $geometryService->prepare($feature['geometry']);
            $actualArea = $geometryService->areaHectares($geometry);
            $this->assertEqualsWithDelta($area, $actualArea, 0.01);
            $referenceArea = $properties['reference_area_sqkm'] * 100;
            $this->assertLessThan(0.03, abs($actualArea - $referenceArea) / $referenceArea);
        }
    }

    public function test_the_three_geofences_and_baguio_share_no_overlapping_interior(): void
    {
        $document = json_decode(file_get_contents(database_path('seeders/data/benguet_reference_boundaries.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $baguio = json_decode(file_get_contents(database_path('seeders/data/baguio_reference_boundary.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $features = array_merge($document['features'], $baguio['features']);
        $geometryService = app(GeoGeometry::class);
        foreach ($features as $index => $first) {
            foreach (array_slice($features, $index + 1) as $second) {
                $this->assertFalse(
                    $geometryService->overlaps($geometryService->prepare($first['geometry']), $geometryService->prepare($second['geometry'])),
                    $first['properties']['shapeName'].' must not overlap '.$second['properties']['shapeName']
                );
            }
        }
    }
}
