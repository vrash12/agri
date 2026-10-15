<?php

namespace App\Support;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class PlantedCropAreas
{
    public function __construct(private GeoGeometry $geometry) {}

    /** Validate crop marks and enclosed boundaries without inferring parcel fills. */
    public function prepare(FarmPlot $plot, array $areas): array
    {
        if (count($areas) > 8 || ! array_is_list($areas)) {
            throw ValidationException::withMessages(['planted_areas' => 'Use up to 8 crop marks per plot and season.']);
        }
        if ($areas === []) {
            return [];
        }
        try {
            if (count($plot->polygon_json ?: []) > 1000) {
                throw new InvalidArgumentException('Crop drawing supports parcel boundaries with at most 1,000 points.');
            }
            $parent = $this->geometry->fromLatLngRing($plot->polygon_json ?: [], false);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['planted_areas' => 'The saved parcel boundary needs office review before crop areas can be drawn.']);
        }
        $result = [];
        $geometries = [];
        $keys = [];
        foreach ($areas as $index => $area) {
            try {
                $areaKey = (string) ($area['area_key'] ?? '');
                if ($areaKey === '') {
                    $areaKey = (string) Str::uuid();
                }
                if (! preg_match('/^[a-f0-9]{8}-[a-f0-9-]{27,36}$/i', $areaKey) || isset($keys[$areaKey])) {
                    throw new InvalidArgumentException('Each crop mark needs a unique section reference. Redraw or reload this season before saving.');
                }
                $keys[$areaKey] = true;
                $type = ($area['type'] ?? 'polygon') === 'line' ? 'line' : 'polygon';
                if (! isset(ParcelCropSeason::CROPS[$area['crop'] ?? '']) || $area['crop'] === 'not_recorded') {
                    throw new InvalidArgumentException('Choose a crop before saving the mark.');
                }
                if ($type === 'line') {
                    $path = $this->pathCoordinates($area['path'] ?? null);
                    if (count($path) < 2 || count($path) > 50 || count(array_unique(array_map(serialize(...), $path))) < 2) {
                        throw new InvalidArgumentException('Draw a crop mark with at least 2 and at most 50 points.');
                    }
                    if (! $this->geometry->containsPath($parent, $path)) {
                        throw new InvalidArgumentException('This crop mark leaves the saved parcel. On an irregular boundary, add points around each bend or along the white outline, then continue.');
                    }
                    $enclosure = $this->visualEnclosure($area['path'], $parent);
                    if ($enclosure !== null) {
                        $this->assertNoOverlap($geometries, $enclosure);
                        $geometries[] = $enclosure;
                    }
                    $result[] = [
                        'type' => 'line', 'area_key' => $areaKey, 'name' => $area['name'] ?? '', 'crop' => $area['crop'],
                        'variety' => $area['variety'] ?? '',
                        'path' => array_map(fn ($point) => ['lat' => $point[1], 'lng' => $point[0]], $path),
                    ];

                    continue;
                }
                if (! is_array($area['polygon'] ?? null) || count($area['polygon']) > 50) {
                    throw new InvalidArgumentException('Draw an enclosed crop area with at least 3 and at most 50 points.');
                }
                $shape = $this->geometry->fromLatLngRing($area['polygon'], false);
                if (! $this->geometry->containsPolygon($parent, $shape)) {
                    throw new InvalidArgumentException('This crop boundary crosses outside the saved parcel. Redraw only the planted part inside the white outline; unplanted land can remain blank. If the outline is wrong, update the parcel boundary first.');
                }
                $this->assertNoOverlap($geometries, $shape);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['planted_areas' => 'Area '.($index + 1).': '.$exception->getMessage()]);
            }
            $geometries[] = $shape;
            $ring = $shape['coordinates'][0];
            array_pop($ring);
            $result[] = [
                'area_key' => $areaKey, 'name' => $area['name'] ?? '', 'crop' => $area['crop'], 'variety' => $area['variety'] ?? '',
                'polygon' => array_map(fn ($point) => ['lat' => $point[1], 'lng' => $point[0]], $ring),
                'area_ha' => round($this->geometry->areaHectares($shape), 4),
            ];
        }

        return $result;
    }

    /** Invalid closures stay freehand lines, as in the display-only shading helper. */
    private function visualEnclosure(array $points, array $parent): ?array
    {
        $original = $this->strictVisualEnclosure($points, $parent);
        if ($original !== null || count($points) < 4) {
            return $original;
        }
        // Match the bounded display closure; keep the submitted line untouched
        // while enforcing overlap rules on any resulting visible enclosure.
        $first = $points[0];
        $last = $points[array_key_last($points)];
        $scale = cos(deg2rad((float) $first['lat']));
        $distance = fn ($point) => hypot(((float) $point['lng'] - (float) $first['lng']) * $scale,
            (float) $point['lat'] - (float) $first['lat']) * 111000;
        $gap = $distance($last);
        $extent = max(array_map($distance, $points));
        if ($gap <= 0 || $gap > min(1, $extent * .01)) {
            return null;
        }

        return $this->strictVisualEnclosure(array_slice($points, 0, -1), $parent)
            ?? $this->strictVisualEnclosure(array_slice($points, 1), $parent);
    }

    private function strictVisualEnclosure(array $points, array $parent): ?array
    {
        if (count($points) < 3) {
            return null;
        }
        try {
            $shape = $this->geometry->fromLatLngRing($points, false);

            return $this->geometry->containsPolygon($parent, $shape) ? $shape : null;
        } catch (InvalidArgumentException) {
            // Open, collinear or crossing marks remain supported; they have no fill.
            return null;
        }
    }

    private function assertNoOverlap(array $existingShapes, array $shape): void
    {
        foreach ($existingShapes as $existing) {
            if ($this->geometry->overlaps($existing, $shape) || $this->alignedOverlap($existing, $shape)) {
                throw new InvalidArgumentException('Crop sections cannot cover the same land. Move their points so they sit beside each other; shared edges are allowed. Use Mixed crops for intercropping in one section.');
            }
        }
    }

    /** Catch positive-area overlap along collinear edges, without rejecting shared edges. */
    private function alignedOverlap(array $first, array $second): bool
    {
        $a = $first['coordinates'][0];
        $b = $second['coordinates'][0];
        $rows = array_unique(array_column(array_merge($a, $b), 1));
        sort($rows, SORT_NUMERIC);
        for ($i = 1; $i < count($rows); $i++) {
            $y = ($rows[$i - 1] + $rows[$i]) / 2;
            $firstSpans = $this->scanline($a, $y);
            $secondSpans = $this->scanline($b, $y);
            for ($x = 0; $x + 1 < count($firstSpans); $x += 2) {
                for ($z = 0; $z + 1 < count($secondSpans); $z += 2) {
                    if (min($firstSpans[$x + 1], $secondSpans[$z + 1]) - max($firstSpans[$x], $secondSpans[$z]) > 1e-10) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return list<float> */
    private function scanline(array $ring, float $y): array
    {
        $crossings = [];
        for ($i = 1; $i < count($ring); $i++) {
            $a = $ring[$i - 1];
            $b = $ring[$i];
            if (($a[1] > $y) !== ($b[1] > $y)) {
                $crossings[] = $a[0] + ($b[0] - $a[0]) * ($y - $a[1]) / ($b[1] - $a[1]);
            }
        }
        sort($crossings, SORT_NUMERIC);

        return $crossings;
    }

    /** @return array<int, array{0:float,1:float}> */
    private function pathCoordinates(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $coordinates = [];
        foreach ($points as $point) {
            if (! is_array($point) || ! isset($point['lat'], $point['lng']) || ! is_numeric($point['lat']) || ! is_numeric($point['lng'])) {
                return [];
            }
            $lat = (float) $point['lat'];
            $lng = (float) $point['lng'];
            if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
                return [];
            }
            $coordinates[] = [$lng, $lat];
        }

        return $coordinates;
    }
}
