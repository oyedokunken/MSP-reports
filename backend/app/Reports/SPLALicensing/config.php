<?php

return [
    'id' => 'ad_spla_licensing',
    'name' => 'AD SPLA Licensing Report',
    'description' => 'Report on SPLA user counts based on Active Directory group memberships',
    'class' => \App\Reports\SPLALicensing\SPLALicensing::class,
    'category' => 'compliance',
    'data_types' => ['active_directory_user', 'active_directory_group'],
    'required_integrations' => ['ActiveDirectory'],
    'frequencies' => ['monthly', 'quarterly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'server',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'spla_breakdown', 'label' => 'SPLA Product Breakdown', 'default' => true],
    ],
];
