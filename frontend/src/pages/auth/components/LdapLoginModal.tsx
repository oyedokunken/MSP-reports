import { useState } from 'react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { MdStorage, MdVisibility, MdVisibilityOff, MdInfo } from 'react-icons/md'
import { Loader2 } from 'lucide-react'

interface LdapLoginModalProps {
  isOpen: boolean
  onClose: () => void
  onSubmit: (username: string, password: string) => Promise<void>
}

export function LdapLoginModal({ isOpen, onClose, onSubmit }: LdapLoginModalProps) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (!username || !password) {
      setError('Please enter both username and password')
      return
    }

    try {
      setIsSubmitting(true)
      await onSubmit(username, password)
    } catch (err: unknown) {
      const error = err as { message?: string }
      setError(error.message || 'Authentication failed. Please check your credentials.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setUsername('')
      setPassword('')
      setError('')
      setShowPassword(false)
      onClose()
    }
  }

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-3 mb-2">
            <div className="p-2 bg-indigo-100 rounded-lg">
              <MdStorage className="w-6 h-6 text-indigo-600" />
            </div>
            <div>
              <DialogTitle className="text-xl">Active Directory Login</DialogTitle>
              <DialogDescription className="text-sm">
                Sign in with your domain credentials
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {/* Info Alert */}
          <Alert className="bg-blue-50 border-blue-200">
            <MdInfo className="h-4 w-4 text-blue-600" />
            <AlertDescription className="text-sm text-blue-800">
              Use your domain username (e.g., <strong>john.doe</strong>) and Active Directory password
            </AlertDescription>
          </Alert>

          {/* Username Field */}
          <div className="space-y-2">
            <Label htmlFor="ldap-username">Domain Username</Label>
            <Input
              id="ldap-username"
              type="text"
              placeholder="john.doe"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              disabled={isSubmitting}
              autoComplete="username"
              autoFocus
            />
            <p className="text-xs text-muted-foreground">
              Enter your username without the @domain.com part
            </p>
          </div>

          {/* Password Field */}
          <div className="space-y-2">
            <Label htmlFor="ldap-password">Password</Label>
            <div className="relative">
              <Input
                id="ldap-password"
                type={showPassword ? 'text' : 'password'}
                placeholder="Enter your AD password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isSubmitting}
                autoComplete="current-password"
                className="pr-10"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                disabled={isSubmitting}
              >
                {showPassword ? (
                  <MdVisibilityOff className="w-5 h-5" />
                ) : (
                  <MdVisibility className="w-5 h-5" />
                )}
              </button>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          {/* Action Buttons */}
          <div className="flex gap-3 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting}
              className="flex-1 bg-indigo-600 hover:bg-indigo-700"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Authenticating...
                </>
              ) : (
                'Sign In'
              )}
            </Button>
          </div>
        </form>

        {/* Help Text */}
        <div className="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
          <p className="text-xs text-gray-600">
            <strong>Need help?</strong> Contact your IT administrator if you're having trouble signing in with your domain credentials.
          </p>
        </div>
      </DialogContent>
    </Dialog>
  )
}
