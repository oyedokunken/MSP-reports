import { useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import { Share2, Copy, Check, Clock, ExternalLink } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import apiClient from '@/lib/api'

interface ShareLinkModalProps {
  open: boolean
  onClose: () => void
  reportId: number
  reportName: string
}

export function ShareLinkModal({
  open,
  onClose,
  reportId,
  reportName,
}: ShareLinkModalProps) {
  const toast = useToast()
  const [loading, setLoading] = useState(false)
  const [shareLink, setShareLink] = useState<string | null>(null)
  const [expiresAt, setExpiresAt] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const generateShareLink = async () => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await apiClient.post(`/reports/${reportId}/share`)
      const data = response.data
      
      setShareLink(data.share_url)
      setExpiresAt(data.expires_at)
      toast.success('Share Link Generated', 'Link is valid for 1 hour')
    } catch (err: any) {
      console.error('Failed to generate share link:', err)
      const errorMessage = err.response?.data?.message || err.message || 'Failed to generate share link'
      setError(errorMessage)
      toast.error('Failed to Generate Link', errorMessage)
    } finally {
      setLoading(false)
    }
  }

  const handleCopyLink = async () => {
    if (!shareLink) return
    
    try {
      await navigator.clipboard.writeText(shareLink)
      setCopied(true)
      toast.success('Copied!', 'Share link copied to clipboard')
      setTimeout(() => setCopied(false), 2000)
    } catch (err) {
      toast.error('Copy Failed', 'Could not copy to clipboard')
    }
  }

  const handleOpenLink = () => {
    if (shareLink) {
      window.open(shareLink, '_blank')
    }
  }

  const handleClose = () => {
    setShareLink(null)
    setExpiresAt(null)
    setError(null)
    setCopied(false)
    onClose()
  }

  const formatExpiryTime = (isoString: string) => {
    const date = new Date(isoString)
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Share2 className="h-5 w-5 text-primary" />
            Quick Share Link
          </DialogTitle>
          <DialogDescription>
            Generate a temporary link to share this report externally. The link will expire in 1 hour.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Report Info */}
          <div className="p-3 bg-muted/50 rounded-lg">
            <p className="text-sm font-medium text-foreground">{reportName}</p>
          </div>

          {/* Error State */}
          {error && (
            <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
              <p className="text-sm text-destructive">{error}</p>
            </div>
          )}

          {/* Generate Button (shown when no link yet) */}
          {!shareLink && !loading && (
            <Button 
              onClick={generateShareLink} 
              className="w-full"
              disabled={loading}
            >
              <Share2 className="mr-2 h-4 w-4" />
              Generate Share Link
            </Button>
          )}

          {/* Loading State */}
          {loading && (
            <div className="flex items-center justify-center py-6">
              <Spinner className="h-8 w-8" />
              <span className="ml-3 text-sm text-muted-foreground">Generating link...</span>
            </div>
          )}

          {/* Share Link Display */}
          {shareLink && !loading && (
            <div className="space-y-4">
              {/* Link Input with Copy */}
              <div className="flex gap-2">
                <Input
                  value={shareLink}
                  readOnly
                  className="font-mono text-xs"
                  onClick={(e) => (e.target as HTMLInputElement).select()}
                />
                <Button
                  variant="outline"
                  size="icon"
                  onClick={handleCopyLink}
                  className="flex-shrink-0"
                >
                  {copied ? (
                    <Check className="h-4 w-4 text-green-600" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                </Button>
              </div>

              {/* Expiry Info */}
              {expiresAt && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Clock className="h-4 w-4" />
                  <span>Expires at {formatExpiryTime(expiresAt)} (1 hour from now)</span>
                </div>
              )}

              {/* Action Buttons */}
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  className="flex-1"
                  onClick={handleCopyLink}
                >
                  {copied ? (
                    <>
                      <Check className="mr-2 h-4 w-4 text-green-600" />
                      Copied!
                    </>
                  ) : (
                    <>
                      <Copy className="mr-2 h-4 w-4" />
                      Copy Link
                    </>
                  )}
                </Button>
                <Button
                  variant="outline"
                  className="flex-1"
                  onClick={handleOpenLink}
                >
                  <ExternalLink className="mr-2 h-4 w-4" />
                  Open Link
                </Button>
              </div>

              {/* Generate New Link */}
              <Button
                variant="ghost"
                size="sm"
                onClick={generateShareLink}
                className="w-full text-muted-foreground"
              >
                Generate New Link
              </Button>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
