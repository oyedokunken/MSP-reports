import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/contexts/AuthContext'
import { BrandingProvider } from '@/contexts/BrandingContext'
import { ThemeProvider } from '@/components/ui/ThemeProvider'
import { ToastProvider } from '@/components/ui/Toast'
import { TenantProvider } from '@/contexts/TenantContext'
import Login from '@/pages/auth/pages/Signin'
import Dashboard from '@/pages/dashboard/Dashboard'
import IntegrationList from '@/pages/integrations/IntegrationList'
import CustomerMapping from '@/pages/customers/CustomerMapping'
import ReportList from '@/pages/reports/ReportList'
import ScheduleList from '@/pages/schedules/ScheduleList'
import Settings from '@/pages/settings/Settings'
import Signup from '@/pages/auth/pages/Signup'
import AuthLayout from '@/pages/auth/Layout'
import { UsersList, CreateUser } from '@/pages/users'

// Protected route wrapper
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-muted-foreground">Loading...</div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}

// Public route wrapper (redirect to dashboard if already logged in)
function PublicRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-muted-foreground">Loading...</div>
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

function AppRoutes() {
  return (
    <Routes>
      {/* Public auth layout with nested routes */}
      <Route
        path="/"
        element={
          <PublicRoute>
            <AuthLayout />
          </PublicRoute>
        }
      >
        <Route index element={<Navigate to="/login" replace />} />
        <Route path="login" element={<Login />} />
        <Route path="sign-up" element={<Signup />} />
      </Route>

      {/* Protected routes */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        }
      />

      <Route
        path="/integrations"
        element={
          <ProtectedRoute>
            <IntegrationList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/customers"
        element={
          <ProtectedRoute>
            <CustomerMapping />
          </ProtectedRoute>
        }
      />

      <Route
        path="/reports"
        element={
          <ProtectedRoute>
            <ReportList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/schedules"
        element={
          <ProtectedRoute>
            <ScheduleList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/settings"
        element={
          <ProtectedRoute>
            <Settings />
          </ProtectedRoute>
        }
      />

      <Route
        path="/users"
        element={
          <ProtectedRoute>
            <UsersList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/users/create"
        element={
          <ProtectedRoute>
            <CreateUser />
          </ProtectedRoute>
        }
      />

      {/* Default redirect for unknown routes */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

function App() {
  return (
    <BrowserRouter>
      <ThemeProvider defaultTheme="light" storageKey="reporting-theme">
        <ToastProvider>
          <TenantProvider>
            <AuthProvider>
              <BrandingProvider>
                <AppRoutes />
              </BrandingProvider>
            </AuthProvider>
          </TenantProvider>
        </ToastProvider>
      </ThemeProvider>
    </BrowserRouter>
  )
}

export default App
