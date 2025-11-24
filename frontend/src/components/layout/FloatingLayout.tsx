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
    <SidebarProvider>
      <div className="flex h-screen w-full bg-gray-50 dark:bg-background">
        {/* Sidebar */}
        <AppSidebar />
        {/* Main content area */}
        <SidebarInset className="flex flex-col flex-1 overflow-hidden">
          {/* Sticky header */}
          <FloatingNav />
          {/* Scrollable content */}
          <main className="flex-1 overflow-y-auto">
            <div className="p-7 md:p-8 max-w-full">
              {children}
            </div>
          </main>
        </SidebarInset>
      </div>
    </SidebarProvider>
  )
}
