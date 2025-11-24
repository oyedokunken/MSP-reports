import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { useAuth } from '@/contexts/AuthContext'
import { useUsersStore } from '@/stores/useUsersStore'
import { UsersHeader, UsersGrid } from './components'
import { CreateUserModal } from './modals/CreateUserModal'
import { EditPermissionsModal } from './modals/EditPermissionsModal'
import { EditUserModal } from './modals/EditUserModal'
import { ResetUserPasswordModal } from './modals/ResetUserPasswordModal'
import { DeleteUserModal } from './modals/DeleteUserModal'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import type { UserWithPermissions } from './types'

export default function UsersList() {
  const toast = useToast()
  const { user: currentUser } = useAuth()
  
  // Zustand store - individual selectors for optimal performance
  const users = useUsersStore(state => state.users)
  const isLoading = useUsersStore(state => state.isLoading)
  const error = useUsersStore(state => state.error)
  const modals = useUsersStore(state => state.modals)
  const selectedUser = useUsersStore(state => state.selectedUser)
  const fetchUsers = useUsersStore(state => state.fetchUsers)
  const deactivateUser = useUsersStore(state => state.deactivateUser)
  const reactivateUser = useUsersStore(state => state.reactivateUser)
  const openModal = useUsersStore(state => state.openModal)
  const closeModal = useUsersStore(state => state.closeModal)
  const setSelectedUser = useUsersStore(state => state.setSelectedUser)

  // Local state for confirmation modals
  const [deactivateConfirm, setDeactivateConfirm] = useState<UserWithPermissions | null>(null)
  const [reactivateConfirm, setReactivateConfirm] = useState<UserWithPermissions | null>(null)
  const [isProcessing, setIsProcessing] = useState(false)

  // Fetch users on mount
  useEffect(() => {
    fetchUsers()
  }, [fetchUsers])

  // Handler functions
  const handleDeactivate = async () => {
    if (!deactivateConfirm) return
    
    setIsProcessing(true)
    try {
      await deactivateUser(deactivateConfirm.user_id)
      toast.success('User Deactivated', `${deactivateConfirm.name} has been deactivated`)
      setDeactivateConfirm(null)
    } catch (err: any) {
      toast.error('Error', err.response?.data?.message || err.message)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleReactivate = async () => {
    if (!reactivateConfirm) return
    
    setIsProcessing(true)
    try {
      await reactivateUser(reactivateConfirm.user_id)
      toast.success('User Reactivated', `${reactivateConfirm.name} has been reactivated`)
      setReactivateConfirm(null)
    } catch (err: any) {
      toast.error('Error', err.response?.data?.message || err.message)
    } finally {
      setIsProcessing(false)
    }
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <UsersHeader onCreateUser={() => openModal('create')} />

        <UsersGrid
          users={users}
          isLoading={isLoading}
          error={error}
          currentUserId={currentUser?.user_id}
          onCreateUser={() => openModal('create')}
          onEdit={(user) => {
            setSelectedUser(user)
            openModal('edit')
          }}
          onEditPermissions={(user) => {
            setSelectedUser(user)
            openModal('permissions')
          }}
          onResetPassword={(user) => {
            setSelectedUser(user)
            openModal('resetPassword')
          }}
          onDeactivate={(user) => setDeactivateConfirm(user)}
          onReactivate={(user) => setReactivateConfirm(user)}
          onDelete={(user) => {
            setSelectedUser(user)
            openModal('delete')
          }}
        />
      
        {/* Modals */}
        <CreateUserModal 
          open={modals.create} 
          onOpenChange={(open) => open ? openModal('create') : closeModal('create')}
          onSuccess={() => {
            fetchUsers()
            closeModal('create')
          }}
        />

        <EditUserModal
          open={modals.edit}
          onOpenChange={(open) => open ? openModal('edit') : closeModal('edit')}
          user={selectedUser}
          onSuccess={() => {
            fetchUsers()
            closeModal('edit')
          }}
        />

        <EditPermissionsModal
          open={modals.permissions}
          onOpenChange={(open) => open ? openModal('permissions') : closeModal('permissions')}
          user={selectedUser}
          onSuccess={() => {
            fetchUsers()
            closeModal('permissions')
          }}
        />

        <ResetUserPasswordModal
          open={modals.resetPassword}
          onOpenChange={(open) => open ? openModal('resetPassword') : closeModal('resetPassword')}
          user={selectedUser}
          onSuccess={() => {
            fetchUsers()
            closeModal('resetPassword')
          }}
        />

        <DeleteUserModal
          open={modals.delete}
          onOpenChange={(open) => open ? openModal('delete') : closeModal('delete')}
          user={selectedUser}
          onSuccess={() => {
            fetchUsers()
            closeModal('delete')
          }}
        />

        {/* Confirmation Modals */}
        <ConfirmationModal
          isOpen={deactivateConfirm !== null}
          onClose={() => setDeactivateConfirm(null)}
          onConfirm={handleDeactivate}
          title="Deactivate User"
          description={`Are you sure you want to deactivate ${deactivateConfirm?.name}? The user will no longer be able to log in, but their data will be preserved.`}
          confirmText="Deactivate"
          cancelText="Cancel"
          type="warning"
          isLoading={isProcessing}
        />

        <ConfirmationModal
          isOpen={reactivateConfirm !== null}
          onClose={() => setReactivateConfirm(null)}
          onConfirm={handleReactivate}
          title="Reactivate User"
          description={`Are you sure you want to reactivate ${reactivateConfirm?.name}? The user will be able to log in again.`}
          confirmText="Reactivate"
          cancelText="Cancel"
          type="success"
          isLoading={isProcessing}
        />
      </div>
    </FloatingLayout>
  )
}
