import { useState } from 'react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { MdStorage, MdVisibility, MdVisibilityOff, MdInfo } from 'react-icons/md'
import { Loader2 } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

const ldapSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
})

type LdapFormData = z.infer<typeof ldapSchema>

interface LdapLoginModalProps {
  isOpen: boolean
  onClose: () => void
  onSubmit: (username: string, password: string) => Promise<void>
}

export function LdapLoginModal({ isOpen, onClose, onSubmit }: LdapLoginModalProps) {
  const [showPassword, setShowPassword] = useState(false)
  const [authError, setAuthError] = useState('')

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
    reset
  } = useForm<LdapFormData>({
    resolver: zodResolver(ldapSchema),
    defaultValues: {
      username: '',
      password: '',
    }
  })

  const onFormSubmit = async (data: LdapFormData) => {
    setAuthError('')
    try {
      await onSubmit(data.username, data.password)
    } catch (err: unknown) {
      const error = err as { message?: string }
      setAuthError(error.message || 'Authentication failed. Please check your credentials.')
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setAuthError('')
      setShowPassword(false)
      reset()
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

        <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4 mt-4" noValidate>
          <Alert className="bg-blue-50 border-blue-200">
            <MdInfo className="h-4 w-4 text-blue-600" />
            <AlertDescription className="text-sm text-blue-800">
              Use your domain username (e.g., <strong>john.doe</strong>) and Active Directory password
            </AlertDescription>
          </Alert>

          <div className="space-y-2">
            <Label htmlFor="ldap-username">Domain Username</Label>
            <Input
              id="ldap-username"
              type="text"
              placeholder="john.doe"
              {...register('username')}
              disabled={isSubmitting}
              autoComplete="username"
              className={errors.username ? 'border-red-500' : ''}
              autoFocus
            />
            {errors.username ? (
              <p className="text-xs text-red-500">{errors.username.message}</p>
            ) : (
              <p className="text-xs text-muted-foreground">
                Enter your username without the @domain.com part
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="ldap-password">Password</Label>
            <div className="relative">
              <Input
                id="ldap-password"
                type={showPassword ? 'text' : 'password'}
                placeholder="Enter your AD password"
                {...register('password')}
                disabled={isSubmitting}
                autoComplete="current-password"
                className={`pr-10 ${errors.password ? 'border-red-500' : ''}`}
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
            {errors.password && (
              <p className="text-xs text-red-500">{errors.password.message}</p>
            )}
          </div>

          {authError && (
            <Alert variant="destructive">
              <AlertDescription>{authError}</AlertDescription>
            </Alert>
          )}

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

        <div className="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
          <p className="text-xs text-gray-600">
            <strong>Need help?</strong> Contact your IT administrator if you're having trouble signing in with your domain credentials.
          </p>
        </div>
      </DialogContent>
    </Dialog>
  )
}
