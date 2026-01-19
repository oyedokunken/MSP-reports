import { useState, useEffect, useCallback } from 'react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { ZoomIn, ZoomOut, Maximize2, CheckCircle2, XCircle, AlertTriangle } from 'lucide-react'
import apiClient from '@/lib/api'
import { cn } from '@/lib/utils'

interface ReportPreviewModalProps {
  open: boolean
  onClose: () => void
  reportId: number
  reportName: string
  onConfirm?: () => Promise<void>
  confirmLabel?: string
  onDiscard?: () => Promise<void>
  discardLabel?: string
}

export function ReportPreviewModal({
  open,
  onClose,
  reportId,
  reportName,
  onConfirm,
  confirmLabel = 'Process Report',
  onDiscard,
  discardLabel = 'Discard & Close',
}: ReportPreviewModalProps) {
  const [loading, setLoading] = useState(true)
  const [isProcessing, setIsProcessing] = useState(false)
  const [isDiscarding, setIsDiscarding] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [pdfUrl, setPdfUrl] = useState<string | null>(null)
  const [zoom, setZoom] = useState(100)
  const [isShaking, setIsShaking] = useState(false)
  const [showActionPrompt, setShowActionPrompt] = useState(false)

  const loadPdfPreview = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)

      // Use apiClient which handles the correct base URL and auth headers automatically
      const response = await apiClient.get(`/reports/${reportId}/download`, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf',
        },
      })

      // Create object URL from the blob
      const blob = new Blob([response.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      setPdfUrl(url)
    } catch (err: unknown) {
      console.error('Failed to load PDF preview:', err)
      const error = err as { response?: { data?: { message?: string }; statusText?: string }; message?: string }
      const errorMessage = error.response?.data?.message
        || error.response?.statusText
        || error.message
        || 'Failed to load PDF preview'
      setError(errorMessage)
    } finally {
      setLoading(false)
    }
  }, [reportId])

  useEffect(() => {
    if (open && reportId) {
      if (pdfUrl) {
        URL.revokeObjectURL(pdfUrl)
        setPdfUrl(null)
      }
      loadPdfPreview()
    }

    return () => {
      if (pdfUrl) {
        URL.revokeObjectURL(pdfUrl)
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, reportId])

  const handleZoomIn = () => {
    setZoom((prev) => Math.min(prev + 25, 200))
  }

  const handleZoomOut = () => {
    setZoom((prev) => Math.max(prev - 25, 50))
  }

  const handleResetZoom = () => {
    setZoom(100)
  }

  const handleConfirm = async () => {
    if (!onConfirm) return
    try {
      setIsProcessing(true)
      await onConfirm()
      onClose()
    } catch (error) {
      console.error('Confirmation failed:', error)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleDiscard = async () => {
    if (!onDiscard) {
      onClose()
      return
    }
    try {
      setIsDiscarding(true)
      await onDiscard()
      onClose()
    } catch (error) {
      console.error('Discard failed:', error)
    } finally {
      setIsDiscarding(false)
    }
  }

  const handleOutsideClick = (e: any) => {
    if (onConfirm || onDiscard) {
      e.preventDefault()
      setIsShaking(true)
      setShowActionPrompt(true)
      setTimeout(() => setIsShaking(false), 500)
    } else {
      onClose()
    }
  }

  return (
    <Dialog open={open} onOpenChange={onDiscard || onConfirm ? handleOutsideClick : onClose}>
      <DialogContent 
        className={cn(
          "max-w-6xl h-[90vh] flex flex-col p-0 transition-all duration-200",
          isShaking && "animate-shake border-destructive/50 shadow-[0_0_15px_rgba(239,68,68,0.2)]"
        )}
        hideClose={!!onConfirm || !!onDiscard}
        onPointerDownOutside={handleOutsideClick}
        onEscapeKeyDown={handleOutsideClick}
      >
        <DialogHeader className="px-6 pt-6 pb-4 border-b">
          <div className="flex items-center justify-between">
            <div>
              <DialogTitle>Report Preview</DialogTitle>
              <DialogDescription className="mt-1">{reportName}</DialogDescription>
            </div>
          </div>

          {/* Toolbar */}
          <div className="flex items-center justify-between gap-2 mt-4">
            <div className="flex items-center gap-1 border rounded-md">
              <Button
                variant="ghost"
                size="sm"
                onClick={handleZoomOut}
                disabled={zoom <= 50}
                className="h-8"
              >
                <ZoomOut className="h-4 w-4" />
              </Button>
              <span className="text-sm font-medium px-2 min-w-[60px] text-center">
                {zoom}%
              </span>
              <Button
                variant="ghost"
                size="sm"
                onClick={handleZoomIn}
                disabled={zoom >= 200}
                className="h-8"
              >
                <ZoomIn className="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={handleResetZoom}
                className="h-8"
              >
                <Maximize2 className="h-4 w-4" />
              </Button>
            </div>

            {/* Action Buttons */}
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleDiscard}
                disabled={isProcessing || isDiscarding}
              >
                {isDiscarding ? (
                  <Spinner className="h-4 w-4 mr-2" />
                ) : (
                  <XCircle className="h-4 w-4 mr-2" />
                )}
                {discardLabel}
              </Button>
              
              {onConfirm && (
                <Button
                  onClick={handleConfirm}
                  disabled={loading || !!error || isProcessing || isDiscarding}
                  size="sm"
                  className="bg-green-600 hover:bg-green-700 text-white"
                >
                  {isProcessing ? (
                    <>
                      <Spinner className="h-4 w-4 mr-2" />
                      Processing...
                    </>
                  ) : (
                    <>
                      <CheckCircle2 className="h-4 w-4 mr-2" />
                      {confirmLabel}
                    </>
                  )}
                </Button>
              )}
            </div>
          </div>
        </DialogHeader>

        {showActionPrompt && (
          <div className="absolute inset-x-0 -bottom-16 animate-in slide-in-from-top-2 flex justify-center z-50">
            <div className="bg-destructive text-destructive-foreground text-[11px] font-bold px-4 py-2 rounded-full shadow-lg flex items-center gap-2 border border-white/20">
              <AlertTriangle className="h-3 w-3" />
              SELECT ONE OF THESE ACTIONS TO PROCEED
            </div>
          </div>
        )}

        {/* PDF Preview */}
        <div className="flex-1 overflow-auto bg-gray-100 dark:bg-gray-900">
          {loading && (
            <div className="flex items-center justify-center h-full">
              <div className="text-center">
                <Spinner className="h-8 w-8 mx-auto mb-4" />
                <p className="text-sm text-muted-foreground">Loading PDF preview...</p>
              </div>
            </div>
          )}

          {error && (
            <div className="flex items-center justify-center h-full">
              <div className="text-center max-w-md">
                <p className="text-destructive font-medium mb-2">Failed to load preview</p>
                <p className="text-sm text-muted-foreground">{error}</p>
                <Button onClick={loadPdfPreview} variant="outline" size="sm" className="mt-4">
                  Try Again
                </Button>
              </div>
            </div>
          )}

          {!loading && !error && pdfUrl && (
            <div className="flex justify-center w-full p-4">
              <iframe
                src={`${pdfUrl}#view=FitH&scrollbar=0`}
                className="bg-white shadow-lg"
                style={{
                  width: `${zoom}%`,
                  minWidth: '800px',
                  height: '100vh',
                  minHeight: '1200px',
                  border: 'none',
                  overflow: 'hidden',
                }}
                title="PDF Preview"
                scrolling="no"
              />
            </div>
          )}
        </div>
      </DialogContent>

    </Dialog>
  )
}
