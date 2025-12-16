import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface OnboardingStep {
  id: string
  title: string
  description: string
  target?: string // CSS selector for the element to highlight
  placement?: 'top' | 'bottom' | 'left' | 'right' | 'center'
  route?: string // Route to navigate to for this step
}

interface OnboardingState {
  hasSeenWelcome: boolean
  hasCompletedTour: boolean
  currentStep: number
  isTourActive: boolean
  showWelcomeModal: boolean
  
  // Actions
  setHasSeenWelcome: (seen: boolean) => void
  setHasCompletedTour: (completed: boolean) => void
  startTour: () => void
  endTour: () => void
  nextStep: () => void
  prevStep: () => void
  goToStep: (step: number) => void
  skipTour: () => void
  resetOnboarding: () => void
  openWelcomeModal: () => void
  closeWelcomeModal: () => void
}

export const tourSteps: OnboardingStep[] = [
  {
    id: 'dashboard',
    title: 'Your Dashboard',
    description: 'This is your main dashboard. Here you can see an overview of your integrations, reports, and system status at a glance.',
    target: '[data-tour="dashboard-stats"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  {
    id: 'integrations',
    title: 'Integrations',
    description: 'Connect your business tools here. Add integrations like Autotask, Datto, IT Glue and more to pull data into the platform.',
    target: '[data-tour="nav-integrations"]',
    placement: 'right',
    route: '/platform/integrations',
  },
  {
    id: 'customers',
    title: 'Customer Management',
    description: 'View and manage your customers. Map customers from different integrations to create a unified customer list.',
    target: '[data-tour="nav-customers"]',
    placement: 'right',
    route: '/platform/customers/master',
  },
  {
    id: 'reports',
    title: 'Reports',
    description: 'Generate professional reports for your customers. View report history and download generated reports.',
    target: '[data-tour="nav-reports"]',
    placement: 'right',
    route: '/platform/reports',
  },
  {
    id: 'schedules',
    title: 'Report Schedules',
    description: 'Set up automated report generation. Schedule reports to be generated and delivered automatically.',
    target: '[data-tour="nav-schedules"]',
    placement: 'right',
    route: '/platform/schedules',
  },
  {
    id: 'help',
    title: 'Help & Guides',
    description: 'Need help? Access comprehensive documentation and guides anytime from here.',
    target: '[data-tour="nav-help"]',
    placement: 'right',
    route: '/docs',
  },
]

export const useOnboardingStore = create<OnboardingState>()(
  persist(
    (set, get) => ({
      hasSeenWelcome: false,
      hasCompletedTour: false,
      currentStep: 0,
      isTourActive: false,
      showWelcomeModal: false,

      setHasSeenWelcome: (seen) => set({ hasSeenWelcome: seen }),
      setHasCompletedTour: (completed) => set({ hasCompletedTour: completed }),
      
      startTour: () => set({ 
        isTourActive: true, 
        currentStep: 0,
        showWelcomeModal: false,
      }),
      
      endTour: () => set({ 
        isTourActive: false, 
        hasCompletedTour: true,
        currentStep: 0,
      }),
      
      nextStep: () => {
        const { currentStep } = get()
        if (currentStep < tourSteps.length - 1) {
          set({ currentStep: currentStep + 1 })
        } else {
          get().endTour()
        }
      },
      
      prevStep: () => {
        const { currentStep } = get()
        if (currentStep > 0) {
          set({ currentStep: currentStep - 1 })
        }
      },
      
      goToStep: (step) => set({ currentStep: step }),
      
      skipTour: () => set({ 
        isTourActive: false, 
        hasSeenWelcome: true,
        showWelcomeModal: false,
      }),
      
      resetOnboarding: () => set({
        hasSeenWelcome: false,
        hasCompletedTour: false,
        currentStep: 0,
        isTourActive: false,
        showWelcomeModal: false,
      }),

      openWelcomeModal: () => set({ showWelcomeModal: true }),
      closeWelcomeModal: () => set({ showWelcomeModal: false, hasSeenWelcome: true }),
    }),
    {
      name: 'onboarding-storage',
      partialize: (state) => ({
        hasSeenWelcome: state.hasSeenWelcome,
        hasCompletedTour: state.hasCompletedTour,
      }),
    }
  )
)
