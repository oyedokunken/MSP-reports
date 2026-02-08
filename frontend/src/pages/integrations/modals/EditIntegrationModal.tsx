import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, Settings, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'
import { logger } from '@/lib/logger'
import { getErrorMessage } from '@/types/errors'
import type { Integration } from '../types/integration'

interface EditIntegrationModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
  onSuccess?: () => void
}

// Static cache to store integration configs and avoid multiple API calls
const configCache: Record<string, any[]> = {}

export function EditIntegrationModal({
  open,
  onOpenChange,
  integration,
  onSuccess
}: EditIntegrationModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [displayName, setDisplayName] = useState('')
  const [credentialFields, setCredentialFields] = useState<any[]>([])
  const [loadingConfig, setLoadingConfig] = useState(false)



  useEffect(() => {
    if (open && integration) {
      setDisplayName(integration.name || '')
      setError('')
      setSuccess(false)

      // Fetch credential fields to display them as read-only/masked
      const fetchConfig = async () => {
        // Use cache if available
        if (configCache[integration.type]) {
          setCredentialFields(configCache[integration.type])
          return
        }

        setLoadingConfig(true)
        try {
          const response = await api.get<{ config: { credential_fields: any[] } }>(`/integrations/config/${integration.type}`)
          if (response?.config?.credential_fields) {
            configCache[integration.type] = response.config.credential_fields
            setCredentialFields(response.config.credential_fields)
          }
        } catch (err) {
          logger.error('Failed to fetch integration credentials schema', err)
        } finally {
          setLoadingConfig(false)
        }
      }
      fetchConfig()
    } else if (!open) {
      // Don't clear fields immediately on close to avoid flicker during exit animation
      // but clear when the modal is fully closed/nullified
      setTimeout(() => {
        if (!open) setCredentialFields([])
      }, 300)
    }
  }, [open, integration])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!integration) return

    if (!displayName.trim()) {
      setError('Please enter a display name')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await api.patch(`/integrations/${integration.id}`, {
        display_name: displayName.trim(),
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        onOpenChange(false)
        onSuccess?.()
      }, 2000)
    } catch (err) {
      logger.apiError(`/integrations/${integration.id}`, err)
      setError(getErrorMessage(err, 'Failed to update integration'))
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      onOpenChange(false)
    }
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Integration Updated!</DialogTitle>
            <DialogDescription className="text-center">
              The integration has been updated successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Settings className="h-5 w-5 text-primary" />
            <DialogTitle>Edit Integration</DialogTitle>
          </div>
          <DialogDescription>
            Update settings for {integration.name}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="displayName" className="text-sm font-medium mb-2 block">
              Display Name
            </Label>
            <Input
              id="displayName"
              type="text"
              placeholder="My Integration"
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          {/* Credentials Schema Display (Read-only/Masked) */}
          {(loadingConfig || credentialFields.length > 0) && (
            <div className="space-y-4 pt-4 border-t border-border">
              <div className="flex items-center justify-between">
                <Label className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">
                  Credentials
                </Label>
                {loadingConfig && <Spinner className="h-3 w-3" />}
              </div>

              <div className="space-y-3">
                {credentialFields.map((field) => (
                  <div key={field.name}>
                    <Label htmlFor={field.name} className="text-[13px] font-medium mb-1.5 block text-foreground/80">
                      {field.label}
                    </Label>
                    <div className="relative">
                      <Input
                        id={field.name}
                        type="text"
                        value="••••••••••••••••"
                        readOnly
                        disabled
                        className="bg-muted/30 border-dashed cursor-not-allowed font-mono text-xs h-9"
                      />
                    </div>
                    {field.help && (
                      <p className="text-[11px] text-muted-foreground mt-1 leading-relaxed">
                        {field.help}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Updating...
                </>
              ) : (
                'Update Integration'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
