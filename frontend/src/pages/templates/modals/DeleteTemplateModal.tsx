import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertTriangle } from 'lucide-react'
import type { ReportTemplate } from '@/pages/templates/types/template'

interface DeleteTemplateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  template: ReportTemplate | null
  onConfirm: () => void
  isDeleting?: boolean
}

export function DeleteTemplateModal({ 
  open, 
  onOpenChange, 
  template, 
  onConfirm,
  isDeleting = false 
}: DeleteTemplateModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="rounded-full bg-destructive/10 p-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
            </div>
            <DialogTitle>Delete Template</DialogTitle>
          </div>
          <DialogDescription className="pt-3">
            Are you sure you want to delete <strong>{template?.template_name}</strong>?
            <br />
            <br />
            This action cannot be undone. The template will be permanently removed from the system.
            <br />
            <br />
            <span className="text-muted-foreground text-sm">
              Note: Existing report schedules created from this template will not be affected.
            </span>
          </DialogDescription>
        </DialogHeader>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isDeleting}
          >
            Cancel
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={isDeleting}
          >
            {isDeleting ? 'Deleting...' : 'Delete Template'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
