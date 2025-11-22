import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
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
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <Card key={i}>
            <CardContent className="pt-6">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center space-x-3 flex-1">
                  <Skeleton className="h-12 w-12 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-4 w-48" />
                  </div>
                </div>
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="space-y-3">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
                <div className="flex space-x-2 pt-2">
                  <Skeleton className="h-9 flex-1" />
                  <Skeleton className="h-9 w-9" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
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
