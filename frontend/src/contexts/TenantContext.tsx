import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { getTenantFromUrl, validateTenantSession } from '@/utils/tenantUtils'

interface TenantContextType {
  subdomain: string | null
  companyId: string | null
  companySlug: string | null
  isValidTenant: boolean
  isLoading: boolean
  validateSession: () => boolean
}

const TenantContext = createContext<TenantContextType | undefined>(undefined)

interface TenantProviderProps {
  children: ReactNode
}

export function TenantProvider({ children }: TenantProviderProps) {
  const [subdomain, setSubdomain] = useState<string | null>(null)
  const [companyId, _setCompanyId] = useState<string | null>(null)
  const [companySlug, setCompanySlug] = useState<string | null>(null)
  const [isValidTenant, setIsValidTenant] = useState(false)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Extract tenant from URL
    const tenantInfo = getTenantFromUrl()
    setSubdomain(tenantInfo.subdomain)
    setIsValidTenant(tenantInfo.isValidTenant)

    // If we have a subdomain, resolve it to company info
    if (tenantInfo.subdomain) {
      resolveTenantInfo(tenantInfo.subdomain)
    } else {
      setIsLoading(false)
    }
  }, [])

  const resolveTenantInfo = async (subdomain: string) => {
    try {
      // TODO: Call API to resolve subdomain to company
      // const response = await api.get(`/api/tenants/${subdomain}`)
      // setCompanyId(response.data.company_id)
      // setCompanySlug(response.data.slug)
      
      // For now, use subdomain as slug
      setCompanySlug(subdomain)
      setIsLoading(false)
    } catch (error) {
      console.error('Failed to resolve tenant:', error)
      setIsValidTenant(false)
      setIsLoading(false)
    }
  }

  const validateSession = (): boolean => {
    // Get current user's company from session
    const userCompanySlug = localStorage.getItem('user_company_slug')
    
    return validateTenantSession(subdomain, userCompanySlug)
  }

  return (
    <TenantContext.Provider
      value={{
        subdomain,
        companyId,
        companySlug,
        isValidTenant,
        isLoading,
        validateSession,
      }}
    >
      {children}
    </TenantContext.Provider>
  )
}

export function useTenant() {
  const context = useContext(TenantContext)
  if (context === undefined) {
    throw new Error('useTenant must be used within a TenantProvider')
  }
  return context
}
