import React, { useEffect, useState } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import type { IntegrationPreview as IntegrationPreviewType } from '@/types/displayTemplate'
import { DynamicSection } from '@/components/dynamic/DynamicSection'
import { RefreshCw, Download, AlertCircle, FileDown } from 'lucide-react'
import { api } from '@/lib/api'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'

export const IntegrationPreview: React.FC = () => {
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

      // api.get returns the data directly (unwrapped from response.data)
      // The response IS the preview data
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

  const handleExportSelected = async () => {
    try {
      setIsExporting(true)
      toast.info('Preparing Export', 'Generating PDF export...')
      
      // Prepare export data
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

      // TODO: Replace with actual PDF generation API call
      // For now, export as JSON until PDF endpoint is ready
      const blob = new Blob([JSON.stringify(exportData, null, 2)], {
        type: 'application/json',
      })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `integration-preview-${integrationId}-${Date.now()}.json`
      a.click()
      URL.revokeObjectURL(url)
      
      toast.success('Export Complete', 'Integration preview exported successfully')
    } catch (err: any) {
      console.error('Export error:', err)
      toast.error('Export Failed', err.message || 'Failed to export integration preview')
    } finally {
      setIsExporting(false)
    }
  }
  
  const handleExportPDF = async () => {
    try {
      setIsExporting(true)
      toast.info('Generating PDF', 'Preparing print preview...')
      
      // Create a printable HTML document
      const printContent = document.createElement('div')
      printContent.className = 'print-container'
      
      // Build the HTML content
      let htmlContent = `
        <style>
          @media print {
            body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
            .print-container { max-width: 100%; }
            h1 { color: #1a56db; margin-bottom: 10px; }
            h2 { color: #374151; margin-top: 20px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f3f4f6; font-weight: bold; }
            .section { margin-bottom: 30px; page-break-inside: avoid; }
            .metadata { color: #6b7280; font-size: 12px; margin-bottom: 20px; }
          }
        </style>
        <h1>${preview?.template.metadata.display_name || 'Integration Preview'}</h1>
        <p class="metadata">Generated: ${new Date().toLocaleString()}</p>
        ${preview?.preview.customer ? `<p class="metadata">Customer: ${preview.preview.customer.name}</p>` : ''}
      `
      
      // Add each section
      preview?.template.sections.forEach(section => {
        const sectionData = section.dataPath ? preview.preview.data[section.dataPath] : null
        
        htmlContent += `<div class="section"><h2>${section.title}</h2>`
        
        if (section.type === 'table' && Array.isArray(sectionData) && sectionData.length > 0) {
          const columns = section.columns || Object.keys(sectionData[0])
          htmlContent += '<table><thead><tr>'
          columns.forEach(col => {
            htmlContent += `<th>${col.replace(/_/g, ' ').toUpperCase()}</th>`
          })
          htmlContent += '</tr></thead><tbody>'
          sectionData.forEach(row => {
            htmlContent += '<tr>'
            columns.forEach(col => {
              htmlContent += `<td>${row[col] || '-'}</td>`
            })
            htmlContent += '</tr>'
          })
          htmlContent += '</tbody></table>'
        } else if (section.type === 'key-value' && sectionData) {
          htmlContent += '<table>'
          Object.entries(sectionData).forEach(([key, value]) => {
            htmlContent += `<tr><th>${key.replace(/_/g, ' ').toUpperCase()}</th><td>${value}</td></tr>`
          })
          htmlContent += '</table>'
        } else if (section.type === 'list' && Array.isArray(sectionData)) {
          htmlContent += '<ul>'
          sectionData.forEach(item => {
            htmlContent += `<li>${typeof item === 'object' ? JSON.stringify(item) : item}</li>`
          })
          htmlContent += '</ul>'
        }
        
        htmlContent += '</div>'
      })
      
      printContent.innerHTML = htmlContent
      
      // Open print dialog
      const printWindow = window.open('', '_blank')
      if (printWindow) {
        printWindow.document.write(htmlContent)
        printWindow.document.close()
        printWindow.focus()
        
        // Wait a bit for content to load, then print
        setTimeout(() => {
          printWindow.print()
          toast.success('PDF Export Ready', 'Print dialog opened. Save as PDF to export.')
        }, 500)
      } else {
        toast.error('Popup Blocked', 'Please allow popups to export as PDF')
      }
    } catch (err: any) {
      console.error('PDF export error:', err)
      toast.error('PDF Export Failed', err.message || 'Failed to generate PDF')
    } finally {
      setIsExporting(false)
    }
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
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
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

          <div className="flex items-center gap-2">
            <button
              onClick={handleExportPDF}
              disabled={isExporting}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary border border-primary rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <FileDown className="w-4 h-4" />
              {isExporting ? 'Exporting...' : 'Export PDF'}
            </button>
            {hasSelectedData && (
              <button
                onClick={handleExportSelected}
                disabled={isExporting}
                className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Download className="w-4 h-4" />
                Export CSV
              </button>
            )}
            <button
              onClick={handleRefresh}
              disabled={refreshing || isExporting}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
              Refresh
            </button>
          </div>
        </div>

        {/* Sections */}
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
        <div className="p-4 bg-white border border-gray-200 rounded-lg">
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
    </FloatingLayout>
  )
}
