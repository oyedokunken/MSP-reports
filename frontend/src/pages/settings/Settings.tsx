import { useEffect, useMemo, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useBranding } from '@/contexts/BrandingContext'
import type { CompanyBranding } from '@/types/branding'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { useToast } from '@/components/ui/Toast'

export default function Settings() {
  const { branding, applyBranding } = useBranding()
  const toast = useToast()

  // Company info form
  const [companyName, setCompanyName] = useState('')
  const [primaryHex, setPrimaryHex] = useState('#3b82f6')

  // Email delivery form
  const [fromEmail, setFromEmail] = useState('')
  const [fromName, setFromName] = useState('')

  // API key (frontend demo only)
  const [apiKeyMasked] = useState('•'.repeat(26))
  const [lastGenerated, setLastGenerated] = useState<string | null>(null)
  
  // Password change modal
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false)

  // Sync initial form values if branding changes
  useEffect(() => {
    if (!branding) return
    setCompanyName(branding.company_name || '')
    setPrimaryHex(branding.primary_color || '#3b82f6')
    setFromEmail(branding.email_from_address || '')
    setFromName(branding.email_from_name || '')
  }, [branding])

  const validHex = useMemo(() => /^#([0-9a-f]{6})$/i.test(primaryHex), [primaryHex])

  const handleSaveBranding = () => {
    if (!validHex || !branding) return
    const updated: CompanyBranding = {
      ...branding,
      enabled: true,
      company_name: companyName || branding.company_name,
      primary_color: primaryHex || branding.primary_color,
    }
    applyBranding(updated)
  }

  const handleSaveEmail = () => {
    if (!branding) return
    const updated: CompanyBranding = {
      ...branding,
      enabled: true,
      email_from_address: fromEmail || branding.email_from_address,
      email_from_name: fromName || branding.email_from_name,
    }
    applyBranding(updated)
  }

  const regenerateKey = () => {
    // Demo-only: generate a pseudo key and show once
    const random = Array.from(crypto.getRandomValues(new Uint8Array(32)))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
    setLastGenerated(random)
    // In a real app, call API to create key, then show once and never store plaintext
    alert('New API key generated. Copy it now; it will only be shown once.\n\n' + random)
  }

  if (!branding) {
    return (
      <FloatingLayout>
        <div className="text-muted-foreground">Loading settings...</div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-foreground">Settings</h1>
          <p className="mt-2 text-muted-foreground">Manage your company and white-label settings</p>
        </div>

        {/* Company Information */}
        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Company Information</h2>
          <p className="mt-1 text-sm text-muted-foreground">Update your company details and branding</p>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="block text-sm font-medium text-foreground">Company Name</label>
              <input
                type="text"
                placeholder="Your Company Name"
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>

            <div className="flex items-center gap-3">
              <label className="block text-sm font-medium text-foreground">Primary Brand Color</label>
            </div>
            <div className="flex items-center gap-3">
              <input
                aria-label="Primary color"
                type="color"
                value={validHex ? primaryHex : '#3b82f6'}
                onChange={(e) => setPrimaryHex(e.target.value)}
                className="h-9 w-12 rounded-md border border-border bg-background dark:bg-muted/20"
              />
              <input
                aria-label="Primary color hex"
                type="text"
                value={primaryHex}
                onChange={(e) => setPrimaryHex(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
                className="w-36 rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                placeholder="#3b82f6"
              />
            </div>
          </div>

          <div className="mt-4">
            <button
              onClick={handleSaveBranding}
              disabled={!validHex}
              className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
              Save Changes
            </button>
          </div>
        </section>

        {/* Email Delivery */}
        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Email Delivery</h2>
          <p className="mt-1 text-sm text-muted-foreground">Configure email settings for report delivery</p>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-sm font-medium text-foreground">From Email Address</label>
              <input
                type="email"
                placeholder="reports@yourcompany.com"
                value={fromEmail}
                onChange={(e) => setFromEmail(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground">From Name</label>
              <input
                type="text"
                placeholder="Your Company Reports"
                value={fromName}
                onChange={(e) => setFromName(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>

          <div className="mt-4">
            <button
              onClick={handleSaveEmail}
              className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
              Save Email Settings
            </button>
          </div>
        </section>

        {/* API Access */}
        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">API Access</h2>
          <p className="mt-1 text-sm text-muted-foreground">Manage API keys for external integrations</p>

          <div className="mt-4">
            <label className="block text-sm font-medium text-foreground">API Key</label>
            <input
              type="password"
              readOnly
              value={apiKeyMasked}
              className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground"
            />
          </div>

          <div className="mt-4 flex items-center gap-3">
            <button
              onClick={regenerateKey}
              className="rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
            >
              Regenerate API Key
            </button>
            {lastGenerated && (
              <span className="text-xs text-muted-foreground">A new key was generated this session.</span>
            )}
          </div>
        </section>

        {/* Security */}
        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Security</h2>
          <p className="mt-1 text-sm text-muted-foreground">Manage your account security settings</p>

          <div className="mt-4">
            <label className="block text-sm font-medium text-foreground mb-2">Password</label>
            <p className="text-sm text-muted-foreground mb-4">
              Change your password to keep your account secure
            </p>
            <button
              onClick={() => setChangePasswordModalOpen(true)}
              className="rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
            >
              Change Password
            </button>
          </div>
        </section>
      </div>

      {/* Change Password Modal */}
      <ChangePasswordModal
        open={changePasswordModalOpen}
        onOpenChange={setChangePasswordModalOpen}
        onSuccess={() => {
          toast.success('Password Changed', 'Your password has been updated successfully')
        }}
      />
    </FloatingLayout>
  )
}
