import { useEffect, useState, useCallback } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { X, ChevronLeft, ChevronRight, SkipForward } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useOnboardingStore, tourSteps } from '@/stores/useOnboardingStore'
import { cn } from '@/lib/utils'

interface TooltipPosition {
  top: number
  left: number
  arrowPosition: 'top' | 'bottom' | 'left' | 'right'
}

export function TourOverlay() {
  const navigate = useNavigate()
  const location = useLocation()
  const { 
    isTourActive, 
    currentStep, 
    nextStep, 
    prevStep,
    skipTour,
  } = useOnboardingStore()

  const [tooltipPosition, setTooltipPosition] = useState<TooltipPosition | null>(null)
  const [targetRect, setTargetRect] = useState<DOMRect | null>(null)

  const step = tourSteps[currentStep]
  const isFirstStep = currentStep === 0
  const isLastStep = currentStep === tourSteps.length - 1

  const scrollAndPosition = useCallback(() => {
    if (!step?.target) {
      setTooltipPosition({
        top: window.innerHeight / 2 - 100,
        left: window.innerWidth / 2 - 175,
        arrowPosition: 'top',
      })
      setTargetRect(null)
      return
    }

    const element = document.querySelector(step.target)
    if (!element) {
      setTooltipPosition({
        top: window.innerHeight / 2 - 100,
        left: window.innerWidth / 2 - 175,
        arrowPosition: 'top',
      })
      setTargetRect(null)
      return
    }

    // Scroll the target element into view so sidebar items below the fold become visible
    element.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' })
  }, [step])

  const calculatePosition = useCallback(() => {
    if (!step?.target) {
      setTooltipPosition({
        top: window.innerHeight / 2 - 100,
        left: window.innerWidth / 2 - 175,
        arrowPosition: 'top',
      })
      setTargetRect(null)
      return
    }

    const element = document.querySelector(step.target)
    if (!element) {
      setTooltipPosition({
        top: window.innerHeight / 2 - 100,
        left: window.innerWidth / 2 - 175,
        arrowPosition: 'top',
      })
      setTargetRect(null)
      return
    }

    const rect = element.getBoundingClientRect()
    setTargetRect(rect)

    const tooltipWidth = 350
    const tooltipHeight = 200
    const padding = 16
    const arrowOffset = 12

    let top = 0
    let left = 0
    let arrowPosition: 'top' | 'bottom' | 'left' | 'right' = 'top'

    const placement = step.placement || 'bottom'

    switch (placement) {
      case 'bottom':
        top = rect.bottom + arrowOffset
        left = rect.left + rect.width / 2 - tooltipWidth / 2
        arrowPosition = 'top'
        break
      case 'top':
        top = rect.top - tooltipHeight - arrowOffset
        left = rect.left + rect.width / 2 - tooltipWidth / 2
        arrowPosition = 'bottom'
        break
      case 'right':
        top = rect.top + rect.height / 2 - tooltipHeight / 2
        left = rect.right + arrowOffset
        arrowPosition = 'left'
        break
      case 'left':
        top = rect.top + rect.height / 2 - tooltipHeight / 2
        left = rect.left - tooltipWidth - arrowOffset
        arrowPosition = 'right'
        break
      case 'center':
        top = window.innerHeight / 2 - tooltipHeight / 2
        left = window.innerWidth / 2 - tooltipWidth / 2
        arrowPosition = 'top'
        break
    }

    if (left < padding) left = padding
    if (left + tooltipWidth > window.innerWidth - padding) {
      left = window.innerWidth - tooltipWidth - padding
    }
    if (top < padding) top = padding
    if (top + tooltipHeight > window.innerHeight - padding) {
      top = window.innerHeight - tooltipHeight - padding
    }

    setTooltipPosition({ top, left, arrowPosition })
  }, [step])

  useEffect(() => {
    if (isTourActive && step?.route && location.pathname !== step.route) {
      navigate(step.route)
    }
  }, [isTourActive, step, location.pathname, navigate])

  useEffect(() => {
    if (!isTourActive) return

    // First scroll the element into view
    scrollAndPosition()

    // Then calculate position after scroll animation settles
    const timer = setTimeout(() => {
      calculatePosition()
    }, 450)

    const handleResize = () => calculatePosition()
    window.addEventListener('resize', handleResize)

    return () => {
      clearTimeout(timer)
      window.removeEventListener('resize', handleResize)
    }
  }, [isTourActive, currentStep, scrollAndPosition, calculatePosition, location.pathname])

  useEffect(() => {
    if (!isTourActive) return

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        skipTour()
      } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
        nextStep()
      } else if (e.key === 'ArrowLeft') {
        prevStep()
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isTourActive, nextStep, prevStep, skipTour])

  if (!isTourActive || !tooltipPosition) return null

  return (
    <>
      <div className="fixed inset-0 z-[9998] bg-black/50 transition-opacity" />

      {targetRect && (
        <div
          className="fixed z-[9999] rounded-lg ring-4 ring-primary ring-offset-2 ring-offset-background transition-all duration-300 pointer-events-none"
          style={{
            top: targetRect.top - 4,
            left: targetRect.left - 4,
            width: targetRect.width + 8,
            height: targetRect.height + 8,
            boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.5)',
          }}
        />
      )}

      <div
        className={cn(
          "fixed z-[10000] w-[350px] rounded-xl bg-background border shadow-2xl p-5 transition-all duration-300",
          "animate-in fade-in-0 zoom-in-95"
        )}
        style={{
          top: tooltipPosition.top,
          left: tooltipPosition.left,
        }}
      >
        <button
          onClick={skipTour}
          className="absolute top-3 right-3 p-1 rounded-md hover:bg-muted transition-colors"
          aria-label="Close tour"
        >
          <X className="h-4 w-4 text-muted-foreground" />
        </button>

        <div className="flex items-center gap-1.5 mb-3">
          {tourSteps.map((_, index) => (
            <div
              key={index}
              className={cn(
                "h-1.5 rounded-full transition-all",
                index === currentStep 
                  ? "w-6 bg-primary" 
                  : index < currentStep 
                    ? "w-1.5 bg-primary/50" 
                    : "w-1.5 bg-muted"
              )}
            />
          ))}
          <span className="ml-auto text-xs text-muted-foreground">
            {currentStep + 1} of {tourSteps.length}
          </span>
        </div>

        <h3 className="text-lg font-semibold mb-2">{step.title}</h3>
        <p className="text-sm text-muted-foreground leading-relaxed mb-4">
          {step.description}
        </p>

        <div className="flex items-center justify-between gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={skipTour}
            className="text-muted-foreground"
          >
            <SkipForward className="h-4 w-4 mr-1" />
            Skip
          </Button>

          <div className="flex items-center gap-2">
            {!isFirstStep && (
              <Button
                variant="outline"
                size="sm"
                onClick={prevStep}
              >
                <ChevronLeft className="h-4 w-4 mr-1" />
                Back
              </Button>
            )}
            <Button
              size="sm"
              onClick={nextStep}
            >
              {isLastStep ? (
                "Finish"
              ) : (
                <>
                  Next
                  <ChevronRight className="h-4 w-4 ml-1" />
                </>
              )}
            </Button>
          </div>
        </div>
      </div>
    </>
  )
}
