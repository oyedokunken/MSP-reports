import { useState } from 'react'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { ApiKeyModal } from '@/components/modals/ApiKeyModal'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Shield, Eye, EyeOff } from 'lucide-react'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { api } from '@/lib/api'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'

export default function Security() {
  const toast = useToast()
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false)
  const [apiKeyModalOpen, setApiKeyModalOpen] = useState(false)
  const [newApiKey, setNewApiKey] = useState('')
  
  const apiKeyMasked = useSettingsStore(state => state.apiKeyMasked)
  const lastGenerated = useSettingsStore(state => state.lastGenerated)
  const setLastGenerated = useSettingsStore(state => state.setLastGenerated)

  const [apiKeyVisible, setApiKeyVisible] = useState(false)
  const [revealedKey, setRevealedKey] = useState('')
  const [passwordPromptOpen, setPasswordPromptOpen] = useState(false)
  const [confirmPassword, setConfirmPassword] = useState('')
  const [verifying, setVerifying] = useState(false)

  const handleRegenerateKey = () => {
    const random = Array.from(crypto.getRandomValues(new Uint8Array(32)))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
    setLastGenerated(random)
    setNewApiKey(random)
    setApiKeyModalOpen(true)
  }

  const handleToggleVisibility = () => {
    if (apiKeyVisible) {
      setApiKeyVisible(false)
      setRevealedKey('')
    } else {
      setPasswordPromptOpen(true)
      setConfirmPassword('')
    }
  }

  const handleVerifyPassword = async () => {
    if (!confirmPassword.trim()) {
      toast.error('Password Required', 'Please enter your password')
      return
    }
    setVerifying(true)
    try {
      const response = await api.post<{ status: string; api_key?: string }>('/auth/verify-password', {
        password: confirmPassword,
      })
      if (response.status === 'success') {
        setRevealedKey(response.api_key || apiKeyMasked)
        setApiKeyVisible(true)
        setPasswordPromptOpen(false)
        setConfirmPassword('')
        // Auto-hide after 30 seconds
        setTimeout(() => {
          setApiKeyVisible(false)
          setRevealedKey('')
        }, 30000)
      } else {
        toast.error('Verification Failed', 'Incorrect password')
      }
    } catch {
      toast.error('Verification Failed', 'Incorrect password or server error')
    } finally {
      setVerifying(false)
    }
  }

  return (
    <>
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
            <div className="relative mt-1">
              <input
                type={apiKeyVisible ? 'text' : 'password'}
                readOnly
                value={apiKeyVisible ? revealedKey : apiKeyMasked}
                className="w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm text-foreground font-mono"
              />
              <button
                type="button"
                onClick={handleToggleVisibility}
                className="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground transition-colors"
                title={apiKeyVisible ? 'Hide API Key' : 'Reveal API Key'}
              >
                {apiKeyVisible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            {apiKeyVisible && (
              <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">Key visible for 30 seconds</p>
            )}
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

      <Dialog open={passwordPromptOpen} onOpenChange={setPasswordPromptOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Verify Your Identity</DialogTitle>
            <DialogDescription>
              Enter your password to reveal the API key
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <Input
              type="password"
              placeholder="Enter your password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleVerifyPassword()}
              autoFocus
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPasswordPromptOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleVerifyPassword} disabled={verifying}>
              {verifying ? 'Verifying...' : 'Verify & Reveal'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
