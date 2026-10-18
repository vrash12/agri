<?php

return [
    // Local aggregate counters only. No external monitoring service is required.
    'enabled' => (bool) env('USAGE_METRICS_ENABLED', true),
    'slow_query_ms' => 200,
    'slow_response_ms' => 1000,
];
