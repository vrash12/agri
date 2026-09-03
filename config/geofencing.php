<?php

return [
    /*
    | Keep browser and request payloads bounded on low-cost shared hosting.
    | Geometry above the simplify threshold is reduced before persistence;
    | geometry above the hard limit is rejected rather than exhausting PHP.
    */
    'simplify_above_vertices' => (int) env('GEOFENCE_SIMPLIFY_ABOVE', 2500),
    'maximum_vertices' => (int) env('GEOFENCE_MAXIMUM_VERTICES', 10000),
    'simplify_tolerance_degrees' => (float) env('GEOFENCE_SIMPLIFY_TOLERANCE', 0.000005),

    /* Parcels this close to a boundary are highlighted for field review. */
    'near_boundary_meters' => (float) env('GEOFENCE_NEAR_BOUNDARY_METERS', 20),
];
