import { Link } from 'react-router-dom'
import { Home, ArrowLeft, Search, HelpCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useBranding } from '@/contexts/BrandingContext'

export default function NotFound() {
  const { branding } = useBranding()

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-muted/30 flex items-center justify-center p-4">
      <div className="max-w-lg w-full text-center">
        <div className="mb-8">
          {branding?.logo_url ? (
            <img
              src={branding.logo_url}
              alt={branding.company_name}
              className="h-12 w-auto mx-auto object-contain"
            />
          ) : (
            <>
              <img
                src="/logo.png"
                alt="Logo"
                className="h-12 w-auto mx-auto object-contain dark:hidden"
              />
              <img
                src="/logo-white.png"
                alt="Logo"
                className="h-12 w-auto mx-auto object-contain hidden dark:block"
              />
            </>
          )}
        </div>

        <div className="relative mb-8">
          <h1 className="text-[120px] md:text-[180px] font-bold text-muted-foreground/20 leading-none select-none">
            404
          </h1>
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="bg-background/80 backdrop-blur-sm rounded-2xl px-6 py-4 shadow-lg border">
              <Search className="h-12 w-12 text-muted-foreground mx-auto mb-2" />
              <p className="text-lg font-semibold text-foreground">Page Not Found</p>
            </div>
          </div>
        </div>

        <p className="text-muted-foreground mb-8 max-w-md mx-auto">
          Sorry, the page you're looking for doesn't exist or has been moved. 
          Check the URL or navigate back to a known page.
        </p>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
          <Button asChild size="lg" className="w-full sm:w-auto">
            <Link to="/platform/dashboard">
              <Home className="mr-2 h-4 w-4" />
              Go to Dashboard
            </Link>
          </Button>
          <Button asChild variant="outline" size="lg" className="w-full sm:w-auto">
            <Link to="/docs">
              <HelpCircle className="mr-2 h-4 w-4" />
              View Help Guides
            </Link>
          </Button>
        </div>

        <div className="mt-8">
          <button
            onClick={() => window.history.back()}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="mr-1 h-4 w-4" />
            Go back to previous page
          </button>
        </div>
      </div>
    </div>
  )
}
