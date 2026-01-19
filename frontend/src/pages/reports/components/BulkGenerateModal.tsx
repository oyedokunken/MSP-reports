import { useState, useEffect } from 'react'
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
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { api } from '@/lib/api'
import { FileText, Users, Calendar, CheckCircle2, AlertCircle, Search } from 'lucide-react'

interface Customer {
  customer_id: number
  canonical_name: string
  report_destination?: string
}

interface BulkGenerateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

interface GenerationResult {
  total_customers: number
  successful: number
  failed: number
  skipped: number
  reports: Array<{
    customer_id: number
    customer_name: string
    reports_generated: Array<{
      generated_report_id: number
      report_type: string
      report_name: string
      status: string
    }>
  }>
  errors: Array<{
    customer_id: number
    customer_name: string
    error: string
  }>
}

export function BulkGenerateModal({ open, onOpenChange, onSuccess }: BulkGenerateModalProps) {
  const [step, setStep] = useState<'select' | 'options' | 'generating' | 'results'>('select')
  const [customers, setCustomers] = useState<Customer[]>([])
  const [selectedCustomerIds, setSelectedCustomerIds] = useState<number[]>([])
  const [searchQuery, setSearchQuery] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [, setIsGenerating] = useState(false)
  const [frequency, setFrequency] = useState<string>('monthly')
  const [results, setResults] = useState<GenerationResult | null>(null)
  const [error, setError] = useState('')

  // Fetch customers with active schedules
  useEffect(() => {
    if (open) {
      fetchCustomers()
    }
  }, [open])

  const fetchCustomers = async () => {
    setIsLoading(true)
    try {
      const response = await api.get<{ data: Customer[] }>('/customers', {
        params: { per_page: 500, has_schedules: true }
      })
      setCustomers(response.data.data || [])
    } catch (err) {
      console.error('Failed to fetch customers:', err)
      setError('Failed to load customers')
    } finally {
      setIsLoading(false)
    }
  }

  const filteredCustomers = customers.filter(c =>
    c.canonical_name.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const toggleCustomer = (customerId: number) => {
    setSelectedCustomerIds(prev =>
      prev.includes(customerId)
        ? prev.filter(id => id !== customerId)
        : [...prev, customerId]
    )
  }

  const toggleAll = () => {
    if (selectedCustomerIds.length === filteredCustomers.length) {
      setSelectedCustomerIds([])
    } else {
      setSelectedCustomerIds(filteredCustomers.map(c => c.customer_id))
    }
  }

  const handleGenerate = async () => {
    setStep('generating')
    setIsGenerating(true)
    setError('')

    try {
      const response = await api.post<{ data: GenerationResult }>('/reports/bulk-generate', {
        customer_ids: selectedCustomerIds,
        frequency: frequency,
      })

      setResults(response.data.data)
      setStep('results')
      onSuccess?.()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      setError(error.response?.data?.message || error.message || 'Failed to generate reports')
      setStep('options')
    } finally {
      setIsGenerating(false)
    }
  }

  const handleClose = () => {
    setStep('select')
    setSelectedCustomerIds([])
    setSearchQuery('')
    setResults(null)
    setError('')
    onOpenChange(false)
  }

  const renderSelectStep = () => (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          <Users className="h-5 w-5" />
          Bulk Generate Reports
        </DialogTitle>
        <DialogDescription>
          Select customers to generate reports for. Only customers with active schedules are shown.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4 py-4">
        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search customers..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>

        {/* Select All */}
        <div className="flex items-center justify-between border-b pb-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={selectedCustomerIds.length === filteredCustomers.length && filteredCustomers.length > 0}
              onCheckedChange={toggleAll}
            />
            <span className="text-sm font-medium">Select All ({filteredCustomers.length})</span>
          </label>
          <span className="text-sm text-muted-foreground">
            {selectedCustomerIds.length} selected
          </span>
        </div>

        {/* Customer List */}
        <div className="max-h-[300px] overflow-y-auto space-y-1 border rounded-lg p-2">
          {isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner className="h-6 w-6" />
            </div>
          ) : filteredCustomers.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              No customers with active schedules found
            </div>
          ) : (
            filteredCustomers.map(customer => (
              <label
                key={customer.customer_id}
                className="flex items-center gap-3 p-2 rounded-md hover:bg-muted cursor-pointer"
              >
                <Checkbox
                  checked={selectedCustomerIds.includes(customer.customer_id)}
                  onCheckedChange={() => toggleCustomer(customer.customer_id)}
                />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{customer.canonical_name}</p>
                  {customer.report_destination && (
                    <p className="text-xs text-muted-foreground truncate">
                      {customer.report_destination}
                    </p>
                  )}
                </div>
              </label>
            ))
          )}
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={handleClose}>
          Cancel
        </Button>
        <Button
          onClick={() => setStep('options')}
          disabled={selectedCustomerIds.length === 0}
        >
          Next ({selectedCustomerIds.length} customers)
        </Button>
      </DialogFooter>
    </>
  )

  const renderOptionsStep = () => (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          <Calendar className="h-5 w-5" />
          Generation Options
        </DialogTitle>
        <DialogDescription>
          Configure report generation settings for {selectedCustomerIds.length} customers.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4 py-4">
        {error && (
          <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
            <p className="text-sm text-destructive">{error}</p>
          </div>
        )}

        {/* Frequency Selection */}
        <div className="space-y-2">
          <label className="text-sm font-medium">Report Frequency</label>
          <div className="grid grid-cols-2 gap-2">
            {['daily', 'weekly', 'monthly', 'yearly'].map(freq => (
              <button
                key={freq}
                onClick={() => setFrequency(freq)}
                className={`p-3 rounded-lg border text-sm font-medium transition-colors ${
                  frequency === freq
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border hover:bg-muted'
                }`}
              >
                {freq.charAt(0).toUpperCase() + freq.slice(1)}
              </button>
            ))}
          </div>
          <p className="text-xs text-muted-foreground">
            Reports will be generated using the date range for the selected frequency.
          </p>
        </div>

        {/* Summary */}
        <div className="rounded-lg border bg-muted/50 p-4 space-y-2">
          <h4 className="text-sm font-medium">Summary</h4>
          <ul className="text-sm text-muted-foreground space-y-1">
            <li>• {selectedCustomerIds.length} customers selected</li>
            <li>• {frequency.charAt(0).toUpperCase() + frequency.slice(1)} reports will be generated</li>
            <li>• Reports will be queued for delivery automatically</li>
          </ul>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={() => setStep('select')}>
          Back
        </Button>
        <Button onClick={handleGenerate}>
          <FileText className="mr-2 h-4 w-4" />
          Generate Reports
        </Button>
      </DialogFooter>
    </>
  )

  const renderGeneratingStep = () => (
    <>
      <DialogHeader>
        <DialogTitle>Generating Reports...</DialogTitle>
        <DialogDescription>
          Please wait while reports are being generated for {selectedCustomerIds.length} customers.
        </DialogDescription>
      </DialogHeader>

      <div className="flex flex-col items-center justify-center py-12">
        <Spinner className="h-12 w-12 mb-4" />
        <p className="text-sm text-muted-foreground">This may take a few moments...</p>
      </div>
    </>
  )

  const renderResultsStep = () => (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          {results && results.successful > 0 ? (
            <CheckCircle2 className="h-5 w-5 text-green-500" />
          ) : (
            <AlertCircle className="h-5 w-5 text-destructive" />
          )}
          Generation Complete
        </DialogTitle>
        <DialogDescription>
          {results?.successful} of {results?.total_customers} customers processed successfully.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4 py-4">
        {/* Stats */}
        <div className="grid grid-cols-3 gap-4">
          <div className="rounded-lg border bg-green-50 dark:bg-green-950/20 p-3 text-center">
            <p className="text-2xl font-bold text-green-600">{results?.successful || 0}</p>
            <p className="text-xs text-green-600/80">Successful</p>
          </div>
          <div className="rounded-lg border bg-yellow-50 dark:bg-yellow-950/20 p-3 text-center">
            <p className="text-2xl font-bold text-yellow-600">{results?.skipped || 0}</p>
            <p className="text-xs text-yellow-600/80">Skipped</p>
          </div>
          <div className="rounded-lg border bg-red-50 dark:bg-red-950/20 p-3 text-center">
            <p className="text-2xl font-bold text-red-600">{results?.failed || 0}</p>
            <p className="text-xs text-red-600/80">Failed</p>
          </div>
        </div>

        {/* Errors */}
        {results?.errors && results.errors.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium text-destructive">Errors</h4>
            <div className="max-h-[150px] overflow-y-auto space-y-1 border border-destructive/30 rounded-lg p-2">
              {results.errors.map((err, idx) => (
                <div key={idx} className="text-xs p-2 bg-destructive/5 rounded">
                  <span className="font-medium">{err.customer_name}:</span> {err.error}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Success List */}
        {results?.reports && results.reports.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium text-green-600">Generated Reports</h4>
            <div className="max-h-[150px] overflow-y-auto space-y-1 border rounded-lg p-2">
              {results.reports.map((report, idx) => (
                <div key={idx} className="text-xs p-2 bg-green-50 dark:bg-green-950/20 rounded">
                  <span className="font-medium">{report.customer_name}:</span>{' '}
                  {report.reports_generated.map(r => r.report_name).join(', ')}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      <DialogFooter>
        <Button onClick={handleClose}>
          Done
        </Button>
      </DialogFooter>
    </>
  )

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-lg">
        {step === 'select' && renderSelectStep()}
        {step === 'options' && renderOptionsStep()}
        {step === 'generating' && renderGeneratingStep()}
        {step === 'results' && renderResultsStep()}
      </DialogContent>
    </Dialog>
  )
}
