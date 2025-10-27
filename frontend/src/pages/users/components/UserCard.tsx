import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Mail, Shield, Clock, Settings, Edit, Lock, UserX, UserCheck, Trash2 } from 'lucide-react'
import { formatDate } from '../utils/formatters'
import type { UserWithPermissions } from '../types'

interface UserCardProps {
  user: UserWithPermissions
  isCurrentUser: boolean
  onEdit: () => void
  onEditPermissions: () => void
  onResetPassword: () => void
  onDeactivate: () => void
  onReactivate: () => void
  onDelete: () => void
}

export function UserCard({
  user,
  isCurrentUser,
  onEdit,
  onEditPermissions,
  onResetPassword,
  onDeactivate,
  onReactivate,
  onDelete,
}: UserCardProps) {
  const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive'> = {
      active: 'default',
      inactive: 'secondary',
      locked: 'destructive',
    }
    return (
      <Badge variant={variants[status] || 'secondary'}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    )
  }

  const getRoleBadge = (roleName: string) => {
    return roleName === 'admin' ? (
      <Badge variant="default" className="bg-blue-500">
        <Shield className="mr-1 h-3 w-3" />
        Admin
      </Badge>
    ) : (
      <Badge variant="outline">User</Badge>
    )
  }

  return (
    <Card className="hover:shadow-md transition-shadow">
      <CardHeader>
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <CardTitle className="text-xl">{user.name}</CardTitle>
              {getRoleBadge(user.role.name)}
              {getStatusBadge(user.status)}
            </div>
            <CardDescription className="flex items-center gap-2">
              <Mail className="h-4 w-4" />
              {user.email}
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm mb-4">
          <div>
            <p className="text-muted-foreground mb-1">Auth Method</p>
            <p className="font-medium capitalize">{user.auth_method}</p>
          </div>
          <div>
            <p className="text-muted-foreground mb-1 flex items-center gap-1">
              <Clock className="h-3 w-3" />
              Last Login
            </p>
            <p className="font-medium">{formatDate(user.last_login)}</p>
          </div>
          <div>
            <p className="text-muted-foreground mb-1">Created</p>
            <p className="font-medium">{formatDate(user.created_at)}</p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2 pt-4 border-t border-border">
          <Button
            variant="outline"
            size="sm"
            onClick={onEdit}
          >
            <Edit className="mr-2 h-4 w-4" />
            Edit Details
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={onEditPermissions}
          >
            <Settings className="mr-2 h-4 w-4" />
            Permissions
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={onResetPassword}
            disabled={user.auth_method !== 'local' || isCurrentUser}
          >
            <Lock className="mr-2 h-4 w-4" />
            Reset Password
          </Button>
          {!isCurrentUser && (
            <>
              {user.status === 'active' ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={onDeactivate}
                >
                  <UserX className="mr-2 h-4 w-4" />
                  Deactivate
                </Button>
              ) : (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={onReactivate}
                >
                  <UserCheck className="mr-2 h-4 w-4" />
                  Reactivate
                </Button>
              )}
              <Button
                variant="destructive"
                size="sm"
                onClick={onDelete}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </Button>
            </>
          )}
          {isCurrentUser && (
            <Badge variant="outline" className="ml-2">
              You
            </Badge>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
