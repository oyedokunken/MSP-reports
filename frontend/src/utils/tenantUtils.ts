/**
 * Tenant Utilities for Multi-Tenant SaaS
 * Based on best practices from multi-tenant architecture
 */

export interface TenantInfo {
  subdomain: string | null
  companyId: string | null
  isValidTenant: boolean
}

/**
 * Extract tenant subdomain from URL
 * Supports formats:
 * - company.reportingplatform.com -> "company"
 * - reportingplatform.com -> null (main domain)
 * - localhost:5173 -> null (development)
 */
export function getTenantFromUrl(): TenantInfo {
  const hostname = window.location.hostname
  
  // Development environment
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return {
      subdomain: null,
      companyId: null,
      isValidTenant: false
    }
  }

  // Extract subdomain
  const parts = hostname.split('.')
  
  // If we have at least 3 parts (subdomain.domain.tld)
  if (parts.length >= 3) {
    const subdomain = parts[0]
    
    // Ignore www
    if (subdomain === 'www') {
      return {
        subdomain: null,
        companyId: null,
        isValidTenant: false
      }
    }

    return {
      subdomain,
      companyId: null, // Will be resolved from API
      isValidTenant: true
    }
  }

  // Main domain (no subdomain)
  return {
    subdomain: null,
    companyId: null,
    isValidTenant: false
  }
}

/**
 * Build tenant-specific URL
 */
export function buildTenantUrl(subdomain: string, path: string = ''): string {
  const protocol = window.location.protocol
  const baseDomain = import.meta.env.VITE_BASE_DOMAIN || 'reportingplatform.com'
  
  return `${protocol}//${subdomain}.${baseDomain}${path}`
}

/**
 * Check if current session is for the correct tenant
 */
export function validateTenantSession(
  currentSubdomain: string | null,
  sessionCompanySlug: string | null
): boolean {
  // Both null = main domain, valid
  if (!currentSubdomain && !sessionCompanySlug) {
    return true
  }

  // Subdomain matches company slug
  return currentSubdomain === sessionCompanySlug
}

/**
 * Redirect to correct tenant subdomain
 */
export function redirectToTenant(companySlug: string, path: string = '/dashboard'): void {
  const targetUrl = buildTenantUrl(companySlug, path)
  window.location.href = targetUrl
}

/**
 * Get tenant display name from subdomain
 */
export function formatTenantName(subdomain: string): string {
  return subdomain
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
