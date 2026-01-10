import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Switch } from '@/components/ui/switch'
import { FileText, AlertCircle, ArrowLeft, ArrowRight, Save, Globe, User } from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import { customerApi } from '@/services/api/customers'
import type { ReportTemplate, CreateTemplateRequest, ReportTypeDefinition } from '../types/template'
import type { Customer } from '@/types/api/customer'
import { useToast } from '@/components/ui/Toast'
import { CustomerIntegrationSelector } from '../components/CustomerIntegrationSelector'
import { ReportTypeSelector } from '../components/ReportTypeSelector'
import { SectionConfigurator } from '../components/SectionConfigurator'

interface CreateTemplateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  template?: ReportTemplate | null
  onSuccess?: () => void
}

const FREQUENCIES = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'on-demand', label: 'On Demand' },
]

const TIMEZONES = [
  { value: 'Australia/Brisbane', label: 'Brisbane (AEST)' },
  { value: 'Australia/Sydney', label: 'Sydney (AEDT)' },
  { value: 'Australia/Melbourne', label: 'Melbourne (AEDT)' },
  { value: 'Australia/Adelaide', label: 'Adelaide (ACDT)' },
  { value: 'Australia/Perth', label: 'Perth (AWST)' },
  { value: 'Australia/Darwin', label: 'Darwin (ACST)' },
]

export function CreateTemplateModal({ open, onOpenChange, template, onSuccess }: CreateTemplateModalProps) {
  const toast = useToast()
  const [step, setStep] = useState(1)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isLoadingData, setIsLoadingData] = useState(false)
  const [error, setError] = useState('')

  const [customers, setCustomers] = useState<Customer[]>([])
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
  const [availableReportTypes, setAvailableReportTypes] = useState<ReportTypeDefinition[]>([])
  const [currentPage, setCurrentPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [customerSearch, setCustomerSearch] = useState('')
  const [searchDebounce, setSearchDebounce] = useState<number | null>(null)
  const [isGlobalTemplate, setIsGlobalTemplate] = useState(false)

  const [formData, setFormData] = useState<CreateTemplateRequest>({
    template_name: '',
    description: '',
    report_type: '',
    frequency: 'monthly',
    time_of_day: '09:00',
    timezone: 'Australia/Brisbane',
    active: true,
    included_sections: [],
  })

  // Load initial data
  useEffect(() => {
    if (open) {
      fetchCustomers(1)
      setCurrentPage(1)
      if (template) {
        setFormData({
          template_name: template.template_name,
          description: template.description || '',
          report_type: template.report_type,
          frequency: template.frequency,
          schedule_config: template.schedule_config || undefined,
          time_of_day: template.time_of_day || '09:00',
          timezone: template.timezone,
          included_sections: template.included_sections || [],
          delivery_settings: template.delivery_settings || undefined,
          report_options: template.report_options || undefined,
          active: template.active,
        })
        setSelectedCustomerId(template.customer_id)
        setIsGlobalTemplate(template.customer_id === null)
        // Skip customer and report type selection when editing - go straight to sections
        setStep(3)
        // Load report types for the customer (needed for section configuration)
        if (template.customer_id) {
          fetchAvailableReportTypes(template.customer_id)
        }
      } else {
        resetForm()
      }
      setError('')
    }
  }, [open, template])

  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && newPage <= totalPages) {
      fetchCustomers(newPage, customerSearch)
    }
  }

  const handleSearchChange = (search: string) => {
    setCustomerSearch(search)

    // Clear existing timeout
    if (searchDebounce) {
      clearTimeout(searchDebounce)
    }

    // Set new timeout to search after 500ms of no typing
    const timeout = setTimeout(() => {
      setCurrentPage(1)
      fetchCustomers(1, search)
    }, 500)

    setSearchDebounce(timeout)
  }

  // Fetch report types when customer is selected OR when global template mode is enabled
  useEffect(() => {
    if (isGlobalTemplate) {
      fetchAllReportTypes()
    } else if (selectedCustomerId) {
      fetchAvailableReportTypes(selectedCustomerId)
    }
  }, [selectedCustomerId, isGlobalTemplate])

  // Auto-select default sections when report type changes (only for new templates)
  useEffect(() => {
    // Don't auto-select sections when editing an existing template, unless the type changed
    if (template && formData.report_type === template.report_type) {
      return
    }

    if (formData.report_type) {
      const reportType = availableReportTypes.find(rt => rt.type === formData.report_type)
      if (reportType) {
        const defaultSections = reportType.available_sections
          .filter(s => s.default)
          .map(s => s.id)
        setFormData(prev => ({ ...prev, included_sections: defaultSections }))
      }
    }
  }, [formData.report_type, availableReportTypes, template])

  const resetForm = () => {
    setFormData({
      template_name: '',
      description: '',
      report_type: '',
      frequency: 'monthly',
      time_of_day: '09:00',
      timezone: 'Australia/Brisbane',
      active: true,
      included_sections: [],
    })
    setStep(1)
    setSelectedCustomerId(null)
    setIsGlobalTemplate(false)
    setAvailableReportTypes([])
    setCustomerSearch('')
    if (searchDebounce) {
      clearTimeout(searchDebounce)
      setSearchDebounce(null)
    }
  }

  const fetchCustomers = async (page: number = 1, search: string = '') => {
    try {
      setIsLoadingData(true)
      const response = await customerApi.getCustomers({
        page,
        search: search || undefined
      })
      setCustomers(response.data || [])
      setCurrentPage(response.pagination?.current_page || 1)
      setTotalPages(response.pagination?.last_page || 1)
    } catch (err) {
      console.error('Failed to fetch customers:', err)
      toast.error('Failed to load customers')
    } finally {
      setIsLoadingData(false)
    }
  }

  const fetchAvailableReportTypes = async (customerId: number) => {
    try {
      setIsLoadingData(true)
      const data = await templateApi.getAvailableReportTypes(customerId)
      setAvailableReportTypes(data.report_types)

      if (formData.report_type && !data.report_types.some((rt: ReportTypeDefinition) => rt.type === formData.report_type)) {
        setFormData(prev => ({ ...prev, report_type: '', included_sections: [] }))
      }
    } catch (err: any) {
      console.error('Failed to fetch report types:', err)
      console.error('Error response:', err.response?.data)
      console.error('Error status:', err.response?.status)
      const errorMsg = err.response?.data?.message || err.response?.data?.errors || 'Failed to load available report types'
      toast.error(typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg))
    } finally {
      setIsLoadingData(false)
    }
  }

  const fetchAllReportTypes = async () => {
    try {
      setIsLoadingData(true)
      const data = await templateApi.getAllReportTypes()
      setAvailableReportTypes(data.report_types)
    } catch (err: any) {
      console.error('Failed to fetch all report types:', err)
      const errorMsg = err.response?.data?.message || 'Failed to load report types'
      toast.error(typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg))
    } finally {
      setIsLoadingData(false)
    }
  }

  const handleNext = () => {
    if (step < 4) setStep(step + 1)
  }

  const handleBack = () => {
    // Constraint removed to allow navigation

    if (step > 1) setStep(step - 1)
  }

  const canProceed = () => {
    switch (step) {
      case 1:
        // For global templates, only need template name. For customer-specific, need customer selection too.
        if (isGlobalTemplate) {
          return formData.template_name.trim() !== ''
        }
        return selectedCustomerId !== null && formData.template_name.trim() !== ''
      case 2:
        return formData.report_type !== ''
      case 3:
        return (formData.included_sections?.length ?? 0) > 0
      case 4:
        return true
      default:
        return false
    }
  }

  const handleSubmit = async (e?: React.FormEvent) => {
    if (e) e.preventDefault()

    // For customer-specific templates, require customer selection
    if (!isGlobalTemplate && !selectedCustomerId) {
      setError('Customer is required for customer-specific templates')
      setStep(1)
      return
    }

    if (!formData.template_name.trim()) {
      setError('Template name is required')
      setStep(1)
      return
    }

    if (!formData.report_type) {
      setError('Report type is required')
      setStep(2)
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      // Ensure time_of_day is in correct format (HH:MM)
      let cleanTimeOfDay = '09:00'
      if (formData.time_of_day) {
        const timeStr = String(formData.time_of_day).trim()
        // Check if it matches HH:MM format
        if (/^\d{2}:\d{2}$/.test(timeStr)) {
          cleanTimeOfDay = timeStr
        }
      }

      const templateData = {
        ...formData,
        customer_id: isGlobalTemplate ? null : selectedCustomerId,
        time_of_day: cleanTimeOfDay,
      }

      if (template) {
        await templateApi.update(template.template_id, templateData)
        toast.success('Template updated successfully')
      } else {
        await templateApi.create(templateData)
        toast.success(isGlobalTemplate
          ? 'Global template created. Apply it to customers to create schedules.'
          : 'Template created and schedule activated for customer'
        )
      }
      onSuccess?.()
      onOpenChange(false)
    } catch (err: any) {
      console.error('Template save error:', err)
      console.error('Error response:', err.response)
      console.error('Error message:', err.message)
      console.error('Error errors:', err.errors)

      // Handle validation errors (422)
      if (err.errors) {
        const errors = err.errors
        const errorMessages = Object.entries(errors)
          .map(([field, messages]) => `${field}: ${(messages as string[]).join(', ')}`)
          .join('\n')
        setError(errorMessages)
        toast.error('Validation failed: ' + errorMessages)
      } else {
        const errorMessage = err.message || 'Failed to save template'
        setError(errorMessage)
        toast.error(errorMessage)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      onOpenChange(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <DialogTitle>
              {template ? 'Edit Template' : 'Create Template'} - Step {step} of 4
            </DialogTitle>
          </div>
          <DialogDescription>
            {step === 1 && 'Basic information and customer selection'}
            {step === 2 && 'Choose a report type compatible with customer integrations'}
            {step === 3 && 'Select sections to include in the report'}
            {step === 4 && 'Configure schedule and delivery settings'}
          </DialogDescription>
        </DialogHeader>

        <div className="py-4 space-y-6">
          {step === 1 && (
            <div className="space-y-4">
              {/* Global Template Toggle */}
              <div className="flex items-center justify-between rounded-lg border p-4 bg-muted/30">
                <div className="flex items-center gap-3">
                  {isGlobalTemplate ? (
                    <Globe className="h-5 w-5 text-primary" />
                  ) : (
                    <User className="h-5 w-5 text-muted-foreground" />
                  )}
                  <div className="space-y-0.5">
                    <Label htmlFor="global-toggle" className="cursor-pointer">
                      {isGlobalTemplate ? 'Global Template' : 'Customer-Specific Template'}
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      {isGlobalTemplate
                        ? 'Can be applied to any customer later'
                        : 'Creates a schedule for a specific customer'
                      }
                    </p>
                  </div>
                </div>
                <Switch
                  id="global-toggle"
                  checked={isGlobalTemplate}
                  onCheckedChange={(checked) => {
                    setIsGlobalTemplate(checked)
                    if (checked) {
                      setSelectedCustomerId(null)
                    }
                  }}
                  disabled={isSubmitting || !!template}
                />
              </div>

              <div>
                <Label htmlFor="template_name">Template Name *</Label>
                <Input
                  id="template_name"
                  value={formData.template_name}
                  onChange={(e) => setFormData({ ...formData, template_name: e.target.value })}
                  placeholder={isGlobalTemplate ? "e.g., End of Month Report" : "e.g., ACME Corp Monthly Report"}
                  disabled={isSubmitting}
                  className="mt-1"
                />
              </div>

              <div>
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={formData.description}
                  onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Optional description of this template"
                  disabled={isSubmitting}
                  className="mt-1"
                  rows={2}
                />
              </div>

              {/* Customer Selection - only show for customer-specific templates */}
              {!isGlobalTemplate && (
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <label className="text-sm font-medium">Select Customer *</label>
                    {totalPages > 1 && (
                      <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Page {currentPage} of {totalPages}</span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => handlePageChange(currentPage - 1)}
                          disabled={currentPage === 1 || isLoadingData}
                          className="h-6 px-2 text-xs"
                        >
                          Previous
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => handlePageChange(currentPage + 1)}
                          disabled={currentPage === totalPages || isLoadingData}
                          className="h-6 px-2 text-xs"
                        >
                          Next
                        </Button>
                      </div>
                    )}
                  </div>

                  {/* Customer Search */}
                  <div className="mb-3">
                    <Input
                      type="text"
                      placeholder="Search customers..."
                      value={customerSearch}
                      onChange={(e) => handleSearchChange(e.target.value)}
                      disabled={isLoadingData}
                      className="h-8 text-sm"
                    />
                  </div>

                  <CustomerIntegrationSelector
                    customers={customers}
                    selectedCustomerId={selectedCustomerId}
                    onSelect={(customer) => setSelectedCustomerId(customer.customer_id)}
                  />
                </div>
              )}
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              {isLoadingData ? (
                <div className="flex flex-col items-center py-8">
                  <Spinner className="h-8 w-8 mb-2" />
                  <p className="text-sm text-muted-foreground">Loading compatible report types...</p>
                </div>
              ) : (
                <ReportTypeSelector
                  reportTypes={availableReportTypes}
                  selectedType={formData.report_type}
                  onSelect={(type) => setFormData({ ...formData, report_type: type })}
                />
              )}
            </div>
          )}

          {step === 3 && (
            <div className="space-y-4">
              <SectionConfigurator
                sections={availableReportTypes.find(rt => rt.type === formData.report_type)?.available_sections || []}
                selectedSections={formData.included_sections || []}
                onSectionsChange={(sections) => setFormData({ ...formData, included_sections: sections })}
              />
            </div>
          )}

          {step === 4 && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="frequency">Frequency</Label>
                  <Select
                    value={formData.frequency}
                    onValueChange={(value: any) => setFormData({ ...formData, frequency: value })}
                    disabled={isSubmitting}
                  >
                    <SelectTrigger className="mt-1">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {FREQUENCIES.map((freq) => (
                        <SelectItem key={freq.value} value={freq.value}>
                          {freq.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div>
                  <Label htmlFor="time_of_day">Time of Day</Label>
                  <Input
                    id="time_of_day"
                    type="time"
                    value={formData.time_of_day}
                    onChange={(e) => setFormData({ ...formData, time_of_day: e.target.value })}
                    disabled={isSubmitting}
                    className="mt-1"
                  />
                </div>
              </div>

              <div>
                <Label htmlFor="timezone">Timezone</Label>
                <Select
                  value={formData.timezone}
                  onValueChange={(value) => setFormData({ ...formData, timezone: value })}
                  disabled={isSubmitting}
                >
                  <SelectTrigger className="mt-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {TIMEZONES.map((tz) => (
                      <SelectItem key={tz.value} value={tz.value}>
                        {tz.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <Label htmlFor="active">Active Status</Label>
                  <p className="text-sm text-muted-foreground">
                    Enable this template for use
                  </p>
                </div>
                <Switch
                  id="active"
                  checked={formData.active}
                  onCheckedChange={(checked) => setFormData({ ...formData, active: checked })}
                  disabled={isSubmitting}
                />
              </div>
            </div>
          )}

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}
        </div>

        <DialogFooter className="border-t pt-4">
          <div className="flex justify-between w-full">
            <Button
              type="button"
              variant="outline"
              onClick={step === 1 ? handleClose : handleBack}
              disabled={isSubmitting}
            >
              {step === 1 ? 'Cancel' : (
                <>
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  Back
                </>
              )}
            </Button>

            <div className="flex gap-2">
              {step < 4 ? (
                <Button
                  type="button"
                  onClick={handleNext}
                  disabled={!canProceed() || isLoadingData}
                >
                  Next
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              ) : (
                <Button
                  type="button"
                  onClick={() => handleSubmit()}
                  disabled={isSubmitting || !canProceed()}
                >
                  {isSubmitting ? (
                    <>
                      <Spinner className="mr-2 h-4 w-4" />
                      {template ? 'Updating...' : 'Creating...'}
                    </>
                  ) : (
                    <>
                      <Save className="mr-2 h-4 w-4" />
                      {template ? 'Update Template' : 'Create Template'}
                    </>
                  )}
                </Button>
              )}
            </div>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
