import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { 
  FileText, 
  Calendar, 
  Clock, 
  Globe, 
  User,
  CheckCircle2,
  Settings
} from 'lucide-react'
import type { ReportTemplate } from '../types/template'

interface TemplatePreviewModalProps {
  open: boolean
  onClose: () => void
  template: ReportTemplate | null
  onEdit?: () => void
}

export function TemplatePreviewModal({
  open,
  onClose,
  template,
  onEdit,
}: TemplatePreviewModalProps) {
  if (!template) return null

  const getFrequencyLabel = (frequency: string) => {
    return frequency.charAt(0).toUpperCase() + frequency.slice(1).replace('-', ' ')
  }

  const formatReportType = (type: string) => {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
  }

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1">
              <DialogTitle className="text-2xl">{template.template_name}</DialogTitle>
              {template.description && (
                <DialogDescription className="mt-2 text-base">
                  {template.description}
                </DialogDescription>
              )}
            </div>
            <div className="flex gap-2">
              {!template.customer_id && (
                <Badge variant="default" className="bg-primary/10 text-primary border-primary/20">
                  <Globe className="h-3 w-3 mr-1" />
                  Global
                </Badge>
              )}
              {template.active ? (
                <Badge variant="default" className="bg-green-500/10 text-green-700 border-green-500/20">
                  <CheckCircle2 className="h-3 w-3 mr-1" />
                  Active
                </Badge>
              ) : (
                <Badge variant="secondary">Inactive</Badge>
              )}
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-6 mt-4">
          <div className="rounded-lg border p-4 space-y-4">
            <h3 className="font-semibold text-lg flex items-center gap-2">
              <Settings className="h-5 w-5" />
              Template Configuration
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1">
                <div className="flex items-center gap-2 text-muted-foreground text-sm">
                  <FileText className="h-4 w-4" />
                  <span className="font-medium">Report Type</span>
                </div>
                <p className="text-foreground pl-6">{formatReportType(template.report_type)}</p>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-2 text-muted-foreground text-sm">
                  <Calendar className="h-4 w-4" />
                  <span className="font-medium">Frequency</span>
                </div>
                <p className="text-foreground pl-6">{getFrequencyLabel(template.frequency)}</p>
              </div>

              {template.customer && (
                <div className="space-y-1">
                  <div className="flex items-center gap-2 text-muted-foreground text-sm">
                    <User className="h-4 w-4" />
                    <span className="font-medium">Customer</span>
                  </div>
                  <p className="text-foreground pl-6">{template.customer.canonical_name}</p>
                </div>
              )}

              {template.time_of_day && (
                <div className="space-y-1">
                  <div className="flex items-center gap-2 text-muted-foreground text-sm">
                    <Clock className="h-4 w-4" />
                    <span className="font-medium">Schedule Time</span>
                  </div>
                  <p className="text-foreground pl-6">
                    {template.time_of_day} ({template.timezone})
                  </p>
                </div>
              )}
            </div>
          </div>

          {template.included_sections && template.included_sections.length > 0 && (
            <div className="rounded-lg border p-4 space-y-3">
              <h3 className="font-semibold text-lg flex items-center gap-2">
                <FileText className="h-5 w-5" />
                Included Report Sections ({template.included_sections.length})
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                {template.included_sections.map((section) => (
                  <div 
                    key={section} 
                    className="flex items-center gap-2 text-sm p-2 rounded-md bg-muted/50"
                  >
                    <CheckCircle2 className="h-4 w-4 text-primary flex-shrink-0" />
                    <span className="truncate">
                      {section.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {template.delivery_settings && template.delivery_settings.recipients && template.delivery_settings.recipients.length > 0 && (
            <div className="rounded-lg border p-4 space-y-3">
              <h3 className="font-semibold text-lg">Delivery Settings</h3>
              <div className="space-y-2 text-sm">
                <div className="pl-6">
                  <p className="text-muted-foreground mb-1">Recipients:</p>
                  <div className="flex flex-wrap gap-1">
                    {template.delivery_settings.recipients.map((email, idx) => (
                      <Badge key={idx} variant="outline" className="text-xs">
                        {email}
                      </Badge>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          {template.schedules_count !== undefined && template.schedules_count > 0 && (
            <div className="rounded-lg border p-4 bg-blue-50 dark:bg-blue-950/20">
              <p className="text-sm text-blue-900 dark:text-blue-100">
                <strong>{template.schedules_count}</strong> {template.schedules_count === 1 ? 'schedule' : 'schedules'} created from this template
              </p>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 mt-6 pt-4 border-t">
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
          {onEdit && (
            <Button onClick={onEdit}>
              <Settings className="h-4 w-4 mr-2" />
              Edit Template
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
