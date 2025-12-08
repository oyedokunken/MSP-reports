import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import {
  CheckCircle2,
  Shield,
  Cable,
  Users,
  FileText,
  Settings,
  Building2
} from 'lucide-react'
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

  const sections = [
    {
      id: 'integrations',
      label: 'Integrations',
      icon: Cable,
      items: [
        { key: 'view', label: 'View Integrations' },
        { key: 'manage', label: 'Manage Integrations' },
        { key: 'sync', label: 'Trigger Syncs' },
      ]
    },
    {
      id: 'customers',
      label: 'Customers',
      icon: Users,
      items: [
        { key: 'view', label: 'View Customers' },
        { key: 'edit', label: 'Edit Customers' },
        { key: 'map', label: 'Map Customers' },
      ]
    },
    {
      id: 'reports',
      label: 'Reports',
      icon: FileText,
      items: [
        { key: 'view', label: 'View Reports' },
        { key: 'configure', label: 'Configure Reports' },
        { key: 'generate', label: 'Generate Reports' },
        { key: 'download', label: 'Download Reports' },
      ]
    },
    {
      id: 'users',
      label: 'User Management',
      icon: Shield,
      items: [
        { key: 'view', label: 'View Users' },
        { key: 'manage', label: 'Manage Users' },
      ]
    },
    {
      id: 'company_settings',
      label: 'Company Settings',
      icon: Building2,
      items: [
        { key: 'view', label: 'View Settings' },
        { key: 'edit', label: 'Edit Settings' },
      ]
    }
  ]

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[800px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Settings className="h-5 w-5 text-primary" />
            <DialogTitle>Edit Permissions</DialogTitle>
          </div>
          <DialogDescription>
            Manage access levels and capabilities for <strong>{user.name}</strong>
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {sections.map((section) => {
              const Icon = section.icon
              return (
                <div key={section.id} className="space-y-4 rounded-lg border border-border/50 p-4 bg-card/50">
                  <div className="flex items-center gap-2 pb-2 border-b border-border/50">
                    <Icon className="h-4 w-4 text-primary" />
                    <h4 className="font-semibold text-sm">{section.label}</h4>
                  </div>
                  <div className="space-y-4">
                    {section.items.map((item) => (
                      <div key={item.key} className="flex items-center justify-between">
                        <Label
                          htmlFor={`${section.id}-${item.key}`}
                          className="text-sm font-medium text-muted-foreground cursor-pointer flex-1"
                        >
                          {item.label}
                        </Label>
                        <Switch
                          id={`${section.id}-${item.key}`}
                          checked={(permissions[section.id as keyof UserPerms] as any)?.[item.key] || false}
                          onCheckedChange={() => togglePermission(section.id as keyof UserPerms, item.key)}
                          disabled={isSubmitting}
                        />
                      </div>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter className="gap-2 sm:gap-0">
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
                  Saving Changes...
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
