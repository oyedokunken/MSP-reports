import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { UserPlus } from 'lucide-react'
import { UserCard } from './UserCard'
import type { UserWithPermissions } from '../types'

interface UsersGridProps {
  users: UserWithPermissions[]
  isLoading: boolean
  error: string | null
  currentUserId: number | undefined
  onCreateUser: () => void
  onEdit: (user: UserWithPermissions) => void
  onEditPermissions: (user: UserWithPermissions) => void
  onResetPassword: (user: UserWithPermissions) => void
  onDeactivate: (user: UserWithPermissions) => void
  onReactivate: (user: UserWithPermissions) => void
  onDelete: (user: UserWithPermissions) => void
}

export function UsersGrid({
  users,
  isLoading,
  error,
  currentUserId,
  onCreateUser,
  onEdit,
  onEditPermissions,
  onResetPassword,
  onDeactivate,
  onReactivate,
  onDelete,
}: UsersGridProps) {
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spinner className="h-8 w-8" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
        <p className="text-sm text-destructive font-medium">{error}</p>
      </div>
    )
  }

  if (users.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <UserPlus className="h-12 w-12 text-muted-foreground mb-4" />
          <p className="text-lg font-medium">No users found</p>
          <p className="text-sm text-muted-foreground mb-4">
            Get started by creating your first user
          </p>
          <Button onClick={onCreateUser}>
            <UserPlus className="mr-2 h-4 w-4" />
            Create User
          </Button>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid gap-4">
      {users.map((user) => (
        <UserCard
          key={user.user_id}
          user={user}
          isCurrentUser={user.user_id === currentUserId}
          onEdit={() => onEdit(user)}
          onEditPermissions={() => onEditPermissions(user)}
          onResetPassword={() => onResetPassword(user)}
          onDeactivate={() => onDeactivate(user)}
          onReactivate={() => onReactivate(user)}
          onDelete={() => onDelete(user)}
        />
      ))}
    </div>
  )
}
