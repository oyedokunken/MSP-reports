import { useEffect, useState } from 'react'
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

  useEffect(() => {
    loadPreview()
  }, [integrationId, customerId])

  const loadPreview = async () => {
    try {
      setLoading(true)
      setError(null)

      console.log('Loading preview for integration:', integrationId)
      console.log('Auth token:', localStorage.getItem('auth_token'))

      const params = customerId ? { customer_id: customerId } : {}
      const response = await api.get<any>(
        `/integrations/${integrationId}/preview`,
        params
      )

      console.log('Preview response:', response)

      const data = response as any
      
      if (data.success) {
        setPreview(data)
      } else {
        setError(data.error || 'Failed to load preview')
      }
    } catch (err: any) {
      console.error('Preview error:', err)
      setError(err.message || 'Failed to load preview')
    } finally {
      setLoading(false)
    }
  }

  const handleRefresh = async () => {
    setRefreshing(true)
    try {
      await loadPreview()
      toast.success('Preview Refreshed', 'Integration data has been updated')
    } catch (err) {
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
