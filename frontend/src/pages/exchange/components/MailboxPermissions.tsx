import { useEffect, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Shield, User, Mail, Send } from 'lucide-react'

interface MailboxPermission {
  id: number
  trustee: string
  trusteeEmail: string
  accessRights: string
  isInherited: boolean
  autoMapping: boolean
}

interface MailboxPermissionsProps {
  mailboxId: number
}

export function MailboxPermissions({ mailboxId }: MailboxPermissionsProps) {
  const [permissions, setPermissions] = useState<MailboxPermission[]>([])
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoint
    setIsLoading(false)
    setPermissions([])
  }, [mailboxId])

  const getPermissionIcon = (accessRights: string) => {
    if (accessRights.includes('FullAccess')) {
      return <Shield className="w-4 h-4 text-red-500" />
    }
    if (accessRights.includes('SendAs')) {
      return <Send className="w-4 h-4 text-blue-500" />
    }
    if (accessRights.includes('SendOnBehalf')) {
      return <Mail className="w-4 h-4 text-green-500" />
    }
    return <User className="w-4 h-4 text-muted-foreground" />
  }

  const getPermissionBadge = (accessRights: string) => {
    if (accessRights.includes('FullAccess')) {
      return <Badge className="bg-red-500">Full Access</Badge>
    }
    if (accessRights.includes('SendAs')) {
      return <Badge className="bg-blue-500">Send As</Badge>
    }
    if (accessRights.includes('SendOnBehalf')) {
      return <Badge className="bg-green-500">Send on Behalf</Badge>
    }
    return <Badge className="bg-muted/500">{accessRights}</Badge>
  }

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Shield className="w-5 h-5 mr-2" />
            Mailbox Permissions
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="flex items-center justify-between p-3 border rounded">
                <div className="flex items-center space-x-3 flex-1">
                  <Skeleton className="h-10 w-10 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="h-3 w-32" />
                  </div>
                </div>
                <Skeleton className="h-6 w-24" />
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <Shield className="w-5 h-5 mr-2" />
          Mailbox Permissions ({permissions.length})
        </CardTitle>
      </CardHeader>
      <CardContent>
        {permissions.length === 0 ? (
          <div className="text-center py-8">
            <Shield className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <p className="text-muted-foreground">No additional permissions configured</p>
            <p className="text-xs text-muted-foreground mt-1">Only the mailbox owner has access</p>
          </div>
        ) : (
          <div className="space-y-3">
            {permissions.map((permission) => (
              <div
                key={permission.id}
                className="flex items-center justify-between p-3 bg-muted/50 rounded-lg hover:bg-muted transition-colors"
              >
                <div className="flex items-center space-x-3 flex-1">
                  <div className="h-10 w-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                    {getPermissionIcon(permission.accessRights)}
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-foreground">{permission.trustee}</p>
                    <p className="text-xs text-muted-foreground">{permission.trusteeEmail}</p>
                    {permission.autoMapping && (
                      <p className="text-xs text-blue-600 mt-1">Auto-mapping enabled</p>
                    )}
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  {getPermissionBadge(permission.accessRights)}
                  {permission.isInherited && (
                    <Badge variant="outline" className="text-xs">Inherited</Badge>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
