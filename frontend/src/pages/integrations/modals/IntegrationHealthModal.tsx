import { useState, useEffect, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Badge } from '@/components/ui/badge'
import { Activity, CheckCircle, XCircle, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import type { Integration } from '../types/integration'

interface HealthCheckResult {
  healthy: boolean
  status: string
  last_sync_at: string
  last_sync_status: string
  last_sync_age_minutes: number
  error_message: string | null
  checks: {
    credentials_valid: boolean
    recent_sync: boolean
    no_errors: boolean
  }
}

interface IntegrationHealthModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
}

export function IntegrationHealthModal({
  open,
  onOpenChange,
  integration
}: IntegrationHealthModalProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [healthData, setHealthData] = useState<HealthCheckResult | null>(null)
  const [error, setError] = useState('')

  const checkHealth = useCallback(async () => {
    if (!integration) return

    setIsLoading(true)
    setError('')

    try {
      const response = await api.get<{ data?: HealthCheckResult } & HealthCheckResult>(`/integrations/${integration.id}/health`)
      const data = response.data || response
      setHealthData(data as HealthCheckResult)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to check health'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }, [integration])

  useEffect(() => {
    if (open && integration) {
      checkHealth()
    }
  }, [open, integration, checkHealth])

  const handleClose = () => {
    onOpenChange(false)
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Activity className="h-5 w-5 text-primary" />
            <DialogTitle>Health Check</DialogTitle>
          </div>
          <DialogDescription>
            Connection status for {integration.name}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner className="h-8 w-8" />
              <span className="ml-2 text-sm text-muted-foreground">Checking connection...</span>
            </div>
          ) : error ? (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 flex items-start gap-2">
              <XCircle className="h-5 w-5 text-destructive flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm text-destructive font-medium">Health Check Failed</p>
                <p className="text-xs text-destructive/80 mt-1">{error}</p>
              </div>
            </div>
          ) : healthData ? (
            <>
              <div className="rounded-lg border border-border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Status</span>
                  {healthData.healthy ? (
                    <Badge variant="default" className="flex items-center gap-1">
                      <CheckCircle className="h-3 w-3" />
                      Healthy
                    </Badge>
                  ) : (
                    <Badge variant="destructive" className="flex items-center gap-1">
                      <XCircle className="h-3 w-3" />
                      Unhealthy
                    </Badge>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="text-muted-foreground mb-1">Integration Status</p>
                    <p className="font-medium capitalize">{healthData.status}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground mb-1">Last Sync</p>
                    <p className="font-medium capitalize">{healthData.last_sync_status}</p>
                  </div>
                </div>

                {healthData.error_message && (
                  <div className="rounded bg-destructive/10 p-2">
                    <p className="text-xs text-destructive font-medium">Error:</p>
                    <p className="text-xs text-destructive/80 mt-1">{healthData.error_message}</p>
                  </div>
                )}

                <div>
                  <p className="text-sm font-medium mb-2">Health Checks</p>
                  <div className="space-y-1">
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">Credentials Valid</span>
                      {healthData.checks.credentials_valid ? (
                        <CheckCircle className="h-4 w-4 text-green-600" />
                      ) : (
                        <XCircle className="h-4 w-4 text-destructive" />
                      )}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">Recent Sync</span>
                      {healthData.checks.recent_sync ? (
                        <CheckCircle className="h-4 w-4 text-green-600" />
                      ) : (
                        <XCircle className="h-4 w-4 text-destructive" />
                      )}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">No Errors</span>
                      {healthData.checks.no_errors ? (
                        <CheckCircle className="h-4 w-4 text-green-600" />
                      ) : (
                        <XCircle className="h-4 w-4 text-destructive" />
                      )}
                    </div>
                  </div>
                </div>

                <div className="text-xs text-muted-foreground pt-2 border-t border-border">
                  Last sync: {new Date(healthData.last_sync_at).toLocaleString('en-US', { timeZone: 'Australia/Darwin' })}
                </div>
              </div>

              <Button
                variant="outline"
                onClick={checkHealth}
                disabled={isLoading}
                className="w-full"
              >
                <RefreshCw className="mr-2 h-4 w-4" />
                Check Again
              </Button>
            </>
          ) : null}
        </div>

        <div className="flex justify-end">
          <Button variant="outline" onClick={handleClose}>
            Close
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
