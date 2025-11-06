import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/contexts/AuthContext'
import { BrandingProvider } from '@/contexts/BrandingContext'
import { ThemeProvider } from '@/components/ui/ThemeProvider'
import { ToastProvider } from '@/components/ui/Toast'
import Login from '@/pages/auth/pages/Signin'
import Dashboard from '@/pages/dashboard/Dashboard'
import IntegrationList from '@/pages/integrations/IntegrationList'
import CustomerList from '@/pages/customers/CustomerList'
import ScheduleList from '@/pages/schedules/ScheduleList'
import Settings from '@/pages/settings/Settings'
import ForgotPassword from '@/pages/auth/pages/ForgotPassword'
import ResetPassword from '@/pages/auth/pages/ResetPassword'
import ContactAdmin from '@/pages/auth/pages/ContactAdmin'
import AuthLayout from '@/pages/auth/Layout'
import { UsersList, CreateUser } from '@/pages/users'
import { ReportsList, CustomerReports, ConfigureReports } from '@/pages/reports'
import Licenses from '@/pages/licenses/Licenses'
import Monitoring from '@/pages/monitoring/Monitoring'
import { Loader2 } from 'lucide-react'

// Protected route wrapper
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
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
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
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
        <Route path="sign-in" element={<Login />} />
        <Route path="forgot-password" element={<ForgotPassword />} />
        <Route path="reset-password" element={<ResetPassword />} />
        <Route path="contact-admin" element={<ContactAdmin />} />
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
            <CustomerList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/customers/:id/configure-reports"
        element={
          <ProtectedRoute>
            <ConfigureReports />
          </ProtectedRoute>
        }
      />

      <Route
        path="/customers/:id/reports"
        element={
          <ProtectedRoute>
            <CustomerReports />
          </ProtectedRoute>
        }
      />

      <Route
        path="/reports"
        element={
          <ProtectedRoute>
            <ReportsList />
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

      <Route
        path="/licenses"
        element={
          <ProtectedRoute>
            <Licenses />
          </ProtectedRoute>
        }
      />

      <Route
        path="/monitoring"
        element={
          <ProtectedRoute>
            <Monitoring />
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
          <BrandingProvider>
            <AuthProvider>
              <AppRoutes />
            </AuthProvider>
          </BrandingProvider>
        </ToastProvider>
      </ThemeProvider>
    </BrowserRouter>
  )
}

export default App
