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
      toast.info('Generating PDF', 'Preparing professional PDF export...')
      
      // Build enhanced HTML content with professional styling
      let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>${preview?.template.metadata.display_name || 'Integration Preview'}</title>
          <style>
            @page {
              margin: 2cm;
              size: A4;
            }
            
            * {
              margin: 0;
              padding: 0;
              box-sizing: border-box;
            }
            
            body {
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
              color: #1f2937;
              line-height: 1.6;
              background: white;
            }
            
            .header {
              border-bottom: 4px solid #1a56db;
              padding-bottom: 20px;
              margin-bottom: 30px;
            }
            
            .logo-area {
              display: flex;
              justify-content: space-between;
              align-items: flex-start;
              margin-bottom: 15px;
            }
            
            .company-name {
              font-size: 24px;
              font-weight: 700;
              color: #1a56db;
              margin-bottom: 5px;
            }
            
            .report-title {
              font-size: 32px;
              font-weight: 700;
              color: #111827;
              margin-bottom: 10px;
            }
            
            .metadata {
              display: flex;
              gap: 30px;
              flex-wrap: wrap;
              color: #6b7280;
              font-size: 14px;
              margin-top: 15px;
            }
            
            .metadata-item {
              display: flex;
              gap: 8px;
            }
            
            .metadata-label {
              font-weight: 600;
              color: #374151;
            }
            
            .section {
              margin-bottom: 40px;
              page-break-inside: avoid;
            }
            
            .section-header {
              background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%);
              color: white;
              padding: 15px 20px;
              border-radius: 8px 8px 0 0;
              margin-bottom: 0;
            }
            
            .section-title {
              font-size: 20px;
              font-weight: 600;
              display: flex;
              align-items: center;
              gap: 10px;
            }
            
            .section-description {
              font-size: 14px;
              opacity: 0.9;
              margin-top: 5px;
            }
            
            .section-content {
              border: 1px solid #e5e7eb;
              border-top: none;
              border-radius: 0 0 8px 8px;
              padding: 20px;
              background: #fafafa;
            }
            
            table {
              width: 100%;
              border-collapse: collapse;
              background: white;
              box-shadow: 0 1px 3px rgba(0,0,0,0.1);
              border-radius: 6px;
              overflow: hidden;
            }
            
            thead {
              background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
              color: white;
            }
            
            th {
              padding: 14px 16px;
              text-align: left;
              font-weight: 600;
              font-size: 13px;
              text-transform: uppercase;
              letter-spacing: 0.5px;
            }
            
            td {
              padding: 12px 16px;
              border-bottom: 1px solid #e5e7eb;
              font-size: 14px;
            }
            
            tbody tr:nth-child(even) {
              background: #f9fafb;
            }
            
            tbody tr:hover {
              background: #f3f4f6;
            }
            
            tbody tr:last-child td {
              border-bottom: none;
            }
            
            .key-value-table td:first-child {
              font-weight: 600;
              color: #374151;
              width: 30%;
              background: #f3f4f6;
            }
            
            .empty-state {
              text-align: center;
              padding: 40px;
              color: #9ca3af;
              font-style: italic;
            }
            
            .badge {
              display: inline-block;
              padding: 4px 12px;
              border-radius: 12px;
              font-size: 12px;
              font-weight: 600;
            }
            
            .badge-success { background: #d1fae5; color: #065f46; }
            .badge-warning { background: #fed7aa; color: #92400e; }
            .badge-error { background: #fee2e2; color: #991b1b; }
            .badge-info { background: #dbeafe; color: #1e40af; }
            
            .footer {
              margin-top: 50px;
              padding-top: 20px;
              border-top: 2px solid #e5e7eb;
              text-align: center;
              color: #6b7280;
              font-size: 12px;
            }
            
            .page-number {
              position: fixed;
              bottom: 20px;
              right: 20px;
              color: #9ca3af;
              font-size: 12px;
            }
            
            @media print {
              .no-print { display: none; }
              body { background: white; }
            }
          </style>
        </head>
        <body>
          <div class="header">
            <div class="logo-area">
              <div>
                <div class="company-name">Territory Technology Solutions</div>
                <div class="report-title">${preview?.template.metadata.display_name || 'Integration Data Preview'}</div>
              </div>
            </div>
            <div class="metadata">
              <div class="metadata-item">
                <span class="metadata-label">Generated:</span>
                <span>${new Date().toLocaleDateString('en-AU', { 
                  weekday: 'long', 
                  year: 'numeric', 
                  month: 'long', 
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                })}</span>
              </div>
              ${preview?.preview.customer ? `
                <div class="metadata-item">
                  <span class="metadata-label">Customer:</span>
                  <span>${preview.preview.customer.name}</span>
                </div>
              ` : ''}
              <div class="metadata-item">
                <span class="metadata-label">Integration:</span>
                <span>${preview?.template.metadata.display_name}</span>
              </div>
            </div>
          </div>
      `
      
      // Add each section with improved styling
      preview?.template.sections.forEach((section) => {
        const sectionData = section.dataPath ? preview.preview.data[section.dataPath] : preview.preview.data
        
        htmlContent += `
          <div class="section">
            <div class="section-header">
              <div class="section-title">
                ${section.icon ? `<span>${section.icon}</span>` : ''}
                ${section.title}
              </div>
              ${section.description ? `<div class="section-description">${section.description}</div>` : ''}
            </div>
            <div class="section-content">
        `
        
        if (!sectionData || (Array.isArray(sectionData) && sectionData.length === 0)) {
          htmlContent += `<div class="empty-state">No data available for this section</div>`
        } else if (section.component === 'table' && Array.isArray(sectionData) && sectionData.length > 0) {
          const config = section.config as any
          const columns = config?.columns || Object.keys(sectionData[0])
          
          htmlContent += '<table><thead><tr>'
          columns.forEach((col: any) => {
            const label = typeof col === 'object' ? col.label : col.toString().replace(/_/g, ' ')
            htmlContent += `<th>${label}</th>`
          })
          htmlContent += '</tr></thead><tbody>'
          
          sectionData.slice(0, 100).forEach(row => { // Limit to first 100 rows for PDF
            htmlContent += '<tr>'
            columns.forEach((col: any) => {
              const key = typeof col === 'object' ? col.key : col
              let value = row[key]
              
              // Format value
              if (value === null || value === undefined) value = '-'
              else if (typeof value === 'boolean') value = value ? '✓' : '✗'
              else if (typeof value === 'object') value = JSON.stringify(value)
              
              htmlContent += `<td>${value}</td>`
            })
            htmlContent += '</tr>'
          })
          
          htmlContent += '</tbody></table>'
          
          if (sectionData.length > 100) {
            htmlContent += `<div style="margin-top: 10px; color: #6b7280; font-size: 12px; font-style: italic;">Showing first 100 of ${sectionData.length} rows</div>`
          }
        } else if (section.component === 'keyvalue' && typeof sectionData === 'object') {
          const config = section.config as any
          const pairs = config?.pairs || Object.entries(sectionData).map(([key]) => ({
            label: key.replace(/_/g, ' ').toUpperCase(),
            valuePath: key
          }))
          
          htmlContent += '<table class="key-value-table">'
          pairs.forEach((pair: any) => {
            const value = sectionData[pair.valuePath] || '-'
            htmlContent += `<tr><td>${pair.label}</td><td>${value}</td></tr>`
          })
          htmlContent += '</table>'
        } else if (section.component === 'list' && Array.isArray(sectionData)) {
          htmlContent += '<table><tbody>'
          sectionData.forEach(item => {
            const displayValue = typeof item === 'object' 
              ? (item.name || item.title || JSON.stringify(item))
              : item
            htmlContent += `<tr><td>${displayValue}</td></tr>`
          })
          htmlContent += '</tbody></table>'
        } else if (section.component === 'grid') {
          const config = section.config as any
          const metrics = config?.metrics || []
          
          htmlContent += '<table><tbody>'
          metrics.forEach((metric: any) => {
            const value = sectionData[metric.valuePath] || '-'
            htmlContent += `<tr><td style="font-weight: 600;">${metric.label}</td><td>${value}</td></tr>`
          })
          htmlContent += '</tbody></table>'
        }
        
        htmlContent += `
            </div>
          </div>
        `
      })
      
      htmlContent += `
          <div class="footer">
            <p>This report was automatically generated by Territory Technology Solutions</p>
            <p style="margin-top: 5px;">© ${new Date().getFullYear()} Territory Technology Solutions. All rights reserved.</p>
          </div>
        </body>
        </html>
      `
      
      // Open print dialog with enhanced content
      const printWindow = window.open('', '_blank', 'width=1200,height=800')
      if (printWindow) {
        printWindow.document.write(htmlContent)
        printWindow.document.close()
        printWindow.focus()
        
        // Wait for content to load, then print
        setTimeout(() => {
          printWindow.print()
          toast.success('PDF Export Ready', 'Professional PDF preview opened. Save as PDF to export.')
        }, 750)
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
