<?php

return [
    'id' => 'itglue_summary',
    'name' => 'ITGlue Summary Report',
    'description' => 'Comprehensive monthly report covering Microsoft licenses, SSL certificates, and domain registrations from ITGlue',
    'class' => \App\Reports\ITGlueSummary\ITGlueSummary::class,
    'category' => 'comprehensive',
    'data_types' => ['flexible_asset', 'ssl_certificate', 'domain'],
    'required_integrations' => ['ITGlue'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'file-text',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'licenses', 'label' => 'Microsoft Licenses', 'default' => true],
        ['id' => 'ssl_certificates', 'label' => 'SSL Certificates', 'default' => true],
        ['id' => 'domains', 'label' => 'Domain Registrations', 'default' => true],
        ['id' => 'alerts', 'label' => 'Alerts & Expiring Items', 'default' => true],
    ],
];
