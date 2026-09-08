<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class TarlacRemainingReferenceBoundaryDataTest extends TestCase
{
    public function test_the_twelve_pinned_features_have_the_correct_tarlac_identity_and_plausible_area(): void
    {
        $contents = file_get_contents(database_path('seeders/data/tarlac_remaining_reference_boundaries.geojson'));
        $this->assertSame('3d9f24fabba985d50679d263998516cc05d24d2d6fc60390aa824d222d8d39b9',
            hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)));
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('FeatureCollection', $document['type']);
        $this->assertSame('9469f09', $document['source']['commit']);
        $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);
        $this->assertSame(2020, $document['source']['boundary_year']);
        $expected = [
            'Bamban' => ['30758251B57034914401732', '0306902000', '036902000', 207.47839251],
            'Capas' => ['30758251B1710226943062', '0306904000', '036904000', 446.47834915],
            'Gerona' => ['30758251B71774891580031', '0306906000', '036906000', 122.34883302],
            'La Paz' => ['30758251B69162702482749', '0306907000', '036907000', 115.70271156],
            'Mayantoc' => ['30758251B27820731210135', '0306908000', '036908000', 240.08967425],
            'Moncada' => ['30758251B50379489571525', '0306909000', '036909000', 101.74105474],
            'Pura' => ['30758251B36664202091084', '0306911000', '036911000', 32.11953092],
            'San Clemente' => ['30758251B85209892464019', '0306913000', '036913000', 71.27505251],
            'San Jose' => ['30758251B86793831645374', '0306918000', '036918000', 595.83013518],
            'San Manuel' => ['30758251B40197742842529', '0306914000', '036914000', 32.678553],
            'Santa Ignacia' => ['30758251B1064490572832', '0306915000', '036915000', 131.45882843],
            'Victoria' => ['30758251B28872871222208', '0306917000', '036917000', 109.66356898],
        ];
        $this->assertCount(12, $document['features']);
        $this->assertSame(array_keys($expected), array_column(array_column($document['features'], 'properties'), 'shapeName'));
        $service = app(GeoGeometry::class);

        foreach ($document['features'] as $feature) {
            $properties = $feature['properties'];
            [$shapeId, $psgc, $legacyPsgc, $referenceArea] = $expected[$properties['shapeName']];
            $this->assertSame($shapeId, $properties['shapeID']);
            $this->assertSame($psgc, $properties['psgc_code']);
            $this->assertSame($legacyPsgc, $properties['legacy_psgc_code']);
            $this->assertSame('Tarlac', $properties['province']);
            $this->assertSame('ADM3', $properties['shapeType']);
            $this->assertSame('PHL', $properties['shapeGroup']);
            $this->assertEqualsWithDelta($referenceArea, $properties['reference_area_sqkm'], 0.000001);
            $geometry = $service->prepare($feature['geometry']);
            $this->assertLessThan(0.03, abs($service->areaHectares($geometry) - $referenceArea * 100) / ($referenceArea * 100), $properties['shapeName']);
            $bounds = $service->bounds($geometry);
            foreach (['min_lng', 'min_lat', 'max_lng', 'max_lat'] as $index => $key) {
                $this->assertEqualsWithDelta($properties['reference_bbox'][$index], $bounds[$key], 0.02, $properties['shapeName'].' '.$key);
            }
        }
    }

    public function test_all_eighteen_tarlac_geofences_and_bulacan_have_no_overlapping_interiors(): void
    {
        $prepared = [];
        $service = app(GeoGeometry::class);
        foreach ([
            'tarlac_reference_boundaries.geojson',
            'tarlac_extended_reference_boundaries.geojson',
            'tarlac_remaining_reference_boundaries.geojson',
            'bulacan_reference_boundary.geojson',
        ] as $filename) {
            $document = json_decode(file_get_contents(database_path('seeders/data/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
            foreach ($document['features'] as $feature) {
                $prepared[$feature['properties']['shapeName']] = $service->prepare($feature['geometry']);
            }
        }
        $this->assertCount(19, $prepared);
        $names = array_keys($prepared);
        foreach ($names as $index => $first) {
            foreach (array_slice($names, $index + 1) as $second) {
                $this->assertFalse($service->overlaps($prepared[$first], $prepared[$second]), $first.' must not overlap '.$second);
            }
        }
    }
}
