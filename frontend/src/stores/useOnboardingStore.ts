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
    description: 'This is your command center. View system health, active integrations, recent reports, and activity trends at a glance.',
    target: '[data-tour="dashboard-stats"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  {
    id: 'integrations',
    title: 'Integrations',
    description: 'Connect your business tools here. Add Autotask, Datto RMM, ITGlue, Sendmarc, BitDefender and more. Preview live data before generating reports.',
    target: '[data-tour="nav-integrations"]',
    placement: 'right',
    route: '/platform/integrations',
  },
  {
    id: 'customers',
    title: 'Customer Management',
    description: 'Manage your unified customer list. Map customers from different integrations, merge duplicates, and set up report configurations.',
    target: '[data-tour="nav-customers"]',
    placement: 'right',
    route: '/platform/customers/master',
  },
  {
    id: 'reports',
    title: 'Reports',
    description: 'View all generated reports. Download PDFs, email reports to customers, or share via secure links. Track delivery status.',
    target: '[data-tour="nav-reports"]',
    placement: 'right',
    route: '/platform/reports',
  },
  {
    id: 'schedules',
    title: 'Report Schedules',
    description: 'Automate report generation. Set daily, weekly, monthly, or yearly schedules. Reports generate and deliver automatically.',
    target: '[data-tour="nav-schedules"]',
    placement: 'right',
    route: '/platform/schedules',
  },
  {
    id: 'monitoring',
    title: 'System Monitoring',
    description: 'Monitor system health, integration sync status, queue processing, and view activity trends. Get alerts when issues occur.',
    target: '[data-tour="nav-monitoring"]',
    placement: 'right',
    route: '/platform/monitoring',
  },
  {
    id: 'notifications',
    title: 'Notifications',
    description: 'Click the bell icon to see system alerts, sync failures, and report status updates. Stay informed about important events.',
    target: '[data-tour="notifications-bell"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  {
    id: 'chatbot',
    title: 'Your AI Assistant',
    description: 'Need help? Click the chat bubble in the bottom-right corner. I can answer questions about any feature and guide you step-by-step.',
    target: '[data-tour="chatbot-button"]',
    placement: 'left',
    route: '/platform/dashboard',
  },
  {
    id: 'help',
    title: 'Documentation',
    description: 'Access comprehensive guides, tutorials, and troubleshooting help. Search for any topic or browse by category.',
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
