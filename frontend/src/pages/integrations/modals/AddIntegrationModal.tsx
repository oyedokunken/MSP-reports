import { useState, useEffect } from 'react'
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
import { DynamicCredentialForm } from '@/components/integrations/DynamicCredentialForm'
import { useIntegrationConfig } from '@/hooks/useIntegrationConfig'

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

  const [formData, setFormData] = useState<{
    integration_type: string
    display_name: string
    status: string
    credentials: Record<string, unknown>
    configuration: Record<string, unknown>
  }>({
    integration_type: '',
    display_name: '',
    status: 'disconnected', // Start as disconnected until credentials are verified
    credentials: {},
    configuration: {},
  })

  // Fetch integration config when type changes
  const { config, loading: loadingConfig } = useIntegrationConfig(formData.integration_type || null)

  // Initialize default credential values when config loads
  useEffect(() => {
    if (config && config.credential_fields) {
      const defaultCredentials: Record<string, string> = {}
      config.credential_fields.forEach((field: { name: string; default?: string }) => {
        if (field.default && !formData.credentials[field.name]) {
          defaultCredentials[field.name] = field.default
        }
      })

      if (Object.keys(defaultCredentials).length > 0) {
        setFormData(prev => ({
          ...prev,
          credentials: { ...prev.credentials, ...defaultCredentials }
        }))
      }
    }
  }, [config, formData.credentials])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setIsSubmitting(true)

    console.log('=== SUBMITTING INTEGRATION ===')
    console.log('Form Data:', JSON.stringify(formData, null, 2))
    console.log('Credentials:', formData.credentials)

    try {
      const response = await api.post('/integrations', formData)

      if (response.data) {
        // Refresh integrations silently
        await fetchIntegrations(true, true)

        setSuccess(true)
        setTimeout(() => {
          setSuccess(false)
          setError('')
          setFormData({
            integration_type: '',
            display_name: '',
            status: 'disconnected',
            credentials: {},
            configuration: {},
          })
          onOpenChange(false)
          onSuccess?.()
        }, 2000)
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to add integration'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }))
  }

  const handleCredentialChange = (field: string, value: string) => {
    setFormData(prev => ({
      ...prev,
      credentials: { ...prev.credentials, [field]: value }
    }))
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setError('')
      setSuccess(false)
      setFormData({
        integration_type: '',
        display_name: '',
        status: 'disconnected',
        credentials: {},
        configuration: {},
      })
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

          {/* Credential Fields - Dynamic based on integration type */}
          {loadingConfig && (
            <div className="flex items-center justify-center py-4">
              <Spinner className="h-6 w-6" />
              <span className="ml-2 text-sm text-muted-foreground">Loading configuration...</span>
            </div>
          )}

          {config && config.credential_fields && config.credential_fields.length > 0 && (
            <div className="space-y-4 pt-4 border-t">
              <div>
                <h4 className="text-sm font-semibold mb-1">Credentials</h4>
                <p className="text-xs text-muted-foreground">{config.description}</p>
              </div>

              <DynamicCredentialForm
                fields={config.credential_fields}
                values={formData.credentials as Record<string, string>}
                onChange={handleCredentialChange}
              />
            </div>
          )}

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
