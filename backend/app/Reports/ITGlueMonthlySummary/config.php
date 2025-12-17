<?php

return [
    'id' => 'itglue_monthly_summary',
    'name' => 'ITGlue Monthly Summary Report',
    'description' => 'Comprehensive monthly report covering Microsoft licenses, SSL certificates, and domain registrations from ITGlue',
    'class' => \App\Reports\ITGlueMonthlySummary\ITGlueMonthlySummary::class,
    'category' => 'comprehensive',
    'data_types' => ['flexible_asset', 'ssl_certificate', 'domain'],
    'required_integrations' => ['ITGlue'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'file-text',
    'enabled' => true,
];
