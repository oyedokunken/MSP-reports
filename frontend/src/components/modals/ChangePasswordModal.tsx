import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, Lock, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'

interface ChangePasswordModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

export function ChangePasswordModal({
  open,
  onOpenChange,
  onSuccess
}: ChangePasswordModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!currentPassword.trim()) {
      setError('Please enter your current password')
      return
    }

    if (!newPassword.trim()) {
      setError('Please enter a new password')
      return
    }

    if (newPassword.length < 8) {
      setError('New password must be at least 8 characters')
      return
    }

    if (newPassword !== confirmPassword) {
      setError('New passwords do not match')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await api.post('/auth/password/change', {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        handleClose()
        onSuccess?.()
      }, 2000)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'Failed to change password'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setCurrentPassword('')
      setNewPassword('')
      setConfirmPassword('')
      setError('')
      setSuccess(false)
      onOpenChange(false)
    }
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Password Changed!</DialogTitle>
            <DialogDescription className="text-center">
              Your password has been updated successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Lock className="h-5 w-5 text-primary" />
            <DialogTitle>Change Password</DialogTitle>
          </div>
          <DialogDescription>
            Enter your current password and choose a new one
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="currentPassword" className="text-sm font-medium mb-2 block">
              Current Password
            </Label>
            <Input
              id="currentPassword"
              type="password"
              placeholder="Enter current password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          <div>
            <Label htmlFor="newPassword" className="text-sm font-medium mb-2 block">
              New Password
            </Label>
            <Input
              id="newPassword"
              type="password"
              placeholder="Enter new password (min 8 characters)"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          <div>
            <Label htmlFor="confirmPassword" className="text-sm font-medium mb-2 block">
              Confirm New Password
            </Label>
            <Input
              id="confirmPassword"
              type="password"
              placeholder="Confirm new password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-xs font-medium mb-2">Password Requirements:</p>
            <ul className="text-xs text-muted-foreground space-y-1">
              <li className="flex items-center gap-2">
                <span className={newPassword.length >= 8 ? 'text-green-600' : ''}>
                  {newPassword.length >= 8 ? '✓' : '•'}
                </span>
                At least 8 characters
              </li>
              <li className="flex items-center gap-2">
                <span className={newPassword === confirmPassword && newPassword.length > 0 ? 'text-green-600' : ''}>
                  {newPassword === confirmPassword && newPassword.length > 0 ? '✓' : '•'}
                </span>
                Passwords match
              </li>
            </ul>
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting || !currentPassword || !newPassword || !confirmPassword || newPassword !== confirmPassword || newPassword.length < 8}
            >
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Changing...
                </>
              ) : (
                <>
                  <Lock className="mr-2 h-4 w-4" />
                  Change Password
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
