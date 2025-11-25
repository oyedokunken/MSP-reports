import React, { useEffect, useState } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import { IntegrationPreview as IntegrationPreviewType } from '@/types/displayTemplate'
import { DynamicSection } from '@/components/dynamic/DynamicSection'
import { ArrowLeft, RefreshCw, Download, Settings, AlertCircle } from 'lucide-react'
import axios from 'axios'

export const IntegrationPreview: React.FC = () => {
  const { integrationId } = useParams<{ integrationId: string }>()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  
  const [preview, setPreview] = useState<IntegrationPreviewType | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshing, setRefreshing] = useState(false)
  const [selectedData, setSelectedData] = useState<Record<string, string[]>>({})

  const customerId = searchParams.get('customer_id')

  useEffect(() => {
    loadPreview()
  }, [integrationId, customerId])

  const loadPreview = async () => {
    try {
      setLoading(true)
      setError(null)

      const params = customerId ? { customer_id: customerId } : {}
      const response = await axios.get(
        `/api/integrations/${integrationId}/preview`,
        { params }
      )

      if (response.data.success) {
        setPreview(response.data)
      } else {
        setError(response.data.error || 'Failed to load preview')
      }
    } catch (err: any) {
      setError(err.response?.data?.error || err.message || 'Failed to load preview')
    } finally {
      setLoading(false)
    }
  }

  const handleRefresh = async () => {
    setRefreshing(true)
    await loadPreview()
    setRefreshing(false)
  }

  const handleSelectionChange = (sectionId: string, selectedIds: string[]) => {
    setSelectedData((prev) => ({
      ...prev,
      [sectionId]: selectedIds,
    }))
  }

  const handleExportSelected = () => {
    // Export selected data as JSON
    const exportData = {
      integration: preview?.integration,
      customer: preview?.preview.customer,
      timestamp: preview?.preview.timestamp,
      selectedData: Object.entries(selectedData).reduce((acc, [sectionId, ids]) => {
        const section = preview?.template.sections.find((s) => s.id === sectionId)
        if (section && section.dataPath) {
          const sectionData = preview?.preview.data[section.dataPath]
          if (Array.isArray(sectionData)) {
            acc[sectionId] = sectionData.filter((item) => ids.includes(item.id))
          }
        }
        return acc
      }, {} as Record<string, any>),
    }

    const blob = new Blob([JSON.stringify(exportData, null, 2)], {
      type: 'application/json',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `integration-preview-${integrationId}.json`
    a.click()
    URL.revokeObjectURL(url)
  }

  const hasSelectedData = Object.values(selectedData).some((ids) => ids.length > 0)

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <RefreshCw className="w-8 h-8 mx-auto mb-4 text-blue-600 animate-spin" />
          <p className="text-gray-600">Loading integration preview...</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="max-w-md p-6 text-center bg-white border border-red-200 rounded-lg">
          <AlertCircle className="w-12 h-12 mx-auto mb-4 text-red-600" />
          <h2 className="mb-2 text-lg font-semibold text-gray-900">
            Failed to Load Preview
          </h2>
          <p className="mb-4 text-sm text-gray-600">{error}</p>
          <div className="flex gap-2 justify-center">
            <button
              onClick={() => navigate('/integrations')}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Back to Integrations
            </button>
            <button
              onClick={loadPreview}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    )
  }

  if (!preview) {
    return null
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <button
                  onClick={() => navigate('/integrations')}
                  className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
                >
                  <ArrowLeft className="w-5 h-5" />
                </button>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">
                    {preview.template.metadata.display_name}
                  </h1>
                  <p className="mt-1 text-sm text-gray-500">
                    {preview.template.metadata.description}
                  </p>
                  {preview.preview.customer && (
                    <p className="mt-1 text-sm font-medium text-blue-600">
                      Customer: {preview.preview.customer.name}
                    </p>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-2">
                {hasSelectedData && (
                  <button
                    onClick={handleExportSelected}
                    className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                  >
                    <Download className="w-4 h-4" />
                    Export Selected
                  </button>
                )}
                <button
                  onClick={handleRefresh}
                  disabled={refreshing}
                  className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                >
                  <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
                  Refresh
                </button>
                <button
                  onClick={() => navigate(`/integrations/${integrationId}/settings`)}
                  className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                >
                  <Settings className="w-4 h-4" />
                  Settings
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="space-y-6">
          {preview.template.sections.map((section) => (
            <DynamicSection
              key={section.id}
              section={section}
              data={preview.preview.data}
              onSelectionChange={handleSelectionChange}
            />
          ))}
        </div>

        {/* Footer Info */}
        <div className="mt-8 p-4 bg-white border border-gray-200 rounded-lg">
          <div className="flex items-center justify-between text-sm text-gray-500">
            <span>
              Last updated: {new Date(preview.preview.timestamp).toLocaleString()}
            </span>
            <span>
              Integration: {preview.integration.name} ({preview.integration.type})
            </span>
          </div>
        </div>
      </div>
    </div>
  )
}
