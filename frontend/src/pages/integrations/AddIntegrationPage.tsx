import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { Cable, ArrowLeft, Info, CheckCircle2, ClipboardPaste, X } from 'lucide-react'
import { getIntegrationIconConfig } from './utils/integrationIcons'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
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
  const queryClient = useQueryClient()
  const { data } = useIntegrationsQuery()
  const availableTypes = data?.availableTypes || []
  const existingIntegrations = data?.integrations || []

  // Create a set of added types, but exclude types that allow multiples (like SharePoint)
  const addedTypes = new Set(
    existingIntegrations
      .filter(i => i.type.toLowerCase() !== 'sharepoint')
      .map(i => i.type.toLowerCase())
  )

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
    if (config) {
      const updates: { credentials?: Record<string, unknown>; configuration?: Record<string, unknown> } = {};
      let hasUpdates = false;

      if (config.credential_fields) {
        const defaultCredentials: Record<string, string> = {}
        config.credential_fields.forEach((field: { name: string; default?: string }) => {
          if (field.default && !formData.credentials[field.name]) {
            defaultCredentials[field.name] = field.default
          }
        })

        if (Object.keys(defaultCredentials).length > 0) {
          updates.credentials = { ...formData.credentials, ...defaultCredentials };
          hasUpdates = true;
        }
      }

      if (config.configuration_fields) {
        const defaultConfig: Record<string, string> = {}
        config.configuration_fields.forEach((field: { name: string; default?: string | number | boolean }) => {
          if (field.default !== undefined && !formData.configuration[field.name]) {
            defaultConfig[field.name] = String(field.default)
          }
        })

        if (Object.keys(defaultConfig).length > 0) {
          updates.configuration = { ...formData.configuration, ...defaultConfig };
          hasUpdates = true;
        }
      }

      if (hasUpdates) {
        setFormData(prev => ({
          ...prev,
          ...updates
        }))
      }
    }
  }, [config, formData.credentials, formData.configuration])

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

      // Invalidate and refetch the integrations list before navigating
      await queryClient.invalidateQueries({ queryKey: ['integrations', 'list'] })

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

  const handleConfigChange = (field: string, value: string) => {
    setFormData(prev => ({
      ...prev,
      configuration: { ...prev.configuration, [field]: value }
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
    <div className="space-y-6 w-full">
      {/* Header */}
      <div className="flex items-center justify-between">
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
            <h1 className="text-3xl font-bold flex items-center gap-2">
              <Cable className="h-8 w-8 text-primary" />
              Add Integration
            </h1>
            <p className="text-muted-foreground">Connect a new integration to your platform</p>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        {/* Left Column: Selection */}
        <div className="space-y-4">
          <Card className="sticky top-6">
            <CardHeader className="pb-2">
              <CardTitle className="text-lg">Integrations</CardTitle>
              <CardDescription className="text-xs">Select provider</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 gap-3">
                {availableTypes.map((type) => {
                  const typeLower = type.toLowerCase();
                  const isAdded = addedTypes.has(typeLower);
                  const iconConfig = getIntegrationIconConfig(type);
                  return (
                    <button
                      key={type}
                      type="button"
                      disabled={isAdded}
                      onClick={() => handleSelectType(type)}
                      className={cn(
                        "relative flex flex-col items-center justify-center p-4 rounded-xl border text-center transition-all min-h-[100px]",
                        formData.integration_type === type
                          ? "border-primary bg-primary/5 ring-2 ring-primary shadow-md"
                          : isAdded
                            ? "bg-muted/10 border-dashed opacity-40 cursor-not-allowed grayscale"
                            : "border-border hover:bg-muted/50 hover:border-primary/50 hover:shadow-sm group"
                      )}
                    >
                      <div className={cn(
                        "rounded-lg p-2 mb-2 transition-all",
                        iconConfig.bgColor,
                        iconConfig.color,
                        !isAdded && "group-hover:scale-110"
                      )}>
                        {iconConfig.icon}
                      </div>
                      <div className={cn(
                        "font-semibold text-xs uppercase tracking-wide truncate w-full",
                        !isAdded && "group-hover:text-primary transition-colors"
                      )}>
                        {type}
                      </div>

                      {isAdded ? (
                        <div className="text-[8px] font-bold text-muted-foreground/60 leading-none mt-1 bg-muted/50 px-2 py-0.5 rounded">
                          ADDED
                        </div>
                      ) : formData.integration_type === type && (
                        <div className="absolute -top-2 -right-2 h-5 w-5 bg-primary rounded-full flex items-center justify-center shadow-sm">
                          <CheckCircle2 className="h-3 w-3 text-white" />
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
              {availableTypes.length === 0 && (
                <div className="text-center py-8 text-muted-foreground">
                  <Spinner className="h-6 w-6 mx-auto mb-2" />
                  Loading available integrations...
                </div>
              )}
              <div className="mt-4 p-2 bg-muted/20 rounded border border-dashed text-center">
                <p className="text-[9px] text-muted-foreground font-semibold uppercase tracking-widest">
                  Note: Active integrations are disabled
                </p>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Right Column: Info & Configuration */}
        <div className="space-y-6">
          {!formData.integration_type ? (
            <Card className="h-[500px] flex flex-col items-center justify-center border-dashed text-center p-8 bg-muted/5">
              <div className="h-20 w-20 bg-muted rounded-full flex items-center justify-center mb-6">
                <Cable className="h-10 w-10 text-muted-foreground opacity-40" />
              </div>
              <h3 className="text-xl font-bold mb-2">No Integration Selected</h3>
              <p className="text-muted-foreground max-w-sm">
                Please select an integration from the list on the left to begin the configuration process.
              </p>
            </Card>
          ) : (
            <>
              {/* Integration Description Card */}
              {config && config.description && (
                <Card className="border-primary/20 bg-primary/5 shadow-none">
                  <CardContent className="pt-0">
                    <div className="flex gap-4">
                      <div className="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <Info className="h-6 w-6 text-primary" />
                      </div>
                      <div className="space-y-1">
                        <h3 className="font-bold text-lg text-primary">{config.name}</h3>
                        <p className="text-muted-foreground leading-relaxed">{config.description}</p>
                        {((config.data_types?.length ?? 0) > 0 || config.supports_historical) && (
                          <div className="flex flex-wrap gap-4 mt-4 pt-4 border-t border-primary/10">
                            {(config.data_types?.length ?? 0) > 0 && (
                              <div className="space-y-1">
                                <p className="text-[10px] uppercase font-bold text-primary/60 tracking-wider">Capabilities</p>
                                <p className="text-xs font-medium">{config.data_types?.join(', ')}</p>
                              </div>
                            )}
                            {config.supports_historical && (
                              <div className="space-y-1">
                                <p className="text-[10px] uppercase font-bold text-primary/60 tracking-wider">Historical Sync</p>
                                <p className="text-xs font-medium">Up to {config.historical_range_days} days</p>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Configuration Card */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg">Configuration</CardTitle>
                  <CardDescription>Enter the connection details for this integration</CardDescription>
                </CardHeader>
                <CardContent className="space-y-8">
                  {/* Display Name Section */}
                  <div className="space-y-3">
                    <Label htmlFor="display_name" className="text-sm font-semibold">Display Name *</Label>
                    <div className="flex gap-2">
                      <div className="flex-1">
                        <Input
                          id="display_name"
                          className={cn(
                            "h-11",
                            existingIntegrations.some(i => i.name.toLowerCase() === formData.display_name.toLowerCase()) && "border-warning ring-warning/20"
                          )}
                          placeholder={`e.g., Production ${formData.integration_type}`}
                          value={formData.display_name}
                          onChange={(e) => handleInputChange('display_name', e.target.value)}
                          required
                          disabled={isSubmitting}
                        />
                      </div>
                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              className="h-11 w-11 shrink-0"
                              onClick={async () => {
                                try {
                                  const text = await navigator.clipboard.readText()
                                  handleInputChange('display_name', text)
                                } catch (err) {
                                  console.error('Failed to read clipboard:', err)
                                }
                              }}
                            >
                              <ClipboardPaste className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>
                            <p>Paste from clipboard</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </div>
                    {existingIntegrations.some(i => i.name.toLowerCase() === formData.display_name.toLowerCase()) && (
                      <p className="text-[10px] text-warning font-semibold flex items-center gap-1 mt-1">
                        <Info className="h-3 w-3" />
                        This name is already in use by another integration.
                      </p>
                    )}
                    <p className="text-[11px] text-muted-foreground italic">
                      A friendly name to help you identify this connection throughout the platform.
                    </p>
                  </div>

                  {loadingConfig ? (
                    <div className="flex flex-col items-center justify-center py-12 bg-muted/20 rounded-lg border border-dashed">
                      <Spinner className="h-8 w-8 mb-4" />
                      <span className="text-sm font-medium text-muted-foreground">Loading security fields...</span>
                    </div>
                  ) : (
                    <>
                      {config && config.credential_fields && config.credential_fields.filter((field: { hidden?: boolean }) => !field.hidden).length > 0 && (
                        <div className="space-y-6 pt-6 border-t border-dashed">
                          <div>
                            <h4 className="text-sm font-bold flex items-center gap-2">
                              <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                              API Credentials
                            </h4>
                            <p className="text-xs text-muted-foreground mt-1">
                              Securely connect to the {formData.integration_type} API provider.
                            </p>
                          </div>

                          <div className="grid gap-4">
                            <DynamicCredentialForm
                              fields={config.credential_fields.filter((field: { hidden?: boolean }) => !field.hidden)}
                              values={formData.credentials as Record<string, string>}
                              onChange={handleCredentialChange}
                            />
                          </div>
                        </div>
                      )}

                      {config && config.configuration_fields && config.configuration_fields.filter((field: { hidden?: boolean }) => !field.hidden).length > 0 && (
                        <div className="space-y-6 pt-6 border-t border-dashed">
                          <div>
                            <h4 className="text-sm font-bold flex items-center gap-2">
                              <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                              Configuration
                            </h4>
                            <p className="text-xs text-muted-foreground mt-1">
                              Configure integration settings.
                            </p>
                          </div>

                          <div className="grid gap-4">
                            <DynamicCredentialForm
                              fields={config.configuration_fields.filter((field: { hidden?: boolean }) => !field.hidden)}
                              values={formData.configuration as Record<string, string>}
                              onChange={handleConfigChange}
                            />
                          </div>
                        </div>
                      )}
                    </>
                  )}

                  {/* Error Message */}
                  {error && (
                    <div className="p-4 bg-destructive/5 border border-destructive/20 rounded-lg flex items-start gap-3">
                      <div className="h-5 w-5 rounded-full bg-destructive/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <X className="h-3 w-3 text-destructive" />
                      </div>
                      <p className="text-sm text-destructive font-medium leading-tight">{error}</p>
                    </div>
                  )}

                  {/* Form Actions */}
                  <div className="flex items-center justify-end gap-3 pt-6 border-t">
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => navigate('/platform/integrations')}
                      disabled={isSubmitting}
                    >
                      Cancel
                    </Button>
                    <Button
                      type="submit"
                      className="min-w-[140px]"
                      disabled={isSubmitting || !formData.integration_type || !formData.display_name}
                    >
                      {isSubmitting ? (
                        <>
                          <Spinner className="mr-2 h-4 w-4" />
                          Connecting...
                        </>
                      ) : (
                        'Add Integration'
                      )}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </form>
    </div>
  )
}
