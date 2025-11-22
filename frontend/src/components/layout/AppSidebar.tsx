import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import {
  Home,
  Cable,
  Users,
  FileText,
  Settings as SettingsIcon,
  Calendar,
  UserPlus,
  Key,
  Activity,
  Shield,
  Mail,
} from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
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
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar'
import { CreateUserModal } from '@/pages/users/modals/CreateUserModal'

export default function AppSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user } = useAuth()
  const { state } = useSidebar()
  const [createUserOpen, setCreateUserOpen] = useState(false)

  const navItems = [
    { path: '/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/integrations', icon: Cable, label: 'Integrations' },
    { path: '/active-directory', icon: Shield, label: 'Active Directory' },
    { path: '/exchange', icon: Mail, label: 'Exchange' },
    { path: '/customers', icon: Users, label: 'Customers' },
    { path: '/reports', icon: FileText, label: 'Reports' },
    { path: '/schedules', icon: Calendar, label: 'Schedules' },
  ]

  const adminNavItems = [
    { path: '/users', icon: UserPlus, label: 'Users' },
    { path: '/api-keys', icon: Key, label: 'API Keys' },
    { path: '/licenses', icon: Key, label: 'Licenses' },
    { path: '/monitoring', icon: Activity, label: 'Monitoring' },
  ]


  const bottomItems = [
    { path: '/settings', icon: SettingsIcon, label: 'Settings' },
  ]

  const isActive = (path: string) => location.pathname === path
  
  // Handler to navigate without expanding sidebar when collapsed
  const handleNavClick = (e: React.MouseEvent, path: string) => {
    if (state === 'collapsed') {
      e.preventDefault()
      navigate(path)
    }
    // If expanded, let the Link component handle navigation normally
  }
  
  const initials = (user?.name ?? 'User')
    .split(' ')
    .filter(Boolean)
    .map((w) => w.charAt(0))
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const companyName = user?.company_name || ''
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
      <SidebarHeader className="px-4 py-4 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-3">
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="flex items-center gap-3 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0">
              {/* Light mode logo */}
              <img 
                src="/logo.png" 
                alt="Logo" 
                className="h-14 w-auto object-contain group-data-[collapsible=icon]:h-10 dark:hidden"
              />
              {/* Dark mode logo */}
              <img 
                src="/logo-white.png" 
                alt="Logo" 
                className="h-14 w-auto object-contain group-data-[collapsible=icon]:h-10 hidden dark:block"
              />
              {companyName && (
                <div className="flex min-w-0 flex-col gap-0.5 leading-none group-data-[collapsible=icon]:hidden">
                  <span className="font-semibold text-base truncate" title={companyName}>{companyMain}</span>
                  {companySub ? (
                    <span className="text-xs text-muted-foreground truncate" title={companyName}>{companySub}</span>
                  ) : null}
                </div>
              )}
            </div>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent className="px-3 py-2 group-data-[collapsible=icon]:px-2">
        <SidebarGroup>
          <SidebarGroupLabel className="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 group-data-[collapsible=icon]:hidden">Platform</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:space-y-2">
              {navItems.map((item) => {
                const Icon = item.icon
                const active = isActive(item.path)
                return (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton 
                      asChild 
                      isActive={active} 
                      tooltip={item.label} 
                      className="rounded-lg px-3 py-2.5 h-10"
                      style={active ? { background: 'var(--brand-gradient)' } : undefined}
                    >
                      <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)}>
                        <Icon className="h-4 w-4" />
                        <span className="font-medium">{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
        <SidebarGroup className="mt-4 group-data-[collapsible=icon]:mt-2">
          <SidebarGroupLabel className="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 group-data-[collapsible=icon]:hidden">Admin</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:space-y-2">
              {adminNavItems.map((item) => {
                const Icon = item.icon
                const active = isActive(item.path)
                return (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton 
                      asChild 
                      isActive={active} 
                      tooltip={item.label} 
                      className="rounded-lg px-3 py-2.5 h-10"
                      style={active ? { background: 'var(--brand-gradient)' } : undefined}
                    >
                      <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)}>
                        <Icon className="h-4 w-4" />
                        <span className="font-medium">{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter className="px-3 pb-3 group-data-[collapsible=icon]:px-2">
        <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:space-y-2">
          {bottomItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <SidebarMenuItem key={item.path}>
                <SidebarMenuButton asChild isActive={active} tooltip={item.label} className="rounded-lg px-3 py-2.5 h-10">
                  <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)}>
                    <Icon className="h-4 w-4" />
                    <span className="font-medium">{item.label}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          })}
        </SidebarMenu>
        <div className="mt-3 pt-3 border-t border-sidebar-border group-data-[collapsible=icon]:mt-2 group-data-[collapsible=icon]:pt-2">
          <div className="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-sidebar-accent/50 cursor-pointer transition-colors group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:py-1">
            <div className="h-8 w-8 rounded-full bg-primary/20 flex items-center justify-center flex-shrink-0">
              <span className="text-primary font-semibold text-xs">{initials}</span>
            </div>
            <div className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
              <p className="text-sm font-medium text-foreground truncate">{user?.name || 'User'}</p>
              <p className="text-xs text-muted-foreground truncate">{user?.email || 'user@example.com'}</p>
            </div>
          </div>
        </div>
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
