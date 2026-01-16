import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { FileText, AlertCircle, CheckCircle2 } from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate } from '@/pages/templates/types/template'
import { useToast } from '@/components/ui/Toast'

interface Customer {
  customer_id: number
  canonical_name?: string
  name?: string
}

interface ApplyTemplateToCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customer: Customer | null
  onSuccess?: () => void
}

export function ApplyTemplateToCustomerModal({ open, onOpenChange, customer, onSuccess }: ApplyTemplateToCustomerModalProps) {
  const toast = useToast()
  const [templates, setTemplates] = useState<ReportTemplate[]>([])
  const [loading, setLoading] = useState(false)
  const [applying, setApplying] = useState(false)
  const [selectedTemplate, setSelectedTemplate] = useState<number | null>(null)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (open) {
      loadTemplates()
      setSelectedTemplate(null)
      setSuccess(false)
      setError('')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const loadTemplates = async () => {
    try {
      setLoading(true)
      const data = await templateApi.list({ active: true })
      setTemplates(data || [])
    } catch (error) {
      toast.error('Failed to load templates')
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  const handleApply = async () => {
    if (!selectedTemplate || !customer) return

    setError('')
    setApplying(true)

    try {
      await templateApi.applyToCustomer(selectedTemplate, {
        customer_id: customer.customer_id!,
      })

      setSuccess(true)
      toast.success('Template applied successfully')

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
      setApplying(false)
    }
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Template Applied!</DialogTitle>
            <DialogDescription className="text-center">
              The template has been successfully applied to {customer?.canonical_name || customer?.name}.
              A report schedule has been created.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <DialogTitle>Apply Template</DialogTitle>
          </div>
          <DialogDescription>
            Select a template to apply to {customer?.canonical_name || customer?.name}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner className="h-6 w-6" />
            </div>
          ) : templates.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
              <FileText className="h-8 w-8 mb-2" />
              <p className="text-sm">No active templates found</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-[400px] overflow-y-auto">
              {templates.map((template) => (
                <button
                  key={template.template_id}
                  onClick={() => setSelectedTemplate(template.template_id)}
                  className={`w-full text-left p-4 rounded-lg border transition-all ${selectedTemplate === template.template_id
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/50 hover:bg-muted/50'
                    }`}
                >
                  <div className="font-medium">{template.template_name}</div>
                  {template.description && (
                    <div className="text-sm text-muted-foreground mt-1">{template.description}</div>
                  )}
                  <div className="text-xs text-muted-foreground mt-2">
                    {template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} • {template.frequency}
                    {template.time_of_day && ` • ${template.time_of_day}`}
                  </div>
                </button>
              ))}
            </div>
          )}

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={applying}
          >
            Cancel
          </Button>
          <Button
            onClick={handleApply}
            disabled={!selectedTemplate || applying}
          >
            {applying ? (
              <>
                <Spinner className="mr-2 h-4 w-4" />
                Applying...
              </>
            ) : (
              'Apply Template'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
