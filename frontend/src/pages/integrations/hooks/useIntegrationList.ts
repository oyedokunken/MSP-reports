import { useEffect, useState, useCallback } from 'react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { transformIntegrations } from '../utils/transformIntegrations'
import type { Integration } from '../types/integration'

interface UseIntegrationListReturn {
  integrations: Integration[]
  availableTypes: string[]
  sourceOfTruthId: number | null
  isLoading: boolean
  fetchIntegrations: () => Promise<void>
  handleSetSourceOfTruth: (id: number, name: string) => Promise<void>
}

export function useIntegrationList(): UseIntegrationListReturn {
  const toast = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [integrations, setIntegrations] = useState<Integration[]>([])
  const [availableTypes, setAvailableTypes] = useState<string[]>([])
  const [sourceOfTruthId, setSourceOfTruthId] = useState<number | null>(null)

  const fetchIntegrations = useCallback(async () => {
    try {
      setIsLoading(true)
      const response = await api.get('/integrations')
      const data = (response as any).data || response

      setAvailableTypes(data.available_types || [])
      const transformed = transformIntegrations(data)
      setIntegrations(transformed)

      const sourceOfTruth = data.configured?.find((int: any) => int.is_source_of_truth)
      if (sourceOfTruth) setSourceOfTruthId(sourceOfTruth.integration_id)
    } catch (err: any) {
      toast.error('Failed to load integrations', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }, [])

  const handleSetSourceOfTruth = async (id: number, name: string) => {
    try {
      await api.patch('/company/source-integration', { integration_id: id })
      setSourceOfTruthId(id)
      toast.success('Master Customer List Set', `${name} is now your master customer list.`)
      fetchIntegrations()
    } catch (err: any) {
      toast.error('Failed to Update', err.response?.data?.message || err.message)
    }
  }

  useEffect(() => {
    fetchIntegrations()
  }, [fetchIntegrations])

  return {
    integrations,
    availableTypes,
    sourceOfTruthId,
    isLoading,
    fetchIntegrations,
    handleSetSourceOfTruth,
  }
}
