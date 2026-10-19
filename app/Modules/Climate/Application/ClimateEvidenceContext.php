<?php

namespace App\Modules\Climate\Application;

use App\Models\FarmPlot;
use App\Models\Municipality;

/** Identifies the reference area used for a source snapshot, without storing coordinates. */
final class ClimateEvidenceContext
{
    public static function municipality(Municipality $municipality): ?string
    {
        $boundary = $municipality->activeBoundary;
        if (! $boundary || ! is_numeric($boundary->centroid_lat) || ! is_numeric($boundary->centroid_lng)) {
            return null;
        }

        return hash('sha256', json_encode([$municipality->id, $boundary->id,
            (float) $boundary->centroid_lat, (float) $boundary->centroid_lng], JSON_THROW_ON_ERROR));
    }

    public static function plot(FarmPlot $plot): string
    {
        return hash('sha256', json_encode([$plot->id, $plot->farmer_id,
            $plot->farmer?->municipality_id, $plot->polygon_json], JSON_THROW_ON_ERROR));
    }
}
