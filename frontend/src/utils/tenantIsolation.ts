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
  data: any,
  expectedCompanyId: string
): IsolationCheckResult {
  const errors: string[] = []
  const warnings: string[] = []

  if (!data) {
    return { isValid: true, errors, warnings }
  }

  // Check single object
  if (data.company_id && data.company_id !== expectedCompanyId) {
    errors.push(
      `Data isolation violation: Expected company_id ${expectedCompanyId}, got ${data.company_id}`
    )
  }

  // Check array of objects
  if (Array.isArray(data)) {
    data.forEach((item, index) => {
      if (item.company_id && item.company_id !== expectedCompanyId) {
        errors.push(
          `Data isolation violation at index ${index}: Expected company_id ${expectedCompanyId}, got ${item.company_id}`
        )
      }
    })
  }

  // Check nested data property
  if (data.data && Array.isArray(data.data)) {
    data.data.forEach((item: any, index: number) => {
      if (item.company_id && item.company_id !== expectedCompanyId) {
        errors.push(
          `Data isolation violation in data array at index ${index}: Expected company_id ${expectedCompanyId}, got ${item.company_id}`
        )
      }
    })
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

  // Log to console in development
  if (import.meta.env.DEV) {
    console.error('🚨 TENANT ISOLATION VIOLATION:', violation)
  }

  // In production, this would be sent to a monitoring service
  // Implementation depends on the monitoring solution chosen
}

/**
 * Sanitize user input to prevent tenant ID manipulation
 */
export function sanitizeTenantInput(input: any): any {
  if (typeof input === 'object' && input !== null) {
    const sanitized = { ...input }
    
    // Remove any company_id from user input
    delete sanitized.company_id
    delete sanitized.companyId
    
    return sanitized
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
