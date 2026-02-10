import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from '@/contexts/AuthContext'
import { BrandingProvider } from '@/contexts/BrandingContext'
import { ThemeProvider } from '@/components/ui/ThemeProvider'
import { ToastProvider } from '@/components/ui/Toast'
import { ErrorBoundary } from '@/components/ui/ErrorBoundary'
import { QueryProvider } from '@/providers/QueryProvider'
import { AppRoutes } from './AppRoutes'
import { WelcomeModal, TourOverlay } from '@/components/onboarding'

function App() {
  return (
    <BrowserRouter>
      <QueryProvider>
        <ThemeProvider defaultTheme="light" storageKey="reporting-theme">
          <ToastProvider>
            <BrandingProvider>
              <AuthProvider>
                <ErrorBoundary>
                  <AppRoutes />
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
