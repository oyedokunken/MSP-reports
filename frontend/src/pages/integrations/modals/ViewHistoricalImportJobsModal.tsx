import { useState, useEffect, useCallback } from 'react'
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
import { Database, CheckCircle, XCircle, Clock, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'
import type { Integration } from '../types/integration'

interface HistoricalImportJob {
  id: number
  integration_id: number
  company_id: number
  import_start_date: string
  import_end_date: string
  current_position: string | null
  status: 'queued' | 'running' | 'completed' | 'failed' | 'paused'
  priority_level: number
  total_records_estimate: number | null
  records_imported: number
  records_failed: number
  error_count: number
  progress_percentage?: number
  created_at: string
  updated_at: string
  started_at: string | null
  completed_at: string | null
}

interface ViewHistoricalImportJobsModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
}

export function ViewHistoricalImportJobsModal({
  open,
  onOpenChange,
  integration,
}: ViewHistoricalImportJobsModalProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [jobs, setJobs] = useState<HistoricalImportJob[]>([])
  const [error, setError] = useState('')

  const fetchJobs = useCallback(async () => {
    if (!integration?.id) return

    setIsLoading(true)
    setError('')

    try {
      const response = await api.get<{ data?: { jobs: HistoricalImportJob[] }; jobs?: HistoricalImportJob[] }>(`/integrations/${integration.id}/historical-import-jobs`)
      const data = response.data || response
      setJobs(Array.isArray(data.jobs) ? data.jobs : [])
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage =
        error.response?.data?.message || error.message || 'Failed to load historical import jobs'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }, [integration])

  useEffect(() => {
    if (open && integration) {
      fetchJobs()
    } else if (!open) {
      // Clear jobs when modal closes to prevent showing stale data
      setJobs([])
      setError('')
    }
  }, [open, integration, fetchJobs])

  // Clear jobs when integration changes (even if modal stays open)
  useEffect(() => {
    setJobs([])
    setError('')
  }, [integration?.id])

  useEffect(() => {
    if (!open || !integration) return

    const interval = setInterval(() => {
      setJobs(currentJobs => {
        const shouldPoll = currentJobs.some(j => j.status === 'running' || j.status === 'queued')
        if (shouldPoll) {
          fetchJobs()
        }
        return currentJobs
      })
    }, 10000)

    return () => clearInterval(interval)
  }, [open, integration, fetchJobs])

  const getStatusBadge = (status: HistoricalImportJob['status']) => {
    const config = {
      queued: { variant: 'secondary' as const, icon: Clock, text: 'Queued', color: 'text-gray-600' },
      running: { variant: 'default' as const, icon: Clock, text: 'Running', color: 'text-blue-600' },
      completed: { variant: 'default' as const, icon: CheckCircle, text: 'Completed', color: 'text-green-600' },
      failed: { variant: 'destructive' as const, icon: XCircle, text: 'Failed', color: 'text-red-600' },
      paused: { variant: 'secondary' as const, icon: AlertCircle, text: 'Paused', color: 'text-orange-600' },
    }

    const statusConfig = config[status] || config.queued
    const Icon = statusConfig.icon

    return (
      <Badge variant={statusConfig.variant} className="flex items-center gap-1">
        <Icon className="h-3 w-3" />
        {statusConfig.text}
      </Badge>
    )
  }

  const calculateProgress = (job: HistoricalImportJob) => {
    if (job.status === 'completed') return 100
    if (job.status === 'failed') return 0

    if (typeof job.progress_percentage === 'number') {
      return job.progress_percentage
    }

    if (!job.current_position || !job.import_start_date || !job.import_end_date) return 0

    const start = new Date(job.import_start_date).getTime()
    const end = new Date(job.import_end_date).getTime()
    const current = new Date(job.current_position).getTime()

    const total = end - start
    const progress = current - start

    return Math.min(Math.max((progress / total) * 100, 0), 100)
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleString()
  }

  const formatDateRange = (start: string, end: string) => {
    return `${new Date(start).toLocaleDateString()} - ${new Date(end).toLocaleDateString()}`
  }

  const calculateETA = (job: HistoricalImportJob): string | null => {
    if (job.status !== 'running' || !job.started_at) return null

    const progress = calculateProgress(job)
    if (progress <= 0 || progress >= 100) return null

    const startedAt = new Date(job.started_at).getTime()
    const now = Date.now()
    const elapsedMs = now - startedAt

    if (elapsedMs < 60000) return 'Calculating...'

    const totalEstimatedMs = (elapsedMs / progress) * 100
    const remainingMs = totalEstimatedMs - elapsedMs

    if (remainingMs <= 0) return 'Almost done...'

    const remainingSeconds = Math.floor(remainingMs / 1000)
    const hours = Math.floor(remainingSeconds / 3600)
    const minutes = Math.floor((remainingSeconds % 3600) / 60)

    if (hours > 0) {
      return `~${hours}h ${minutes}m remaining`
    } else if (minutes > 0) {
      return `~${minutes}m remaining`
    } else {
      return 'Less than a minute'
    }
  }

  const handleClose = () => {
    onOpenChange(false)
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-3xl max-h-[80vh] flex flex-col gap-0 p-0">
        <DialogHeader className="px-6 py-4 border-b">
          <div className="flex items-center gap-2">
            <Database className="h-5 w-5 text-primary" />
            <DialogTitle>Historical Import Jobs</DialogTitle>
          </div>
          <DialogDescription>
            View historical data import progress for {integration.name}
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto thin-scrollbar">
          <div className="space-y-4 p-6">
            {isLoading && jobs.length === 0 ? (
              <div className="flex items-center justify-center py-8">
                <Spinner className="h-8 w-8" />
              </div>
            ) : error ? (
              <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                <p className="text-sm text-destructive font-medium">{error}</p>
              </div>
            ) : jobs.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Database className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No historical import jobs found</p>
                <p className="text-xs mt-1">Start a historical import to see jobs here</p>
              </div>
            ) : (
              <div className="space-y-3">
                {jobs.map((job, index) => {
                  const progress = calculateProgress(job)
                  return (
                    <div
                      key={`job-${job.id}-${index}`}
                      className="rounded-lg border border-border p-4 space-y-3"
                    >
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="flex items-center gap-2 mb-1">
                            <h4 className="font-semibold">Job #{job.id}</h4>
                            {getStatusBadge(job.status)}
                          </div>
                          <p className="text-sm text-muted-foreground">
                            Date Range: {formatDateRange(job.import_start_date, job.import_end_date)}
                          </p>
                        </div>
                        <div className="text-right text-sm">
                          <p className="text-muted-foreground">Priority</p>
                          <p className="font-medium">{job.priority_level}</p>
                        </div>
                      </div>

                      {(job.status === 'running' || job.status === 'queued') && (
                        <div className="space-y-2">
                          <div className="flex items-center justify-between text-xs">
                            <span className="text-muted-foreground">
                              {job.status === 'running' ? 'Progress' : 'Waiting to start...'}
                            </span>
                            <div className="flex items-center gap-3">
                              {job.status === 'running' && calculateETA(job) && (
                                <span className="text-muted-foreground font-medium">
                                  {calculateETA(job)}
                                </span>
                              )}
                              <span className="font-medium text-primary">{Math.round(progress)}%</span>
                            </div>
                          </div>
                          <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                              className="bg-primary h-2 rounded-full transition-all duration-300 ease-out"
                              style={{ width: `${progress}%` }}
                            />
                          </div>
                          {job.current_position && (
                            <p className="text-xs text-muted-foreground">
                              Current Position: {new Date(job.current_position).toLocaleDateString()}
                            </p>
                          )}
                        </div>
                      )}

                      <div className="grid grid-cols-3 gap-3 pt-3 border-t">
                        <div>
                          <p className="text-xs text-muted-foreground">Records Imported</p>
                          <p className="text-sm font-medium text-green-600">{(job.records_imported || 0).toLocaleString()}</p>
                        </div>
                        <div>
                          <p className="text-xs text-muted-foreground">Records Failed</p>
                          <p className="text-sm font-medium text-red-600">{(job.records_failed || 0).toLocaleString()}</p>
                        </div>
                        <div>
                          <p className="text-xs text-muted-foreground">Error Count</p>
                          <p className="text-sm font-medium text-orange-600">{job.error_count || 0}</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-3 text-xs pt-3 border-t">
                        <div key={`${job.id}-created`}>
                          <p className="text-muted-foreground">Created</p>
                          <p className="font-medium">{formatDate(job.created_at)}</p>
                        </div>
                        {job.started_at && (
                          <div key={`${job.id}-started`}>
                            <p className="text-muted-foreground">Started</p>
                            <p className="font-medium">{formatDate(job.started_at)}</p>
                          </div>
                        )}
                        {job.completed_at && (
                          <div key={`${job.id}-completed`}>
                            <p className="text-muted-foreground">Completed</p>
                            <p className="font-medium">{formatDate(job.completed_at)}</p>
                          </div>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>

        <div className="flex justify-between items-center p-4 border-t bg-muted/20">
          <Button
            variant="outline"
            size="sm"
            onClick={fetchJobs}
            disabled={isLoading}
          >
            {isLoading ? <Spinner className="h-4 w-4 mr-2" /> : null}
            Refresh
          </Button>
          <Button variant="outline" onClick={handleClose}>
            Close
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
