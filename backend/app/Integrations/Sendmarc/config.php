<?php

declare(strict_types=1);

return [
    'name' => 'Sendmarc',
    'display_name' => 'Sendmarc DMARC',
    'description' => 'Integrate with Sendmarc to monitor email authentication, analyze sender statistics, and ensure DMARC compliance across domains.',
    'version' => '1.0.0',
    
    'credentials' => [
        'api_token' => [
            'type' => 'password',
            'label' => 'API Token',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your API Token',
            'help' => 'Found in Sendmarc Portal under API Settings',
        ],
        'base_url' => [
            'type' => 'text',
            'label' => 'API Base URL',
            'required' => false,
            'default' => 'https://portal.sendmarc.com/api/v1',
            'placeholder' => 'https://portal.sendmarc.com/api/v1',
            'help' => 'Leave default unless using a custom endpoint',
        ],
    ],
    
    'configuration' => [
        'base_url' => [
            'type' => 'text',
            'label' => 'API Base URL',
            'default' => 'https://portal.sendmarc.com/api/v1',
            'required' => false,
        ],
        'sync_sender_statistics' => [
            'type' => 'boolean',
            'label' => 'Sync Sender Statistics',
            'default' => true,
        ],
        'sync_volume_data' => [
            'type' => 'boolean',
            'label' => 'Sync Volume Data',
            'default' => true,
        ],
        'sync_ip_addresses' => [
            'type' => 'boolean',
            'label' => 'Sync IP Address Statistics',
            'default' => true,
        ],
    ],
    
    'data_types' => [
        'domains' => 'Email Domains & DMARC Status',
        'sender_statistics' => 'Detailed DMARC Sender Analysis',
        'volume_totals' => 'Aggregated Email Volume Data',
        'volume_timeline' => 'Historical Email Volume Tracking',
        'ip_address_statistics' => 'Source IP Reputation & Insights',
    ],
    
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
        'min_request_interval_ms' => 100,
        'concurrent_requests' => 3,
        'historical_import_multiplier' => 0.5,
    ],
    
    'sync_frequency' => [
        'recommended' => 'daily',
        'minimum' => 'daily',
        'maximum' => 'hourly',
    ],
    
    'historical_data' => [
        'supports_historical' => true,
        'max_range_days' => 90,
        'recommended_range_days' => 30,
    ],
    
    'features' => [
        'customer_mapping' => true,
        'historical_import' => true,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
