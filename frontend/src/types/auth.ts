export interface User {
  id: number
  name: string
  email: string
  company_id: number
  company_name: string
  permissions: UserPermissions
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
}

export interface LoginResponse {
  user: User
  token: string
  expires_at: string
}

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}
