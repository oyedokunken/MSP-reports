import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate, ReportSection } from '../types/template'
import { useToast } from '@/components/ui/Toast'
import { Spinner } from '@/components/ui/spinner'
import { Check, Zap } from 'lucide-react'

interface EditTemplateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  template: ReportTemplate | null
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
  { value: 'Australia/Sydney', label: 'Sydney (AEDT)' },
  { value: 'Australia/Brisbane', label: 'Brisbane (AEST)' },
  { value: 'Australia/Melbourne', label: 'Melbourne (AEDT)' },
  { value: 'Australia/Adelaide', label: 'Adelaide (ACDT)' },
  { value: 'Australia/Perth', label: 'Perth (AWST)' },
  { value: 'Australia/Darwin', label: 'Darwin (ACST)' },
]

export function EditTemplateModal({ open, onOpenChange, template, onSuccess }: EditTemplateModalProps) {
  const toast = useToast()
  const [loading, setLoading] = useState(false)
  const [availableSections, setAvailableSections] = useState<ReportSection[]>([])
  const [formData, setFormData] = useState<{
    template_name: string
    description: string
    frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
    timezone: string
    time_of_day: string
    active: boolean
  }>({
    template_name: '',
    description: '',
    frequency: 'monthly',
    timezone: 'Australia/Sydney',
    time_of_day: '',
    active: true,
  })

  // Populate form when template changes
  useEffect(() => {
    if (template) {
      setFormData({
        template_name: template.template_name || '',
        description: template.description || '',
        frequency: template.frequency || 'monthly',
        timezone: template.timezone || 'Australia/Sydney',
        time_of_day: template.time_of_day || '',
        active: template.active ?? true,
      })
    }
  }, [template])

  // Fetch available sections for the report type
  useEffect(() => {
    const fetchSections = async () => {
      if (!template) return

      try {
        const reportTypes = await templateApi.getAllReportTypes()
        const reportTypeDef = reportTypes.report_types.find(
          (rt) => rt.type === template.report_type
        )
        if (reportTypeDef) {
          setAvailableSections(reportTypeDef.available_sections || [])
        }
      } catch (error) {
        console.error('Failed to fetch report type sections:', error)
      }
    }

    fetchSections()
  }, [template])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!template) return

    try {
      setLoading(true)

      const updateData: { template_name: string; description: string; frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'; timezone: string; active: boolean; time_of_day?: string } = {
        template_name: formData.template_name,
        description: formData.description,
        frequency: formData.frequency,
        timezone: formData.timezone,
        active: formData.active,
      }

      // Only include time_of_day if it has a value, and format it correctly (HH:MM)
      if (formData.time_of_day) {
        // Remove seconds if present (e.g., "14:30:00" -> "14:30")
        const timeParts = formData.time_of_day.split(':')
        updateData.time_of_day = `${timeParts[0]}:${timeParts[1]}`
      }

      await templateApi.update(template.template_id, updateData)

      toast.success('Template updated successfully')
      onSuccess?.()
      onOpenChange(false)
    } catch (error) {
      console.error('Failed to update template:', error)
      toast.error('Failed to update template')
    } finally {
      setLoading(false)
    }
  }

  const handleClose = () => {
    if (!loading) {
      onOpenChange(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit Template</DialogTitle>
          <DialogDescription>
            Update the details of your report template
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Template Name */}
          <div className="space-y-2">
            <Label htmlFor="template_name">
              Template Name <span className="text-destructive">*</span>
            </Label>
            <Input
              id="template_name"
              value={formData.template_name}
              onChange={(e) => setFormData({ ...formData, template_name: e.target.value })}
              placeholder="e.g., Monthly Security Report"
              required
              disabled={loading}
            />
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea
              id="description"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Describe what this template is for..."
              rows={3}
              disabled={loading}
            />
          </div>

          {/* Frequency */}
          <div className="space-y-2">
            <Label htmlFor="frequency">
              Frequency <span className="text-destructive">*</span>
            </Label>
            <Select
              value={formData.frequency}
              onValueChange={(value) => setFormData({ ...formData, frequency: value as 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand' })}
              disabled={loading}
            >
              <SelectTrigger id="frequency">
                <SelectValue placeholder="Select frequency" />
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

          {/* Time of Day */}
          <div className="space-y-2">
            <Label htmlFor="time_of_day">Time of Day (Optional)</Label>
            <Input
              id="time_of_day"
              type="time"
              value={formData.time_of_day}
              onChange={(e) => setFormData({ ...formData, time_of_day: e.target.value })}
              disabled={loading}
            />
            <p className="text-sm text-muted-foreground">
              Leave empty for on-demand reports
            </p>
          </div>

          {/* Timezone */}
          <div className="space-y-2">
            <Label htmlFor="timezone">
              Timezone <span className="text-destructive">*</span>
            </Label>
            <Select
              value={formData.timezone}
              onValueChange={(value) => setFormData({ ...formData, timezone: value })}
              disabled={loading}
            >
              <SelectTrigger id="timezone">
                <SelectValue placeholder="Select timezone" />
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

          {/* Active Status */}
          <div className="flex items-center justify-between rounded-lg border p-4">
            <div className="space-y-0.5">
              <Label htmlFor="active" className="text-base">
                Active Status
              </Label>
              <p className="text-sm text-muted-foreground">
                {formData.active ? 'Template is active and can be used' : 'Template is inactive'}
              </p>
            </div>
            <Switch
              id="active"
              checked={formData.active}
              onCheckedChange={(checked) => setFormData({ ...formData, active: checked })}
              disabled={loading}
            />
          </div>

          {/* Sync Impact Warning */}
          {template && template.schedules_count && template.schedules_count > 0 && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
              <div className="flex items-start gap-3">
                <div className="rounded-full bg-amber-100 p-1 mt-0.5">
                  <Zap className="h-4 w-4 text-amber-600" />
                </div>
                <div>
                  <h4 className="text-sm font-semibold text-amber-900">Sync Impact</h4>
                  <p className="text-xs text-amber-700 mt-1 leading-relaxed">
                    This template is linked to <strong>{template.schedules_count}</strong> customer {template.schedules_count === 1 ? 'schedule' : 'schedules'}.
                    Saving changes will automatically push updates to all customers <strong>except</strong> those who have manually customized their report settings.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Read-only Information */}
          {template && (
            <div className="rounded-lg border p-4 space-y-3 bg-muted/50">
              <h4 className="font-medium text-sm">Template Information</h4>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="text-muted-foreground">Report Type</p>
                  <p className="font-medium">
                    {template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                  </p>
                </div>
                {template.customer && (
                  <div>
                    <p className="text-muted-foreground">Customer</p>
                    <p className="font-medium">{template.customer.canonical_name}</p>
                  </div>
                )}
                {!template.customer_id && (
                  <div>
                    <p className="text-muted-foreground">Scope</p>
                    <p className="font-medium">Global Template</p>
                  </div>
                )}
                <div>
                  <p className="text-muted-foreground">Created</p>
                  <p className="font-medium">
                    {new Date(template.created_at).toLocaleDateString()}
                  </p>
                </div>
              </div>
              <p className="text-xs text-muted-foreground mt-2">
                Note: Report type and customer assignment cannot be changed after creation
              </p>
            </div>
          )}

          {/* Included Sections */}
          {template && template.included_sections && template.included_sections.length > 0 && (
            <div className="rounded-lg border p-4 space-y-3 bg-muted/50">
              <h4 className="font-medium text-sm">Included Report Sections</h4>
              <div className="space-y-2">
                {template.included_sections.map((sectionId) => {
                  const sectionDef = availableSections.find((s) => s.id === sectionId)
                  return (
                    <div key={sectionId} className="flex items-start gap-2 text-sm">
                      <Check className="h-4 w-4 text-primary mt-0.5 flex-shrink-0" />
                      <div className="flex-1">
                        <p className="font-medium">
                          {sectionDef?.label || sectionId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                        </p>
                        {sectionDef?.description && (
                          <p className="text-xs text-muted-foreground mt-0.5">
                            {sectionDef.description}
                          </p>
                        )}
                        {sectionDef?.requires && (
                          <p className="text-xs text-muted-foreground mt-0.5">
                            Requires: {sectionDef.requires}
                          </p>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
              <p className="text-xs text-muted-foreground mt-2">
                Note: Sections are configured during template creation and reflect the selected integrations
              </p>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={loading || !formData.template_name}>
              {loading ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Updating...
                </>
              ) : (
                'Update Template'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
