import { useEffect } from 'react'
import { Sparkles, Rocket, BookOpen } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/contexts/AuthContext'
import { useBranding } from '@/contexts/BrandingContext'
import { useOnboardingStore } from '@/stores/useOnboardingStore'

export function WelcomeModal() {
  const { user } = useAuth()
  const { branding } = useBranding()
  const { 
    showWelcomeModal, 
    hasSeenWelcome,
    hasCompletedTour,
    closeWelcomeModal, 
    startTour,
    openWelcomeModal,
  } = useOnboardingStore()

  useEffect(() => {
    if (user && !hasSeenWelcome) {
      const timer = setTimeout(() => {
        openWelcomeModal()
      }, 500)
      return () => clearTimeout(timer)
    }
  }, [user, hasSeenWelcome, openWelcomeModal])

  const firstName = user?.name?.split(' ')[0] || 'there'
  const companyName = branding?.company_name || 'the platform'
  const isFirstTime = !hasCompletedTour

  const handleStartTour = () => {
    startTour()
  }

  const handleSkip = () => {
    closeWelcomeModal()
  }

  return (
    <Dialog open={showWelcomeModal} onOpenChange={(open) => !open && closeWelcomeModal()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader className="text-center sm:text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-primary/20 to-primary/5">
            {isFirstTime ? (
              <Sparkles className="h-8 w-8 text-primary" />
            ) : (
              <Rocket className="h-8 w-8 text-primary" />
            )}
          </div>
          <DialogTitle className="text-2xl">
            {isFirstTime ? (
              <>Welcome, {firstName}! 🎉</>
            ) : (
              <>Welcome back, {firstName}!</>
            )}
          </DialogTitle>
          <DialogDescription className="text-base pt-2">
            {isFirstTime ? (
              <>
                We're excited to have you on board! {companyName} helps you manage integrations, 
                customers, and generate professional reports all in one place.
              </>
            ) : (
              <>
                Great to see you again! Would you like a quick refresher on how to use the platform?
              </>
            )}
          </DialogDescription>
        </DialogHeader>

        <div className="mt-4 space-y-3">
          {isFirstTime && (
            <div className="rounded-lg border bg-muted/50 p-4">
              <h4 className="font-medium mb-2 flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-primary" />
                Quick Tour Available
              </h4>
              <p className="text-sm text-muted-foreground">
                Take a 2-minute guided tour to learn where everything is and how to get started.
              </p>
            </div>
          )}

          <div className="flex flex-col-reverse sm:flex-row gap-2 pt-2">
            <Button
              variant="outline"
              className="flex-1"
              onClick={handleSkip}
            >
              {isFirstTime ? "Skip for now" : "Maybe later"}
            </Button>
            <Button
              className="flex-1"
              onClick={handleStartTour}
            >
              <Rocket className="mr-2 h-4 w-4" />
              {isFirstTime ? "Start Tour" : "Take the Tour"}
            </Button>
          </div>

          {!isFirstTime && (
            <p className="text-xs text-center text-muted-foreground pt-1">
              You can always access the tour from Help & Guides
            </p>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
