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
import { Mail, CheckCircle2 } from 'lucide-react'

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
  const [success, setSuccess] = useState(false)
  const [sentEmails, setSentEmails] = useState('')

  const handleSend = async () => {
    if (!emails.trim()) {
      return
    }

    try {
      setSending(true)
      await onSend(emails)
      setSentEmails(emails) // Store the emails that were sent to
      setSuccess(true)
      // Auto-close after 3 seconds
      setTimeout(() => {
        handleClose()
      }, 3000)
    } catch (error) {
      // Error handled by parent component
    } finally {
      setSending(false)
    }
  }

  const handleClose = () => {
    if (!sending) {
      setEmails(defaultEmail) // Reset to default
      setSuccess(false)
      setSentEmails('')
      onClose()
    }
  }

  // Success state - show confirmation
  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">
              Report Sent!
            </DialogTitle>
            <DialogDescription className="text-center">
              The report has been sent to:
              <span className="block mt-2 font-medium text-foreground">
                {sentEmails}
              </span>
            </DialogDescription>
            <Button
              variant="outline"
              onClick={handleClose}
              className="mt-6"
            >
              Close
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    )
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
