<?php

return [
    'id' => 'librenms_device_report',
    'class' => \App\Reports\LibreNMSDeviceReport\LibreNMSDeviceReport::class,
    'name' => 'LibreNMS Device Report',
    'description' => 'Device availability report showing 24h, 7d, 30d, and 365d uptime metrics',
    'category' => 'infrastructure',
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'required_integrations' => ['LibreNMS'],
    'data_types' => ['device_uptime', 'device_availability', 'device_details'],
];
