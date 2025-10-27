import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, Database, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'
import type { Integration } from '../types/integration'

interface HistoricalImportModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
  onSuccess?: () => void
}

export function HistoricalImportModal({ 
  open, 
  onOpenChange,
  integration,
  onSuccess 
}: HistoricalImportModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [days, setDays] = useState('90')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!integration) return
    
    const daysNum = parseInt(days)
    if (isNaN(daysNum) || daysNum < 1) {
      setError('Please enter a valid number of days (minimum 1)')
      return
    }

    if (daysNum > 365) {
      setError('Maximum 365 days allowed')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await api.post(`/integrations/${integration.id}/historical-import`, {
        days: daysNum,
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        setDays('90')
        onOpenChange(false)
        onSuccess?.()
      }, 3000)
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to start historical import'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setDays('90')
      setError('')
      setSuccess(false)
      onOpenChange(false)
    }
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Import Started!</DialogTitle>
            <DialogDescription className="text-center">
              Historical data import has been queued. This will run in the background and may take several hours depending on the amount of data.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Database className="h-5 w-5 text-primary" />
            <DialogTitle>Import Historical Data</DialogTitle>
          </div>
          <DialogDescription>
            Import past data from {integration.name}
          </DialogDescription>
        </DialogHeader>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Info Box */}
          <div className="rounded-lg border border-blue-500/50 bg-blue-500/10 p-3">
            <p className="text-sm text-blue-700 dark:text-blue-400">
              ℹ️ Historical import runs in the background and pulls data from the past. This is useful for generating reports with historical trends.
            </p>
          </div>

          {/* Days Input */}
          <div>
            <Label htmlFor="days" className="text-sm font-medium mb-2 block">
              Number of Days
            </Label>
            <Input
              id="days"
              type="number"
              min="1"
              max="365"
              placeholder="90"
              value={days}
              onChange={(e) => setDays(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
            <p className="text-xs text-muted-foreground mt-1">
              Import data from the last {days || '0'} days (max 365)
            </p>
          </div>

          {/* Warning */}
          <div className="rounded-lg border border-orange-500/50 bg-orange-500/10 p-3">
            <p className="text-sm text-orange-700 dark:text-orange-400 font-medium mb-1">
              ⚠️ Important Notes:
            </p>
            <ul className="text-xs text-orange-700/80 dark:text-orange-400/80 space-y-1 list-disc list-inside">
              <li>This process may take several hours</li>
              <li>It will respect API rate limits</li>
              <li>You can continue using the system while it runs</li>
              <li>Progress can be monitored in sync logs</li>
            </ul>
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Starting...
                </>
              ) : (
                <>
                  <Database className="mr-2 h-4 w-4" />
                  Start Import
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
