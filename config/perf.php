<?php

return [
    // Enable lightweight SQL profiling (local/testing only). When true and environment is local/testing,
    // AppServiceProvider will attach a DB::listen() logger for query timings.
    'profile' => env('PERF_PROFILE', false),
    // Feature flags for performance-related caches
    'feature_cache_forecast_chart' => env('FEATURE_CACHE_FORECAST_CHART', false),
    'forecast_chart_ttl' => (int) env('FORECAST_CHART_TTL', 60), // seconds

    'feature_cache_strat_summary' => env('FEATURE_CACHE_STRAT_SUMMARY', false),
    'strat_summary_ttl' => (int) env('STRAT_SUMMARY_TTL', 600), // seconds (default 10 minutes)

    // Optional downsampling toggles (Proposals B & D)
    'forecast_downsample' => env('FORECAST_DOWNSAMPLE', false),
    'forecast_bucket_minutes' => (int) env('FORECAST_BUCKET_MINUTES', 30),

    'inverter_downsample' => env('INVERTER_DOWNSAMPLE', false),
    'inverter_bucket_minutes' => (int) env('INVERTER_BUCKET_MINUTES', 30),
];
