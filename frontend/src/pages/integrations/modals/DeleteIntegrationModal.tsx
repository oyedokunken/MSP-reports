import { useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Trash2, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'
import { SuccessDialog } from '@/components/modals/SuccessDialog'
import type { Integration } from '../types/integration'

interface DeleteIntegrationModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
  onSuccess?: () => void
}

export function DeleteIntegrationModal({
  open,
  onOpenChange,
  integration,
  onSuccess
}: DeleteIntegrationModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)



  const handleDelete = async () => {
    if (!integration) return

    setError('')
    setIsSubmitting(true)

    try {
      await api.delete(`/integrations/${integration.id}`)

      setSuccess(true)

      setTimeout(() => {
        setSuccess(false)
        onOpenChange(false)
        onSuccess?.()
      }, 1500)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage =
        error.response?.data?.message ||
        error.message ||
        'Failed to delete integration'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (isSubmitting) return
    setError('')
    setSuccess(false)
    onOpenChange(false)
  }

  if (success) {
    return (
      <SuccessDialog
        open={open}
        onOpenChange={handleClose}
        title="Integration Deleted!"
        description="The integration has been deleted successfully."
      />
    )
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-destructive" />
            <DialogTitle>Delete Integration</DialogTitle>
          </div>
          <DialogDescription>
            Are you sure you want to delete this integration?
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Integration Info */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-sm font-medium mb-1">Integration:</p>
            <p className="font-semibold">{integration.name}</p>
            <p className="text-sm text-muted-foreground capitalize">
              {integration.type}
            </p>
          </div>

          {/* Warning */}
          <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
            <p className="text-sm text-destructive font-medium mb-2">
              ⚠️ Warning: This action cannot be undone!
            </p>
            <ul className="text-xs text-destructive/80 space-y-1 list-disc list-inside">
              <li>All customer mappings will be removed</li>
              <li>Sync history will be deleted</li>
              <li>Historical data will be retained but unlinked</li>
            </ul>
          </div>

          {/* Error Message */}
          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={handleDelete}
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <>
                <Spinner className="mr-2 h-4 w-4" />
                Deleting...
              </>
            ) : (
              <>
                <Trash2 className="mr-2 h-4 w-4" />
                Delete Integration
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
