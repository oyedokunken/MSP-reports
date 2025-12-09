import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import type { IntegrationPreview as IntegrationPreviewType } from '@/types/displayTemplate'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { reportApi } from '@/services/api/reports'

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
  const [isDownloading, setIsDownloading] = useState(false)
  const [showDownloadConfirm, setShowDownloadConfirm] = useState(false)

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

      // Check if the data itself is the IntegrationPreviewType
      if (
        typeof data === 'object' &&
        data !== null &&
        'integration' in data &&
        'template' in data &&
        'preview' in data
      ) {
        setPreview(data as IntegrationPreviewType)
      } else if (typeof data === 'object' && data !== null && 'success' in data) {
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

  const handleDownloadReport = () => {
    // Show confirmation modal first
    setShowDownloadConfirm(true)
  }

  const confirmDownload = async () => {
    setShowDownloadConfirm(false)
    setIsDownloading(true)
    try {
      const currentDate = new Date()
      await reportApi.downloadLicenseConsumptionReport(
        currentDate.getMonth() + 1, // JavaScript months are 0-indexed
        currentDate.getFullYear()
      )
      toast.success('Report Downloaded', 'License consumption report has been downloaded successfully')
    } catch (err) {
      console.error('Download error:', err)
      toast.error('Download Failed', 'Failed to download license consumption report')
    } finally {
      setIsDownloading(false)
    }
  }

  // Check if this is ITGlue integration - only check type, not ID
  const integrationType = preview?.integration?.type?.toLowerCase() || ''
  const isITGlueIntegration = integrationType === 'itglue' || integrationType.includes('it_glue')
  
  // Debug log to verify integration type
  if (preview?.integration) {
    console.log('Integration check:', {
      id: preview.integration.id,
      type: preview.integration.type,
      normalizedType: integrationType,
      isITGlue: isITGlueIntegration
    })
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
    isDownloading,
    isITGlueIntegration,
    showDownloadConfirm,
    navigate,
    toast,
    setIsExporting,
    setShowDownloadConfirm,
    handleRefresh,
    handleSelectionChange,
    handleBack,
    handleDownloadReport,
    confirmDownload,
  }
}
