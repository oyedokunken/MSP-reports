<?php

namespace App\Integrations\DisplayTemplate;

/**
 * Template Schema Definition
 * 
 * Defines the structure for integration display templates that describe
 * how data should be rendered in the frontend without requiring custom components.
 */
class TemplateSchema
{
    /**
     * Validate a display template structure
     * 
     * @param array<string, mixed> $template
     */
    public static function validate(array $template): bool
    {
        // Required top-level keys
        if (!isset($template['version']) || !isset($template['sections'])) {
            return false;
        }

        // Validate version
        if (!in_array($template['version'], ['1.0'])) {
            return false;
        }

        // Validate sections
        if (!is_array($template['sections']) || empty($template['sections'])) {
            return false;
        }

        foreach ($template['sections'] as $section) {
            if (!self::validateSection($section)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a section structure
     * 
     * @param array<string, mixed> $section
     */
    private static function validateSection(array $section): bool
    {
        $requiredKeys = ['id', 'title', 'component', 'config'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($section[$key])) {
                return false;
            }
        }

        // Validate component type
        $validComponents = [
            'table',
            'list',
            'card',
            'grid',
            'chart',
            'metric',
            'timeline',
            'tree',
            'keyvalue'
        ];

        if (!in_array($section['component'], $validComponents)) {
            return false;
        }

        return true;
    }

    /**
     * Get the template schema structure documentation
     * 
     * @return array<string, mixed>
     */
    public static function getSchema(): array
    {
        return [
            'version' => '1.0',
            'description' => 'Integration Display Template Schema',
            'structure' => [
                'version' => 'string (required) - Template version (e.g., "1.0")',
                'metadata' => [
                    'integration_type' => 'string - Integration identifier',
                    'display_name' => 'string - Human-readable name',
                    'description' => 'string - Template description',
                ],
                'sections' => [
                    [
                        'id' => 'string (required) - Unique section identifier',
                        'title' => 'string (required) - Section display title',
                        'description' => 'string (optional) - Section description',
                        'component' => 'string (required) - Component type (table, list, card, etc.)',
                        'icon' => 'string (optional) - Icon name for section header',
                        'collapsible' => 'boolean (optional) - Whether section can be collapsed',
                        'defaultCollapsed' => 'boolean (optional) - Initial collapsed state',
                        'order' => 'integer (optional) - Display order',
                        'config' => 'object (required) - Component-specific configuration',
                        'dataPath' => 'string (optional) - Path to data in preview response',
                        'emptyMessage' => 'string (optional) - Message when no data',
                    ]
                ]
            ],
            'component_types' => [
                'table' => [
                    'description' => 'Tabular data display',
                    'config' => [
                        'columns' => 'array (required) - Column definitions',
                        'sortable' => 'boolean - Enable sorting',
                        'filterable' => 'boolean - Enable filtering',
                        'paginated' => 'boolean - Enable pagination',
                        'pageSize' => 'integer - Rows per page',
                        'selectable' => 'boolean - Enable row selection',
                        'exportable' => 'boolean - Enable data export',
                    ]
                ],
                'list' => [
                    'description' => 'List of items with optional icons',
                    'config' => [
                        'itemTemplate' => 'object - Template for each item',
                        'groupBy' => 'string - Field to group by',
                        'sortBy' => 'string - Field to sort by',
                    ]
                ],
                'card' => [
                    'description' => 'Card-based layout',
                    'config' => [
                        'layout' => 'string - grid or stack',
                        'columns' => 'integer - Grid columns',
                        'cardTemplate' => 'object - Template for each card',
                    ]
                ],
                'chart' => [
                    'description' => 'Data visualization',
                    'config' => [
                        'chartType' => 'string - line, bar, pie, donut, area',
                        'xAxis' => 'object - X-axis configuration',
                        'yAxis' => 'object - Y-axis configuration',
                        'series' => 'array - Data series configuration',
                    ]
                ],
                'metric' => [
                    'description' => 'Single metric display',
                    'config' => [
                        'value' => 'string - Data path to value',
                        'label' => 'string - Metric label',
                        'format' => 'string - number, percentage, currency, duration',
                        'trend' => 'object - Trend indicator config',
                        'icon' => 'string - Icon name',
                        'color' => 'string - Color theme',
                    ]
                ],
                'keyvalue' => [
                    'description' => 'Key-value pairs display',
                    'config' => [
                        'pairs' => 'array - Key-value pair definitions',
                        'layout' => 'string - horizontal or vertical',
                    ]
                ],
            ]
        ];
    }
}
