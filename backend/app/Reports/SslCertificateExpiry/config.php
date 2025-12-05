<?php

return [
    'id' => 'ssl_certificate_expiry',
    'name' => 'SSL Certificate Expiry Report',
    'description' => 'Track SSL certificates expiring in the next 90 days to prevent website outages',
    'class' => \App\Reports\SslCertificateExpiry\SslCertificateExpiry::class,
    'category' => 'security',
    'data_types' => ['ssl_certificate'],
    'required_integrations' => ['ITGlue'],
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
];
