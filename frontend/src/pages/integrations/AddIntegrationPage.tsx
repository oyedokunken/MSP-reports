import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { Cable, ArrowLeft, Info, CheckCircle2 } from 'lucide-react'
import { api } from '@/lib/api'
import { DynamicCredentialForm } from '@/components/integrations/DynamicCredentialForm'
import { useIntegrationConfig } from '@/hooks/useIntegrationConfig'
import { useIntegrationsQuery } from './hooks/useIntegrations'
import { useToast } from '@/components/ui/Toast'
import { cn } from '@/lib/utils'
import type { Integration } from './types/integration'

export default function AddIntegrationPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { data } = useIntegrationsQuery()
  const availableTypes = data?.availableTypes || []

  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const [formData, setFormData] = useState<{
    integration_type: string
    display_name: string
    credentials: Record<string, unknown>
    configuration: Record<string, unknown>
  }>({
    integration_type: '',
    display_name: '',
    credentials: {},
    configuration: {},
  })

  const { config, loading: loadingConfig } = useIntegrationConfig(formData.integration_type || null)

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

    try {
      await api.post<{ data: Integration }>('/integrations', {
        ...formData,
        status: 'active',
      })

      toast.success('Integration Added', 'The integration has been added successfully.')
      navigate('/platform/integrations')
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

  const handleSelectType = (type: string) => {
    setFormData(prev => ({
      ...prev,
      integration_type: type,
      credentials: {},
      configuration: {},
    }))
  }

  return (
    <FloatingLayout>
      <div className="space-y-6 max-w-4xl mx-auto">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => navigate('/platform/integrations')}
            className="shrink-0"
          >
            <ArrowLeft className="h-5 w-5" />
          </Button>
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-2">
              <Cable className="h-6 w-6 text-primary" />
              Add Integration
            </h1>
            <p className="text-muted-foreground">Connect a new integration to your platform</p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Step 1: Select Integration Type */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Step 1: Select Integration Type</CardTitle>
              <CardDescription>Choose the type of integration you want to add</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                {availableTypes.map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => handleSelectType(type)}
                    className={cn(
                      "relative p-4 rounded-lg border-2 text-left transition-all hover:border-primary/50",
                      formData.integration_type === type
                        ? "border-primary bg-primary/5"
                        : "border-border hover:bg-muted/50"
                    )}
                  >
                    <div className="font-medium">{type}</div>
                    {formData.integration_type === type && (
                      <CheckCircle2 className="absolute top-2 right-2 h-4 w-4 text-primary" />
                    )}
                  </button>
                ))}
              </div>
              {availableTypes.length === 0 && (
                <div className="text-center py-8 text-muted-foreground">
                  <Spinner className="h-6 w-6 mx-auto mb-2" />
                  Loading available integrations...
                </div>
              )}
            </CardContent>
          </Card>

          {/* Integration Info */}
          {config && config.description && (
            <Card className="border-primary/20 bg-primary/5">
              <CardContent className="pt-6">
                <div className="flex gap-3">
                  <Info className="h-5 w-5 text-primary mt-0.5 flex-shrink-0" />
                  <div>
                    <h3 className="font-semibold text-primary mb-1">{config.name}</h3>
                    <p className="text-sm text-muted-foreground">{config.description}</p>
                    {config.data_types && config.data_types.length > 0 && (
                      <p className="text-sm text-muted-foreground mt-2">
                        <span className="font-medium">Data synced:</span> {config.data_types.join(', ')}
                      </p>
                    )}
                    {config.supports_historical && (
                      <p className="text-sm text-muted-foreground mt-1">
                        <span className="font-medium">Historical data:</span> Up to {config.historical_range_days} days
                      </p>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Step 2: Configuration */}
          {formData.integration_type && (
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Step 2: Configuration</CardTitle>
                <CardDescription>Enter the details for your {formData.integration_type} integration</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <Label htmlFor="display_name">Display Name *</Label>
                  <Input
                    id="display_name"
                    type="text"
                    placeholder={`e.g., Production ${formData.integration_type}`}
                    value={formData.display_name}
                    onChange={(e) => handleInputChange('display_name', e.target.value)}
                    required
                    disabled={isSubmitting}
                    className="max-w-md"
                  />
                  <p className="text-xs text-muted-foreground">
                    A friendly name to identify this integration
                  </p>
                </div>

                {loadingConfig && (
                  <div className="flex items-center justify-center py-8">
                    <Spinner className="h-6 w-6" />
                    <span className="ml-2 text-sm text-muted-foreground">Loading configuration...</span>
                  </div>
                )}

                {config && config.credential_fields && config.credential_fields.filter((field: { hidden?: boolean }) => !field.hidden).length > 0 && (
                  <div className="space-y-4 pt-4 border-t">
                    <div>
                      <h4 className="text-sm font-semibold mb-1">API Credentials</h4>
                      <p className="text-xs text-muted-foreground">
                        Enter your API credentials to connect to {formData.integration_type}
                      </p>
                    </div>

                    <div className="max-w-md">
                      <DynamicCredentialForm
                        fields={config.credential_fields.filter((field: { hidden?: boolean }) => !field.hidden)}
                        values={formData.credentials as Record<string, string>}
                        onChange={handleCredentialChange}
                      />
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Error Display */}
          {error && (
            <Card className="border-destructive/50 bg-destructive/10">
              <CardContent className="pt-6">
                <p className="text-sm text-destructive font-medium">{error}</p>
              </CardContent>
            </Card>
          )}

          {/* Actions */}
          <div className="flex items-center justify-end gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigate('/platform/integrations')}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting || !formData.integration_type || !formData.display_name}
            >
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Adding Integration...
                </>
              ) : (
                'Add Integration'
              )}
            </Button>
          </div>
        </form>
      </div>
    </FloatingLayout>
  )
}
