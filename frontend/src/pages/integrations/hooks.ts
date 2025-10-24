import { useCallback, useRef, useState } from 'react'
import { api } from '@/lib/api'

export const useIntegrationSync = (
  onSuccess: (title: string, description?: string) => void,
  onError: (title: string, description?: string) => void
) => {
  const [syncingId, setSyncingId] = useState<number | null>(null)
  const [progress, setProgress] = useState<number>(0)
  const intervalRef = useRef<number | null>(null)

  const handleSync = useCallback(async (integrationId: number, name: string) => {
    setSyncingId(integrationId)
    setProgress(0)
    try {
      // Start progress animation
      intervalRef.current = window.setInterval(() => {
        setProgress(prev => Math.min(prev + Math.random() * 15, 90))
      }, 300)

      // Call real API
      await api.get(`/integrations/${integrationId}/sync`)

      if (intervalRef.current) window.clearInterval(intervalRef.current)
      setProgress(100)
      await new Promise(resolve => setTimeout(resolve, 300))
      
      // Sync completed successfully
      onSuccess('Sync Complete', `${name} synced successfully`)
    } catch (e: any) {
      if (intervalRef.current) window.clearInterval(intervalRef.current)
      const errorMsg = e.response?.data?.message || e.message || 'Sync failed'
      onError('Sync Failed', `${name}: ${errorMsg}`)
    } finally {
      setSyncingId(null)
      setProgress(0)
    }
  }, [onSuccess, onError])

  return { syncingId, progress, handleSync }
}


