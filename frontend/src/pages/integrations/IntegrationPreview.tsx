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
    deliverViaEmail,
    emailTo,
    setShowDownloadConfirm,
    setDeliverViaEmail,
    setEmailTo,
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
            downloadButtonLabel={isITGlueIntegration ? 'M365 Licenses' : 'Device Report'}
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

      {showDownloadConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
              {isITGlueIntegration ? 'Download M365 License Consumption Report' : 'Download LibreNMS Device Report'}
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              {isITGlueIntegration
                ? 'This will generate an Excel file containing M365 license consumption data for all customers in your ITGlue integration.'
                : 'This will generate a PDF report containing device status and metrics for all customers in your LibreNMS integration.'}
            </p>

            <div className="mb-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={deliverViaEmail}
                  onChange={(e) => setDeliverViaEmail(e.target.checked)}
                  className="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary dark:bg-gray-700 dark:border-gray-600"
                />
                <span className="text-sm font-medium text-gray-900 dark:text-white">
                  Deliver via Email
                </span>
              </label>
            </div>

            {deliverViaEmail && (
              <div className="space-y-3 mb-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Send To
                  </label>
                  <input
                    type="email"
                    value={emailTo}
                    onChange={(e) => setEmailTo(e.target.value)}
                    placeholder="recipient@example.com"
                    className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                  />
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    The report will be sent from your account
                  </p>
                </div>
              </div>
            )}

            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setShowDownloadConfirm(false)}
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={confirmDownload}
                disabled={deliverViaEmail && !emailTo}
                className="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {deliverViaEmail ? 'Send Email' : 'OK, Generate Report'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
