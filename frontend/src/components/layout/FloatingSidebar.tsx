import { Link, useLocation } from 'react-router-dom'
import {
  Home,
  Cable,
  Users,
  FileText,
  Settings,
  LogOut,
  Calendar,
  SlidersHorizontal,
  X,
} from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'

/**
 * Floating Sidebar
 * Modern floating sidebar inspired by Templify
 */
interface FloatingSidebarProps {
  mobileOpen?: boolean
  onClose?: () => void
}

export default function FloatingSidebar({ mobileOpen = false, onClose }: FloatingSidebarProps) {
  const location = useLocation()
  const { user, logout } = useAuth()

  // 👇 Main navigation links
  const navItems = [
    { path: '/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/integrations', icon: Cable, label: 'Integrations' },
    { path: '/customers', icon: Users, label: 'Customers' },
    { path: '/reports', icon: FileText, label: 'Reports' },
    { path: '/schedules', icon: Calendar, label: 'Schedules' }, // 🆕 Added
  ]

  // 👇 Bottom links (settings area)
  const bottomItems = [
    { path: '/settings', icon: Settings, label: 'Settings' },
    { path: '/options', icon: SlidersHorizontal, label: 'Options' }, // 🆕 Added
  ]

  const isActive = (path: string) => location.pathname === path
  const initials = (user?.name ?? 'User')
    .split(' ')
    .filter(Boolean)
    .map((w) => w.charAt(0))
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const SidebarContent = () => (
    <>
      {/* Main Navigation */}
      <nav className="p-4 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon
          const active = isActive(item.path)
          return (
            <Link
              key={item.path}
              to={item.path}
              className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                active
                  ? 'bg-primary text-white'
                  : 'text-muted-foreground hover:text-foreground hover:bg-gray-100 dark:hover:bg-gray-800'
              }`}
            >
              <Icon className={`h-5 w-5 ${active ? 'text-white' : ''}`} />
              <span>{item.label}</span>
            </Link>
          )
        })}
      </nav>

      {/* Bottom Section */}
      <div className="mt-auto p-4 border-t border-gray-200 dark:border-gray-800">
        {/* User Block */}
        <div className="flex items-center gap-3 mb-3">
          <div className="h-9 w-9 rounded-full bg-primary/20 flex items-center justify-center">
            <span className="text-primary font-semibold text-sm">{initials}</span>
          </div>
          <div className="min-w-0">
            <p className="text-sm font-medium text-foreground truncate">
              {user?.name || 'User'}
            </p>
            <p className="text-xs text-muted-foreground truncate">
              {user?.email || 'user@example.com'}
            </p>
          </div>
        </div>

        {/* Secondary Links */}
        <div className="space-y-1 mb-2">
          {bottomItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                  active
                    ? 'bg-primary text-white'
                    : 'text-muted-foreground hover:text-foreground hover:bg-gray-100 dark:hover:bg-gray-800'
                }`}
              >
                <Icon className={`h-5 w-5 ${active ? 'text-white' : ''}`} />
                <span>{item.label}</span>
              </Link>
            )
          })}
        </div>

        {/* Logout Button */}
        <button
          onClick={logout}
          className="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
        >
          <LogOut className="h-5 w-5" />
          <span>Sign out</span>
        </button>
      </div>
    </>
  )

  return (
    <>
      {/* Mobile Drawer */}
      <aside
        className={`fixed left-0 top-[73px] bottom-0 w-64 z-40 lg:hidden border-r border-gray-200 dark:border-gray-800 bg-background dark:bg-[hsl(var(--auth-card))] transform transition-transform duration-200 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}
        aria-hidden={!mobileOpen}
      >
        <div className="h-full flex flex-col">
          <div className="p-3 border-b border-gray-200 dark:border-gray-800 flex justify-end">
            <button
              type="button"
              aria-label="Close menu"
              onClick={onClose}
              className="p-2 rounded-md hover:bg-muted"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
          <SidebarContent />
        </div>
      </aside>

      {/* Desktop Sidebar */}
      <aside className="fixed left-0 top-[73px] bottom-0 w-64 z-40 hidden lg:block border-r border-gray-200 dark:border-gray-800 bg-background dark:bg-[hsl(var(--auth-card))]">
        <div className="h-full flex flex-col">
          <SidebarContent />
        </div>
      </aside>
    </>
  )
}
