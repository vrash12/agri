<?php

return [
    // Enable only after privately configuring a Copernicus Data Space OAuth client.
    'enabled' => (bool) env('SENTINEL_ENABLED', false),
    'client_id' => env('SENTINEL_CLIENT_ID', ''),
    'client_secret' => env('SENTINEL_CLIENT_SECRET', ''),
    'cache_minutes' => 720,
    // Local request ceilings are additional safeguards, not a provider pricing guarantee.
    'daily_requests' => (int) env('SENTINEL_DAILY_REQUESTS', 100),
    'monthly_requests' => (int) env('SENTINEL_MONTHLY_REQUESTS', 2000),
];
