import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import type { IntegrationPreview as IntegrationPreviewType } from '@/types/displayTemplate'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'

export function useIntegrationPreview() {
  const { integrationId } = useParams<{ integrationId: string }>()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const toast = useToast()

  const [preview, setPreview] = useState<IntegrationPreviewType | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshing, setRefreshing] = useState(false)
  const [selectedData, setSelectedData] = useState<Record<string, string[]>>({})
  const [isExporting, setIsExporting] = useState(false)

  const customerId = searchParams.get('customer_id')

  const loadPreview = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)

      console.log('Loading preview for integration:', integrationId)
      console.log('Auth token:', localStorage.getItem('auth_token'))

      const params = customerId ? { customer_id: customerId } : {}
      const response = await api.get<unknown>(
        `/integrations/${integrationId}/preview`,
        params
      )

      console.log('Preview response:', response)

      // Handle both direct data and nested data structures
      const data = (response as { data?: unknown }).data || response

      // Type guard for the preview data
      if (typeof data === 'object' && data !== null && 'success' in data) {
        const previewData = data as { success: boolean; preview?: IntegrationPreviewType; message?: string }
        if (previewData.success && previewData.preview) {
          setPreview(previewData.preview)
        } else {
          throw new Error(previewData.message || 'Failed to load preview data')
        }
      } else {
        // Fallback for direct preview object
        setPreview(data as IntegrationPreviewType)
      }
    } catch (err: unknown) {
      console.error('Preview error:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const message = error.response?.data?.message || error.message || 'Failed to load integration preview'
      setError(message)
      toast.error('Error', message)
    } finally {
      setLoading(false)
    }
  }, [integrationId, customerId, toast])

  useEffect(() => {
    loadPreview()
  }, [loadPreview])

  const handleRefresh = async () => {
    setRefreshing(true)
    try {
      await loadPreview()
      toast.success('Preview Refreshed', 'Integration data has been updated')
    } catch {
      // Error already handled in loadPreview
    } finally {
      setRefreshing(false)
    }
  }

  const handleSelectionChange = (sectionId: string, selectedIds: string[]) => {
    setSelectedData((prev) => ({
      ...prev,
      [sectionId]: selectedIds,
    }))
  }

  const handleBack = () => {
    navigate('/platform/integrations')
  }

  return {
    integrationId,
    customerId,
    preview,
    loading,
    error,
    refreshing,
    selectedData,
    isExporting,
    navigate,
    toast,
    setIsExporting,
    handleRefresh,
    handleSelectionChange,
    handleBack,
  }
}
