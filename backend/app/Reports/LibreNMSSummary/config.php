<?php

return [
    'id' => 'librenms_summary',
    'class' => \App\Reports\LibreNMSSummary\LibreNMSSummary::class,
    'name' => 'LibreNMS Executive Summary',
    'description' => 'Executive summary of network health, device status, and uptime trends from LibreNMS',
    'category' => 'infrastructure',
    'frequencies' => ['weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'required_integrations' => ['LibreNMS'],
    'data_types' => ['device_uptime', 'device_availability', 'device_details'],
    'available_sections' => [
        ['id' => 'summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'infrastructure', 'label' => 'Infrastructure Breakdown', 'default' => true],
        ['id' => 'top_issues', 'label' => 'Top Availability Issues', 'default' => true],
    ],
];
