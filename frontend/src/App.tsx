import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/contexts/AuthContext'
import { BrandingProvider } from '@/contexts/BrandingContext'
import { ThemeProvider } from '@/components/ui/ThemeProvider'
import { ToastProvider } from '@/components/ui/Toast'
import Login from '@/pages/auth/pages/Signin'
import Dashboard from '@/pages/dashboard/Dashboard'
import IntegrationList from '@/pages/integrations/IntegrationList'
import { IntegrationPreview } from '@/pages/integrations/IntegrationPreview'
import CustomerList from '@/pages/customers/CustomerList'
import CustomerIntegrationDetails from '@/pages/customers/CustomerIntegrationDetails'
import ScheduleList from '@/pages/schedules/ScheduleList'
import Settings from '@/pages/settings/Settings'
import ForgotPassword from '@/pages/auth/pages/ForgotPassword'
import ResetPassword from '@/pages/auth/pages/ResetPassword'
import ContactAdmin from '@/pages/auth/pages/ContactAdmin'
import AuthLayout from '@/pages/auth/Layout'
import { UsersList, CreateUser } from '@/pages/users'
import { ReportsList, CustomerReports, ConfigureReports } from '@/pages/reports'
import { ApiKeysList } from '@/pages/api-keys'
// TODO: Create these pages when needed
// import { 
//   ActiveDirectoryDashboard, 
//   UsersList as ADUsersList, 
//   UserDetail as ADUserDetail,
//   GroupsList as ADGroupsList,
//   GroupDetail as ADGroupDetail
// } from '@/pages/active-directory'
// import {
//   ExchangeDashboard,
//   MailboxesList,
//   MailboxDetail
// } from '@/pages/exchange'
import Licenses from '@/pages/licenses/Licenses'
import Monitoring from '@/pages/monitoring/Monitoring'
import NotificationsPage from '@/pages/notifications/NotificationsPage'
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

// Public route wrapper (redirects to dashboard if already logged in) 
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
    return <Navigate to="/platform/dashboard" replace />
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
        path="/platform/dashboard"
        element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/integrations"
        element={
          <ProtectedRoute>
            <IntegrationList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/integrations/:integrationId/preview"
        element={
          <ProtectedRoute>
            <IntegrationPreview />
          </ProtectedRoute>
        }
      />

      {/* TODO: Uncomment when Active Directory pages are created
      <Route
        path="/platform/active-directory"
        element={
          <ProtectedRoute>
            <ActiveDirectoryDashboard />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/active-directory/users"
        element={
          <ProtectedRoute>
            <ADUsersList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/active-directory/users/:id"
        element={
          <ProtectedRoute>
            <ADUserDetail />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/active-directory/groups"
        element={
          <ProtectedRoute>
            <ADGroupsList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/active-directory/groups/:id"
        element={
          <ProtectedRoute>
            <ADGroupDetail />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/exchange"
        element={
          <ProtectedRoute>
            <ExchangeDashboard />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/exchange/mailboxes"
        element={
          <ProtectedRoute>
            <MailboxesList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/exchange/mailboxes/:id"
        element={
          <ProtectedRoute>
            <MailboxDetail />
          </ProtectedRoute>
        }
      />
      */}

      <Route
        path="/platform/customers"
        element={
          <ProtectedRoute>
            <CustomerList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/customers/:customerId/integrations"
        element={
          <ProtectedRoute>
            <CustomerIntegrationDetails />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/customers/:id/configure-reports"
        element={
          <ProtectedRoute>
            <ConfigureReports />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/customers/:id/reports"
        element={
          <ProtectedRoute>
            <CustomerReports />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/reports"
        element={
          <ProtectedRoute>
            <ReportsList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/schedules"
        element={
          <ProtectedRoute>
            <ScheduleList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/settings"
        element={
          <ProtectedRoute>
            <Settings />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/users"
        element={
          <ProtectedRoute>
            <UsersList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/users/create"
        element={
          <ProtectedRoute>
            <CreateUser />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/licenses"
        element={
          <ProtectedRoute>
            <Licenses />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/api-keys"
        element={
          <ProtectedRoute>
            <ApiKeysList />
          </ProtectedRoute>
        }
      />

      <Route
        path="/admin/monitoring"
        element={
          <ProtectedRoute>
            <Monitoring />
          </ProtectedRoute>
        }
      />

      <Route
        path="/platform/notifications"
        element={
          <ProtectedRoute>
            <NotificationsPage />
          </ProtectedRoute>
        }
      />

      {/* Default redirect for unknown routes */}
      <Route path="*" element={<Navigate to="/platform/dashboard" replace />} />
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
