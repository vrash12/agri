<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mutating request synchronization
    |--------------------------------------------------------------------------
    |
    | State-changing requests for the same route-bound record are serialized.
    | Creates without a record parameter are serialized per user and route.
    |
    */
    'write_lock_seconds' => (int) env('CONCURRENCY_LOCK_SECONDS', 120),
    'write_wait_seconds' => (int) env('CONCURRENCY_WAIT_SECONDS', 5),

    /* Retry transactions that lose a database deadlock race. */
    'transaction_attempts' => (int) env('DB_TRANSACTION_ATTEMPTS', 3),
];
