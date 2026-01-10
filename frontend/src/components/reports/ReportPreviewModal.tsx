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
import { ZoomIn, ZoomOut, Maximize2, Mail } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import apiClient from '@/lib/api'
import { EmailReportDialog } from './EmailReportDialog'

interface ReportPreviewModalProps {
  open: boolean
  onClose: () => void
  reportId: number
  reportName: string
}

export function ReportPreviewModal({
  open,
  onClose,
  reportId,
  reportName,
}: ReportPreviewModalProps) {
  const toast = useToast()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pdfUrl, setPdfUrl] = useState<string | null>(null)
  const [zoom, setZoom] = useState(100)
  const [emailDialogOpen, setEmailDialogOpen] = useState(false)

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
      // Always reload when modal opens or reportId changes
      // Clean up old URL first if it exists
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

  const handleEmailReport = async (emails: string) => {
    try {
      await apiClient.post(`/reports/${reportId}/email`, {
        emails: emails
      })

      toast.success('Email Sent!', 'Report has been emailed successfully')
    } catch (err: unknown) {
      console.error('Failed to email report:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message
        || error.message
        || 'Failed to send email'
      toast.error('Email Failed', errorMessage)
      throw err // Re-throw so dialog knows to stay open
    }
  }

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-6xl h-[90vh] flex flex-col p-0">
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

            {/* Email Report Button */}
            <Button
              onClick={() => setEmailDialogOpen(true)}
              disabled={loading || !!error}
              size="sm"
              className="bg-blue-600 hover:bg-blue-700 text-white"
            >
              <Mail className="h-4 w-4 mr-2" />
              Email Report
            </Button>
          </div>
        </DialogHeader>

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

      {/* Email Report Dialog */}
      <EmailReportDialog
        open={emailDialogOpen}
        onClose={() => setEmailDialogOpen(false)}
        onSend={handleEmailReport}
        reportName={reportName}
      />
    </Dialog>
  )
}
