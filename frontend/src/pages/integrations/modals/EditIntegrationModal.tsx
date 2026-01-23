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



  useEffect(() => {
    if (open && integration) {
      setDisplayName(integration.name || '')
      setError('')
      setSuccess(false)
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
          {/* Display Name */}
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
