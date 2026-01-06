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
import { Trash2, AlertTriangle, CheckCircle2 } from 'lucide-react'
import type { ReportSchedule } from '../types/schedule'

interface DeleteScheduleModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  schedule: ReportSchedule | null
  onConfirm: () => Promise<void>
}

export function DeleteScheduleModal({
  open,
  onOpenChange,
  schedule,
  onConfirm
}: DeleteScheduleModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  const handleDelete = async () => {
    if (!schedule) return

    setError('')
    setIsSubmitting(true)

    try {
      await onConfirm()
      setSuccess(true)

      setTimeout(() => {
        setSuccess(false)
        onOpenChange(false)
      }, 1500)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage =
        error.response?.data?.message ||
        error.message ||
        'Failed to delete schedule'
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
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Schedule Deleted!</DialogTitle>
            <DialogDescription className="text-center">
              The schedule has been deleted successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!schedule) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-destructive" />
            <DialogTitle>Delete Schedule</DialogTitle>
          </div>
          <DialogDescription>
            Are you sure you want to delete this schedule?
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Schedule Info */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-sm font-medium mb-1">Schedule:</p>
            <p className="font-semibold">{schedule.customer_name}</p>
            <p className="text-sm text-muted-foreground capitalize">
              {schedule.report_type} • {schedule.frequency}
            </p>
          </div>

          {/* Template Info */}
          {schedule.template_id && (
            <div className="rounded-lg border border-blue-500/50 bg-blue-50/50 dark:bg-blue-950/20 p-3">
              <p className="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">
                📋 Associated Template
              </p>
              <p className="text-sm text-blue-600 dark:text-blue-400">
                Template ID: {schedule.template_id}
              </p>
              <p className="text-xs text-blue-600/80 dark:text-blue-400/80 mt-1">
                This template will also be deleted
              </p>
            </div>
          )}

          {/* Warning */}
          <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
            <p className="text-sm text-destructive font-medium mb-2">
              ⚠️ Warning: This action cannot be undone!
            </p>
            <ul className="text-xs text-destructive/80 space-y-1 list-disc list-inside">
              <li>The schedule will be permanently deleted</li>
              {schedule.template_id && (
                <li className="font-semibold">The associated template (ID: {schedule.template_id}) will also be deleted</li>
              )}
              <li>No future reports will be generated</li>
              <li>Historical reports will be retained</li>
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
                Delete Schedule
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
