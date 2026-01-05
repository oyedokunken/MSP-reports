import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog'
import { CheckCircle2 } from 'lucide-react'

interface SuccessDialogProps {
  open: boolean
  onOpenChange?: (open: boolean) => void
  title: string
  description: string
}

export function SuccessDialog({
  open,
  onOpenChange,
  title,
  description,
}: SuccessDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <div className="flex flex-col items-center justify-center py-8 text-center">
          <div className="mb-4 rounded-full bg-green-500/10 p-3">
            <CheckCircle2 className="h-12 w-12 text-green-500" />
          </div>
          <DialogTitle className="text-2xl text-green-600 mb-2">
            {title}
          </DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </div>
      </DialogContent>
    </Dialog>
  )
}
