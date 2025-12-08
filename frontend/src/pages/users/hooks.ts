import { useState } from 'react'
import { api } from '@/lib/api'
import type { UserWithPermissions } from './types'

export function useUsers() {
  const [users, setUsers] = useState<UserWithPermissions[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchUsers = async () => {
    try {
      setIsLoading(true)
      const response = await api.get<UserWithPermissions[]>('/users')

      if (response.data) {
        setUsers(response.data)
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to load users'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  return {
    users,
    isLoading,
    error,
    fetchUsers,
    setUsers,
  }
}

export function useUserActions(onSuccess?: () => void, onError?: (message: string) => void) {
  const [isSubmitting, setIsSubmitting] = useState(false)

  const deactivateUser = async (userId: number) => {
    setIsSubmitting(true)
    try {
      await api.patch(`/users/${userId}/deactivate`)
      onSuccess?.()
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to deactivate user'
      onError?.(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const reactivateUser = async (userId: number) => {
    setIsSubmitting(true)
    try {
      await api.patch(`/users/${userId}/reactivate`)
      onSuccess?.()
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to reactivate user'
      onError?.(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  return {
    isSubmitting,
    deactivateUser,
    reactivateUser,
  }
}
