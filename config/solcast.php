<?php

return [
    'api_key' => env('SOLCAST_API_KEY'),
    'resource_id' => env('SOLCAST_RESOURCE_ID'),
    'allowance' => [
        // Combined daily cap across forecast + actual endpoints
        'daily_cap' => env('SOLCAST_DAILY_CAP', 6),

        // ISO-8601 durations
        'forecast_min_interval' => env('SOLCAST_FORECAST_MIN_INTERVAL', 'PT4H'),
        'actual_min_interval' => env('SOLCAST_ACTUAL_MIN_INTERVAL', 'PT8H'),
        'backoff_429' => env('SOLCAST_429_BACKOFF', 'PT8H'),

        // IANA timezone for the daily reset boundary
        'reset_tz' => env('SOLCAST_RESET_TZ', 'UTC'),

        // Optional DB logging of allowance events
        'log_to_db' => env('SOLCAST_ALLOWANCE_LOG_TO_DB', false),
        // Max days to retain logs when pruning
        'log_max_days' => env('SOLCAST_ALLOWANCE_LOG_MAX_DAYS', 14),
    ],
];
