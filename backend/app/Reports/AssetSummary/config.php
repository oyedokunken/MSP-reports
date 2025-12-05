<?php

return [
    'id' => 'asset_summary',
    'name' => 'Asset Summary Report',
    'description' => 'Inventory of assets, configurations, and warranty expirations',
    'class' => \App\Reports\AssetSummary\AssetSummary::class,
    'category' => 'inventory',
    'data_types' => ['configuration'], // We will look for 'configuration' data
    'required_integrations' => ['ITGlue'], // Specific to ITGlue
    'frequencies' => ['monthly', 'quarterly', 'yearly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'server', // Icon for the UI
    'enabled' => true,
];
