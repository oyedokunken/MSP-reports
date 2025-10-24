import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { UserPlus, Mail, Shield, Clock, Settings, Edit, Lock, UserX, UserCheck, Trash2 } from 'lucide-react'
import { CreateUserModal } from './modals/CreateUserModal'
import { EditPermissionsModal } from './modals/EditPermissionsModal'
import { EditUserModal } from './modals/EditUserModal'
import { ResetUserPasswordModal } from './modals/ResetUserPasswordModal'
import { DeleteUserModal } from './modals/DeleteUserModal'
import { useToast } from '@/components/ui/Toast'
import { useAuth } from '@/contexts/AuthContext'
import { useUsers, useUserActions } from './hooks'
import type { UserWithPermissions } from './types'

export default function UsersList() {
  const toast = useToast()
  const { user: currentUser } = useAuth()
  const { users, isLoading, error, fetchUsers } = useUsers()
  const { deactivateUser, reactivateUser } = useUserActions(
    () => {
      fetchUsers()
      toast.success('Success', 'User status updated')
    },
    (message) => toast.error('Error', message)
  )
  
  const [createUserOpen, setCreateUserOpen] = useState(false)
  const [editPermissionsOpen, setEditPermissionsOpen] = useState(false)
  const [editUserOpen, setEditUserOpen] = useState(false)
  const [resetPasswordOpen, setResetPasswordOpen] = useState(false)
  const [deleteUserOpen, setDeleteUserOpen] = useState(false)
  const [selectedUser, setSelectedUser] = useState<UserWithPermissions | null>(null)

  useEffect(() => {
    fetchUsers()
  }, [])

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

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  }

  const handleDeactivate = async (user: UserWithPermissions) => {
    await deactivateUser(user.user_id)
    toast.success('User Deactivated', `${user.name} has been deactivated`)
  }

  const handleReactivate = async (user: UserWithPermissions) => {
    await reactivateUser(user.user_id)
    toast.success('User Reactivated', `${user.name} has been reactivated`)
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <div className="flex items-center justify-center h-64">
          <Spinner className="h-8 w-8" />
        </div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Users</h1>
            <p className="mt-2 text-muted-foreground">
              Manage user accounts and permissions
            </p>
          </div>
          <Button onClick={() => setCreateUserOpen(true)}>
            + Create User
          </Button>
        </div>

      {error && (
        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 mb-6">
          <p className="text-sm text-destructive font-medium">{error}</p>
        </div>
      )}

      <div className="grid gap-4">
        {users.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <UserPlus className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-lg font-medium">No users found</p>
              <p className="text-sm text-muted-foreground mb-4">
                Get started by creating your first user
              </p>
              <Button onClick={() => setCreateUserOpen(true)}>
                <UserPlus className="mr-2 h-4 w-4" />
                Create User
              </Button>
            </CardContent>
          </Card>
        ) : (
          users.map((user) => (
            <Card key={user.user_id} className="hover:shadow-md transition-shadow">
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
                    onClick={() => {
                      setSelectedUser(user)
                      setEditUserOpen(true)
                    }}
                  >
                    <Edit className="mr-2 h-4 w-4" />
                    Edit Details
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setSelectedUser(user)
                      setEditPermissionsOpen(true)
                    }}
                  >
                    <Settings className="mr-2 h-4 w-4" />
                    Permissions
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setSelectedUser(user)
                      setResetPasswordOpen(true)
                    }}
                    disabled={user.auth_method !== 'local' || user.user_id === currentUser?.user_id}
                  >
                    <Lock className="mr-2 h-4 w-4" />
                    Reset Password
                  </Button>
                  {user.user_id !== currentUser?.user_id && (
                    <>
                      {user.status === 'active' ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDeactivate(user)}
                        >
                          <UserX className="mr-2 h-4 w-4" />
                          Deactivate
                        </Button>
                      ) : (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleReactivate(user)}
                        >
                          <UserCheck className="mr-2 h-4 w-4" />
                          Reactivate
                        </Button>
                      )}
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => {
                          setSelectedUser(user)
                          setDeleteUserOpen(true)
                        }}
                      >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete
                      </Button>
                    </>
                  )}
                  {user.user_id === currentUser?.user_id && (
                    <Badge variant="outline" className="ml-2">
                      You
                    </Badge>
                  )}
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>
      
      {/* Create User Modal */}
      <CreateUserModal 
        open={createUserOpen} 
        onOpenChange={setCreateUserOpen}
        onSuccess={() => {
          fetchUsers() // Refresh the user list
        }}
      />

      {/* Edit User Modal */}
      <EditUserModal
        open={editUserOpen}
        onOpenChange={setEditUserOpen}
        user={selectedUser}
        onSuccess={() => {
          fetchUsers() // Refresh the user list
        }}
      />

      {/* Edit Permissions Modal */}
      <EditPermissionsModal
        open={editPermissionsOpen}
        onOpenChange={setEditPermissionsOpen}
        user={selectedUser}
        onSuccess={() => {
          fetchUsers() // Refresh the user list
        }}
      />

      {/* Reset Password Modal */}
      <ResetUserPasswordModal
        open={resetPasswordOpen}
        onOpenChange={setResetPasswordOpen}
        user={selectedUser}
        onSuccess={() => {
          fetchUsers() // Refresh the user list
        }}
      />

      {/* Delete User Modal */}
      <DeleteUserModal
        open={deleteUserOpen}
        onOpenChange={setDeleteUserOpen}
        user={selectedUser}
        onSuccess={() => {
          fetchUsers() // Refresh the user list
        }}
      />
      </div>
    </FloatingLayout>
  )
}
