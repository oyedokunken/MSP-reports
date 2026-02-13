import { useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  RefreshCw,
  Users,
  FileText,
  Database,
  Settings,
  ArrowRight,
  ArrowLeft,
  CheckCircle2,
  Sparkles,
  Cable,
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface WalkthroughStep {
  title: string
  description: string
  icon: React.ReactNode
  tip?: string
}

interface IntegrationWalkthroughModalProps {
  isOpen: boolean
  onClose: () => void
  integrationName: string
  integrationType: string
  onNavigate?: (path: string) => void
}

function getWalkthroughSteps(name: string, type: string): WalkthroughStep[] {
  const normalizedType = type.toLowerCase()

  const commonSteps: WalkthroughStep[] = [
    {
      title: `${name} has been added!`,
      description: `Your new integration is now active and ready to sync. Here's a quick walkthrough on how to get the most out of it.`,
      icon: <CheckCircle2 className="h-8 w-8 text-emerald-500" />,
      tip: 'The integration will attempt its first sync automatically.',
    },
    {
      title: 'Syncing Your Data',
      description: 'Integrations sync automatically on a schedule (typically every 4 hours). You can also trigger a manual sync anytime from the integration card or table view by clicking the sync button.',
      icon: <RefreshCw className="h-8 w-8 text-blue-500" />,
      tip: 'Check the sync logs if a sync fails — it usually means credentials need updating.',
    },
    {
      title: 'Customer Mapping',
      description: 'After syncing, customers from this integration appear in the "Unmapped Customers" list. You can auto-match them to your master customer list or manually map them.',
      icon: <Users className="h-8 w-8 text-violet-500" />,
      tip: 'Set one integration as "Source of Truth" to use its customer names as the canonical list.',
    },
    {
      title: 'Preview Integration Data',
      description: 'Click on the integration name to view live data — devices, tenants, tickets, or whatever this integration provides. This helps verify everything is syncing correctly.',
      icon: <Database className="h-8 w-8 text-amber-500" />,
      tip: 'Use the preview to verify data quality before generating reports.',
    },
    {
      title: 'Generate Reports',
      description: 'Once customers are mapped, you can configure report schedules. Reports pull data from all mapped integrations to create comprehensive, branded PDFs delivered on your schedule.',
      icon: <FileText className="h-8 w-8 text-rose-500" />,
      tip: 'Navigate to Reports → Schedules to set up automated report delivery.',
    },
  ]

  // Add integration-specific tips
  if (normalizedType.includes('datto')) {
    commonSteps.splice(2, 0, {
      title: 'Historical Data Import',
      description: 'Datto supports historical data import. You can import up to 90 days of backup and device history for trend analysis in reports. Go to Actions → Import Historical Data.',
      icon: <Database className="h-8 w-8 text-teal-500" />,
      tip: 'Historical imports run in the background and can take several hours for large datasets.',
    })
  }

  if (normalizedType.includes('cipp') || normalizedType.includes('m365') || normalizedType.includes('microsoft')) {
    commonSteps.splice(2, 0, {
      title: 'Microsoft 365 Tenants',
      description: 'This integration syncs all managed tenants from your CIPP instance. Each tenant appears as a customer that can be mapped to your master list. The sync may take a few minutes for large tenant counts.',
      icon: <Cable className="h-8 w-8 text-sky-500" />,
      tip: 'CIPP syncs can be heavy with 100+ tenants — the system handles this with parallel processing.',
    })
  }

  if (normalizedType.includes('autotask')) {
    commonSteps.splice(2, 0, {
      title: 'Autotask Customers & Tickets',
      description: 'Autotask syncs your company list, active tickets, and service data. Consider setting Autotask as your Source of Truth if it has your most complete customer list.',
      icon: <Settings className="h-8 w-8 text-orange-500" />,
      tip: 'Autotask is commonly used as the Source of Truth for customer names.',
    })
  }

  if (normalizedType.includes('threatlocker')) {
    commonSteps.splice(2, 0, {
      title: 'ThreatLocker Security Data',
      description: 'ThreatLocker syncs application control policies, audit logs, and endpoint security data. This data powers the ThreatLocker Summary Report for your customers.',
      icon: <Settings className="h-8 w-8 text-red-500" />,
      tip: 'Make sure your ThreatLocker API token has read access to all managed organizations.',
    })
  }

  if (normalizedType.includes('itglue')) {
    commonSteps.splice(2, 0, {
      title: 'IT Glue Documentation',
      description: 'IT Glue syncs your documentation assets including configurations, passwords, and flexible assets. License utilization data is also tracked for reporting.',
      icon: <FileText className="h-8 w-8 text-indigo-500" />,
      tip: 'IT Glue data is great for compliance and documentation coverage reports.',
    })
  }

  return commonSteps
}

export function IntegrationWalkthroughModal({
  isOpen,
  onClose,
  integrationName,
  integrationType,
  onNavigate,
}: IntegrationWalkthroughModalProps) {
  const [currentStep, setCurrentStep] = useState(0)
  const steps = getWalkthroughSteps(integrationName, integrationType)
  const isLastStep = currentStep === steps.length - 1
  const isFirstStep = currentStep === 0
  const step = steps[currentStep]

  const handleNext = () => {
    if (isLastStep) {
      onClose()
      if (onNavigate) {
        onNavigate('/platform/integrations')
      }
    } else {
      setCurrentStep(prev => prev + 1)
    }
  }

  const handleBack = () => {
    if (!isFirstStep) {
      setCurrentStep(prev => prev - 1)
    }
  }

  const handleSkip = () => {
    onClose()
    if (onNavigate) {
      onNavigate('/platform/integrations')
    }
  }

  return (
    <Dialog open={isOpen} onOpenChange={(open) => { if (!open) handleSkip() }}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <div className="flex items-center gap-2 mb-1">
            <Sparkles className="h-5 w-5 text-amber-500" />
            <Badge variant="secondary" className="text-[10px]">
              AI Walkthrough
            </Badge>
            <Badge variant="outline" className="text-[10px] ml-auto">
              {currentStep + 1} / {steps.length}
            </Badge>
          </div>
          <DialogTitle className="text-xl">{step.title}</DialogTitle>
          <DialogDescription className="sr-only">
            Integration walkthrough step {currentStep + 1} of {steps.length}
          </DialogDescription>
        </DialogHeader>

        <div className="mt-2 space-y-4">
          {/* Icon + Description */}
          <div className="flex gap-4">
            <div className="flex-shrink-0 mt-1">
              <div className="rounded-xl bg-muted/50 p-3">
                {step.icon}
              </div>
            </div>
            <div className="flex-1">
              <p className="text-sm text-muted-foreground leading-relaxed">
                {step.description}
              </p>
            </div>
          </div>

          {/* Tip */}
          {step.tip && (
            <div className="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 px-4 py-3">
              <p className="text-xs text-amber-800 dark:text-amber-300">
                <span className="font-semibold">💡 Tip:</span> {step.tip}
              </p>
            </div>
          )}

          {/* Progress dots */}
          <div className="flex items-center justify-center gap-1.5 pt-2">
            {steps.map((_, i) => (
              <button
                key={i}
                onClick={() => setCurrentStep(i)}
                className={cn(
                  'h-2 rounded-full transition-all duration-200',
                  i === currentStep
                    ? 'w-6 bg-primary'
                    : i < currentStep
                      ? 'w-2 bg-primary/40'
                      : 'w-2 bg-muted-foreground/20'
                )}
              />
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-between mt-4">
          <div>
            {isFirstStep ? (
              <Button variant="ghost" size="sm" onClick={handleSkip} className="text-muted-foreground">
                Skip Walkthrough
              </Button>
            ) : (
              <Button variant="ghost" size="sm" onClick={handleBack}>
                <ArrowLeft className="h-4 w-4 mr-1" />
                Back
              </Button>
            )}
          </div>
          <Button onClick={handleNext} size="sm">
            {isLastStep ? (
              <>
                Go to Integrations
                <CheckCircle2 className="h-4 w-4 ml-1" />
              </>
            ) : (
              <>
                Next
                <ArrowRight className="h-4 w-4 ml-1" />
              </>
            )}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
