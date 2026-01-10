import { useState, useEffect, Fragment } from 'react'
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
  MapPin,
  HelpCircle,
} from 'lucide-react'
import { hasPermission } from '@/utils/permissions'
import { useAuth } from '@/contexts/AuthContext'
import { useBranding } from '@/contexts/BrandingContext'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { useCustomersStore } from '@/stores/useCustomersStore'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { CreateUserModal } from '@/pages/users/modals/CreateUserModal'

export default function AppSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user } = useAuth()
  const { branding } = useBranding()
  const { state } = useSidebar()
  const [createUserOpen, setCreateUserOpen] = useState(false)
  const [customersExpanded, setCustomersExpanded] = useState(() => {
    // Check sessionStorage first, then fallback to pathname check
    const stored = sessionStorage.getItem('customersExpanded')
    if (stored !== null) {
      return stored === 'true'
    }
    return location.pathname.startsWith('/platform/customers')
  })
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)
  const unreadCount = getUnreadCount()
  const unmappedCount = useCustomersStore((state) => state.unmappedCount)

  // Keep customers dropdown open when navigating within customers section
  useEffect(() => {
    if (location.pathname.startsWith('/platform/customers')) {
      setCustomersExpanded(true)
      sessionStorage.setItem('customersExpanded', 'true')
    }
  }, [location.pathname])

  // Persist dropdown state to sessionStorage
  useEffect(() => {
    sessionStorage.setItem('customersExpanded', String(customersExpanded))
  }, [customersExpanded])

  const navItems = [
    { path: '/platform/dashboard', icon: Home, label: 'Dashboard', show: true, tourId: 'nav-dashboard' },
    { path: '/platform/integrations', icon: Cable, label: 'Integrations', show: hasPermission(user, 'integrations', 'view'), tourId: 'nav-integrations' },
    { path: '/platform/reports', icon: FileText, label: 'Reports', show: hasPermission(user, 'reports', 'view'), tourId: 'nav-reports' },
    { path: '/platform/schedules', icon: Calendar, label: 'Schedules', show: hasPermission(user, 'reports', 'view'), tourId: 'nav-schedules' },
    { path: '/platform/templates', icon: FileText, label: 'Templates', show: hasPermission(user, 'reports', 'configure'), tourId: 'nav-templates' },
    { path: '/platform/notifications', icon: Bell, label: 'Notifications', show: true, tourId: 'nav-notifications' },
  ].filter(item => item.show !== false)

  const customersSubItems = [
    { path: '/platform/customers/master', icon: Database, label: 'Master List' },
    { path: '/platform/customers/secondary', icon: Building2, label: 'Secondary List' },
    { path: '/platform/customers/map', icon: MapPin, label: 'Map Customers', badge: unmappedCount },
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
                    <SidebarMenuItem className="relative group-data-[collapsible=icon]:static group">
                      <span className={`absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full transition-colors group-data-[collapsible=icon]:hidden ${active ? 'bg-primary' : 'bg-sidebar-border'}`} />
                      <SidebarMenuButton
                        asChild
                        isActive={active}
                        tooltip={item.label}
                        className="rounded-lg group-data-[collapsible=icon]:!p-0"
                        style={active ? { background: 'var(--brand-gradient)' } : undefined}
                      >
                        <Link to={item.path} onClick={(e) => handleNavClick(e, item.path)} data-tour={item.tourId} className="flex items-center gap-3 px-3 py-2.5 h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:justify-center">
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
                      <SidebarMenuItem className="group-data-[collapsible=icon]:static" data-tour="nav-customers">
                        {state === 'collapsed' ? (
                          <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                              <div className="relative">
                                <SidebarMenuButton
                                  tooltip="Customers"
                                  className="rounded-lg group-data-[collapsible=icon]:!p-0 px-3 py-2.5 h-10"
                                >
                                  <Users className="h-4 w-4 flex-shrink-0" />
                                  <span className="font-medium group-data-[collapsible=icon]:hidden flex-1">Customers</span>
                                </SidebarMenuButton>
                              </div>
                            </TooltipTrigger>
                            <TooltipContent side="right" className="p-0 min-w-[180px]">
                              <div className="flex flex-col">
                                {customersSubItems.map((subItem, index) => {
                                  const SubIcon = subItem.icon
                                  const subActive = isActive(subItem.path)
                                  const showBadge = typeof subItem.badge === 'number' && subItem.badge > 0
                                  const isFirst = index === 0
                                  const isLast = index === customersSubItems.length - 1
                                  return (
                                    <Link
                                      key={subItem.path}
                                      to={subItem.path}
                                      className={`flex items-center gap-3 px-3 py-2.5 transition-colors relative ${isFirst ? 'rounded-t-md' : ''
                                        } ${isLast ? 'rounded-b-md' : ''}`}
                                      style={subActive ? { background: 'var(--brand-gradient)' } : undefined}
                                      onMouseEnter={(e) => {
                                        if (!subActive) {
                                          e.currentTarget.style.background = 'rgba(0, 0, 0, 0.1)'
                                        }
                                      }}
                                      onMouseLeave={(e) => {
                                        if (!subActive) {
                                          e.currentTarget.style.background = ''
                                        }
                                      }}
                                    >
                                      <SubIcon className="h-4 w-4 flex-shrink-0" />
                                      <span className="flex-1 text-sm whitespace-nowrap">{subItem.label}</span>
                                      {showBadge && (
                                        <span className="h-4 min-w-[16px] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold flex items-center justify-center">
                                          {subItem.badge}
                                        </span>
                                      )}
                                    </Link>
                                  )
                                })}
                              </div>
                            </TooltipContent>
                          </Tooltip>
                        ) : (
                          <>
                            <div className="relative group">
                              <span className={`absolute left-[-14px] top-5 h-1.5 w-1.5 rounded-full transition-colors group-data-[collapsible=icon]:hidden ${location.pathname.startsWith('/platform/customers') ? 'bg-primary' : 'bg-sidebar-border'}`} />
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
                              className={`overflow-hidden transition-all duration-300 ease-in-out ${customersExpanded ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
                                }`}
                            >
                              <SidebarMenuSub className="group-data-[collapsible=icon]:hidden">
                                {customersSubItems.map((subItem) => {
                                  const SubIcon = subItem.icon
                                  const subActive = isActive(subItem.path)
                                  const showBadge = typeof subItem.badge === 'number' && subItem.badge > 0
                                  return (
                                    <SidebarMenuSubItem key={subItem.path} className="relative group">
                                      <span className={`absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full transition-colors ${subActive ? 'bg-primary' : 'bg-sidebar-border'}`} />
                                      {showBadge && (
                                        <span className="absolute -top-2 -right-2 h-4 min-w-[16px] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold flex items-center justify-center ring-1 ring-background z-10">
                                          {subItem.badge}
                                        </span>
                                      )}
                                      <SidebarMenuSubButton
                                        asChild
                                        isActive={subActive}
                                        style={subActive ? { background: 'var(--brand-gradient)' } : undefined}
                                      >
                                        <Link to={subItem.path} onClick={(e) => handleNavClick(e, subItem.path)}>
                                          <div className="relative flex-shrink-0">
                                            <SubIcon className="h-4 w-4" />
                                          </div>
                                          <span className="flex-1 whitespace-nowrap">{subItem.label}</span>
                                        </Link>
                                      </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                  )
                                })}
                              </SidebarMenuSub>
                            </div>
                          </>
                        )}
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
                    <SidebarMenuItem key={item.path} className="relative group-data-[collapsible=icon]:static group">
                      <span className={`absolute left-[-14px] top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full transition-colors group-data-[collapsible=icon]:hidden ${active ? 'bg-primary' : 'bg-sidebar-border'}`} />
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
        {/* Help & Documentation Link */}
        <SidebarMenu className="border-t border-sidebar-border pt-3 group-data-[collapsible=icon]:pt-2">
          <SidebarMenuItem className="relative group-data-[collapsible=icon]:static">
            <SidebarMenuButton
              asChild
              isActive={isActive('/docs')}
              tooltip="Help & Guides"
              className="rounded-lg group-data-[collapsible=icon]:!p-0"
              style={isActive('/docs') ? { background: 'var(--brand-gradient)' } : undefined}
            >
              <Link to="/docs" onClick={(e) => handleNavClick(e, '/docs')} data-tour="nav-help" className="flex items-center gap-3 px-3 py-2.5 h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:justify-center">
                <HelpCircle className="h-4 w-4 flex-shrink-0" />
                <span className="font-medium group-data-[collapsible=icon]:hidden">Help & Guides</span>
              </Link>
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
          // User created successfully - list will refresh automatically
        }}
      />
    </Sidebar>
  )
}
