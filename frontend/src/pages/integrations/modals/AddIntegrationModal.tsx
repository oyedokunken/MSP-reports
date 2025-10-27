import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Cable } from 'lucide-react'
import { api } from '@/lib/api'
import { useIntegrationStore } from '@/stores/useIntegrationStore'
import { SuccessDialog } from '@/components/modals/SuccessDialog'

interface AddIntegrationModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  availableTypes: string[]
  onSuccess?: () => void
}

export function AddIntegrationModal({ open, onOpenChange, availableTypes, onSuccess }: AddIntegrationModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  
  // Get fetchIntegrations from store
  const fetchIntegrations = useIntegrationStore(state => state.fetchIntegrations)

  const [formData, setFormData] = useState({
    integration_type: '',
    display_name: '',
    status: 'active',
  })

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setIsSubmitting(true)

    try {
      const response = await api.post('/integrations', formData)

      if (response.data) {
        // Refresh integrations silently
        await fetchIntegrations(true)
        
        setSuccess(true)
        setTimeout(() => {
          setSuccess(false)
          setError('')
          setFormData({
            integration_type: '',
            display_name: '',
            status: 'active',
          })
          onOpenChange(false)
          onSuccess?.()
        }, 2000)
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to add integration'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }))
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setError('')
      setSuccess(false)
      onOpenChange(false)
    }
  }

  if (success) {
    return (
      <SuccessDialog
        open={open}
        onOpenChange={handleClose}
        title="Integration Added!"
        description="The integration has been added successfully."
      />
    )
  }
  
  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Cable className="h-5 w-5 text-primary" />
            <DialogTitle>Add Integration</DialogTitle>
          </div>
          <DialogDescription>Connect a new integration to your platform</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Integration Type */}
          <div className="space-y-2">
            <Label htmlFor="integration_type">Integration Type *</Label>
            <Select
              value={formData.integration_type}
              onValueChange={(value: string) => handleInputChange('integration_type', value)}
              disabled={isSubmitting}
            >
              <SelectTrigger id="integration_type">
                <SelectValue placeholder="Select integration type" />
              </SelectTrigger>
              <SelectContent>
                {availableTypes.map((type) => (
                  <SelectItem key={type} value={type}>
                    {type}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">Choose the type of integration to add</p>
          </div>

          {/* Display Name */}
          <div className="space-y-2">
            <Label htmlFor="display_name">Display Name *</Label>
            <Input
              id="display_name"
              type="text"
              placeholder="e.g., Production Datto RMM"
              value={formData.display_name}
              onChange={(e) => handleInputChange('display_name', e.target.value)}
              required
              disabled={isSubmitting}
            />
          </div>

          {/* Status */}
          <div className="space-y-2">
            <Label htmlFor="status">Status *</Label>
            <Select
              value={formData.status}
              onValueChange={(value: string) => handleInputChange('status', value)}
              disabled={isSubmitting}
            >
              <SelectTrigger id="status">
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="disconnected">Disconnected</SelectItem>
                <SelectItem value="error">Error</SelectItem>
                <SelectItem value="importing_historical">Importing Historical</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={handleClose} disabled={isSubmitting}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting || !formData.integration_type || !formData.display_name}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Adding...
                </>
              ) : (
                'Add Integration'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
