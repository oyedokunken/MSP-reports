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
  scoped_customers: number[] | null
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
  schedules: {
    view: boolean
    create: boolean
    edit: boolean
    delete: boolean
  }
  templates: {
    view: boolean
    create: boolean
    edit: boolean
    delete: boolean
  }
  users: {
    view: boolean
    manage: boolean
  }
  api_keys: {
    view: boolean
    create: boolean
    revoke: boolean
  }
  licenses: {
    view: boolean
    manage: boolean
  }
  monitoring: {
    view: boolean
    configure: boolean
  }
  notifications: {
    view: boolean
    configure: boolean
  }
  security: {
    view_logs: boolean
    configure: boolean
  }
  exchange: {
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
  scoped_customers?: number[]
}

export interface UpdateUserForm {
  name: string
  email: string
  role_id: number
  scoped_customers?: number[]
}

export interface Role {
  role_id: number
  name: string
  description: string
}
