import { useState, useEffect, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Gauge, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import type { Integration } from '../types/integration'

interface RateLimitStatus {
  integration_type: string
  limits: {
    per_minute: number
    per_hour: number
  }
  current_usage: {
    requests_last_hour: number
    rate_limit_hits: number
  }
  percentage_used: number
  status: string
}

interface RateLimitStatusModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
}

export function RateLimitStatusModal({
  open,
  onOpenChange,
  integration
}: RateLimitStatusModalProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [rateLimitData, setRateLimitData] = useState<RateLimitStatus | null>(null)
  const [error, setError] = useState('')

  const fetchRateLimit = useCallback(async () => {
    if (!integration) return

    setIsLoading(true)
    setError('')

    try {
      const response = await api.get(`/integrations/${integration.id}/rate-limit`)
      const data = (response as unknown as { data: RateLimitStatus }).data || response as unknown as RateLimitStatus
      setRateLimitData(data)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to load rate limit status'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }, [integration])

  useEffect(() => {
    if (open && integration) {
      fetchRateLimit()
    }
  }, [open, integration, fetchRateLimit])

  const getProgressColor = (percentage: number) => {
    if (percentage >= 90) return 'bg-destructive'
    if (percentage >= 70) return 'bg-orange-500'
    return 'bg-green-500'
  }

  const handleClose = () => {
    onOpenChange(false)
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Gauge className="h-5 w-5 text-primary" />
            <DialogTitle>Rate Limit Status</DialogTitle>
          </div>
          <DialogDescription>
            API usage for {integration.name}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner className="h-8 w-8" />
            </div>
          ) : error ? (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          ) : rateLimitData ? (
            <>
              {/* Status Overview */}
              <div className="rounded-lg border border-border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Integration Type</span>
                  <span className="text-sm font-semibold">{rateLimitData.integration_type}</span>
                </div>

                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Status</span>
                  <span className={`text-sm font-semibold capitalize ${rateLimitData.status === 'normal' ? 'text-green-600' :
                    rateLimitData.status === 'warning' ? 'text-orange-600' :
                      'text-destructive'
                    }`}>
                    {rateLimitData.status}
                  </span>
                </div>
              </div>

              {/* Hourly Usage */}
              <div className="rounded-lg border border-border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Requests This Hour</span>
                  <span className="text-xs text-muted-foreground">
                    {rateLimitData.current_usage.requests_last_hour} / {rateLimitData.limits.per_hour}
                  </span>
                </div>

                <div className="space-y-2">
                  <div className="w-full bg-accent rounded-full h-2 overflow-hidden">
                    <div
                      className={`h-full transition-all ${getProgressColor(rateLimitData.percentage_used)}`}
                      style={{ width: `${rateLimitData.percentage_used}%` }}
                    />
                  </div>

                  <div className="flex justify-between text-xs text-muted-foreground">
                    <span>Usage: {rateLimitData.percentage_used.toFixed(1)}%</span>
                    <span>Remaining: {rateLimitData.limits.per_hour - rateLimitData.current_usage.requests_last_hour}</span>
                  </div>
                </div>
              </div>

              {/* Limits Info */}
              <div className="rounded-lg border border-border p-4 space-y-2">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Per Minute Limit</span>
                  <span className="font-medium">{rateLimitData.limits.per_minute}</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Per Hour Limit</span>
                  <span className="font-medium">{rateLimitData.limits.per_hour}</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Rate Limit Hits</span>
                  <span className={`font-medium ${rateLimitData.current_usage.rate_limit_hits > 0 ? 'text-destructive' : 'text-green-600'}`}>
                    {rateLimitData.current_usage.rate_limit_hits}
                  </span>
                </div>
              </div>

              <Button
                variant="outline"
                onClick={fetchRateLimit}
                disabled={isLoading}
                className="w-full"
              >
                <RefreshCw className="mr-2 h-4 w-4" />
                Refresh
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
