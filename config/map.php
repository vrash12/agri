<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parcel payload ceiling
    |--------------------------------------------------------------------------
    |
    | The most parcels one map request will return. Parcel geometry is the
    | heaviest thing this system sends to a browser — a carefully traced
    | agricultural boundary runs to a few kilobytes on its own — and the office
    | works over rural connections, so an unbounded response is a page that never
    | finishes loading rather than a slow one.
    |
    | Reaching this ceiling is never silent. The response says how many parcels
    | exist, how many it returned, and that it stopped early, so the map can tell
    | the user to narrow to a municipality instead of quietly drawing a partial
    | picture of their area.
    |
    | Raise it for a province with denser mapping; lower it for offices on poor
    | connections. It is a deployment setting, not a code constant, because the
    | right number differs by province.
    |
    */

    'max_plots_per_request' => (int) env('MAP_MAX_PLOTS_PER_REQUEST', 2000),

];
