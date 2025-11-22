import { useState } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface RevokeApiKeyModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  apiKey: any
  onSuccess: () => void
}

export function RevokeApiKeyModal({ open, onOpenChange, apiKey, onSuccess }: RevokeApiKeyModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleRevoke = async () => {
    setIsSubmitting(true)

    try {
      await api.delete(`/api-keys/${apiKey.api_key_id}`)
      onSuccess()
      onOpenChange(false)
    } catch (error: any) {
      console.error('Failed to revoke API key:', error)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-5 w-5" />
            Revoke API Key
          </DialogTitle>
          <DialogDescription>
            This action cannot be undone. The API key will be immediately invalidated.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="p-4 bg-muted rounded-lg">
            <p className="text-sm font-medium text-foreground mb-1">
              {apiKey.display_name}
            </p>
            <p className="text-sm text-muted-foreground">
              Created {new Date(apiKey.created_at).toLocaleDateString()}
            </p>
          </div>

          <p className="text-sm text-muted-foreground">
            Any applications or scripts using this API key will immediately lose access.
            Make sure to update them with a new key if needed.
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={handleRevoke} disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <Spinner className="mr-2" />
                Revoking...
              </>
            ) : (
              'Revoke Key'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
