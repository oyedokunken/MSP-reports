<?php

namespace App\Integrations\DisplayTemplate;

/**
 * Template Builder
 * 
 * Fluent builder for creating display templates programmatically
 */
class TemplateBuilder
{
    private array $template = [
        'version' => '1.0',
        'metadata' => [],
        'sections' => [],
    ];

    public function __construct(string $integrationType, string $displayName)
    {
        $this->template['metadata'] = [
            'integration_type' => $integrationType,
            'display_name' => $displayName,
        ];
    }

    public function description(string $description): self
    {
        $this->template['metadata']['description'] = $description;
        return $this;
    }

    public function addSection(string $id, string $title, string $component): SectionBuilder
    {
        return new SectionBuilder($this, $id, $title, $component);
    }

    public function appendSection(array $section): self
    {
        $this->template['sections'][] = $section;
        return $this;
    }

    public function build(): array
    {
        // Sort sections by order if specified
        usort($this->template['sections'], function ($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });

        return $this->template;
    }
}

/**
 * Section Builder
 * 
 * Fluent builder for creating template sections
 */
class SectionBuilder
{
    private TemplateBuilder $templateBuilder;
    private array $section;

    public function __construct(TemplateBuilder $templateBuilder, string $id, string $title, string $component)
    {
        $this->templateBuilder = $templateBuilder;
        $this->section = [
            'id' => $id,
            'title' => $title,
            'component' => $component,
            'config' => [],
        ];
    }

    public function description(string $description): self
    {
        $this->section['description'] = $description;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->section['icon'] = $icon;
        return $this;
    }

    public function collapsible(bool $collapsible = true, bool $defaultCollapsed = false): self
    {
        $this->section['collapsible'] = $collapsible;
        $this->section['defaultCollapsed'] = $defaultCollapsed;
        return $this;
    }

    public function order(int $order): self
    {
        $this->section['order'] = $order;
        return $this;
    }

    public function dataPath(string $path): self
    {
        $this->section['dataPath'] = $path;
        return $this;
    }

    public function emptyMessage(string $message): self
    {
        $this->section['emptyMessage'] = $message;
        return $this;
    }

    public function config(array $config): self
    {
        $this->section['config'] = array_merge($this->section['config'], $config);
        return $this;
    }

    // Table-specific helpers
    public function tableColumns(array $columns): self
    {
        $this->section['config']['columns'] = $columns;
        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->section['config']['sortable'] = $sortable;
        return $this;
    }

    public function filterable(bool $filterable = true): self
    {
        $this->section['config']['filterable'] = $filterable;
        return $this;
    }

    public function paginated(bool $paginated = true, int $pageSize = 10): self
    {
        $this->section['config']['paginated'] = $paginated;
        $this->section['config']['pageSize'] = $pageSize;
        return $this;
    }

    public function selectable(bool $selectable = true): self
    {
        $this->section['config']['selectable'] = $selectable;
        return $this;
    }

    public function exportable(bool $exportable = true): self
    {
        $this->section['config']['exportable'] = $exportable;
        return $this;
    }

    // Chart-specific helpers
    public function chartType(string $type): self
    {
        $this->section['config']['chartType'] = $type;
        return $this;
    }

    public function chartSeries(array $series): self
    {
        $this->section['config']['series'] = $series;
        return $this;
    }

    // Metric-specific helpers
    public function metricValue(string $valuePath, string $format = 'number'): self
    {
        $this->section['config']['value'] = $valuePath;
        $this->section['config']['format'] = $format;
        return $this;
    }

    public function metricLabel(string $label): self
    {
        $this->section['config']['label'] = $label;
        return $this;
    }

    public function end(): TemplateBuilder
    {
        return $this->templateBuilder->appendSection($this->section);
    }
}
