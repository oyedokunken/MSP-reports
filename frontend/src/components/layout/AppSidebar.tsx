import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import {
  Home,
  Cable,
  Users,
  FileText,
  Settings as SettingsIcon,
  Calendar,
  Bell,
  UserPlus,
  Key,
  Activity,
  Shield,
  Mail,
} from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { useNotificationStore } from '@/stores/useNotificationStore'
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
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)
  const unreadCount = getUnreadCount()

  const navItems = [
    { path: '/platform/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/platform/integrations', icon: Cable, label: 'Integrations' },
    { path: '/platform/active-directory', icon: Shield, label: 'Active Directory' },
    { path: '/platform/exchange', icon: Mail, label: 'Exchange' },
    { path: '/platform/customers', icon: Users, label: 'Customers' },
    { path: '/platform/reports', icon: FileText, label: 'Reports' },
    { path: '/platform/schedules', icon: Calendar, label: 'Schedules' },
    { path: '/platform/notifications', icon: Bell, label: 'Notifications' },
  ]

  const adminNavItems = [
    { path: '/admin/users', icon: UserPlus, label: 'Users' },
    { path: '/admin/api-keys', icon: Key, label: 'API Keys' },
    { path: '/admin/licenses', icon: Key, label: 'Licenses' },
    { path: '/admin/monitoring', icon: Activity, label: 'Monitoring' },
  ]


  const bottomItems = [
    { path: '/admin/settings', icon: SettingsIcon, label: 'Settings' },
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
            <Link 
              to="/platform/dashboard"
              className="flex items-center gap-3 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 hover:opacity-80 transition-opacity"
              onClick={(e) => handleNavClick(e, '/platform/dashboard')}
            >
              {/* Expanded state - full logos */}
              <img 
                src="/logo.png" 
                alt="Logo" 
                className="h-14 w-auto object-contain dark:hidden group-data-[collapsible=icon]:hidden"
              />
              <img 
                src="/logo-white.png" 
                alt="Logo" 
                className="h-14 w-auto object-contain hidden dark:block group-data-[collapsible=icon]:hidden"
              />
              
              {/* Collapsed state - icon logo */}
              <img 
                src="/msp-logo-icon.png" 
                alt="Logo Icon" 
                className="h-10 w-10 object-contain hidden group-data-[collapsible=icon]:block"
              />
              
              {companyName && (
                <div className="flex min-w-0 flex-col gap-0.5 leading-none group-data-[collapsible=icon]:hidden">
                  <span className="font-semibold text-base truncate" title={companyName}>{companyMain}</span>
                  {companySub ? (
                    <span className="text-xs text-muted-foreground truncate" title={companyName}>{companySub}</span>
                  ) : null}
                </div>
              )}
            </Link>
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
                const showBadge = item.path === '/notifications' && unreadCount > 0
                return (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton 
                      asChild 
                      isActive={active} 
                      tooltip={item.label} 
                      className="rounded-lg group-data-[collapsible=icon]:!p-0"
                      style={active ? { background: 'var(--brand-gradient)' } : undefined}
                    >
                      <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)} className="flex items-center gap-3 px-3 py-2.5 h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:justify-center">
                        <div className="relative flex-shrink-0">
                          <Icon className="h-4 w-4" />
                          {showBadge && (
                            <span className="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-background" />
                          )}
                        </div>
                        <span className="font-medium group-data-[collapsible=icon]:hidden flex-1">{item.label}</span>
                        {showBadge && (
                          <span className="ml-auto h-2 w-2 rounded-full bg-red-500 group-data-[collapsible=icon]:hidden" />
                        )}
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
                      className="rounded-lg group-data-[collapsible=icon]:!p-0"
                      style={active ? { background: 'var(--brand-gradient)' } : undefined}
                    >
                      <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)} className="flex items-center gap-3 px-3 py-2.5 h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:justify-center">
                        <Icon className="h-4 w-4 flex-shrink-0" />
                        <span className="font-medium group-data-[collapsible=icon]:hidden">{item.label}</span>
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
                <SidebarMenuButton asChild isActive={active} tooltip={item.label} className="rounded-lg group-data-[collapsible=icon]:!p-0">
                  <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)} className="flex items-center gap-3 px-3 py-2.5 h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:justify-center">
                    <Icon className="h-4 w-4 flex-shrink-0" />
                    <span className="font-medium group-data-[collapsible=icon]:hidden">{item.label}</span>
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
