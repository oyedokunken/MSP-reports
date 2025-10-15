import { useCallback, useRef, useState } from 'react'

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
      intervalRef.current = window.setInterval(() => {
        setProgress(prev => Math.min(prev + Math.random() * 15, 90))
      }, 300)

      await new Promise(resolve => setTimeout(resolve, 2000))

      if (intervalRef.current) window.clearInterval(intervalRef.current)
      setProgress(100)
      await new Promise(resolve => setTimeout(resolve, 300))
      onSuccess('Sync Complete', `${name} synced successfully`)
    } catch (e) {
      onError('Sync Failed', `Failed to sync ${name}`)
    } finally {
      setSyncingId(null)
      setProgress(0)
    }
  }, [onSuccess, onError])

  return { syncingId, progress, handleSync }
}


