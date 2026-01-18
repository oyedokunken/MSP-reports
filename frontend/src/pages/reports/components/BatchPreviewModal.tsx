import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { FileText, Play, Eye, AlertTriangle } from 'lucide-react'
import { Spinner } from '@/components/ui/spinner'
import { useState } from 'react'
import { cn } from '@/lib/utils'

interface BatchPreviewReport {
  id: number
  name: string
  status: 'preview' | 'delivered'
}

interface BatchPreviewModalProps {
  open: boolean
  onClose: () => void
  reports: BatchPreviewReport[]
  onPreview: (report: BatchPreviewReport) => void
  onDeliverAll: () => Promise<void>
  onDiscard: () => Promise<void>
}

export function BatchPreviewModal({
  open,
  onClose,
  reports,
  onPreview,
  onDeliverAll,
  onDiscard,
}: BatchPreviewModalProps) {
  const [isProcessing, setIsProcessing] = useState(false)
  const [isShaking, setIsShaking] = useState(false)
  const [showActionPrompt, setShowActionPrompt] = useState(false)

  const handleDiscard = async () => {
    try {
      setIsProcessing(true)
      await onDiscard()
    } catch (error) {
      console.error('Batch discard failed:', error)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleOutsideClick = (e: any) => {
    e.preventDefault()
    setIsShaking(true)
    setShowActionPrompt(true)
    setTimeout(() => setIsShaking(false), 500)
  }

  const handleDeliverAll = async () => {
    try {
      setIsProcessing(true)
      await onDeliverAll()
      onClose()
    } catch (error) {
      console.error('Batch delivery failed:', error)
    } finally {
      setIsProcessing(false)
    }
  }

  return (
    <Dialog open={open}>
      <DialogContent 
        className={cn(
          "sm:max-w-[500px] transition-all duration-200",
          isShaking && "animate-shake border-destructive/50 shadow-[0_0_15px_rgba(239,68,68,0.2)]"
        )}
        hideClose
        onPointerDownOutside={handleOutsideClick}
        onEscapeKeyDown={handleOutsideClick}
      >
        <DialogHeader>
          <DialogTitle>Reports Ready for Review</DialogTitle>
          <DialogDescription>
            {reports.length} reports have been generated for preview. Review each before final delivery.
          </DialogDescription>
        </DialogHeader>

        <div className="py-4 space-y-2">
          {reports.map((report) => (
            <div
              key={report.id}
              className="group flex items-center justify-between p-3 rounded-lg border bg-card hover:bg-accent transition-colors cursor-pointer"
              onClick={() => onPreview(report)}
            >
              <div className="flex items-center gap-3">
                <div className="h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                  <FileText className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium leading-none">{report.name}</p>
                </div>
              </div>
              <Button variant="ghost" size="sm" className="opacity-0 group-hover:opacity-100 transition-opacity">
                <Eye className="h-4 w-4 mr-2" />
                Preview
              </Button>
            </div>
          ))}
        </div>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="outline" onClick={handleDiscard} disabled={isProcessing}>
            Discard & Close
          </Button>
          <Button
            onClick={handleDeliverAll}
            disabled={isProcessing}
            className="bg-green-600 hover:bg-green-700 text-white"
          >
            {isProcessing ? (
              <>
                <Spinner className="h-4 w-4 mr-2" />
                Processing Batch...
              </>
            ) : (
              <>
                <Play className="h-4 w-4 mr-2" />
                Deliver All Reports
              </>
            )}
          </Button>
        </DialogFooter>

        {showActionPrompt && (
          <div className="absolute inset-x-0 -bottom-16 animate-in slide-in-from-top-2 flex justify-center">
            <div className="bg-destructive text-destructive-foreground text-[11px] font-bold px-4 py-2 rounded-full shadow-lg flex items-center gap-2 border border-white/20">
              <AlertTriangle className="h-3 w-3" />
              SELECT ONE OF THESE ACTIONS TO PROCEED
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
