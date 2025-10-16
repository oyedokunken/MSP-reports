export interface Permissions {
  integrations?: {
    view?: boolean
    manage?: boolean
    sync?: boolean
  }
  customers?: {
    view?: boolean
    edit?: boolean
    map?: boolean
  }
  reports?: {
    view?: boolean
    configure?: boolean
    generate?: boolean
    download?: boolean
  }
  users?: {
    view?: boolean
    manage?: boolean
  }
  company_settings?: {
    view?: boolean
    edit?: boolean
  }
}

export interface UserWithPermissions {
  user_id: number
  name: string
  email: string
  status: 'active' | 'inactive' | 'locked'
  auth_method: string
  last_login_at: string | null
  created_at: string
  role: {
    id: number
    name: string
  }
  permissions?: Permissions | null
}
