import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import {
  Home,
  Cable,
  Users,
  FileText,
  Settings as SettingsIcon,
  LogOut,
  Calendar,
  SlidersHorizontal,
  UserPlus,
} from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { useBranding } from '@/contexts/BrandingContext'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarSeparator,
  SidebarRail,
} from '@/components/ui/sidebar'
import { CreateUserModal } from '@/pages/users/modals/CreateUserModal'

export default function AppSidebar() {
  const location = useLocation()
  const { user, logout } = useAuth()
  const { branding } = useBranding()
  const [createUserOpen, setCreateUserOpen] = useState(false)

  const navItems = [
    { path: '/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/integrations', icon: Cable, label: 'Integrations' },
    { path: '/customers', icon: Users, label: 'Customers' },
    { path: '/reports', icon: FileText, label: 'Reports' },
    { path: '/schedules', icon: Calendar, label: 'Schedules' },
  ]

  const adminNavItems = [
    { path: '/users', icon: UserPlus, label: 'Users' },
  ]


  const bottomItems = [
    { path: '/settings', icon: SettingsIcon, label: 'Settings' },
    { path: '/options', icon: SlidersHorizontal, label: 'Options' },
  ]

  const isActive = (path: string) => location.pathname === path
  const initials = (user?.name ?? 'User')
    .split(' ')
    .filter(Boolean)
    .map((w) => w.charAt(0))
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const companyName = branding?.company_name || 'Reporting'
  const companyInitial = companyName.charAt(0) || 'R'
  const MAX_MAIN = 22
  let companyMain = companyName
  let companySub = ''
  if (companyName.length > MAX_MAIN) {
    const cut = companyName.lastIndexOf(' ', MAX_MAIN)
    const idx = cut > 0 ? cut : MAX_MAIN
    companyMain = companyName.slice(0, idx)
    companySub = companyName.slice(idx).trimStart()
  }

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-sidebar-border">
      <SidebarHeader className="p-2">
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="flex items-center gap-3 px-2 py-1.5">
              <div className="flex aspect-square size-8 items-center justify-center">
                <span className="font-semibold text-sidebar-primary">{companyInitial}</span>
              </div>
              <div className="flex min-w-0 flex-col gap-0.5 leading-none">
                <span className="font-medium truncate" title={companyName}>{companyMain}</span>
                {companySub ? (
                  <span className="text-xs text-muted-foreground truncate" title={companyName}>{companySub}</span>
                ) : null}
              </div>
            </div>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarSeparator />
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Platform</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {navItems.map((item) => {
                const Icon = item.icon
                const active = isActive(item.path)
                return (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                      <Link to={item.path}>
                        <Icon />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
        <SidebarGroup>
          <SidebarGroupLabel>Admin</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {adminNavItems.map((item) => {
                const Icon = item.icon
                const active = isActive(item.path)
                return (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                      <Link to={item.path}>
                        <Icon />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter>
        <div className="flex items-center gap-3 px-2 pb-2">
          <div className="h-9 w-9 rounded-full bg-primary/20 flex items-center justify-center">
            <span className="text-primary font-semibold text-sm">{initials}</span>
          </div>
          <div className="min-w-0">
            <p className="text-sm font-medium text-foreground truncate">{user?.name || 'User'}</p>
            <p className="text-xs text-muted-foreground truncate">{user?.email || 'user@example.com'}</p>
          </div>
        </div>
        <SidebarSeparator />
        <SidebarMenu>
          {bottomItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <SidebarMenuItem key={item.path}>
                <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                  <Link to={item.path}>
                    <Icon />
                    <span>{item.label}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          })}
          <SidebarMenuItem>
            <SidebarMenuButton asChild tooltip="Sign out">
              <button onClick={logout} className="text-red-600 dark:text-red-400">
                <LogOut />
                <span>Sign out</span>
              </button>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      {/* Collapsible rail to toggle icon mode */}
      <SidebarRail />
      
      {/* Create User Modal */}
      <CreateUserModal 
        open={createUserOpen} 
        onOpenChange={setCreateUserOpen}
        onSuccess={() => {
          // Optionally refresh user list or show toast
          console.log('User created successfully')
        }}
      />
    </Sidebar>
  )
}
