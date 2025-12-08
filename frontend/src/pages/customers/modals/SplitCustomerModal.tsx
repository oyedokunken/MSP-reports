import { useState, useEffect, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, GitBranch, AlertCircle, History, Undo2 } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { api } from '@/lib/api'
import type { Customer } from '../types'

interface IntegrationMapping {
  integration_customer_id: number
  display_name: string
  name: string
  integration_type?: string
  external_id?: string
  active?: boolean
  last_seen_at?: string
}

interface MergeHistoryItem {
  merged_at: string
  merged_customer_name: string
  merged_customer_id: number | null
  integration_customers_moved: number
}

interface SplitCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customer: Customer | null
  onSuccess?: () => void
}

export function SplitCustomerModal({
  open,
  onOpenChange,
  customer,
  onSuccess
}: SplitCustomerModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [newCustomerName, setNewCustomerName] = useState('')
  const [integrations, setIntegrations] = useState<IntegrationMapping[]>([])
  const [selectedIntegrations, setSelectedIntegrations] = useState<number[]>([])
  const [mergeHistory, setMergeHistory] = useState<MergeHistoryItem[]>([])

  const fetchIntegrations = useCallback(async () => {
    if (!customer) return

    setIsLoading(true)
    try {
      const response = await api.get(`/customers/${customer.customer_id}/integrations`)
      const data = response as { data?: IntegrationMapping[] } | IntegrationMapping[]
      const integrationsList = Array.isArray(data) ? data : (data.data || [])
      setIntegrations(integrationsList)
    } catch {
      setError('Failed to load integrations')
    } finally {
      setIsLoading(false)
    }
  }, [customer])

  const fetchMergeHistory = useCallback(async () => {
    if (!customer) return

    try {
      const response = await api.get(`/customers/${customer.customer_id}/merge-history`)
      const data = response as { data?: MergeHistoryItem[] } | MergeHistoryItem[]
      const historyList = Array.isArray(data) ? data : (data.data || [])
      setMergeHistory(historyList)
    } catch {
      // Merge history is optional, fail silently
    }
  }, [customer])

  useEffect(() => {
    if (open && customer) {
      setNewCustomerName('')
      setSelectedIntegrations([])
      setError('')
      setSuccess(false)
      setMergeHistory([])
      fetchIntegrations()
      fetchMergeHistory()
    }
  }, [open, customer, fetchIntegrations, fetchMergeHistory])

  const toggleIntegration = (id: number) => {
    setSelectedIntegrations(prev =>
      prev.includes(id)
        ? prev.filter(i => i !== id)
        : [...prev, id]
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!newCustomerName.trim()) {
      setError('Please enter a name for the new customer')
      return
    }

    if (selectedIntegrations.length === 0) {
      setError('Please select at least one integration to move')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await api.post(`/customers/${customer?.customer_id}/split`, {
        new_customer_name: newCustomerName.trim(),
        integration_customer_ids: selectedIntegrations,
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        onOpenChange(false)
        onSuccess?.()
      }, 2000)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to split customer'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
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
            <DialogTitle className="text-2xl text-green-600 mb-2">Customer Split!</DialogTitle>
            <DialogDescription className="text-center">
              The customer has been split successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!customer) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <GitBranch className="h-5 w-5 text-primary" />
            <DialogTitle>Split Customer</DialogTitle>
          </div>
          <DialogDescription>
            Split {customer.name} into two separate customers
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Merge History */}
          {mergeHistory.length > 0 && (
            <div className="rounded-lg border border-purple-500/50 bg-purple-500/10 p-3">
              <div className="flex items-center gap-2 mb-2">
                <History className="h-4 w-4 text-purple-700 dark:text-purple-400" />
                <p className="text-sm font-medium text-purple-700 dark:text-purple-400">
                  Merge History ({mergeHistory.length})
                </p>
              </div>
              <div className="space-y-2">
                {mergeHistory.map((merge, index) => (
                  <div key={index} className="flex items-center justify-between text-xs bg-white dark:bg-gray-800 rounded p-2">
                    <div>
                      <p className="font-medium text-purple-900 dark:text-purple-300">
                        "{merge.merged_customer_name}" was merged here
                      </p>
                      <p className="text-muted-foreground">
                        {merge.integration_customers_moved} integration(s) • {new Date(merge.merged_at).toLocaleDateString()}
                      </p>
                    </div>
                    <Undo2 className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                  </div>
                ))}
              </div>
              <p className="text-xs text-purple-600 dark:text-purple-400 mt-2">
                💡 Tip: Select the integrations from a merged customer to undo the merge
              </p>
            </div>
          )}

          {/* Info */}
          <div className="rounded-lg border border-blue-500/50 bg-blue-500/10 p-3">
            <p className="text-sm text-blue-700 dark:text-blue-400">
              ℹ️ Select which integrations should be moved to the new customer. The remaining integrations will stay with "{customer.name}".
            </p>
          </div>

          {/* New Customer Name */}
          <div>
            <Label htmlFor="newName" className="text-sm font-medium mb-2 block">
              New Customer Name
            </Label>
            <Input
              id="newName"
              type="text"
              placeholder="Enter new customer name"
              value={newCustomerName}
              onChange={(e) => setNewCustomerName(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          {/* Integration Selection */}
          <div>
            <Label className="text-sm font-medium mb-2 block">
              Select integrations to move to new customer
            </Label>
            {isLoading ? (
              <div className="flex items-center justify-center py-8">
                <Spinner className="h-6 w-6" />
              </div>
            ) : integrations.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-4">
                No integrations found
              </p>
            ) : (
              <div className="space-y-2 border border-border rounded-lg p-3 max-h-64 overflow-y-auto">
                {integrations.map((integration) => (
                  <div
                    key={integration.integration_customer_id}
                    className="flex items-center space-x-2 p-2 rounded hover:bg-accent"
                  >
                    <Checkbox
                      id={`integration-${integration.integration_customer_id}`}
                      checked={selectedIntegrations.includes(integration.integration_customer_id)}
                      onCheckedChange={() => toggleIntegration(integration.integration_customer_id)}
                      disabled={isSubmitting}
                    />
                    <label
                      htmlFor={`integration-${integration.integration_customer_id}`}
                      className="flex-1 text-sm cursor-pointer"
                    >
                      <p className="font-medium">{integration.display_name} - {integration.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {integration.integration_type && `${integration.integration_type} • `}
                        {integration.external_id}
                      </p>
                    </label>
                  </div>
                ))}
              </div>
            )}
          </div>

          {selectedIntegrations.length > 0 && (
            <div className="rounded-lg border border-border bg-accent/50 p-3 text-sm">
              <p className="font-medium mb-1">Summary:</p>
              <p>• {selectedIntegrations.length} integration(s) will be moved to "{newCustomerName || 'new customer'}"</p>
              <p>• {integrations.length - selectedIntegrations.length} integration(s) will remain with "{customer.name}"</p>
            </div>
          )}

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
                  Splitting...
                </>
              ) : (
                <>
                  <GitBranch className="mr-2 h-4 w-4" />
                  Split Customer
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
