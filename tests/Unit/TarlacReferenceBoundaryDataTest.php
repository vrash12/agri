<?php

namespace Tests\Unit;

use App\Support\GeoGeometry;
use Tests\TestCase;

class TarlacReferenceBoundaryDataTest extends TestCase
{
    public function test_the_pinned_reference_boundaries_are_valid_and_non_overlapping(): void
    {
        $sources = [
            'seeders/data/tarlac_reference_boundaries.geojson' => 'a782a110527abd49b8ca91f6fd0636dcb1943989f0a3c12e78a9aa877755e815',
            'seeders/data/tarlac_extended_reference_boundaries.geojson' => '445c9a349e2313b34afd04105b5c81339aeeb03fdd3d5d04c1c50040ff6b9952',
        ];
        $features = collect();

        foreach ($sources as $relativePath => $checksum) {
            $path = database_path($relativePath);
            $this->assertFileExists($path);
            $contents = (string) file_get_contents($path);
            $this->assertSame(
                $checksum,
                hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents))
            );

            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('9469f09', $document['source']['commit']);
            $this->assertSame('CC BY 3.0 IGO', $document['source']['license']);
            $features = $features->concat($document['features']);
        }

        $expectations = [
            'Anao' => ['30758251B97244135669664', 2544.0],
            'Camiling' => ['30758251B23459833682053', 13139.0],
            'Concepcion' => ['30758251B96121562186522', 21310.0],
            'Paniqui' => ['30758251B69585850409571', 10664.0],
            'Ramos' => ['30758251B37101241671575', 2574.0],
            'City of Tarlac' => ['30758251B41951653344210', 26247.0],
        ];
        $features = $features->keyBy('properties.shapeName');
        $geometry = app(GeoGeometry::class);
        $prepared = [];

        foreach ($expectations as $name => [$shapeId, $psaAreaHectares]) {
            $feature = $features->get($name);
            $this->assertIsArray($feature, "Missing {$name} feature.");
            $this->assertSame($shapeId, $feature['properties']['shapeID']);

            $prepared[$name] = $geometry->prepare($feature['geometry']);
            $areaDifference = abs($geometry->areaHectares($prepared[$name]) - $psaAreaHectares)
                / $psaAreaHectares;
            $this->assertLessThan(0.03, $areaDifference, "{$name} failed the PSA area sanity check.");
        }

        $names = array_keys($prepared);
        foreach ($names as $leftIndex => $leftName) {
            foreach (array_slice($names, $leftIndex + 1) as $rightName) {
                $this->assertFalse(
                    $geometry->overlaps($prepared[$leftName], $prepared[$rightName]),
                    "{$leftName} unexpectedly overlaps {$rightName}."
                );
            }
        }
    }
}
