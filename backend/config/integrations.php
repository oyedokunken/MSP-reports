<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Integration Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        // Stale sync threshold (hours) - syncs running longer than this are marked as failed
        'stale_threshold_hours' => env('INTEGRATION_STALE_THRESHOLD_HOURS', 2),
        
        // Default batch size for processing items
        'batch_size' => env('INTEGRATION_BATCH_SIZE', 50),
        
        // Chunk size for bulk database operations
        'chunk_size' => env('INTEGRATION_CHUNK_SIZE', 500),
        
        // Maximum array size for whereIn operations to avoid parameter limits
        'max_where_in_size' => env('INTEGRATION_MAX_WHERE_IN_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // Default requests per minute
        'default_rpm' => 60,
        
        // Default requests per hour
        'default_rph' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        // Days to keep integration data
        'data_retention_days' => env('INTEGRATION_DATA_RETENTION_DAYS', 90),
        
        // Days to keep sync logs
        'sync_log_retention_days' => env('INTEGRATION_SYNC_LOG_RETENTION_DAYS', 30),
    ],
];
