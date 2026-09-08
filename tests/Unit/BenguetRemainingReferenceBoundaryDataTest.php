<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class BenguetRemainingReferenceBoundaryDataTest extends TestCase
{
    public function test_the_ten_pinned_features_have_the_correct_benguet_identity_and_plausible_area(): void
    {
        $contents = file_get_contents(database_path('seeders/data/benguet_remaining_reference_boundaries.geojson'));
        $this->assertSame('9ca916bb897342b9b56ae57f10e0f4dd94572ed2e453a3bf7e827d4b45e6b1a7',
            hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)));
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('FeatureCollection', $document['type']);
        $this->assertSame('9469f09', $document['source']['commit']);
        $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);
        $this->assertSame(2020, $document['source']['boundary_year']);
        $expected = [
            'Bakun' => ['30758251B56433040620438', '1401103000', '141103000', 315.23852867],
            'Bokod' => ['30758251B72023971813507', '1401104000', '141104000', 392.0003863],
            'Buguias' => ['30758251B42417191393765', '1401105000', '141105000', 176.38592402],
            'Itogon' => ['30758251B42509136002589', '1401106000', '141106000', 438.15593933],
            'Kabayan' => ['30758251B34627250160857', '1401107000', '141107000', 190.17825829],
            'Kapangan' => ['30758251B17255873954553', '1401108000', '141108000', 180.96918408],
            'Kibungan' => ['30758251B24433463121222', '1401109000', '141109000', 167.11283852],
            'Mankayan' => ['30758251B68871031890947', '1401111000', '141111000', 146.1012692],
            'Sablan' => ['30758251B47648783241888', '1401112000', '141112000', 101.55429773],
            'Tuba' => ['30758251B14138125500475', '1401113000', '141113000', 341.9426068],
        ];
        $this->assertCount(10, $document['features']);
        $this->assertSame(array_keys($expected), array_column(array_column($document['features'], 'properties'), 'shapeName'));
        $service = app(GeoGeometry::class);

        foreach ($document['features'] as $feature) {
            $properties = $feature['properties'];
            [$shapeId, $psgc, $legacyPsgc, $referenceArea] = $expected[$properties['shapeName']];
            $this->assertSame($shapeId, $properties['shapeID']);
            $this->assertSame($psgc, $properties['psgc_code']);
            $this->assertSame($legacyPsgc, $properties['legacy_psgc_code']);
            $this->assertSame('Benguet', $properties['province']);
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

    public function test_all_thirteen_benguet_geofences_and_baguio_have_no_overlapping_interiors(): void
    {
        $prepared = [];
        $service = app(GeoGeometry::class);
        foreach ([
            'benguet_reference_boundaries.geojson',
            'benguet_remaining_reference_boundaries.geojson',
            'baguio_reference_boundary.geojson',
        ] as $filename) {
            $document = json_decode(file_get_contents(database_path('seeders/data/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
            foreach ($document['features'] as $feature) {
                $prepared[$feature['properties']['shapeName']] = $service->prepare($feature['geometry']);
            }
        }
        $this->assertCount(14, $prepared);
        $names = array_keys($prepared);
        foreach ($names as $index => $first) {
            foreach (array_slice($names, $index + 1) as $second) {
                $this->assertFalse($service->overlaps($prepared[$first], $prepared[$second]), $first.' must not overlap '.$second);
            }
        }
    }
}
