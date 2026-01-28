import { useState, useEffect, useRef, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Checkbox } from '@/components/ui/checkbox'
import { Users, Search, AlertCircle, CheckCircle2 } from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import { api } from '@/lib/api'
import type { ReportTemplate } from '../types/template'
import { useToast } from '@/components/ui/Toast'

interface ApplyTemplateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  template: ReportTemplate | null
  onSuccess?: () => void
}

interface Customer {
  customer_id: number
  canonical_name: string
  status: string
}

const getRequiredIntegration = (reportType: string): string | null => {
  const mapping: Record<string, string> = {
    'autotask_summary': 'Autotask',
    'datto_rmm_summary': 'Datto',
    'itglue_summary': 'ITGlue',
    'bitdefender_summary': 'BitDefender',
    'saas_summary': 'SaaS',
    'sendmarc_summary': 'Sendmarc',
    'librenms_device_report': 'LibreNMS',
  }
  return mapping[reportType] || null
}

export function ApplyTemplateModal({ open, onOpenChange, template, onSuccess }: ApplyTemplateModalProps) {
  const toast = useToast()
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [isLoadingMore, setIsLoadingMore] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [selectedCustomers, setSelectedCustomers] = useState<number[]>([])
  const [searchQuery, setSearchQuery] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [hasMore, setHasMore] = useState(true)
  const [totalCount, setTotalCount] = useState(0)
  const observerTarget = useRef<HTMLDivElement>(null)
  const isInitialLoad = useRef(true)

  const requiredIntegration = template?.report_type ? getRequiredIntegration(template.report_type) : null

  useEffect(() => {
    if (open) {
      isInitialLoad.current = true
      setSearchQuery('')
      setCustomers([])
      setSelectedCustomers([])
      setCurrentPage(1)
      setHasMore(true)
      setError('')
      setSuccess(false)
      loadInitialCustomers()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const loadInitialCustomers = async () => {
    setIsLoading(true)
    try {
      const params = new URLSearchParams({
        page: '1',
        per_page: '20'
      })

      if (requiredIntegration) {
        params.append('integration_types[]', requiredIntegration)
      }

      const response = await api.get(`/customers?${params.toString()}`)
      const data = response as { data?: Customer[]; pagination?: { current_page?: number; last_page?: number; total?: number } }
      const customerData = (data.data || []).filter(c => c.status === 'active')
      const pagination = data.pagination || {}

      setCustomers(customerData)
      setTotalCount(pagination.total || customerData.length)
      setCurrentPage(pagination.current_page || 1)
      setHasMore((pagination.current_page || 1) < (pagination.last_page || 1))
    } catch (err) {
      logger.error('Failed to fetch customers:', err)
      toast.error('Failed to load customers')
      setCustomers([])
      setHasMore(false)
    } finally {
      setIsLoading(false)
    }
  }

  const loadMoreCustomers = useCallback(async (page: number, append: boolean = false) => {
    if (append) {
      setIsLoadingMore(true)
    } else {
      setIsLoading(true)
    }

    try {
      const params = new URLSearchParams({
        page: page.toString(),
        per_page: '20'
      })

      if (searchQuery.trim()) {
        params.append('search', searchQuery)
      }

      if (requiredIntegration) {
        params.append('integration_types[]', requiredIntegration)
      }

      const response = await api.get(`/customers?${params.toString()}`)
      const data = response as { data?: Customer[]; pagination?: { current_page?: number; last_page?: number; total?: number } }
      const customerData = (data.data || []).filter(c => c.status === 'active')
      const pagination = data.pagination || {}

      if (append) {
        setCustomers(prev => [...prev, ...customerData])
      } else {
        setCustomers(customerData)
      }

      setTotalCount(pagination.total || customerData.length)
      setCurrentPage(pagination.current_page || page)
      setHasMore((pagination.current_page || page) < (pagination.last_page || 1))
    } catch (err) {
      logger.error('Failed to fetch customers:', err)
      if (!append) {
        setCustomers([])
      }
      setHasMore(false)
    } finally {
      setIsLoading(false)
      setIsLoadingMore(false)
    }
  }, [searchQuery, requiredIntegration])

  useEffect(() => {
    if (!open) return

    if (isInitialLoad.current) {
      isInitialLoad.current = false
      return
    }

    setCurrentPage(1)
    setHasMore(true)

    const timeoutId = setTimeout(() => loadMoreCustomers(1, false), 300)
    return () => clearTimeout(timeoutId)
  }, [searchQuery, open, loadMoreCustomers])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isLoadingMore && !isLoading) {
          loadMoreCustomers(currentPage + 1, true)
        }
      },
      { threshold: 0.1 }
    )

    const currentTarget = observerTarget.current
    if (currentTarget) {
      observer.observe(currentTarget)
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget)
      }
    }
  }, [hasMore, isLoadingMore, isLoading, currentPage, loadMoreCustomers])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (selectedCustomers.length === 0) {
      setError('Please select at least one customer')
      return
    }

    if (!template) return

    setError('')
    setIsSubmitting(true)

    try {
      if (selectedCustomers.length === 1) {
        await templateApi.applyToCustomer(template.template_id, {
          customer_id: selectedCustomers[0],
        })
      } else {
        await templateApi.applyToCustomers(template.template_id, {
          customer_ids: selectedCustomers,
        })
      }

      setSuccess(true)
      toast.success(`Template applied to ${selectedCustomers.length} customer${selectedCustomers.length > 1 ? 's' : ''}`)

      setTimeout(() => {
        onSuccess?.()
        onOpenChange(false)
      }, 2000)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to apply template'
      setError(errorMessage)
      toast.error(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleToggleCustomer = (customerId: number) => {
    setSelectedCustomers(prev =>
      prev.includes(customerId)
        ? prev.filter(id => id !== customerId)
        : [...prev, customerId]
    )
  }

  const handleToggleAll = () => {
    if (selectedCustomers.length === customers.length) {
      setSelectedCustomers([])
    } else {
      setSelectedCustomers(customers.map(c => c.customer_id))
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
            <DialogTitle className="text-2xl text-green-600 mb-2">Template Applied!</DialogTitle>
            <DialogDescription className="text-center">
              The template has been successfully applied to {selectedCustomers.length} customer{selectedCustomers.length > 1 ? 's' : ''}.
              Report schedules have been created.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!template) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Users className="h-5 w-5 text-primary" />
            <DialogTitle>Apply Template to Customers</DialogTitle>
          </div>
          <DialogDescription>
            Select customers to apply "{template.template_name}" template
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
          <div className="space-y-4 flex-1 overflow-hidden flex flex-col">
            <div className="rounded-lg border bg-muted/50 p-4">
              <div className="text-sm font-medium mb-1">{template.template_name}</div>
              <div className="text-xs text-muted-foreground">
                {template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} • {template.frequency.charAt(0).toUpperCase() + template.frequency.slice(1)}
                {template.time_of_day && ` • ${template.time_of_day}`}
              </div>
              {requiredIntegration && (
                <div className="mt-2 text-xs text-primary flex items-center gap-1">
                  <AlertCircle className="h-3 w-3" />
                  Showing only customers with {requiredIntegration} integration
                </div>
              )}
            </div>

            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search customers..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
                disabled={isSubmitting}
              />
            </div>

            <div className="flex items-center justify-between text-sm">
              <Label className="text-muted-foreground">
                {selectedCustomers.length} of {customers.length} selected
                {hasMore && ` (${totalCount} total, scroll for more)`}
              </Label>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleToggleAll}
                disabled={isLoading || isSubmitting || customers.length === 0}
              >
                {selectedCustomers.length === customers.length && customers.length > 0 ? 'Deselect All' : 'Select All'}
              </Button>
            </div>

            <div className="flex-1 overflow-y-auto border rounded-lg">
              {isLoading ? (
                <div className="flex items-center justify-center py-8">
                  <Spinner className="h-6 w-6" />
                </div>
              ) : customers.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                  <Users className="h-8 w-8 mb-2" />
                  <p className="text-sm">{searchQuery ? 'No customers found matching your search' : 'No customers found with required integration'}</p>
                </div>
              ) : (
                <div className="divide-y">
                  {customers.map((customer) => (
                    <div
                      key={customer.customer_id}
                      className="flex items-center gap-3 p-3 hover:bg-muted/50"
                    >
                      <Checkbox
                        checked={selectedCustomers.includes(customer.customer_id)}
                        onCheckedChange={() => handleToggleCustomer(customer.customer_id)}
                        disabled={isSubmitting}
                      />
                      <div
                        className="flex-1 cursor-pointer"
                        onClick={() => handleToggleCustomer(customer.customer_id)}
                      >
                        <div className="text-sm font-medium">{customer.canonical_name}</div>
                      </div>
                    </div>
                  ))}

                  <div ref={observerTarget} className="py-2">
                    {isLoadingMore && (
                      <div className="flex items-center justify-center py-2">
                        <Spinner className="h-4 w-4" />
                        <span className="ml-2 text-xs text-muted-foreground">Loading more...</span>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2 mt-4">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter className="mt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting || selectedCustomers.length === 0}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Applying...
                </>
              ) : (
                <>Apply to {selectedCustomers.length} Customer{selectedCustomers.length !== 1 ? 's' : ''}</>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
