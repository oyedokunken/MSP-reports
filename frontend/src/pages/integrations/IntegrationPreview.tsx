import FloatingLayout from '@/components/layout/FloatingLayout'
import {
  PreviewHeader,
  PreviewLoadingState,
  PreviewErrorState,
  PreviewContent,
} from './components'
import { useIntegrationPreview } from './hooks/useIntegrationPreview'

export const IntegrationPreview = () => {
  const {
    preview,
    loading,
    error,
    refreshing,
    isDownloading,
    isITGlueIntegration,
    showDownloadConfirm,
    setShowDownloadConfirm,
    handleRefresh,
    handleSelectionChange,
    handleBack,
    handleDownloadReport,
    confirmDownload,
  } = useIntegrationPreview()

  if (loading) {
    return <PreviewLoadingState />
  }

  if (error) {
    return (
      <PreviewErrorState
        error={error}
        onBack={handleBack}
        onRetry={handleRefresh}
      />
    )
  }

  if (!preview) {
    return null
  }

  return (
    <>
      <FloatingLayout>
        <div className="space-y-6">
          <PreviewHeader
            integrationName={preview.template?.metadata?.display_name || preview.integration.name}
            customerName={preview.preview.customer?.name}
            isRefreshing={refreshing}
            onBack={handleBack}
            onRefresh={handleRefresh}
            onDownloadReport={handleDownloadReport}
            isDownloading={isDownloading}
            showDownloadButton={isITGlueIntegration}
          />

        {preview.template ? (
          <PreviewContent
            preview={preview}
            onSelectionChange={handleSelectionChange}
          />
        ) : (
          <div className="p-8 text-center text-muted-foreground bg-card border border-border rounded-lg">
            No preview configuration available for this integration.
          </div>
        )}

          {/* Footer Info */}
          <div className="p-4 bg-card dark:bg-[hsl(var(--auth-card))] border border-border rounded-lg">
            <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
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

      {/* Download Confirmation Modal */}
      {showDownloadConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
              Download M365 License Consumption Report
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
              This will generate an Excel file containing M365 license consumption data for all customers in your ITGlue integration.
            </p>
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setShowDownloadConfirm(false)}
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={confirmDownload}
                className="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors"
              >
                OK, Generate Report
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
