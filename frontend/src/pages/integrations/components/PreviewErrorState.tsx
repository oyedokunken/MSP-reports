import { AlertCircle } from 'lucide-react'

interface PreviewErrorStateProps {
  error: string
  onBack: () => void
  onRetry: () => void
}

export function PreviewErrorState({ error, onBack, onRetry }: PreviewErrorStateProps) {
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
            onClick={onBack}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Back to Integrations
          </button>
          <button
            onClick={onRetry}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>
  )
}
