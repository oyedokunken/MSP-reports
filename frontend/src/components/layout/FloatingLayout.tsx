import { type ReactNode } from 'react'
import AppSidebar from './AppSidebar'
import FloatingNav from './FloatingNav'
import { SidebarProvider, SidebarInset } from '@/components/ui/sidebar'

/**
 * Floating Layout
 * Templify-style: Top navbar (full width) + sidebar below
 */

interface FloatingLayoutProps {
  children: ReactNode
}

export default function FloatingLayout({ children }: FloatingLayoutProps) {
  return (
    <div className="min-h-screen bg-gray-50 dark:bg-background overflow-x-hidden">
      <SidebarProvider>
        {/* Sidebar + Content */}
        <AppSidebar />
        <SidebarInset>
          {/* Inset header/nav area */}
          <FloatingNav />
          {/* Page content */}
          <div className="flex-1 bg-gray-50 dark:bg-background">
            <div className="p-7 md:p-8 max-w-full overflow-x-hidden">
              {children}
            </div>
          </div>
        </SidebarInset>
      </SidebarProvider>
    </div>
  )
}
