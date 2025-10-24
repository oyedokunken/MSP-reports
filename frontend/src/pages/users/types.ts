export interface UserWithPermissions {
  user_id: number
  name: string
  email: string
  role_id: number
  role: {
    role_id: number
    name: string
  }
  status: 'active' | 'inactive' | 'locked'
  auth_method: 'local' | 'saml' | 'm365' | 'ldap'
  last_login: string | null
  created_at: string
  permissions: UserPermissions
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

export interface CreateUserForm {
  name: string
  email: string
  password: string
  role_id: number
  status: 'active' | 'inactive'
  auth_method: 'local' | 'saml' | 'm365' | 'ldap'
}

export interface UpdateUserForm {
  name: string
  email: string
  role_id: number
}

export interface Role {
  role_id: number
  name: string
  description: string
}
