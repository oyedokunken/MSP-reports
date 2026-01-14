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
  schedules?: {
    view?: boolean
    create?: boolean
    edit?: boolean
    delete?: boolean
  }
  templates?: {
    view?: boolean
    create?: boolean
    edit?: boolean
    delete?: boolean
  }
  users?: {
    view?: boolean
    manage?: boolean
  }
  api_keys?: {
    view?: boolean
    create?: boolean
    revoke?: boolean
  }
  licenses?: {
    view?: boolean
    manage?: boolean
  }
  monitoring?: {
    view?: boolean
    configure?: boolean
  }
  notifications?: {
    view?: boolean
    configure?: boolean
  }
  security?: {
    view_logs?: boolean
    configure?: boolean
  }
  exchange?: {
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
