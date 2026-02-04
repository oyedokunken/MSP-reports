import { lazy, Suspense } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/contexts/AuthContext'
import { BrandingProvider } from '@/contexts/BrandingContext'
import { ThemeProvider } from '@/components/ui/ThemeProvider'
import { ToastProvider } from '@/components/ui/Toast'
import { ErrorBoundary } from '@/components/ui/ErrorBoundary'
import { QueryProvider } from '@/providers/QueryProvider'
import Login from '@/pages/auth/pages/Signin'
import AuthCallback from '@/pages/auth/pages/AuthCallback'
import Dashboard from '@/pages/dashboard/Dashboard'
import FloatingLayout from '@/components/layout/FloatingLayout'

// Lazy Load Pages
const IntegrationList = lazy(() => import('@/pages/integrations/IntegrationList'))
const AddIntegrationPage = lazy(() => import('@/pages/integrations/AddIntegrationPage'))
const IntegrationPreview = lazy(() => import('@/pages/integrations/IntegrationPreview').then(m => ({ default: m.IntegrationPreview })))
const MasterCustomerList = lazy(() => import('@/pages/customers/MasterCustomerList'))
const SecondaryCustomerList = lazy(() => import('@/pages/customers/SecondaryCustomerList'))
const MapCustomersPage = lazy(() => import('@/pages/customers/MapCustomersPage'))
const CustomerIntegrationDetails = lazy(() => import('@/pages/customers/CustomerIntegrationDetails'))
const ScheduleList = lazy(() => import('@/pages/schedules/ScheduleList'))
const Settings = lazy(() => import('@/pages/settings/Settings'))
const Security = lazy(() => import('@/pages/security/Security'))
const ForgotPassword = lazy(() => import('@/pages/auth/pages/ForgotPassword'))
const ResetPassword = lazy(() => import('@/pages/auth/pages/ResetPassword'))
const ContactAdmin = lazy(() => import('@/pages/auth/pages/ContactAdmin'))
const AuthLayout = lazy(() => import('@/pages/auth/Layout'))
const UsersList = lazy(() => import('@/pages/users').then(m => ({ default: m.UsersList })))
const CreateUser = lazy(() => import('@/pages/users').then(m => ({ default: m.CreateUser })))
const ReportsList = lazy(() => import('@/pages/reports').then(m => ({ default: m.ReportsList })))
const CustomerReports = lazy(() => import('@/pages/reports').then(m => ({ default: m.CustomerReports })))
const ConfigureReports = lazy(() => import('@/pages/reports').then(m => ({ default: m.ConfigureReports })))
const ApiKeysList = lazy(() => import('@/pages/api-keys').then(m => ({ default: m.ApiKeysList })))
const TemplateList = lazy(() => import('@/pages/templates/TemplateList'))
const Profile = lazy(() => import('@/pages/profile/Profile'))
const Licenses = lazy(() => import('@/pages/licenses/Licenses'))
const Monitoring = lazy(() => import('@/pages/monitoring/Monitoring'))
const AuditLogs = lazy(() => import('@/pages/audit-logs').then(m => ({ default: m.AuditLogs })))
const NotificationsPage = lazy(() => import('@/pages/notifications/NotificationsPage'))
const PrivacyPolicy = lazy(() => import('@/pages/legal').then(m => ({ default: m.PrivacyPolicy })))
const TermsOfService = lazy(() => import('@/pages/legal').then(m => ({ default: m.TermsOfService })))
const DocsIndex = lazy(() => import('@/pages/docs').then(m => ({ default: m.DocsIndex })))
const DocPage = lazy(() => import('@/pages/docs').then(m => ({ default: m.DocPage })))
const NotFound = lazy(() => import('@/pages/errors').then(m => ({ default: m.NotFound })))

import { WelcomeModal, TourOverlay } from '@/components/onboarding'
import { Loader2 } from 'lucide-react'


import { hasPermission } from '@/utils/permissions'

function ProtectedRoute({ children, permission }: { children: React.ReactNode, permission?: { section: string, action: string } }) {
  const { user, isAuthenticated, isLoading } = useAuth()

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

  if (permission && !hasPermission(user, permission.section, permission.action)) {
    return <Navigate to="/platform/dashboard" replace />
  }

  return <>{children}</>
}

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
        <Route path="privacy-policy" element={<PrivacyPolicy />} />
        <Route path="terms-of-service" element={<TermsOfService />} />
        <Route path="auth/callback" element={<AuthCallback />} />
      </Route>

      {/* Main Platform Layout */}
      <Route
        element={
          <ProtectedRoute>
            <FloatingLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/platform/dashboard" element={<Dashboard />} />
        
        <Route
          path="/platform/integrations"
          element={
            <ProtectedRoute permission={{ section: 'integrations', action: 'view' }}>
              <IntegrationList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/integrations/add"
          element={
            <ProtectedRoute permission={{ section: 'integrations', action: 'manage' }}>
              <AddIntegrationPage />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/integrations/:integrationId/preview"
          element={
            <ProtectedRoute permission={{ section: 'integrations', action: 'view' }}>
              <IntegrationPreview />
            </ProtectedRoute>
          }
        />

        <Route path="/platform/profile" element={<Profile />} />

        {/* TODO: Add Active Directory and Exchange routes when features are implemented */}


        <Route
          path="/platform/customers"
          element={<Navigate to="/platform/customers/master" replace />}
        />

        <Route
          path="/platform/customers/master"
          element={
            <ProtectedRoute permission={{ section: 'customers', action: 'view' }}>
              <MasterCustomerList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/customers/secondary"
          element={
            <ProtectedRoute permission={{ section: 'customers', action: 'view' }}>
              <SecondaryCustomerList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/customers/map"
          element={
            <ProtectedRoute permission={{ section: 'customers', action: 'map' }}>
              <MapCustomersPage />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/customers/:customerId/integrations"
          element={
            <ProtectedRoute permission={{ section: 'customers', action: 'view' }}>
              <CustomerIntegrationDetails />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/customers/:id/configure-reports"
          element={
            <ProtectedRoute permission={{ section: 'reports', action: 'configure' }}>
              <ConfigureReports />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/customers/:id/reports"
          element={
            <ProtectedRoute permission={{ section: 'reports', action: 'view' }}>
              <CustomerReports />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/reports"
          element={
            <ProtectedRoute permission={{ section: 'reports', action: 'view' }}>
              <ReportsList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/schedules"
          element={
            <ProtectedRoute permission={{ section: 'reports', action: 'view' }}>
              <ScheduleList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/templates"
          element={
            <ProtectedRoute permission={{ section: 'reports', action: 'configure' }}>
              <TemplateList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/settings"
          element={
            <ProtectedRoute permission={{ section: 'company_settings', action: 'view' }}>
              <Settings />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/security"
          element={
            <ProtectedRoute permission={{ section: 'security', action: 'view_logs' }}>
              <Security />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/users"
          element={
            <ProtectedRoute permission={{ section: 'users', action: 'view' }}>
              <UsersList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/users/create"
          element={
            <ProtectedRoute permission={{ section: 'users', action: 'manage' }}>
              <CreateUser />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/licenses"
          element={
            <ProtectedRoute permission={{ section: 'company_settings', action: 'view' }}>
              <Licenses />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/api-keys"
          element={
            <ProtectedRoute permission={{ section: 'api_keys', action: 'view' }}>
              <ApiKeysList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/monitoring"
          element={
            <ProtectedRoute permission={{ section: 'monitoring', action: 'view' }}>
              <Monitoring />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/audit-logs"
          element={
            <ProtectedRoute permission={{ section: 'security', action: 'view_logs' }}>
              <AuditLogs />
            </ProtectedRoute>
          }
        />

        <Route
          path="/platform/notifications"
          element={
            <ProtectedRoute permission={{ section: 'notifications', action: 'view' }}>
              <NotificationsPage />
            </ProtectedRoute>
          }
        />

        <Route path="/docs" element={<DocsIndex />} />
        <Route path="/docs/:categoryId/:articleId" element={<DocPage />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

function App() {
  return (
    <BrowserRouter>
      <QueryProvider>
        <ThemeProvider defaultTheme="light" storageKey="reporting-theme">
          <ToastProvider>
            <BrandingProvider>
              <AuthProvider>
                <ErrorBoundary>
                  <Suspense fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  }>
                    <AppRoutes />
                  </Suspense>
                </ErrorBoundary>
                <WelcomeModal />
                <TourOverlay />
              </AuthProvider>
            </BrandingProvider>
          </ToastProvider>
        </ThemeProvider>
      </QueryProvider>
    </BrowserRouter>
  )
}

export default App
