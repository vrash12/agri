<?php

namespace App\Support;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
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
        foreach ($areas as $index => $area) {
            try {
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
                        throw new InvalidArgumentException('This crop mark crosses outside the saved parcel. Redraw the line inside the white outline, or update the parcel boundary first.');
                    }
                    $result[] = [
                        'type' => 'line', 'name' => $area['name'] ?? '', 'crop' => $area['crop'],
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
                foreach ($geometries as $existing) {
                    if ($this->geometry->overlaps($existing, $shape)) {
                        throw new InvalidArgumentException('Planted areas cannot overlap. Use Mixed crops for intercropping in one area.');
                    }
                }
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['planted_areas' => 'Area '.($index + 1).': '.$exception->getMessage()]);
            }
            $geometries[] = $shape;
            $ring = $shape['coordinates'][0];
            array_pop($ring);
            $result[] = [
                'name' => $area['name'] ?? '', 'crop' => $area['crop'], 'variety' => $area['variety'] ?? '',
                'polygon' => array_map(fn ($point) => ['lat' => $point[1], 'lng' => $point[0]], $ring),
                'area_ha' => round($this->geometry->areaHectares($shape), 4),
            ];
        }

        return $result;
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
