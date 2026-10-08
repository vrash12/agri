<?php

namespace App\Support;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class PlantedCropAreas
{
    public function __construct(private GeoGeometry $geometry)
    {
    }

    /** Validate full boundaries, never inferred rectangles or parcel-wide fills. */
    public function prepare(FarmPlot $plot, array $areas): array
    {
        if (count($areas) > 8 || ! array_is_list($areas)) {
            throw ValidationException::withMessages(['planted_areas' => 'Use up to 8 planted areas per plot and season.']);
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
                if (! isset(ParcelCropSeason::CROPS[$area['crop'] ?? '']) || $area['crop'] === 'not_recorded' || ! is_array($area['polygon'] ?? null) || count($area['polygon']) > 50) {
                    throw new InvalidArgumentException('Choose a crop and use at most 50 points.');
                }
                $shape = $this->geometry->fromLatLngRing($area['polygon'], false);
                if (! $this->geometry->containsPolygon($parent, $shape)) {
                    throw new InvalidArgumentException('Keep the whole planted area inside the saved parcel boundary.');
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
}
