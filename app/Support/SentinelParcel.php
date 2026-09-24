<?php

namespace App\Support;

use App\Models\FarmPlot;
use InvalidArgumentException;

/** Preserve the saved polygon; never simplify a parcel for satellite measurement. */
final class SentinelParcel
{
    public function __construct(private GeoGeometry $geometry)
    {
    }

    public function describe(FarmPlot $plot): array
    {
        $points = $plot->polygon_json;
        if (! is_array($points) || count($points) < 3 || count($points) > 500) {
            throw new InvalidArgumentException('Satellite analysis requires a saved boundary with 3–500 points.');
        }
        foreach ($points as $point) {
            if (! is_array($point) || ! isset($point['lat'], $point['lng'])
                || ! is_numeric($point['lat']) || ! is_numeric($point['lng'])
                || ! is_finite((float) $point['lat']) || ! is_finite((float) $point['lng'])
                || abs((float) $point['lat']) > 60 || abs((float) $point['lng']) > 180) {
                throw new InvalidArgumentException('The saved parcel has invalid coordinates for satellite analysis.');
            }
        }
        // prepare() normally simplifies large boundaries; this bounded input stays below that threshold.
        if (count($points) > max(50, (int) config('geofencing.simplify_above_vertices', 2500))) {
            throw new InvalidArgumentException('This parcel exceeds the configured geometry limit.');
        }
        $geometry = $this->geometry->fromLatLngRing($points);
        $bounds = $this->geometry->bounds($geometry);
        $lat = ($bounds['min_lat'] + $bounds['max_lat']) / 2;
        $resY = 10 / 111320;
        $resX = $resY / cos(deg2rad($lat));
        $width = (int) ceil(($bounds['max_lng'] - $bounds['min_lng']) / $resX);
        $height = (int) ceil(($bounds['max_lat'] - $bounds['min_lat']) / $resY);
        if ($width < 1 || $height < 1 || $width > 1024 || $height > 1024 || $width * $height > 262144) {
            throw new InvalidArgumentException('The parcel extent exceeds the satellite pilot limit. Use a smaller saved parcel.');
        }

        return [
            'geometry' => $geometry,
            'bbox' => [$bounds['min_lng'], $bounds['min_lat'], $bounds['max_lng'], $bounds['max_lat']],
            'map_bounds' => ['west' => $bounds['min_lng'], 'south' => $bounds['min_lat'], 'east' => $bounds['max_lng'], 'north' => $bounds['max_lat']],
            'resx' => ($bounds['max_lng'] - $bounds['min_lng']) / $width,
            'resy' => ($bounds['max_lat'] - $bounds['min_lat']) / $height,
            'width' => $width, 'height' => $height,
            'area_ha' => $this->geometry->areaHectares($geometry),
            'center' => ['lat' => $lat, 'lng' => ($bounds['min_lng'] + $bounds['max_lng']) / 2],
        ];
    }
}
