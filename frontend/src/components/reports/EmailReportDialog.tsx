import { useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { Mail } from 'lucide-react'

interface EmailReportDialogProps {
  open: boolean
  onClose: () => void
  onSend: (emails: string) => Promise<void>
  reportName: string
  defaultEmail?: string
}

export function EmailReportDialog({
  open,
  onClose,
  onSend,
  reportName,
  defaultEmail = '',
}: EmailReportDialogProps) {
  const [emails, setEmails] = useState(defaultEmail)
  const [sending, setSending] = useState(false)

  const handleSend = async () => {
    if (!emails.trim()) {
      return
    }

    try {
      setSending(true)
      await onSend(emails)
      setEmails(defaultEmail) // Reset to default
      onClose()
    } catch (error) {
      // Error handled by parent component
    } finally {
      setSending(false)
    }
  }

  const handleClose = () => {
    if (!sending) {
      setEmails(defaultEmail) // Reset to default
      onClose()
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Email Report</DialogTitle>
          <DialogDescription>
            Send "{reportName}" to one or more email addresses
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="emails">Email Address(es)</Label>
            <Input
              id="emails"
              type="text"
              placeholder="email@example.com, another@example.com"
              value={emails}
              onChange={(e) => setEmails(e.target.value)}
              disabled={sending}
              className="w-full"
            />
            <p className="text-sm text-muted-foreground">
              Enter one or more email addresses separated by commas
            </p>
          </div>
        </div>

        <DialogFooter className="flex gap-2 sm:gap-0">
          <Button
            variant="outline"
            onClick={handleClose}
            disabled={sending}
          >
            Cancel
          </Button>
          <Button
            onClick={handleSend}
            disabled={sending || !emails.trim()}
            className="bg-blue-600 hover:bg-blue-700 text-white"
          >
            {sending ? (
              <>
                <Spinner className="h-4 w-4 mr-2" />
                Sending...
              </>
            ) : (
              <>
                <Mail className="h-4 w-4 mr-2" />
                Send Email
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
