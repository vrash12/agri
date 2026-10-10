<?php

return [
    'enabled' => (bool) env('ASSISTANT_ENABLED', false),
    'model' => env('ASSISTANT_MODEL', 'gpt-4.1-mini-2025-04-14'),
    'timeout_seconds' => 20,
    'daily_requests' => max(0, (int) env('ASSISTANT_DAILY_REQUESTS', 100)),
    'monthly_requests' => max(0, (int) env('ASSISTANT_MONTHLY_REQUESTS', 1000)),
    'user_daily_requests' => max(0, (int) env('ASSISTANT_USER_DAILY_REQUESTS', 20)),
];
