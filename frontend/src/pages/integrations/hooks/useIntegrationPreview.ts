import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import { logger } from '@/lib/logger'
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
  const [deliverViaEmail, setDeliverViaEmail] = useState(false)
  const [emailTo, setEmailTo] = useState('')

  const customerId = searchParams.get('customer_id')

  const loadPreview = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)

      const params = customerId ? { customer_id: customerId } : {}
      const response = await api.get<unknown>(
        `/integrations/${integrationId}/preview`,
        params
      )

      const data = (response as { data?: unknown }).data || response

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
        setPreview(data as IntegrationPreviewType)
      }
    } catch (err: unknown) {
      logger.error('Preview error:', err)
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
    } catch (error) {
      logger.debug('Refresh failed', error)
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
    setShowDownloadConfirm(true)
  }

  const integrationType = preview?.integration?.type?.toLowerCase() || ''
  const isITGlueIntegration = integrationType === 'itglue' || integrationType.includes('it_glue')
  const isLibreNMSIntegration = integrationType === 'librenms' || integrationType.includes('libre_nms')

  const confirmDownload = async () => {
    setShowDownloadConfirm(false)
    setIsDownloading(true)
    try {
      if (isITGlueIntegration) {
        const currentDate = new Date()
        await reportApi.downloadLicenseConsumptionReport(
          currentDate.getMonth() + 1,
          currentDate.getFullYear(),
          deliverViaEmail ? emailTo : undefined,
          undefined // emailFrom will be the logged-in user's email from backend
        )
      } else if (isLibreNMSIntegration) {
        await reportApi.downloadLibreNMSDeviceReport(
          undefined,
          deliverViaEmail ? emailTo : undefined,
          undefined
        )
      }

      if (deliverViaEmail) {
        toast.success('Report Sent & Downloaded', `Report has been downloaded and sent to ${emailTo}`)
        setDeliverViaEmail(false)
        setEmailTo('')
      } else {
        toast.success('Report Downloaded', 'Report has been downloaded successfully')
      }
    } catch (err) {
      logger.error('Download error:', err)
      if (deliverViaEmail) {
        toast.error('Email Failed', 'Failed to send report via email')
      } else {
        toast.error('Download Failed', 'Failed to download report')
      }
    } finally {
      setIsDownloading(false)
    }
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
    isLibreNMSIntegration,
    showDownloadConfirm,
    deliverViaEmail,
    emailTo,
    navigate,
    toast,
    setIsExporting,
    setShowDownloadConfirm,
    setDeliverViaEmail,
    setEmailTo,
    handleRefresh,
    handleSelectionChange,
    handleBack,
    handleDownloadReport,
    confirmDownload,
  }
}
