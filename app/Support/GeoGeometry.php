<?php

namespace App\Support;

use InvalidArgumentException;

final class GeoGeometry
{
    private const EPSILON = 0.000000001;

    /**
     * Normalize, validate, and safely simplify a GeoJSON Polygon/MultiPolygon.
     *
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>
     */
    public function prepare(array $geometry): array
    {
        $geometry = $this->normalize($geometry);
        $vertices = $this->vertexCount($geometry);
        $hardLimit = max(100, (int) config('geofencing.maximum_vertices', 10000));

        if ($vertices > $hardLimit) {
            throw new InvalidArgumentException(
                "The boundary contains {$vertices} points; the maximum is {$hardLimit}. Simplify it in your GIS software and try again."
            );
        }

        $simplifyAbove = max(50, (int) config('geofencing.simplify_above_vertices', 2500));
        if ($vertices > $simplifyAbove) {
            $geometry = $this->simplifyToLimit($geometry, $simplifyAbove);
            $geometry = $this->normalize($geometry);
        }

        $this->assertValid($geometry);

        return $geometry;
    }

    /**
     * @param  array<int, array{lat:mixed,lng:mixed}>  $points
     * @return array<string, mixed>
     */
    public function fromLatLngRing(array $points): array
    {
        $coordinates = [];

        foreach ($points as $point) {
            if (! isset($point['lat'], $point['lng'])) {
                continue;
            }

            $coordinates[] = [(float) $point['lng'], (float) $point['lat']];
        }

        return $this->prepare([
            'type' => 'Polygon',
            'coordinates' => [$coordinates],
        ]);
    }

    /** @param  array<string, mixed>  $geometry */
    public function vertexCount(array $geometry): int
    {
        $count = 0;
        foreach ($this->polygons($geometry) as $polygon) {
            foreach ($polygon as $ring) {
                $count += max(0, count($ring) - 1);
            }
        }

        return $count;
    }

    /** @param  array<string, mixed>  $geometry */
    public function areaHectares(array $geometry): float
    {
        $area = 0.0;

        foreach ($this->polygons($geometry) as $polygon) {
            if ($polygon === []) {
                continue;
            }

            $area += abs($this->ringAreaSquareMeters($polygon[0]));
            foreach (array_slice($polygon, 1) as $hole) {
                $area -= abs($this->ringAreaSquareMeters($hole));
            }
        }

        return max(0.0, $area / 10000.0);
    }

    /**
     * Return [latitude, longitude].
     *
     * @param  array<string, mixed>  $geometry
     * @return array{0: float, 1: float}
     */
    public function centroid(array $geometry): array
    {
        $weightedLng = 0.0;
        $weightedLat = 0.0;
        $weightTotal = 0.0;

        foreach ($this->polygons($geometry) as $polygon) {
            $ring = $polygon[0] ?? [];
            [$lng, $lat, $weight] = $this->ringCentroid($ring);
            $weightedLng += $lng * $weight;
            $weightedLat += $lat * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= self::EPSILON) {
            $first = $this->polygons($geometry)[0][0] ?? [];
            $points = array_slice($first, 0, -1);
            $count = count($points);

            if ($count === 0) {
                return [0.0, 0.0];
            }

            return [
                array_sum(array_column($points, 1)) / $count,
                array_sum(array_column($points, 0)) / $count,
            ];
        }

        return [$weightedLat / $weightTotal, $weightedLng / $weightTotal];
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array{min_lat: float,max_lat: float,min_lng: float,max_lng: float}
     */
    public function bounds(array $geometry): array
    {
        $lats = [];
        $lngs = [];

        foreach ($this->polygons($geometry) as $polygon) {
            foreach ($polygon as $ring) {
                foreach ($ring as $point) {
                    $lngs[] = $point[0];
                    $lats[] = $point[1];
                }
            }
        }

        return [
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }

    /**
     * Classify a farm parcel against one municipality boundary.
     *
     * @param  array<int, array{lat:mixed,lng:mixed}>  $parcel
     * @param  array<string, mixed>  $boundary
     */
    public function classifyParcel(array $parcel, array $boundary): string
    {
        $parcelGeometry = $this->fromLatLngRing($parcel);
        $parcelBounds = $this->bounds($parcelGeometry);
        $boundaryBounds = $this->bounds($boundary);
        if (
            $parcelBounds['max_lat'] < $boundaryBounds['min_lat']
            || $parcelBounds['min_lat'] > $boundaryBounds['max_lat']
            || $parcelBounds['max_lng'] < $boundaryBounds['min_lng']
            || $parcelBounds['min_lng'] > $boundaryBounds['max_lng']
        ) {
            return 'outside';
        }

        $parcelRing = $this->polygons($parcelGeometry)[0][0];
        $parcelPoints = array_slice($parcelRing, 0, -1);
        $insideCount = 0;

        foreach ($parcelPoints as $point) {
            if ($this->pointInGeometry($point, $boundary, true)) {
                $insideCount++;
            }
        }

        $intersects = $this->ringIntersectsGeometry($parcelRing, $boundary);

        if ($insideCount === count($parcelPoints) && ! $intersects) {
            $nearMeters = max(0.0, (float) config('geofencing.near_boundary_meters', 20));

            return $this->minimumBoundaryDistanceMeters($parcelPoints, $boundary) <= $nearMeters
                ? 'near_boundary'
                : 'inside';
        }

        if ($insideCount > 0 || $intersects || $this->geometryHasPointInsideRing($boundary, $parcelRing)) {
            return 'partial';
        }

        return 'outside';
    }

    /** @param  array<string, mixed>  $first @param  array<string, mixed>  $second */
    public function overlaps(array $first, array $second): bool
    {
        $firstBounds = $this->bounds($first);
        $secondBounds = $this->bounds($second);

        if (
            $firstBounds['max_lat'] < $secondBounds['min_lat']
            || $firstBounds['min_lat'] > $secondBounds['max_lat']
            || $firstBounds['max_lng'] < $secondBounds['min_lng']
            || $firstBounds['min_lng'] > $secondBounds['max_lng']
        ) {
            return false;
        }

        foreach ($this->polygons($first) as $firstPolygon) {
            foreach ($this->polygons($second) as $secondPolygon) {
                if ($this->ringsProperlyIntersect($firstPolygon[0], $secondPolygon[0])) {
                    return true;
                }

                foreach (array_slice($firstPolygon[0], 0, -1) as $point) {
                    if ($this->pointInPolygon($point, $secondPolygon, false)) {
                        return true;
                    }
                }
                foreach (array_slice($secondPolygon[0], 0, -1) as $point) {
                    if ($this->pointInPolygon($point, $firstPolygon, false)) {
                        return true;
                    }
                }

                [$firstLng, $firstLat] = $this->ringCentroid($firstPolygon[0]);
                [$secondLng, $secondLat] = $this->ringCentroid($secondPolygon[0]);
                if (
                    $this->pointInPolygon([$firstLng, $firstLat], $secondPolygon, false)
                    || $this->pointInPolygon([$secondLng, $secondLat], $firstPolygon, false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $geometry */
    public function metadata(array $geometry): array
    {
        [$lat, $lng] = $this->centroid($geometry);

        return array_merge([
            'geometry_type' => $geometry['type'],
            'vertices' => $this->vertexCount($geometry),
            'area_ha' => round($this->areaHectares($geometry), 4),
            'centroid_lat' => round($lat, 7),
            'centroid_lng' => round($lng, 7),
        ], $this->bounds($geometry));
    }

    /** @param  array<string, mixed>  $geometry */
    private function normalize(array $geometry): array
    {
        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;

        if (! in_array($type, ['Polygon', 'MultiPolygon'], true) || ! is_array($coordinates)) {
            throw new InvalidArgumentException('Only GeoJSON Polygon and MultiPolygon geometry is supported.');
        }

        $sourcePolygons = $type === 'Polygon' ? [$coordinates] : $coordinates;
        $polygons = [];

        foreach ($sourcePolygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                throw new InvalidArgumentException('Every polygon must contain an outer ring.');
            }

            $rings = [];
            foreach ($polygon as $ring) {
                $rings[] = $this->normalizeRing(is_array($ring) ? $ring : []);
            }
            $polygons[] = $rings;
        }

        return $type === 'Polygon'
            ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }

    /** @param  array<int, mixed>  $ring @return array<int, array{0: float,1: float}> */
    private function normalizeRing(array $ring): array
    {
        $points = [];

        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                throw new InvalidArgumentException('Each coordinate must contain numeric longitude and latitude values.');
            }

            $lng = (float) $point[0];
            $lat = (float) $point[1];
            if (! is_finite($lng) || ! is_finite($lat) || $lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                throw new InvalidArgumentException('A boundary coordinate is outside the valid latitude or longitude range.');
            }

            $candidate = [$lng, $lat];
            if ($points === [] || ! $this->samePoint($points[count($points) - 1], $candidate)) {
                $points[] = $candidate;
            }
        }

        if (count($points) > 1 && $this->samePoint($points[0], $points[count($points) - 1])) {
            array_pop($points);
        }

        if (count($points) < 3) {
            throw new InvalidArgumentException('Every boundary ring must contain at least three different points.');
        }

        $points[] = $points[0];

        return $points;
    }

    /** @param  array<string, mixed>  $geometry */
    private function assertValid(array $geometry): void
    {
        $polygons = $this->polygons($geometry);

        foreach ($polygons as $polygonIndex => $polygon) {
            foreach ($polygon as $ringIndex => $ring) {
                if ($this->ringSelfIntersects($ring)) {
                    throw new InvalidArgumentException('The boundary contains a self-intersecting ring. Adjust the crossing edges and try again.');
                }

                if (abs($this->ringAreaSquareMeters($ring)) < 1.0) {
                    throw new InvalidArgumentException('The boundary contains a ring with no measurable area.');
                }

                if ($ringIndex > 0) {
                    if (! $this->pointInRing($ring[0], $polygon[0], false) || $this->ringsIntersect($ring, $polygon[0])) {
                        throw new InvalidArgumentException('A boundary hole must be fully contained by its polygon.');
                    }
                }
            }

            for ($hole = 1; $hole < count($polygon); $hole++) {
                for ($other = $hole + 1; $other < count($polygon); $other++) {
                    if ($this->ringsIntersect($polygon[$hole], $polygon[$other])) {
                        throw new InvalidArgumentException('Boundary holes cannot overlap each other.');
                    }
                }
            }

            for ($otherPolygon = $polygonIndex + 1; $otherPolygon < count($polygons); $otherPolygon++) {
                if ($this->polygonsOverlap($polygon, $polygons[$otherPolygon])) {
                    throw new InvalidArgumentException('Parts of a MultiPolygon boundary cannot overlap each other.');
                }
            }
        }
    }

    /** @param  array<string, mixed>  $geometry @return array<int, array<int, array<int, array{0:float,1:float}>>> */
    private function polygons(array $geometry): array
    {
        return $geometry['type'] === 'Polygon'
            ? [$geometry['coordinates']]
            : $geometry['coordinates'];
    }

    /** @param  array<string, mixed>  $geometry */
    private function simplifyToLimit(array $geometry, int $limit): array
    {
        $tolerance = max(0.0000001, (float) config('geofencing.simplify_tolerance_degrees', 0.000005));
        $candidate = $geometry;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $polygons = [];
            foreach ($this->polygons($geometry) as $polygon) {
                $rings = [];
                foreach ($polygon as $ring) {
                    $rings[] = $this->simplifyRing($ring, $tolerance);
                }
                $polygons[] = $rings;
            }

            $candidate = $geometry['type'] === 'Polygon'
                ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
                : ['type' => 'MultiPolygon', 'coordinates' => $polygons];

            if ($this->vertexCount($candidate) <= $limit) {
                return $candidate;
            }

            $tolerance *= 2;
        }

        throw new InvalidArgumentException('The boundary is too detailed to simplify safely. Simplify it in your GIS software and try again.');
    }

    /** @param  array<int, array{0:float,1:float}>  $ring */
    private function simplifyRing(array $ring, float $tolerance): array
    {
        $open = array_slice($ring, 0, -1);
        if (count($open) <= 4) {
            return $ring;
        }

        $simplified = $this->douglasPeucker($open, $tolerance);
        if (count($simplified) < 3) {
            return $ring;
        }
        $simplified[] = $simplified[0];

        return $simplified;
    }

    /** @param  array<int, array{0:float,1:float}>  $points */
    private function douglasPeucker(array $points, float $epsilon): array
    {
        if (count($points) < 3) {
            return $points;
        }

        $maximumDistance = 0.0;
        $index = 0;
        $last = count($points) - 1;

        for ($current = 1; $current < $last; $current++) {
            $distance = $this->pointSegmentDistanceDegrees($points[$current], $points[0], $points[$last]);
            if ($distance > $maximumDistance) {
                $maximumDistance = $distance;
                $index = $current;
            }
        }

        if ($maximumDistance > $epsilon) {
            $left = $this->douglasPeucker(array_slice($points, 0, $index + 1), $epsilon);
            $right = $this->douglasPeucker(array_slice($points, $index), $epsilon);

            return array_merge(array_slice($left, 0, -1), $right);
        }

        return [$points[0], $points[$last]];
    }

    /** @param  array<int, array{0:float,1:float}>  $ring */
    private function ringSelfIntersects(array $ring): bool
    {
        $edgeCount = count($ring) - 1;
        for ($first = 0; $first < $edgeCount; $first++) {
            for ($second = $first + 1; $second < $edgeCount; $second++) {
                if (abs($first - $second) <= 1 || ($first === 0 && $second === $edgeCount - 1)) {
                    continue;
                }

                if ($this->segmentsIntersect($ring[$first], $ring[$first + 1], $ring[$second], $ring[$second + 1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<int, array{0:float,1:float}>  $first @param  array<int, array{0:float,1:float}>  $second */
    private function ringsIntersect(array $first, array $second): bool
    {
        for ($a = 0; $a < count($first) - 1; $a++) {
            for ($b = 0; $b < count($second) - 1; $b++) {
                if ($this->segmentsIntersect($first[$a], $first[$a + 1], $second[$b], $second[$b + 1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect an interior crossing while allowing neighboring municipalities to
     * share a border segment or vertex.
     *
     * @param  array<int, array{0:float,1:float}>  $first
     * @param  array<int, array{0:float,1:float}>  $second
     */
    private function ringsProperlyIntersect(array $first, array $second): bool
    {
        for ($a = 0; $a < count($first) - 1; $a++) {
            for ($b = 0; $b < count($second) - 1; $b++) {
                $o1 = $this->orientation($first[$a], $first[$a + 1], $second[$b]);
                $o2 = $this->orientation($first[$a], $first[$a + 1], $second[$b + 1]);
                $o3 = $this->orientation($second[$b], $second[$b + 1], $first[$a]);
                $o4 = $this->orientation($second[$b], $second[$b + 1], $first[$a + 1]);

                if ($o1 !== 0 && $o2 !== 0 && $o3 !== 0 && $o4 !== 0 && $o1 !== $o2 && $o3 !== $o4) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<int, array<int, float>>  $first @param  array<int, array<int, float>>  $second */
    private function polygonsOverlap(array $first, array $second): bool
    {
        return $this->ringsIntersect($first[0], $second[0])
            || $this->pointInPolygon($first[0][0], $second, false)
            || $this->pointInPolygon($second[0][0], $first, false);
    }

    /** @param  array{0:float,1:float}  $a @param  array{0:float,1:float}  $b @param  array{0:float,1:float}  $c @param  array{0:float,1:float}  $d */
    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $o1 = $this->orientation($a, $b, $c);
        $o2 = $this->orientation($a, $b, $d);
        $o3 = $this->orientation($c, $d, $a);
        $o4 = $this->orientation($c, $d, $b);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }

        return ($o1 === 0 && $this->onSegment($a, $c, $b))
            || ($o2 === 0 && $this->onSegment($a, $d, $b))
            || ($o3 === 0 && $this->onSegment($c, $a, $d))
            || ($o4 === 0 && $this->onSegment($c, $b, $d));
    }

    private function orientation(array $a, array $b, array $c): int
    {
        $value = ($b[1] - $a[1]) * ($c[0] - $b[0]) - ($b[0] - $a[0]) * ($c[1] - $b[1]);

        return abs($value) <= self::EPSILON ? 0 : ($value > 0 ? 1 : 2);
    }

    private function onSegment(array $a, array $point, array $b): bool
    {
        return $point[0] <= max($a[0], $b[0]) + self::EPSILON
            && $point[0] >= min($a[0], $b[0]) - self::EPSILON
            && $point[1] <= max($a[1], $b[1]) + self::EPSILON
            && $point[1] >= min($a[1], $b[1]) - self::EPSILON;
    }

    /** @param  array{0:float,1:float}  $point @param  array<string,mixed>  $geometry */
    private function pointInGeometry(array $point, array $geometry, bool $includeBoundary): bool
    {
        foreach ($this->polygons($geometry) as $polygon) {
            if ($this->pointInPolygon($point, $polygon, $includeBoundary)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, array<int, array{0:float,1:float}>>  $polygon */
    private function pointInPolygon(array $point, array $polygon, bool $includeBoundary): bool
    {
        if (! $this->pointInRing($point, $polygon[0], $includeBoundary)) {
            return false;
        }

        foreach (array_slice($polygon, 1) as $hole) {
            if ($this->pointInRing($point, $hole, true)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<int, array{0:float,1:float}>  $ring */
    private function pointInRing(array $point, array $ring, bool $includeBoundary): bool
    {
        $inside = false;
        $count = count($ring) - 1;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            if ($this->pointSegmentDistanceDegrees($point, $ring[$j], $ring[$i]) <= self::EPSILON) {
                return $includeBoundary;
            }

            $intersects = (($ring[$i][1] > $point[1]) !== ($ring[$j][1] > $point[1]))
                && ($point[0] < ($ring[$j][0] - $ring[$i][0]) * ($point[1] - $ring[$i][1])
                    / (($ring[$j][1] - $ring[$i][1]) ?: self::EPSILON) + $ring[$i][0]);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @param  array<int, array{0:float,1:float}>  $ring @param  array<string,mixed>  $geometry */
    private function ringIntersectsGeometry(array $ring, array $geometry): bool
    {
        foreach ($this->polygons($geometry) as $polygon) {
            foreach ($polygon as $boundaryRing) {
                if ($this->ringsIntersect($ring, $boundaryRing)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<string,mixed>  $geometry @param  array<int,array{0:float,1:float}>  $ring */
    private function geometryHasPointInsideRing(array $geometry, array $ring): bool
    {
        foreach ($this->polygons($geometry) as $polygon) {
            if ($this->pointInRing($polygon[0][0], $ring, false)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int,array{0:float,1:float}>  $points @param  array<string,mixed>  $geometry */
    private function minimumBoundaryDistanceMeters(array $points, array $geometry): float
    {
        $minimum = INF;

        foreach ($points as $point) {
            foreach ($this->polygons($geometry) as $polygon) {
                foreach ($polygon as $ring) {
                    for ($edge = 0; $edge < count($ring) - 1; $edge++) {
                        $minimum = min($minimum, $this->pointSegmentDistanceMeters($point, $ring[$edge], $ring[$edge + 1]));
                    }
                }
            }
        }

        return $minimum;
    }

    private function pointSegmentDistanceMeters(array $point, array $start, array $end): float
    {
        $latitude = deg2rad(($point[1] + $start[1] + $end[1]) / 3);
        $scaleX = 111320.0 * cos($latitude);
        $scaleY = 110540.0;

        return $this->pointSegmentDistanceDegrees(
            [$point[0] * $scaleX, $point[1] * $scaleY],
            [$start[0] * $scaleX, $start[1] * $scaleY],
            [$end[0] * $scaleX, $end[1] * $scaleY]
        );
    }

    private function pointSegmentDistanceDegrees(array $point, array $start, array $end): float
    {
        $dx = $end[0] - $start[0];
        $dy = $end[1] - $start[1];

        if (abs($dx) <= self::EPSILON && abs($dy) <= self::EPSILON) {
            return hypot($point[0] - $start[0], $point[1] - $start[1]);
        }

        $t = (($point[0] - $start[0]) * $dx + ($point[1] - $start[1]) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        return hypot($point[0] - ($start[0] + $t * $dx), $point[1] - ($start[1] + $t * $dy));
    }

    /** @param  array<int,array{0:float,1:float}>  $ring */
    private function ringAreaSquareMeters(array $ring): float
    {
        $radius = 6378137.0;
        $sum = 0.0;

        for ($index = 0; $index < count($ring) - 1; $index++) {
            $lng1 = deg2rad($ring[$index][0]);
            $lat1 = deg2rad($ring[$index][1]);
            $lng2 = deg2rad($ring[$index + 1][0]);
            $lat2 = deg2rad($ring[$index + 1][1]);
            $sum += ($lng2 - $lng1) * (2 + sin($lat1) + sin($lat2));
        }

        return $sum * ($radius * $radius / 2.0);
    }

    /** @param  array<int,array{0:float,1:float}>  $ring @return array{0:float,1:float,2:float} */
    private function ringCentroid(array $ring): array
    {
        $twiceArea = 0.0;
        $x = 0.0;
        $y = 0.0;

        for ($index = 0; $index < count($ring) - 1; $index++) {
            $cross = $ring[$index][0] * $ring[$index + 1][1] - $ring[$index + 1][0] * $ring[$index][1];
            $twiceArea += $cross;
            $x += ($ring[$index][0] + $ring[$index + 1][0]) * $cross;
            $y += ($ring[$index][1] + $ring[$index + 1][1]) * $cross;
        }

        if (abs($twiceArea) <= self::EPSILON) {
            return [0.0, 0.0, 0.0];
        }

        $weight = abs($twiceArea);

        return [$x / (3 * $twiceArea), $y / (3 * $twiceArea), $weight];
    }

    private function samePoint(array $first, array $second): bool
    {
        return abs($first[0] - $second[0]) <= self::EPSILON
            && abs($first[1] - $second[1]) <= self::EPSILON;
    }
}
