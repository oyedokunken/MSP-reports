import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Check, Building2, Palette, Users, Settings } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'

/**
 * Multi-Tenant Onboarding Flow
 * Guides new tenants through setup process
 */

interface OnboardingStep {
  id: string
  title: string
  description: string
  icon: any
  completed: boolean
}

export default function TenantOnboarding() {
  const navigate = useNavigate()
  const toast = useToast()
  const [currentStep, setCurrentStep] = useState(0)
  const [formData, setFormData] = useState({
    companyName: '',
    subdomain: '',
    industry: '',
    primaryColor: '#1a56db',
    secondaryColor: '#7c3aed',
    logo: null as File | null,
    adminName: '',
    adminEmail: '',
  })

  const steps: OnboardingStep[] = [
    {
      id: 'company',
      title: 'Company Information',
      description: 'Tell us about your organization',
      icon: Building2,
      completed: false,
    },
    {
      id: 'branding',
      title: 'Branding',
      description: 'Customize your platform appearance',
      icon: Palette,
      completed: false,
    },
    {
      id: 'users',
      title: 'Team Setup',
      description: 'Invite your team members',
      icon: Users,
      completed: false,
    },
    {
      id: 'integrations',
      title: 'Integrations',
      description: 'Connect your systems',
      icon: Settings,
      completed: false,
    },
  ]

  const handleNext = () => {
    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1)
    } else {
      // Complete onboarding
      handleComplete()
    }
  }

  const handleBack = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1)
    }
  }

  const handleComplete = async () => {
    try {
      // TODO: Submit onboarding data to API
      console.log('Onboarding completed:', formData)
      
      toast.success('Welcome!', 'Your account has been set up successfully')
      
      // Redirect to dashboard
      setTimeout(() => navigate('/dashboard'), 1500)
    } catch (error) {
      toast.error('Setup Failed', 'There was an error setting up your account. Please try again.')
    }
  }

  const renderStepContent = () => {
    switch (currentStep) {
      case 0:
        return (
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Company Name
              </label>
              <input
                type="text"
                value={formData.companyName}
                onChange={(e) => setFormData({ ...formData, companyName: e.target.value })}
                className="w-full rounded-md border border-input bg-background px-4 py-2 text-foreground"
                placeholder="Acme Corporation"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Subdomain
              </label>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={formData.subdomain}
                  onChange={(e) => setFormData({ ...formData, subdomain: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '') })}
                  className="flex-1 rounded-md border border-input bg-background px-4 py-2 text-foreground"
                  placeholder="acme"
                />
                <span className="text-muted-foreground">.reportingplatform.com</span>
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                This will be your unique URL
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Industry
              </label>
              <select
                value={formData.industry}
                onChange={(e) => setFormData({ ...formData, industry: e.target.value })}
                className="w-full rounded-md border border-input bg-background px-4 py-2 text-foreground"
              >
                <option value="">Select industry</option>
                <option value="msp">Managed Service Provider</option>
                <option value="telco">Telecommunications</option>
                <option value="it">IT Services</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>
        )

      case 1:
        return (
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Company Logo
              </label>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => setFormData({ ...formData, logo: e.target.files?.[0] || null })}
                className="w-full rounded-md border border-input bg-background px-4 py-2 text-foreground"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-2">
                  Primary Color
                </label>
                <input
                  type="color"
                  value={formData.primaryColor}
                  onChange={(e) => setFormData({ ...formData, primaryColor: e.target.value })}
                  className="w-full h-12 rounded-md border border-input"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-foreground mb-2">
                  Secondary Color
                </label>
                <input
                  type="color"
                  value={formData.secondaryColor}
                  onChange={(e) => setFormData({ ...formData, secondaryColor: e.target.value })}
                  className="w-full h-12 rounded-md border border-input"
                />
              </div>
            </div>
          </div>
        )

      case 2:
        return (
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Admin Name
              </label>
              <input
                type="text"
                value={formData.adminName}
                onChange={(e) => setFormData({ ...formData, adminName: e.target.value })}
                className="w-full rounded-md border border-input bg-background px-4 py-2 text-foreground"
                placeholder="John Doe"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">
                Admin Email
              </label>
              <input
                type="email"
                value={formData.adminEmail}
                onChange={(e) => setFormData({ ...formData, adminEmail: e.target.value })}
                className="w-full rounded-md border border-input bg-background px-4 py-2 text-foreground"
                placeholder="john@acme.com"
              />
            </div>
            <p className="text-sm text-muted-foreground">
              You can invite more team members later from the settings page.
            </p>
          </div>
        )

      case 3:
        return (
          <div className="space-y-6">
            <p className="text-foreground">
              You can connect integrations after completing onboarding from the Integrations page.
            </p>
            <div className="rounded-lg border border-border bg-muted/50 p-6">
              <h3 className="font-semibold text-foreground mb-2">Available Integrations:</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Autotask PSA</li>
                <li>• Datto RMM</li>
                <li>• ConnectWise Manage</li>
                <li>• Custom integrations</li>
              </ul>
            </div>
          </div>
        )

      default:
        return null
    }
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-6">
      <div className="w-full max-w-4xl">
        {/* Progress Steps */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            {steps.map((step, index) => {
              const Icon = step.icon
              const isActive = index === currentStep
              const isCompleted = index < currentStep

              return (
                <div key={step.id} className="flex-1 flex items-center">
                  <div className="flex flex-col items-center flex-1">
                    <div
                      className={`w-12 h-12 rounded-full flex items-center justify-center border-2 transition-colors ${
                        isActive
                          ? 'border-primary bg-primary text-primary-foreground'
                          : isCompleted
                          ? 'border-primary bg-primary text-primary-foreground'
                          : 'border-border bg-background text-muted-foreground'
                      }`}
                    >
                      {isCompleted ? <Check className="h-6 w-6" /> : <Icon className="h-6 w-6" />}
                    </div>
                    <p className={`mt-2 text-sm font-medium ${isActive ? 'text-foreground' : 'text-muted-foreground'}`}>
                      {step.title}
                    </p>
                  </div>
                  {index < steps.length - 1 && (
                    <div className={`flex-1 h-0.5 ${isCompleted ? 'bg-primary' : 'bg-border'}`} />
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Step Content */}
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[hsl(var(--auth-card))] p-8 shadow-sm">
          <h2 className="text-2xl font-bold text-foreground mb-2">{steps[currentStep].title}</h2>
          <p className="text-muted-foreground mb-6">{steps[currentStep].description}</p>

          {renderStepContent()}

          {/* Navigation Buttons */}
          <div className="flex items-center justify-between mt-8 pt-6 border-t border-border">
            <button
              onClick={handleBack}
              disabled={currentStep === 0}
              className="px-4 py-2 text-sm font-medium text-foreground hover:bg-muted rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Back
            </button>
            <button
              onClick={handleNext}
              className="px-6 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
            >
              {currentStep === steps.length - 1 ? 'Complete Setup' : 'Continue'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
