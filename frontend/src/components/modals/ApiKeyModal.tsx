import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Copy, CheckCircle2, AlertTriangle } from 'lucide-react'

interface ApiKeyModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  apiKey: string
}

export function ApiKeyModal({ open, onOpenChange, apiKey }: ApiKeyModalProps) {
  const [copied, setCopied] = useState(false)

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(apiKey)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch (error) {
      logger.error('Failed to copy:', error)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/20">
              <AlertTriangle className="h-6 w-6 text-orange-600 dark:text-orange-500" />
            </div>
            <div>
              <DialogTitle>New API Key Generated</DialogTitle>
              <DialogDescription className="mt-1">
                Copy this key now. It will only be shown once.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-foreground">API Key</label>
            <div className="relative">
              <input
                type="text"
                readOnly
                value={apiKey}
                className="w-full rounded-md border border-input bg-background px-3 py-2 pr-12 text-sm text-foreground font-mono"
              />
              <button
                onClick={handleCopy}
                className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-2 hover:bg-muted transition-colors"
                title="Copy to clipboard"
              >
                {copied ? (
                  <CheckCircle2 className="h-4 w-4 text-green-600" />
                ) : (
                  <Copy className="h-4 w-4 text-muted-foreground" />
                )}
              </button>
            </div>
          </div>

          <div className="rounded-lg bg-orange-50 dark:bg-orange-950/20 p-3 border border-orange-200 dark:border-orange-900/50">
            <p className="text-xs text-orange-800 dark:text-orange-300">
              <strong>Important:</strong> Store this key securely. You won't be able to see it again after closing this dialog.
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button
            onClick={() => onOpenChange(false)}
            variant="default"
          >
            I've Saved the Key
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
