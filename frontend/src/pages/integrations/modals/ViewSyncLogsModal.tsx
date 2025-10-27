import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Badge } from '@/components/ui/badge'
import { FileText, CheckCircle, XCircle, Clock } from 'lucide-react'
import { api } from '@/lib/api'

// ---- Types ----

import type { Integration } from '../types/integration'

export interface SyncLog {
  sync_id: number
  integration_id: number
  sync_type: string
  started_at: string
  completed_at: string | null
  status: 'success' | 'failed' | 'running' | 'partial'
  records_synced: number
  records_failed: number
  error_messages: Record<string, any> | null
  rate_limit_hits?: number
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

interface SyncLogsApiData {
  integration: Integration
  sync_logs: SyncLog[]
}

interface ViewSyncLogsModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
}

// ---- Component ----

export function ViewSyncLogsModal({
  open,
  onOpenChange,
  integration,
}: ViewSyncLogsModalProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [logs, setLogs] = useState<SyncLog[]>([])
  const [error, setError] = useState('')

  useEffect(() => {
    if (open && integration) {
      fetchSyncLogs()
    }
  }, [open, integration])

  const fetchSyncLogs = async () => {
    if (!integration?.id) return

    setIsLoading(true)
    setError('')

    try {
      // ✅ use `integration.id` — backend expects that field
      const response = await api.get<SyncLogsApiData>(
        `/integrations/${integration.id}/sync-logs`
      )

      const syncLogs = response.data.sync_logs ?? []
      setLogs(Array.isArray(syncLogs) ? syncLogs : [])
    } catch (err: any) {
      const errorMessage =
        err.response?.data?.message || err.message || 'Failed to load sync logs'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  const getStatusBadge = (status: SyncLog['status']) => {
    const variants: Record<
      SyncLog['status'],
      { variant: 'default' | 'secondary' | 'destructive'; icon: any }
    > = {
      success: { variant: 'default', icon: CheckCircle },
      failed: { variant: 'destructive', icon: XCircle },
      running: { variant: 'secondary', icon: Clock },
      partial: { variant: 'secondary', icon: Clock },
    }

    const config = variants[status] || variants.running
    const Icon = config.icon

    return (
      <Badge variant={config.variant} className="flex items-center gap-1">
        <Icon className="h-3 w-3" />
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    )
  }

  const formatDate = (dateString: string) =>
    new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })

  const handleClose = () => onOpenChange(false)

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <DialogTitle>Sync Logs</DialogTitle>
          </div>
          <DialogDescription>
            Sync history for {integration.name}
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
          ) : logs.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <FileText className="h-12 w-12 mx-auto mb-2 opacity-50" />
              <p>No sync logs found</p>
            </div>
          ) : (
            <div className="space-y-3">
              {logs.map((log) => (
                <div
                  key={log.sync_id}
                  className="rounded-lg border border-border p-4 space-y-2"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      {getStatusBadge(log.status)}
                      <span className="text-sm text-muted-foreground capitalize">
                        {log.sync_type}
                      </span>
                    </div>
                    <span className="text-xs text-muted-foreground">
                      {formatDate(log.started_at)}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-muted-foreground">Records Synced</p>
                      <p className="font-medium">{log.records_synced}</p>
                    </div>
                    <div>
                      <p className="text-muted-foreground">Records Failed</p>
                      <p className="font-medium">{log.records_failed}</p>
                    </div>
                  </div>

                  {log.error_messages &&
                    Object.keys(log.error_messages).length > 0 && (
                      <div className="rounded bg-destructive/10 p-2">
                        <p className="text-xs text-destructive font-medium">
                          Errors:
                        </p>
                        <pre className="text-xs text-destructive/80 mt-1 overflow-x-auto">
                          {JSON.stringify(log.error_messages, null, 2)}
                        </pre>
                      </div>
                    )}
                </div>
              ))}
            </div>
          )}
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
