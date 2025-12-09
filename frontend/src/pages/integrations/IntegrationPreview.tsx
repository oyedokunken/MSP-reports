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
    handleRefresh,
    handleSelectionChange,
    handleBack,
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
    <FloatingLayout>
      <div className="space-y-6">
        <PreviewHeader
          integrationName={preview.template?.metadata?.display_name || preview.integration.name}
          customerName={preview.preview.customer?.name}
          isRefreshing={refreshing}
          onBack={handleBack}
          onRefresh={handleRefresh}
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
  )
}
