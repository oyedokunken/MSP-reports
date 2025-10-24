import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, Shield } from 'lucide-react'
import { api } from '@/lib/api'
import type { UserWithPermissions, UserPermissions as UserPerms } from '../types'

interface EditPermissionsModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  user: UserWithPermissions | null
  onSuccess?: () => void
}

export function EditPermissionsModal({ open, onOpenChange, user, onSuccess }: EditPermissionsModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [permissions, setPermissions] = useState<UserPerms>({
    integrations: { view: true, manage: false, sync: false },
    customers: { view: true, edit: false, map: false },
    reports: { view: true, configure: false, generate: false, download: false },
    users: { view: false, manage: false },
    company_settings: { view: false, edit: false },
  })

  useEffect(() => {
    if (user && user.permissions) {
      setPermissions(user.permissions)
    } else if (user) {
      // Set default permissions if none exist
      setPermissions({
        integrations: { view: true, manage: false, sync: false },
        customers: { view: true, edit: false, map: false },
        reports: { view: true, configure: false, generate: false, download: true },
        users: { view: false, manage: false },
        company_settings: { view: false, edit: false },
      })
    }
  }, [user])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!user) return

    setError('')
    setIsSubmitting(true)

    try {
      const response = await api.patch(`/users/${user.user_id}/permissions`, {
        permissions
      })

      if (response.data) {
        setSuccess(true)
        setTimeout(() => {
          setSuccess(false)
          onOpenChange(false)
          onSuccess?.()
        }, 2000)
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to update permissions'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const togglePermission = (section: keyof UserPerms, action: string) => {
    setPermissions(prev => ({
      ...prev,
      [section]: {
        ...prev[section],
        [action]: !prev[section]?.[action as keyof typeof prev[typeof section]]
      }
    }))
  }

  const handleClose = () => {
    if (!isSubmitting) {
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
            <DialogTitle className="text-2xl text-green-600 mb-2">Permissions Updated!</DialogTitle>
            <DialogDescription className="text-center">
              User permissions have been updated successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!user) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Shield className="h-5 w-5 text-primary" />
            <DialogTitle>Edit Permissions</DialogTitle>
          </div>
          <DialogDescription>
            Manage permissions for <strong>{user.name}</strong> ({user.email})
          </DialogDescription>
        </DialogHeader>
        
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Integrations */}
          <div className="space-y-3">
            <Label className="text-base font-semibold">Integrations</Label>
            <div className="space-y-2 pl-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.integrations?.view || false}
                  onChange={() => togglePermission('integrations', 'view')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">View integrations</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.integrations?.manage || false}
                  onChange={() => togglePermission('integrations', 'manage')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Manage integrations</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.integrations?.sync || false}
                  onChange={() => togglePermission('integrations', 'sync')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Trigger syncs</span>
              </label>
            </div>
          </div>

          {/* Customers */}
          <div className="space-y-3">
            <Label className="text-base font-semibold">Customers</Label>
            <div className="space-y-2 pl-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.customers?.view || false}
                  onChange={() => togglePermission('customers', 'view')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">View customers</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.customers?.edit || false}
                  onChange={() => togglePermission('customers', 'edit')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Edit customers</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.customers?.map || false}
                  onChange={() => togglePermission('customers', 'map')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Map customers</span>
              </label>
            </div>
          </div>

          {/* Reports */}
          <div className="space-y-3">
            <Label className="text-base font-semibold">Reports</Label>
            <div className="space-y-2 pl-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.reports?.view || false}
                  onChange={() => togglePermission('reports', 'view')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">View reports</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.reports?.configure || false}
                  onChange={() => togglePermission('reports', 'configure')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Configure reports</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.reports?.generate || false}
                  onChange={() => togglePermission('reports', 'generate')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Generate reports</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.reports?.download || false}
                  onChange={() => togglePermission('reports', 'download')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Download reports</span>
              </label>
            </div>
          </div>

          {/* Users */}
          <div className="space-y-3">
            <Label className="text-base font-semibold">Users</Label>
            <div className="space-y-2 pl-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.users?.view || false}
                  onChange={() => togglePermission('users', 'view')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">View users</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.users?.manage || false}
                  onChange={() => togglePermission('users', 'manage')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Manage users</span>
              </label>
            </div>
          </div>

          {/* Company Settings */}
          <div className="space-y-3">
            <Label className="text-base font-semibold">Company Settings</Label>
            <div className="space-y-2 pl-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.company_settings?.view || false}
                  onChange={() => togglePermission('company_settings', 'view')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">View settings</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={permissions.company_settings?.edit || false}
                  onChange={() => togglePermission('company_settings', 'edit')}
                  className="w-4 h-4 rounded border-gray-300"
                  disabled={isSubmitting}
                />
                <span className="text-sm">Edit settings</span>
              </label>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
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
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Saving...
                </>
              ) : (
                'Save Permissions'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
