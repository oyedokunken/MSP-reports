import type { FormatType } from '@/types/displayTemplate'

/**
 * Format value based on format type
 */
export function formatValue(value: any, format?: FormatType): string {
  if (value === null || value === undefined) {
    return '-'
  }

  switch (format) {
    case 'number':
      return formatNumber(value)
    
    case 'percentage':
      return formatPercentage(value)
    
    case 'currency':
      return formatCurrency(value)
    
    case 'filesize':
      return formatFilesize(value)
    
    case 'duration':
      return formatDuration(value)
    
    case 'datetime':
      return formatDateTime(value)
    
    case 'date':
      return formatDate(value)
    
    case 'time':
      return formatTime(value)
    
    case 'boolean':
      return formatBoolean(value)
    
    case 'badge':
    case 'status':
    case 'text':
    default:
      return String(value)
  }
}

/**
 * Format number with thousands separator
 */
export function formatNumber(value: number | string): string {
  const num = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(num)) return String(value)
  return new Intl.NumberFormat('en-US').format(num)
}

/**
 * Format percentage
 */
export function formatPercentage(value: number | string): string {
  const num = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(num)) return String(value)
  return `${num.toFixed(1)}%`
}

/**
 * Format currency (AUD)
 */
export function formatCurrency(value: number | string): string {
  const num = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(num)) return String(value)
  return new Intl.NumberFormat('en-AU', {
    style: 'currency',
    currency: 'AUD',
  }).format(num)
}

/**
 * Format file size in bytes to human readable
 */
export function formatFilesize(bytes: number | string): string {
  const num = typeof bytes === 'string' ? parseInt(bytes) : bytes
  if (isNaN(num) || num === 0) return '0 B'
  
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(num) / Math.log(k))
  
  return `${parseFloat((num / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

/**
 * Format duration in seconds to human readable
 */
export function formatDuration(seconds: number | string): string {
  const num = typeof seconds === 'string' ? parseInt(seconds) : seconds
  if (isNaN(num)) return String(seconds)
  
  if (num < 60) {
    return `${num}s`
  } else if (num < 3600) {
    const mins = Math.floor(num / 60)
    const secs = num % 60
    return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`
  } else {
    const hours = Math.floor(num / 3600)
    const mins = Math.floor((num % 3600) / 60)
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`
  }
}

/**
 * Format ISO date string to readable datetime
 */
export function formatDateTime(value: string | Date): string {
  try {
    const date = typeof value === 'string' ? new Date(value) : value
    return new Intl.DateTimeFormat('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return String(value)
  }
}

/**
 * Format ISO date string to readable date
 */
export function formatDate(value: string | Date): string {
  try {
    const date = typeof value === 'string' ? new Date(value) : value
    return new Intl.DateTimeFormat('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(date)
  } catch {
    return String(value)
  }
}

/**
 * Format ISO date string to time only
 */
export function formatTime(value: string | Date): string {
  try {
    const date = typeof value === 'string' ? new Date(value) : value
    return new Intl.DateTimeFormat('en-AU', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return String(value)
  }
}

/**
 * Format boolean to Yes/No
 */
export function formatBoolean(value: boolean | string): string {
  if (typeof value === 'string') {
    return value.toLowerCase() === 'true' ? 'Yes' : 'No'
  }
  return value ? 'Yes' : 'No'
}

/**
 * Get value from object using dot notation path
 */
export function getValueByPath(obj: any, path: string): any {
  return path.split('.').reduce((current, key) => current?.[key], obj)
}

/**
 * Replace template variables in string (e.g., {{variable}})
 */
export function replaceTemplateVariables(template: string, data: Record<string, any>): string {
  return template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
    return data[key] !== undefined ? String(data[key]) : match
  })
}
