import { logger } from '@/lib/logger'

/**
 * Tenant Isolation Utilities
 * Ensures data isolation and prevents cross-tenant data leakage
 */

interface IsolationCheckResult {
  isValid: boolean
  errors: string[]
  warnings: string[]
}

/**
 * Verify that API response data belongs to current tenant
 */
export function verifyTenantData(
  data: unknown,
  expectedCompanyId: string
): IsolationCheckResult {
  const errors: string[] = []
  const warnings: string[] = []

  if (!data) {
    return { isValid: true, errors, warnings }
  }

  if (typeof data === 'object' && data !== null && 'company_id' in data) {
    const obj = data as { company_id?: string }
    if (obj.company_id && obj.company_id !== expectedCompanyId) {
      errors.push(
        `Data isolation violation: Expected company_id ${expectedCompanyId}, got ${obj.company_id}`
      )
    }
  }

  if (Array.isArray(data)) {
    data.forEach((item: unknown, index: number) => {
      if (typeof item === 'object' && item !== null && 'company_id' in item) {
        const obj = item as { company_id?: string }
        if (obj.company_id && obj.company_id !== expectedCompanyId) {
          errors.push(
            `Data isolation violation at index ${index}: Expected company_id ${expectedCompanyId}, got ${obj.company_id}`
          )
        }
      }
    })
  }

  if (typeof data === 'object' && data !== null && 'data' in data) {
    const obj = data as { data?: unknown }
    if (Array.isArray(obj.data)) {
      obj.data.forEach((item: unknown, index: number) => {
        if (typeof item === 'object' && item !== null && 'company_id' in item) {
          const itemObj = item as { company_id?: string }
          if (itemObj.company_id && itemObj.company_id !== expectedCompanyId) {
            errors.push(
              `Data isolation violation in data array at index ${index}: Expected company_id ${expectedCompanyId}, got ${itemObj.company_id}`
            )
          }
        }
      })
    }
  }

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
  }
}

/**
 * Log isolation violations for monitoring
 */
export function logIsolationViolation(
  endpoint: string,
  expectedCompanyId: string,
  actualCompanyId: string,
  userId: string
): void {
  const violation = {
    timestamp: new Date().toISOString(),
    endpoint,
    expectedCompanyId,
    actualCompanyId,
    userId,
    userAgent: navigator.userAgent,
    url: window.location.href,
  }

  if (import.meta.env.DEV) {
    logger.error('🚨 TENANT ISOLATION VIOLATION:', violation)
  }


}

/**
 * Sanitize user input to prevent tenant ID manipulation
 */
export function sanitizeTenantInput<T>(input: T): T {
  if (typeof input === 'object' && input !== null) {
    const sanitized = { ...input } as Record<string, unknown>

    delete sanitized.company_id
    delete sanitized.companyId

    return sanitized as T
  }

  return input
}

/**
 * Verify API request headers include tenant context
 */
export function verifyTenantHeaders(headers: Headers): boolean {
  const companyId = headers.get('X-Company-ID')
  const tenantId = headers.get('X-Tenant-ID')

  return !!(companyId || tenantId)
}
