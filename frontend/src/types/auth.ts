export interface User {
  user_id: number
  id?: number // Alias for compatibility
  name: string
  email: string
  role_id: number
  role?: {
    id: number
    name: string
    slug: string
  }
  company_id: number
  company_name: string
  company?: Company
  permissions: UserPermissions
  profile_picture_url?: string | null
  last_login: string | null
  created_at: string
}

export interface UserPermissions {
  integrations: {
    view: boolean
    manage: boolean
    sync: boolean
  }
  customers: {
    view: boolean
    edit: boolean
    map: boolean
  }
  reports: {
    view: boolean
    configure: boolean
    generate: boolean
    download: boolean
  }
  users: {
    view: boolean
    manage: boolean
  }
  company_settings: {
    view: boolean
    edit: boolean
  }
}

export interface LoginCredentials {
  email: string
  password: string
  auth_method?: 'local' | 'saml' | 'm365' | 'ldap'
  rememberMe?: boolean
}

export interface Company {
  company_id: number
  name: string
  contact_email: string | null
  contact_phone: string | null
  status: string
  settings: CompanySettings
}

export interface CompanySettings {
  white_label?: WhiteLabelSettings
  [key: string]: unknown
}

export interface WhiteLabelSettings {
  enabled: boolean
  company_name: string
  logo_url: string | null
  primary_color: string
  secondary_color: string
  favicon_url?: string | null
  email_from_name: string
  email_from_address: string
  support_email: string
  support_phone: string
  footer_text: string
  report_header_text: string
}

export interface LoginResponse {
  user: User
  company: Company
  token: string
  expires_at?: string
}

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}
