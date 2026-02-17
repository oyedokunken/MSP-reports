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
  // --- Dashboard ---
  {
    id: 'dashboard',
    title: 'Your Dashboard',
    description: 'This is your command center. View system health, active integrations, recent reports, and activity trends at a glance.',
    target: '[data-tour="dashboard-stats"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  // --- Global Search ---
  {
    id: 'global-search',
    title: 'Global Search',
    description: 'Quickly find any page, feature, or setting. Press "/" on your keyboard to jump straight into search. Works from anywhere in the platform.',
    target: '[data-tour="global-search"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  // --- Clients ---
  {
    id: 'integrations',
    title: 'Integrations',
    description: 'Connect your business tools here. Add Autotask, Datto RMM, ITGlue, Sendmarc, BitDefender, ThreatLocker, CIPP, Vonahi, and more. Run syncs, preview live data, and import historical records.',
    target: '[data-tour="nav-integrations"]',
    placement: 'right',
    route: '/platform/integrations',
  },
  {
    id: 'customers',
    title: 'Customer Management',
    description: 'Manage your unified customer list. Map customers from different integrations, merge duplicates, and configure per-customer report settings and delivery destinations.',
    target: '[data-tour="nav-customers"]',
    placement: 'right',
    route: '/platform/customers/master',
  },
  // --- Reporting ---
  {
    id: 'reports',
    title: 'Reports',
    description: 'View all generated reports in table or folder view. Download PDFs, email to customers, share via secure links, or bulk-generate reports for multiple customers at once.',
    target: '[data-tour="nav-reports"]',
    placement: 'right',
    route: '/platform/reports',
  },
  {
    id: 'schedules',
    title: 'Report Schedules',
    description: 'Automate report generation with flexible scheduling. Set daily, weekly, monthly, or yearly schedules. Reports generate and deliver automatically — no manual work needed.',
    target: '[data-tour="nav-schedules"]',
    placement: 'right',
    route: '/platform/schedules',
  },
  {
    id: 'templates',
    title: 'Report Templates',
    description: 'Create and manage reusable report templates. Configure which sections to include, customise branding, and apply templates to customers in bulk.',
    target: '[data-tour="nav-templates"]',
    placement: 'right',
    route: '/platform/templates',
  },
  // --- Management ---
  {
    id: 'users',
    title: 'User Management',
    description: 'Create and manage platform users. Assign roles (Admin, Manager, Operator, Viewer), set granular permissions, and control who can access what across the system.',
    target: '[data-tour="nav-users"]',
    placement: 'right',
    route: '/admin/users',
  },
  {
    id: 'api-keys',
    title: 'API Keys',
    description: 'Generate API keys for programmatic access. Configure per-key permissions, IP whitelists, and endpoint restrictions. Perfect for automation and agent-based integrations.',
    target: '[data-tour="nav-api-keys"]',
    placement: 'right',
    route: '/admin/api-keys',
  },
  {
    id: 'licenses',
    title: 'Licenses',
    description: 'View your platform licenses, integration entitlements, and data retention limits. Track usage and ensure your subscription covers your needs.',
    target: '[data-tour="nav-licenses"]',
    placement: 'right',
    route: '/admin/licenses',
  },
  // --- System ---
  {
    id: 'monitoring',
    title: 'System Monitoring',
    description: 'Monitor system health, integration sync status, queue processing, report generation rates, and activity trends. Get real-time insights into platform performance.',
    target: '[data-tour="nav-monitoring"]',
    placement: 'right',
    route: '/admin/monitoring',
  },
  {
    id: 'status',
    title: 'System Status',
    description: 'Check real-time health for all platform services — API, database, Redis, email, scheduler, storage, and external connections. Instant visibility into any issues.',
    target: '[data-tour="nav-status"]',
    placement: 'right',
    route: '/admin/status',
  },
  {
    id: 'audit-logs',
    title: 'Audit Logs',
    description: 'Full audit trail of every action on the platform. Track logins, permission changes, report generation, integration syncs, and more. Export logs for compliance reporting.',
    target: '[data-tour="nav-audit-logs"]',
    placement: 'right',
    route: '/admin/audit-logs',
  },
  // --- Toolbar & Utilities ---
  {
    id: 'notifications',
    title: 'Notifications',
    description: 'Click the bell icon to see system alerts, sync failures, report completions, and important status updates. Stay informed about everything happening on the platform.',
    target: '[data-tour="notifications-bell"]',
    placement: 'bottom',
    route: '/platform/dashboard',
  },
  {
    id: 'chatbot',
    title: 'Your AI Assistant',
    description: 'Need help? Click the chat bubble in the bottom-right corner. Ask questions about any feature, get step-by-step guidance, and receive real-time activity insights as you work.',
    target: '[data-tour="chatbot-button"]',
    placement: 'left',
    route: '/platform/dashboard',
  },
  {
    id: 'help',
    title: 'Documentation',
    description: 'Access comprehensive guides, tutorials, and troubleshooting help. Browse the system walkthrough document, search for any topic, or explore by category.',
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
