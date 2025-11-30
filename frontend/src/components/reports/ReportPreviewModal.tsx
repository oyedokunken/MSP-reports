import { useState, useEffect } from 'react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { ZoomIn, ZoomOut, Maximize2 } from 'lucide-react'

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
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pdfUrl, setPdfUrl] = useState<string | null>(null)
  const [zoom, setZoom] = useState(100)

  useEffect(() => {
    if (open && reportId) {
      loadPdfPreview()
    }

    return () => {
      if (pdfUrl) {
        URL.revokeObjectURL(pdfUrl)
      }
    }
  }, [open, reportId])

  const loadPdfPreview = async () => {
    try {
      setLoading(true)
      setError(null)

      const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8081/api'
      const fetchUrl = `${apiUrl}/reports/${reportId}/download`
      const token = localStorage.getItem('auth_token')
      
      console.log('Fetching PDF:', fetchUrl)
      console.log('Report ID:', reportId)
      console.log('Token exists:', !!token)

      // Fetch the PDF as a blob
      const response = await fetch(fetchUrl, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/pdf',
        },
      })

      if (!response.ok) {
        const errorText = await response.text()
        console.error('API Error Response:', errorText)
        console.error('Status:', response.status, response.statusText)
        throw new Error(`Failed to fetch PDF: ${response.statusText} (${response.status})`)
      }

      const blob = await response.blob()
      console.log('PDF Blob:', blob.type, blob.size)
      
      // Create object URL
      const url = URL.createObjectURL(blob)
      console.log('PDF URL:', url)
      setPdfUrl(url)
    } catch (err: any) {
      console.error('Failed to load PDF preview:', err)
      setError(err.message || 'Failed to load PDF preview')
    } finally {
      setLoading(false)
    }
  }

  const handleZoomIn = () => {
    setZoom((prev) => Math.min(prev + 25, 200))
  }

  const handleZoomOut = () => {
    setZoom((prev) => Math.max(prev - 25, 50))
  }

  const handleResetZoom = () => {
    setZoom(100)
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
          <div className="flex items-center gap-2 mt-4">
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
          </div>
        </DialogHeader>

        {/* PDF Preview */}
        <div className="flex-1 overflow-hidden bg-gray-100 dark:bg-gray-900">
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
            <div className="flex justify-center w-full h-full">
              <iframe
                src={pdfUrl}
                className="bg-white shadow-lg w-full"
                style={{
                  width: `${zoom}%`,
                  minWidth: '800px',
                  height: '100%',
                  minHeight: '1000px',
                  border: 'none',
                }}
                title="PDF Preview"
              />
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
