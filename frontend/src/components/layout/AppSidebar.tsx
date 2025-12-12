import { useState, Fragment } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import {
  Home,
  Cable,
  Users,
  FileText,
  Calendar,
  Bell,
  UserPlus,
  Key,
  Activity,
  ChevronDown,
  Database,
  Building2,
} from 'lucide-react'
import { hasPermission } from '@/utils/permissions'
import { useAuth } from '@/contexts/AuthContext'
import { useBranding } from '@/contexts/BrandingContext'
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
  SidebarMenuSub,
  SidebarMenuSubItem,
  SidebarMenuSubButton,
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar'
import { CreateUserModal } from '@/pages/users/modals/CreateUserModal'

export default function AppSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user } = useAuth()
  const { branding } = useBranding()
  const { state } = useSidebar()
  const [createUserOpen, setCreateUserOpen] = useState(false)
  const [customersExpanded, setCustomersExpanded] = useState(() => {
    return location.pathname.startsWith('/platform/customers')
  })
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)
  const unreadCount = getUnreadCount()

  const navItems = [
    { path: '/platform/dashboard', icon: Home, label: 'Dashboard', show: true },
    { path: '/platform/integrations', icon: Cable, label: 'Integrations', show: hasPermission(user, 'integrations', 'view') },
    { path: '/platform/reports', icon: FileText, label: 'Reports', show: hasPermission(user, 'reports', 'view') },
    { path: '/platform/schedules', icon: Calendar, label: 'Schedules', show: hasPermission(user, 'reports', 'view') },
    { path: '/platform/notifications', icon: Bell, label: 'Notifications', show: true },
  ].filter(item => item.show !== false)

  const customersSubItems = [
    { path: '/platform/customers/master', icon: Database, label: 'Master List' },
    { path: '/platform/customers/secondary', icon: Building2, label: 'Secondary List' },
  ]

  const adminNavItems = [
    { path: '/admin/users', icon: UserPlus, label: 'Users', show: hasPermission(user, 'users', 'view') },
    { path: '/admin/api-keys', icon: Key, label: 'API Keys', show: hasPermission(user, 'company_settings', 'view') },
    { path: '/admin/licenses', icon: Key, label: 'Licenses', show: hasPermission(user, 'company_settings', 'view') },
    { path: '/admin/monitoring', icon: Activity, label: 'Monitoring', show: hasPermission(user, 'company_settings', 'view') },
  ].filter(item => item.show !== false)

  const isActive = (path: string) => {
    // For exact match paths (dashboard, settings)
    if (path === '/platform/dashboard' || path === '/admin/settings') {
      return location.pathname === path
    }
    // For section paths, check if current path starts with the section path
    return location.pathname.startsWith(path)
  }

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
              {/* Expanded state - brand logo */}
              {branding?.logo_url ? (
                <img
                  src={branding.logo_url}
                  alt={branding.company_name}
                  className="h-14 w-auto object-contain group-data-[collapsible=icon]:hidden"
                />
              ) : (
                <>
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
                </>
              )}

              {/* Collapsed state - icon logo */}
              {branding?.logo_url ? (
                <img
                  src={branding.logo_url}
                  alt={branding.company_name}
                  className="h-10 w-10 object-contain hidden group-data-[collapsible=icon]:block"
                />
              ) : (
                <>
                  {/* Light mode collapsed icon */}
                  <img
                    src="/msp-logo-icon.png"
                    alt="Logo Icon"
                    className="h-10 w-10 object-contain hidden group-data-[collapsible=icon]:block dark:!hidden"
                  />
                  {/* Dark mode collapsed icon */}
                  <img
                    src="/icon.png"
                    alt="Logo Icon"
                    className="h-10 w-10 object-contain !hidden dark:group-data-[collapsible=icon]:!block"
                  />
                </>
              )}

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
      <SidebarContent className="px-3 py-2 group-data-[collapsible=icon]:px-0">
        <SidebarGroup>
          <SidebarGroupLabel className="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 group-data-[collapsible=icon]:hidden">Platform</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:space-y-2 border-l border-sidebar-border ml-3.5 pl-2.5 pr-3 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:ml-0 group-data-[collapsible=icon]:pl-0 group-data-[collapsible=icon]:pr-0">
              {navItems.map((item) => {
                const Icon = item.icon
                const active = isActive(item.path)
                const showBadge = item.path === '/notifications' && unreadCount > 0
                
                return (
                  <Fragment key={item.path}>
                    <SidebarMenuItem className="relative group-data-[collapsible=icon]:static">
                      <span className="absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full bg-sidebar-border group-data-[collapsible=icon]:hidden" />
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

                    {/* Insert Customers menu after Integrations */}
                    {item.path === '/platform/integrations' && hasPermission(user, 'customers', 'view') && (
                      <SidebarMenuItem className="group-data-[collapsible=icon]:static">
                        <div className="relative">
                          <span className="absolute left-[-14px] top-5 h-1.5 w-1.5 rounded-full bg-sidebar-border group-data-[collapsible=icon]:hidden" />
                          <SidebarMenuButton
                            tooltip="Customers"
                            onClick={() => setCustomersExpanded(!customersExpanded)}
                            className="rounded-lg group-data-[collapsible=icon]:!p-0 px-3 py-2.5 h-10"
                          >
                            <Users className="h-4 w-4 flex-shrink-0" />
                            <span className="font-medium group-data-[collapsible=icon]:hidden flex-1">Customers</span>
                            <ChevronDown className={`h-4 w-4 transition-transform group-data-[collapsible=icon]:hidden ${customersExpanded ? 'rotate-180' : ''}`} />
                          </SidebarMenuButton>
                        </div>
                        <div 
                          className={`overflow-hidden transition-all duration-300 ease-in-out ${
                            customersExpanded ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
                          }`}
                        >
                          <SidebarMenuSub className="group-data-[collapsible=icon]:hidden">
                            {customersSubItems.map((subItem) => {
                              const SubIcon = subItem.icon
                              const subActive = isActive(subItem.path)
                              return (
                                <SidebarMenuSubItem key={subItem.path} className="relative">
                                  <span className="absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full bg-sidebar-border" />
                                  <SidebarMenuSubButton
                                    asChild
                                    isActive={subActive}
                                  >
                                    <Link to={subItem.path} onClick={(e) => handleNavClick(e, subItem.path)}>
                                      <SubIcon className="h-4 w-4 !text-muted-foreground" />
                                      <span>{subItem.label}</span>
                                    </Link>
                                  </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                              )
                            })}
                          </SidebarMenuSub>
                        </div>
                      </SidebarMenuItem>
                    )}
                  </Fragment>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {/* Only show Admin group if there are admin items */}
        {adminNavItems.length > 0 && (
          <SidebarGroup className="mt-4 group-data-[collapsible=icon]:mt-2">
            <SidebarGroupLabel className="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 group-data-[collapsible=icon]:hidden">Admin</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:space-y-2 border-l border-sidebar-border ml-3.5 pl-2.5 pr-2 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:ml-0 group-data-[collapsible=icon]:pl-0 group-data-[collapsible=icon]:pr-0">
                {adminNavItems.map((item) => {
                  const Icon = item.icon
                  const active = isActive(item.path)
                  return (
                    <SidebarMenuItem key={item.path} className="relative group-data-[collapsible=icon]:static">
                      <span className="absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full bg-sidebar-border group-data-[collapsible=icon]:hidden" />
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
        )}
      </SidebarContent>

      <SidebarFooter className="px-3 pb-3 group-data-[collapsible=icon]:px-2">
        <div className="border-t border-sidebar-border pt-3 group-data-[collapsible=icon]:pt-2">
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
