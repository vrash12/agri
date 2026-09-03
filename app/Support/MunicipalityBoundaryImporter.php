<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

final class MunicipalityBoundaryImporter
{
    public function __construct(private GeoGeometry $geometry)
    {
    }

    /** @return array<string, mixed> */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $contents = $extension === 'kmz'
            ? $this->readKmz($file)
            : file_get_contents($file->getRealPath());

        if (! is_string($contents) || trim($contents) === '') {
            throw new InvalidArgumentException('The uploaded boundary file is empty or unreadable.');
        }

        $geometry = in_array($extension, ['json', 'geojson'], true)
            ? $this->fromGeoJson($contents)
            : $this->fromKml($contents);

        return $this->geometry->prepare($geometry);
    }

    private function readKmz(UploadedFile $file): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException('KMZ import requires the PHP ZIP extension. Upload KML or GeoJSON instead.');
        }

        $archive = new ZipArchive();
        if ($archive->open($file->getRealPath()) !== true) {
            throw new InvalidArgumentException('The KMZ archive could not be opened.');
        }

        $kml = '';
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = (string) $archive->getNameIndex($index);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'kml') {
                $candidate = $archive->getFromIndex($index);
                if (is_string($candidate)) {
                    $kml = $candidate;
                    break;
                }
            }
        }
        $archive->close();

        if ($kml === '') {
            throw new InvalidArgumentException('The KMZ archive does not contain a KML boundary.');
        }

        return $kml;
    }

    /** @return array<string, mixed> */
    private function fromGeoJson(string $contents): array
    {
        $json = json_decode($contents, true);
        if (! is_array($json)) {
            throw new InvalidArgumentException('The GeoJSON file contains invalid JSON.');
        }

        $geometries = [];
        $type = (string) ($json['type'] ?? '');

        if (in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            $geometries[] = $json;
        } elseif ($type === 'Feature') {
            $geometries[] = $json['geometry'] ?? [];
        } elseif ($type === 'FeatureCollection') {
            foreach (($json['features'] ?? []) as $feature) {
                if (is_array($feature) && is_array($feature['geometry'] ?? null)) {
                    $geometries[] = $feature['geometry'];
                }
            }
        }

        return $this->mergeGeometries($geometries);
    }

    /** @return array<string, mixed> */
    private function fromKml(string $contents): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new InvalidArgumentException('The KML document is invalid.');
        }

        $namespaces = $xml->getDocNamespaces(true);
        $namespace = $namespaces[''] ?? $namespaces['kml'] ?? 'http://www.opengis.net/kml/2.2';
        $xml->registerXPathNamespace('kml', $namespace);
        $polygonNodes = $xml->xpath('//kml:Polygon') ?: [];
        $polygons = [];

        foreach ($polygonNodes as $polygonNode) {
            $polygonNode->registerXPathNamespace('kml', $namespace);
            $outerNodes = $polygonNode->xpath('./kml:outerBoundaryIs/kml:LinearRing/kml:coordinates') ?: [];
            if (! isset($outerNodes[0])) {
                continue;
            }

            $rings = [$this->parseKmlCoordinates((string) $outerNodes[0])];
            foreach (($polygonNode->xpath('./kml:innerBoundaryIs/kml:LinearRing/kml:coordinates') ?: []) as $inner) {
                $rings[] = $this->parseKmlCoordinates((string) $inner);
            }
            $polygons[] = $rings;
        }

        if ($polygons === []) {
            throw new InvalidArgumentException('No polygon boundary was found in the KML document.');
        }

        return count($polygons) === 1
            ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }

    /** @return array<int, array{0:float,1:float}> */
    private function parseKmlCoordinates(string $coordinates): array
    {
        $points = [];
        foreach ((preg_split('/\s+/', trim($coordinates)) ?: []) as $coordinate) {
            $parts = explode(',', $coordinate);
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $points[] = [(float) $parts[0], (float) $parts[1]];
            }
        }

        return $points;
    }

    /**
     * @param  array<int, mixed>  $geometries
     * @return array<string, mixed>
     */
    private function mergeGeometries(array $geometries): array
    {
        $polygons = [];

        foreach ($geometries as $geometry) {
            if (! is_array($geometry)) {
                continue;
            }

            $type = (string) ($geometry['type'] ?? '');
            $coordinates = $geometry['coordinates'] ?? [];
            if ($type === 'Polygon' && is_array($coordinates)) {
                $polygons[] = $coordinates;
            } elseif ($type === 'MultiPolygon' && is_array($coordinates)) {
                foreach ($coordinates as $polygon) {
                    $polygons[] = $polygon;
                }
            }
        }

        if ($polygons === []) {
            throw new InvalidArgumentException('The file contains no Polygon or MultiPolygon geometry.');
        }

        return count($polygons) === 1
            ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }
}
