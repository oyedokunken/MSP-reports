import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { ApiKeyModal } from '@/components/modals/ApiKeyModal'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Shield } from 'lucide-react'
import { useSettingsStore } from '@/stores/useSettingsStore'

export default function Security() {
  const toast = useToast()
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false)
  const [apiKeyModalOpen, setApiKeyModalOpen] = useState(false)
  const [newApiKey, setNewApiKey] = useState('')
  
  const apiKeyMasked = useSettingsStore(state => state.apiKeyMasked)
  const lastGenerated = useSettingsStore(state => state.lastGenerated)
  const setLastGenerated = useSettingsStore(state => state.setLastGenerated)

  const handleRegenerateKey = () => {
    const random = Array.from(crypto.getRandomValues(new Uint8Array(32)))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
    setLastGenerated(random)
    setNewApiKey(random)
    setApiKeyModalOpen(true)
  }

  return (
    <FloatingLayout>
      <div className="space-y-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
              <Shield className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-foreground">Security</h1>
              <p className="text-sm text-muted-foreground">
                Manage your account security settings
              </p>
            </div>
          </div>
        </div>

        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Password</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Change your password to keep your account secure
          </p>

          <div className="mt-4">
            <Button
              onClick={() => setChangePasswordModalOpen(true)}
              variant="outline"
            >
              Change Password
            </Button>
          </div>
        </section>

        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">API Access</h2>
          <p className="mt-1 text-sm text-muted-foreground">Manage API keys for external integrations</p>

          <div className="mt-4">
            <label className="block text-sm font-medium text-foreground">API Key</label>
            <input
              type="password"
              readOnly
              value={apiKeyMasked}
              className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
            />
          </div>

          <div className="mt-4 flex items-center gap-3">
            <Button
              onClick={handleRegenerateKey}
              variant="outline"
            >
              Regenerate API Key
            </Button>
            {lastGenerated && (
              <span className="text-xs text-muted-foreground">A new key was generated this session.</span>
            )}
          </div>
        </section>
      </div>

      <ChangePasswordModal
        open={changePasswordModalOpen}
        onOpenChange={setChangePasswordModalOpen}
        onSuccess={() => {
          toast.success('Password Changed', 'Your password has been updated successfully')
        }}
      />

      <ApiKeyModal
        open={apiKeyModalOpen}
        onOpenChange={(open) => {
          setApiKeyModalOpen(open)
          if (!open) {
            toast.success('API Key Saved', 'Make sure you have stored your API key securely')
          }
        }}
        apiKey={newApiKey}
      />
    </FloatingLayout>
  )
}
