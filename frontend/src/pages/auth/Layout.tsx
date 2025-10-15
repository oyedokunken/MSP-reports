import { ThemeToggle } from "@/components/ui/theme-toggle"
import { Outlet } from "react-router-dom"

const Layout = () => {
  return (
    <div className="fixed inset-0 overflow-auto bg-[hsl(var(--auth-background))] auth-theme">
      <div className="relative w-full min-h-svh flex items-start justify-center pt-24 px-4">
        <div className="fixed right-4 top-4 z-10">
          <ThemeToggle />
        </div>
        <div className="w-full flex justify-center">
          <Outlet />
        </div>
      </div>
    </div>
  )
}

export default Layout
