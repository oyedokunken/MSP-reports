<?php

return [
    'id' => 'microsoft_license_utilization',
    'name' => 'Microsoft License Utilization Report',
    'description' => 'Track Microsoft 365 license consumption, identify unused licenses, and optimize costs',
    'class' => \App\Reports\MicrosoftLicenseUtilization\MicrosoftLicenseUtilization::class,
    'category' => 'licensing',
    'data_types' => ['flexible_asset'],
    'required_integrations' => ['ITGlue'],
    'frequencies' => ['monthly'],
    'formats' => ['pdf', 'html', 'xlsx', 'csv'],
    'default_frequency' => 'monthly',
    'default_format' => 'xlsx', // Default to Excel for data analysis
    'icon' => 'file-spreadsheet',
    'enabled' => true,
];
