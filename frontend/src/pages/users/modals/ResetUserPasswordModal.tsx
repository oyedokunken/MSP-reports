import { useState } from 'react'
import type { UserWithPermissions } from '../types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, Lock, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'

interface ResetUserPasswordModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  user: UserWithPermissions | null
  onSuccess?: () => void
}

export function ResetUserPasswordModal({ 
  open, 
  onOpenChange,
  user,
  onSuccess 
}: ResetUserPasswordModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [newPassword, setNewPassword] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!newPassword.trim()) {
      setError('Please enter a new password')
      return
    }
    
    if (newPassword.length < 8) {
      setError('Password must be at least 8 characters')
      return
    }

    if (!user) return
    
    setError('')
    setIsSubmitting(true)

    try {
      await api.post(`/users/${user.user_id}/reset-password`, {
        new_password: newPassword,
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        setNewPassword('')
        onOpenChange(false)
        onSuccess?.()
      }, 2000)
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to reset password'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setNewPassword('')
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
            <DialogTitle className="text-2xl text-green-600 mb-2">Password Reset!</DialogTitle>
            <DialogDescription className="text-center">
              The user's password has been reset successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!user) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Lock className="h-5 w-5 text-primary" />
            <DialogTitle>Reset User Password</DialogTitle>
          </div>
          <DialogDescription>
            Set a new password for {user.name}
          </DialogDescription>
        </DialogHeader>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Warning */}
          <div className="rounded-lg border border-orange-500/50 bg-orange-500/10 p-3">
            <p className="text-sm text-orange-700 dark:text-orange-400">
              ⚠️ This will log out the user from all devices and they will need to sign in with the new password.
            </p>
          </div>

          {/* New Password */}
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

          {/* Password Requirements */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-xs font-medium mb-2">Password Requirements:</p>
            <ul className="text-xs text-muted-foreground space-y-1">
              <li className="flex items-center gap-2">
                <span className={newPassword.length >= 8 ? 'text-green-600' : ''}>
                  {newPassword.length >= 8 ? '✓' : '•'}
                </span>
                At least 8 characters
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
            <Button type="submit" disabled={isSubmitting || newPassword.length < 8}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Resetting...
                </>
              ) : (
                <>
                  <Lock className="mr-2 h-4 w-4" />
                  Reset Password
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
