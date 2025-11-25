/**
 * Display Template Types
 * 
 * TypeScript definitions for integration display templates
 */

export type ComponentType = 
  | 'table' 
  | 'list' 
  | 'card' 
  | 'grid' 
  | 'chart' 
  | 'metric' 
  | 'timeline' 
  | 'tree' 
  | 'keyvalue'

export type FormatType = 
  | 'text'
  | 'number'
  | 'percentage'
  | 'currency'
  | 'filesize'
  | 'duration'
  | 'datetime'
  | 'date'
  | 'time'
  | 'badge'
  | 'status'
  | 'boolean'
  | 'link'
  | 'email'

export type ChartType = 
  | 'line' 
  | 'bar' 
  | 'pie' 
  | 'donut' 
  | 'area'
  | 'scatter'

export interface TemplateMetadata {
  integration_type: string
  display_name: string
  description?: string
}

export interface ColumnDefinition {
  key: string
  label: string
  sortable?: boolean
  filterable?: boolean
  format?: FormatType
  width?: string
  colorMap?: Record<string, string>
  align?: 'left' | 'center' | 'right'
}

export interface TableConfig {
  columns: ColumnDefinition[]
  sortable?: boolean
  filterable?: boolean
  paginated?: boolean
  pageSize?: number
  selectable?: boolean
  exportable?: boolean
}

export interface ListConfig {
  itemTemplate: {
    title: string
    subtitle?: string
    icon?: string
    iconColor?: string
    badge?: string
  }
  groupBy?: string
  sortBy?: string
  sortOrder?: 'asc' | 'desc'
}

export interface CardConfig {
  layout: 'grid' | 'stack'
  columns?: number
  cardTemplate: {
    title: string
    subtitle?: string
    content?: string
    footer?: string
    image?: string
    icon?: string
  }
}

export interface ChartSeries {
  name: string
  dataPath: string
  color?: string
  type?: ChartType
}

export interface ChartConfig {
  chartType: ChartType
  xAxis?: {
    label?: string
    dataPath: string
  }
  yAxis?: {
    label?: string
    format?: FormatType
  }
  series: ChartSeries[]
  height?: number
  legend?: boolean
}

export interface MetricConfig {
  value: string
  label: string
  format: FormatType
  trend?: {
    value: number
    direction: 'up' | 'down'
    positive?: boolean
  }
  icon?: string
  color?: string
}

export interface GridMetric {
  id: string
  label: string
  valuePath: string
  format: FormatType
  icon?: string
  color?: string
}

export interface GridConfig {
  columns: number
  gap?: number
  metrics: GridMetric[]
}

export interface KeyValuePair {
  key: string
  label: string
  valuePath: string
  format?: FormatType
}

export interface KeyValueConfig {
  pairs: KeyValuePair[]
  layout: 'horizontal' | 'vertical'
}

export type SectionConfig = 
  | TableConfig 
  | ListConfig 
  | CardConfig 
  | ChartConfig 
  | MetricConfig 
  | GridConfig
  | KeyValueConfig

export interface TemplateSection {
  id: string
  title: string
  description?: string
  component: ComponentType
  icon?: string
  collapsible?: boolean
  defaultCollapsed?: boolean
  order?: number
  config: SectionConfig
  dataPath?: string
  emptyMessage?: string
}

export interface DisplayTemplate {
  version: string
  metadata: TemplateMetadata
  sections: TemplateSection[]
}

export interface IntegrationPreviewData {
  success: boolean
  customer?: {
    id: number
    name: string
    external_id: string
  }
  data: Record<string, any>
  timestamp: string
  error?: string
}

export interface IntegrationPreview {
  success: boolean
  integration: {
    id: number
    name: string
    type: string
  }
  template: DisplayTemplate
  preview: IntegrationPreviewData
}
