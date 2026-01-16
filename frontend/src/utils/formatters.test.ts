import { describe, it, expect } from 'vitest'
import {
    formatNumber,
    formatPercentage,
    formatCurrency,
    formatFilesize,
    formatDuration,
    formatBoolean,
    formatValue,
    getValueByPath,
    replaceTemplateVariables,
} from './formatters'

describe('formatNumber', () => {
    it('formats integers with thousands separator', () => {
        expect(formatNumber(1000)).toBe('1,000')
        expect(formatNumber(1000000)).toBe('1,000,000')
    })

    it('handles string input', () => {
        expect(formatNumber('1234')).toBe('1,234')
    })

    it('returns original value for non-numeric strings', () => {
        expect(formatNumber('abc')).toBe('abc')
    })
})

describe('formatPercentage', () => {
    it('formats number as percentage with one decimal', () => {
        expect(formatPercentage(50)).toBe('50.0%')
        expect(formatPercentage(99.99)).toBe('100.0%')
    })

    it('handles string input', () => {
        expect(formatPercentage('75.5')).toBe('75.5%')
    })
})

describe('formatCurrency', () => {
    it('formats as AUD currency', () => {
        expect(formatCurrency(100)).toMatch(/\$100\.00/)
    })

    it('handles negative values', () => {
        expect(formatCurrency(-50)).toMatch(/-?\$50\.00/)
    })
})

describe('formatFilesize', () => {
    it('formats bytes', () => {
        expect(formatFilesize(500)).toBe('500 B')
    })

    it('formats kilobytes', () => {
        expect(formatFilesize(1024)).toBe('1 KB')
        expect(formatFilesize(2048)).toBe('2 KB')
    })

    it('formats megabytes', () => {
        expect(formatFilesize(1048576)).toBe('1 MB')
    })

    it('handles zero', () => {
        expect(formatFilesize(0)).toBe('0 B')
    })
})

describe('formatDuration', () => {
    it('formats seconds only', () => {
        expect(formatDuration(30)).toBe('30s')
    })

    it('formats minutes and seconds', () => {
        expect(formatDuration(90)).toBe('1m 30s')
        expect(formatDuration(120)).toBe('2m')
    })

    it('formats hours and minutes', () => {
        expect(formatDuration(3660)).toBe('1h 1m')
        expect(formatDuration(3600)).toBe('1h')
    })
})

describe('formatBoolean', () => {
    it('formats true/false as Yes/No', () => {
        expect(formatBoolean(true)).toBe('Yes')
        expect(formatBoolean(false)).toBe('No')
    })

    it('handles string input', () => {
        expect(formatBoolean('true')).toBe('Yes')
        expect(formatBoolean('false')).toBe('No')
    })
})

describe('formatValue', () => {
    it('returns "-" for null/undefined', () => {
        expect(formatValue(null)).toBe('-')
        expect(formatValue(undefined)).toBe('-')
    })

    it('applies correct format type', () => {
        expect(formatValue(1000, 'number')).toBe('1,000')
        expect(formatValue(50, 'percentage')).toBe('50.0%')
        expect(formatValue(true, 'boolean')).toBe('Yes')
    })

    it('returns string for text/default format', () => {
        expect(formatValue('hello', 'text')).toBe('hello')
        expect(formatValue(123)).toBe('123')
    })
})

describe('getValueByPath', () => {
    it('gets nested value using dot notation', () => {
        const obj = { a: { b: { c: 'value' } } }
        expect(getValueByPath(obj, 'a.b.c')).toBe('value')
    })

    it('returns undefined for missing path', () => {
        const obj = { a: 1 }
        expect(getValueByPath(obj, 'a.b.c')).toBeUndefined()
    })

    it('gets top-level value', () => {
        const obj = { name: 'test' }
        expect(getValueByPath(obj, 'name')).toBe('test')
    })
})

describe('replaceTemplateVariables', () => {
    it('replaces template variables', () => {
        const result = replaceTemplateVariables('Hello {{name}}!', { name: 'World' })
        expect(result).toBe('Hello World!')
    })

    it('leaves unmatched variables as-is', () => {
        const result = replaceTemplateVariables('{{foo}} {{bar}}', { foo: 'A' })
        expect(result).toBe('A {{bar}}')
    })

    it('replaces multiple occurrences', () => {
        const result = replaceTemplateVariables('{{x}} and {{x}}', { x: 'Y' })
        expect(result).toBe('Y and Y')
    })
})
