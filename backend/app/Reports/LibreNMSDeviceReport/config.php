<?php

return [
    'id' => 'librenms_device_report',
    'class' => \App\Reports\LibreNMSDeviceReport\LibreNMSDeviceReport::class,
    'name' => 'LibreNMS Device Report',
    'description' => 'Device availability report showing 24h, 7d, 30d, and 365d uptime metrics',
    'category' => 'infrastructure',
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
];
