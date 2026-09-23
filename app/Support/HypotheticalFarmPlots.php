<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/** Generate demonstration geometry only; positions imply no surveyed ownership. */
final class HypotheticalFarmPlots
{
    private const MAXIMUM_PLOTS = 48;

    private const MAXIMUM_CANDIDATES = 1024;

    private const MAXIMUM_GEOMETRY_POINTS = 2500;

    private const MAXIMUM_EXCLUDED_PLOTS = 128;

    private const MAXIMUM_EXCLUDED_POINTS = 10000;

    private const METERS_PER_DEGREE = 111320.0;

    public function __construct(private GeoGeometry $geometry)
    {
    }

    /**
     * @param  array<string, mixed>  $boundary
     * @param  array<int, array<int, array{lat:mixed,lng:mixed}>>  $excluded
     * @return array<int, array{polygon_json:array<int, array{lat:float,lng:float}>,area_ha:float,centroid_lat:float,centroid_lng:float}>
     */
    public function generate(array $boundary, int $count, array $excluded = []): array
    {
        if ($count < 1 || $count > self::MAXIMUM_PLOTS) {
            throw new InvalidArgumentException('Request between 1 and 48 hypothetical plots.');
        }

        $boundary = $this->validatedBoundary($boundary);
        $occupied = $this->excludedGeometries($excluded);

        // Corner containment alone cannot detect a hole enclosed by a small parcel.
        $polygons = $boundary['type'] === 'Polygon' ? [$boundary['coordinates']] : $boundary['coordinates'];
        foreach ($polygons as $polygon) {
            foreach (array_slice($polygon, 1) as $hole) {
                $occupied[] = $this->withBounds(['type' => 'Polygon', 'coordinates' => [$hole]]);
            }
        }

        $anchor = $this->geometry->labelPosition($boundary);
        if ($anchor === null || abs($anchor['lat']) >= 80) {
            throw new InvalidArgumentException('The boundary has no supported interior position for hypothetical plots.');
        }

        $plots = [];
        foreach ($this->candidateRings($anchor) as $ring) {
            if ($this->geometry->classifyParcel($ring, $boundary) !== 'inside') {
                continue;
            }

            $geometry = $this->geometry->fromLatLngRing($ring);
            $candidate = $this->withBounds($geometry);
            if ($this->intersectsOccupied($candidate, $occupied)) {
                continue;
            }

            [$lat, $lng] = $this->geometry->centroid($geometry);
            $plots[] = [
                'polygon_json' => $ring,
                'area_ha' => $this->geometry->areaHectares($geometry),
                'centroid_lat' => $lat,
                'centroid_lng' => $lng,
            ];
            $occupied[] = $candidate;
            if (count($plots) === $count) {
                return $plots;
            }
        }

        throw new RuntimeException('Cannot place the requested hypothetical plots safely inside this boundary without overlap. No partial result was returned.');
    }

    /** @param  array<string, mixed>  $boundary @return array<string, mixed> */
    private function validatedBoundary(array $boundary): array
    {
        if (! in_array($boundary['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            || ! is_array($boundary['coordinates'] ?? null) || $boundary['coordinates'] === []) {
            throw new InvalidArgumentException('A nonempty Polygon or MultiPolygon boundary is required.');
        }

        $polygons = $boundary['type'] === 'Polygon' ? [$boundary['coordinates']] : $boundary['coordinates'];
        $points = 0;
        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                throw new InvalidArgumentException('Each boundary polygon needs an outer ring.');
            }
            foreach ($polygon as $ring) {
                if (! is_array($ring)) {
                    throw new InvalidArgumentException('Boundary rings must contain coordinate arrays.');
                }
                $points += count($ring);
                $this->assertGeometryLimit($points);
                foreach ($ring as $point) {
                    if (! is_array($point) || ! isset($point[0], $point[1])) {
                        throw new InvalidArgumentException('Boundary coordinates require longitude and latitude.');
                    }
                }
            }
        }

        return $this->geometry->prepare($boundary);
    }

    /** @param  array<int, array<int, array{lat:mixed,lng:mixed}>>  $excluded */
    private function excludedGeometries(array $excluded): array
    {
        if (count($excluded) > self::MAXIMUM_EXCLUDED_PLOTS) {
            throw new InvalidArgumentException('Too many existing plots for this bounded hypothetical import.');
        }

        $occupied = [];
        $totalPoints = 0;
        foreach ($excluded as $ring) {
            if (! is_array($ring)) {
                throw new InvalidArgumentException('Existing plots must contain coordinate arrays.');
            }
            $this->assertGeometryLimit(count($ring));
            $totalPoints += count($ring);
            if ($totalPoints > self::MAXIMUM_EXCLUDED_POINTS) {
                throw new InvalidArgumentException('Existing plot geometry exceeds the hypothetical import limit.');
            }
            foreach ($ring as $point) {
                if (! is_array($point) || ! isset($point['lat'], $point['lng'])
                    || ! is_numeric($point['lat']) || ! is_numeric($point['lng'])) {
                    throw new InvalidArgumentException('Existing plots require numeric latitude and longitude values.');
                }
            }
            $occupied[] = $this->withBounds($this->geometry->fromLatLngRing($ring));
        }

        return $occupied;
    }

    private function assertGeometryLimit(int $points): void
    {
        // Do not let GeoGeometry simplify either the real boundary or occupied plots.
        $limit = min(self::MAXIMUM_GEOMETRY_POINTS, max(50, (int) config('geofencing.simplify_above_vertices', 2500)));
        if ($points > $limit) {
            throw new InvalidArgumentException('Geometry is too detailed for this bounded hypothetical import; existing geometry must not be simplified.');
        }
    }

    /** @param  array{lat:float,lng:float}  $anchor */
    private function candidateRings(array $anchor): \Generator
    {
        $attempts = 0;
        for ($radius = 0; $attempts < self::MAXIMUM_CANDIDATES; $radius++) {
            for ($row = -$radius; $row <= $radius; $row++) {
                for ($column = -$radius; $column <= $radius; $column++) {
                    if (max(abs($row), abs($column)) !== $radius) {
                        continue;
                    }
                    if ($attempts++ >= self::MAXIMUM_CANDIDATES) {
                        return;
                    }
                    $lat = $anchor['lat'] + $row * 80 / self::METERS_PER_DEGREE;
                    $lng = $anchor['lng'] + $column * 80 / (self::METERS_PER_DEGREE * cos(deg2rad($anchor['lat'])));
                    $dy = 25 / self::METERS_PER_DEGREE;
                    $dx = 25 / (self::METERS_PER_DEGREE * cos(deg2rad($lat)));
                    if (abs($lat) + $dy >= 90 || abs($lng) + $dx >= 180) {
                        continue;
                    }

                    yield [
                        ['lat' => $lat - $dy, 'lng' => $lng - $dx],
                        ['lat' => $lat - $dy, 'lng' => $lng + $dx],
                        ['lat' => $lat + $dy, 'lng' => $lng + $dx],
                        ['lat' => $lat + $dy, 'lng' => $lng - $dx],
                    ];
                }
            }
        }
    }

    /** @param  array<string, mixed>  $geometry */
    private function withBounds(array $geometry): array
    {
        return ['geometry' => $geometry, 'bounds' => $this->geometry->bounds($geometry)];
    }

    private function intersectsOccupied(array $candidate, array $occupied): bool
    {
        $bounds = $candidate['bounds'];
        foreach ($occupied as $existing) {
            $other = $existing['bounds'];
            if ($bounds['max_lat'] < $other['min_lat'] || $bounds['min_lat'] > $other['max_lat']
                || $bounds['max_lng'] < $other['min_lng'] || $bounds['min_lng'] > $other['max_lng']) {
                continue;
            }
            if ($this->geometry->overlaps($candidate['geometry'], $existing['geometry'])) {
                return true;
            }
        }

        return false;
    }
}
