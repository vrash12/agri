<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forecast provider
    |--------------------------------------------------------------------------
    |
    | Open-Meteo does not require an API key for its non-commercial service.
    | Results are cached so dashboard traffic does not become provider traffic.
    |
    */
    'forecast_url' => env(
        'WEATHER_FORECAST_URL',
        'https://api.open-meteo.com/v1/forecast'
    ),
    'cache_minutes' => (int) env('WEATHER_CACHE_MINUTES', 30),
    'stale_hours' => (int) env('WEATHER_STALE_HOURS', 12),
    'timeout_seconds' => (int) env('WEATHER_TIMEOUT_SECONDS', 8),
    'refresh_lock_seconds' => (int) env('WEATHER_REFRESH_LOCK_SECONDS', 30),
    'refresh_wait_seconds' => (int) env('WEATHER_REFRESH_WAIT_SECONDS', 3),
    'timezone' => env('WEATHER_TIMEZONE', 'Asia/Manila'),

    /*
    |--------------------------------------------------------------------------
    | Advisory thresholds
    |--------------------------------------------------------------------------
    |
    | These values create operational guidance, not official warnings. PAGASA
    | remains the authority for tropical cyclone, rainfall, and flood warnings.
    |
    */
    'thresholds' => [
        'heavy_rain_mm' => (float) env('WEATHER_HEAVY_RAIN_MM', 50),
        'moderate_rain_mm' => (float) env('WEATHER_MODERATE_RAIN_MM', 25),
        'fieldwork_rain_probability' => (int) env(
            'WEATHER_FIELDWORK_RAIN_PROBABILITY',
            70
        ),
        'strong_gust_kmh' => (float) env('WEATHER_STRONG_GUST_KMH', 62),
        'moderate_gust_kmh' => (float) env('WEATHER_MODERATE_GUST_KMH', 40),
        'heat_celsius' => (float) env('WEATHER_HEAT_CELSIUS', 35),
        'dry_three_day_rain_mm' => (float) env(
            'WEATHER_DRY_THREE_DAY_RAIN_MM',
            3
        ),
        'high_et0_mm' => (float) env('WEATHER_HIGH_ET0_MM', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tarlac municipality reference points
    |--------------------------------------------------------------------------
    |
    | These coordinates are town-center reference points for municipality-level
    | forecasts. They are not parcel centroids and must not be used for surveys.
    |
    */
    'municipalities' => [
        'anao' => ['latitude' => 15.7292, 'longitude' => 120.6250],
        'bamban' => ['latitude' => 15.2732, 'longitude' => 120.5660],
        'camiling' => ['latitude' => 15.6863, 'longitude' => 120.4121],
        'capas' => ['latitude' => 15.3312, 'longitude' => 120.5898],
        'concepcion' => ['latitude' => 15.3249, 'longitude' => 120.6554],
        'gerona' => ['latitude' => 15.6065, 'longitude' => 120.5978],
        'la paz' => ['latitude' => 15.4432, 'longitude' => 120.7285],
        'mayantoc' => ['latitude' => 15.6190, 'longitude' => 120.3770],
        'moncada' => ['latitude' => 15.7351, 'longitude' => 120.5740],
        'paniqui' => ['latitude' => 15.6689, 'longitude' => 120.5806],
        'pura' => ['latitude' => 15.6248, 'longitude' => 120.6486],
        'ramos' => ['latitude' => 15.6651, 'longitude' => 120.6412],
        'san clemente' => ['latitude' => 15.7125, 'longitude' => 120.3594],
        'san jose' => ['latitude' => 15.4381, 'longitude' => 120.3372],
        'san manuel' => ['latitude' => 15.7992, 'longitude' => 120.6087],
        'santa ignacia' => ['latitude' => 15.6169, 'longitude' => 120.4358],
        'tarlac city' => ['latitude' => 15.4865, 'longitude' => 120.5903],
        'city of tarlac' => ['latitude' => 15.4865, 'longitude' => 120.5903],
        'victoria' => ['latitude' => 15.5781, 'longitude' => 120.6810],
    ],

    'pagasa' => [
        'weather' => 'https://www.pagasa.dost.gov.ph/weather',
        'tropical_cyclone' => 'https://www.pagasa.dost.gov.ph/tropical-cyclone/severe-weather-bulletin',
        'flood' => 'https://www.pagasa.dost.gov.ph/flood',
        'agri_weather' => 'https://www.pagasa.dost.gov.ph/agri-weather',
    ],
];
