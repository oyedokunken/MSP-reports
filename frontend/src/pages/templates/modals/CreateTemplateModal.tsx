import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Switch } from '@/components/ui/switch'
import { FileText, AlertCircle } from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate, CreateTemplateRequest } from '../types/template'
import { useToast } from '@/components/ui/Toast'

interface CreateTemplateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  template?: ReportTemplate | null
  onSuccess?: () => void
}

const REPORT_TYPES = [
  { value: 'autotask_monthly_summary', label: 'Autotask Monthly Summary' },
  { value: 'datto_rmm_monthly_summary', label: 'Datto RMM Monthly Summary' },
  { value: 'itglue_monthly_summary', label: 'IT Glue Monthly Summary' },
  { value: 'monthly_comprehensive', label: 'Monthly Comprehensive' },
  { value: 'uptime_report', label: 'Uptime Report' },
  { value: 'backup_status', label: 'Backup Status' },
  { value: 'security_summary', label: 'Security Summary' },
]

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
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  
  const [formData, setFormData] = useState<CreateTemplateRequest>({
    template_name: '',
    description: '',
    report_type: '',
    frequency: 'monthly',
    time_of_day: '09:00',
    timezone: 'Australia/Brisbane',
    active: true,
  })

  useEffect(() => {
    if (open) {
      if (template) {
        setFormData({
          template_name: template.template_name,
          description: template.description || '',
          report_type: template.report_type,
          frequency: template.frequency,
          schedule_config: template.schedule_config || undefined,
          time_of_day: template.time_of_day || '09:00',
          timezone: template.timezone,
          included_sections: template.included_sections || undefined,
          delivery_settings: template.delivery_settings || undefined,
          report_options: template.report_options || undefined,
          active: template.active,
        })
      } else {
        setFormData({
          template_name: '',
          description: '',
          report_type: '',
          frequency: 'monthly',
          time_of_day: '09:00',
          timezone: 'Australia/Brisbane',
          active: true,
        })
      }
      setError('')
    }
  }, [open, template])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!formData.template_name.trim()) {
      setError('Template name is required')
      return
    }

    if (!formData.report_type) {
      setError('Report type is required')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      if (template) {
        await templateApi.update(template.template_id, formData)
        toast.success('Template updated successfully')
      } else {
        await templateApi.create(formData)
        toast.success('Template created successfully')
      }
      onSuccess?.()
      onOpenChange(false)
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to save template'
      setError(errorMessage)
      toast.error(errorMessage)
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
      <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <DialogTitle>{template ? 'Edit Template' : 'Create Template'}</DialogTitle>
          </div>
          <DialogDescription>
            {template ? 'Update template settings' : 'Create a new report template to apply to customers'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="template_name">Template Name *</Label>
            <Input
              id="template_name"
              value={formData.template_name}
              onChange={(e) => setFormData({ ...formData, template_name: e.target.value })}
              placeholder="e.g., Standard Monthly Report"
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

          <div>
            <Label htmlFor="report_type">Report Type *</Label>
            <Select
              value={formData.report_type}
              onValueChange={(value) => setFormData({ ...formData, report_type: value })}
              disabled={isSubmitting}
            >
              <SelectTrigger className="mt-1">
                <SelectValue placeholder="Select report type" />
              </SelectTrigger>
              <SelectContent>
                {REPORT_TYPES.map((type) => (
                  <SelectItem key={type.value} value={type.value}>
                    {type.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

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
              <Label htmlFor="active">Active</Label>
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
                  {template ? 'Updating...' : 'Creating...'}
                </>
              ) : (
                <>{template ? 'Update Template' : 'Create Template'}</>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
