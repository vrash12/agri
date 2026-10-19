<?php

return [
    'refresh_batch_size' => min(200, max(10, (int) env('CLIMATE_REFRESH_BATCH_SIZE', 100))),
    'refresh_runtime_seconds' => min(600, max(30, (int) env('CLIMATE_REFRESH_RUNTIME_SECONDS', 240))),

    // Transparent inspection prompts, not official drought classifications.
    'monitoring' => [
        'high_temperature_c' => 35,
        'low_forecast_rain_mm' => 10,
        'rainfall_deficit_percent' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automated evidence refresh
    |--------------------------------------------------------------------------
    |
    | These feeds create evidence snapshots only. They never create or change
    | an office risk assessment and never mark a parcel as affected.
    |
    */
    'sources' => [
        'open_meteo' => [
            'enabled' => (bool) env('CLIMATE_OPEN_METEO_ENABLED', true),
            'refresh_hours' => max(1, (int) env('CLIMATE_OPEN_METEO_REFRESH_HOURS', 6)),
        ],
        'chirps' => [
            'enabled' => (bool) env('CLIMATE_CHIRPS_ENABLED', true),
            'refresh_hours' => max(6, (int) env('CLIMATE_CHIRPS_REFRESH_HOURS', 24)),
            'baseline_years' => min(5, max(1, (int) env('CLIMATE_CHIRPS_BASELINE_YEARS', 3))),
            'endpoint' => env('CLIMATE_CHIRPS_ENDPOINT', 'https://climateserv.servirglobal.net/api'),
            'timeout_seconds' => max(5, (int) env('CLIMATE_CHIRPS_TIMEOUT_SECONDS', 20)),
            'poll_seconds' => max(1, (int) env('CLIMATE_CHIRPS_POLL_SECONDS', 20)),
        ],
        // Sentinel requests remain opt-in because they consume the existing
        // account budget. Existing saved readings are always displayed.
        'sentinel2' => [
            'enabled' => (bool) env('CLIMATE_SENTINEL_AUTO_ENABLED', false),
            'max_plots' => min(50, max(1, (int) env('CLIMATE_SENTINEL_AUTO_MAX_PLOTS', 10))),
            'refresh_hours' => max(24, (int) env('CLIMATE_SENTINEL_AUTO_REFRESH_HOURS', 168)),
        ],
    ],
];
