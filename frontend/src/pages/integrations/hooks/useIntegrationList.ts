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

interface ConfiguredIntegration {
  integration_id: number
  is_source_of_truth: boolean
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
      const response = await api.get<{ data?: { available_types: string[], configured: ConfiguredIntegration[] }; available_types?: string[]; configured?: ConfiguredIntegration[] }>('/integrations')
      // Handle both wrapped and unwrapped response formats
      const data = response.data || response

      setAvailableTypes(data.available_types || [])
      const transformed = transformIntegrations(data)
      setIntegrations(transformed)

      const sourceOfTruth = data.configured?.find((int) => int.is_source_of_truth)
      if (sourceOfTruth) setSourceOfTruthId(sourceOfTruth.integration_id)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Failed to load integrations', error.response?.data?.message || error.message)
    } finally {
      setIsLoading(false)
    }
  }, [toast])

  const handleSetSourceOfTruth = async (id: number, name: string) => {
    try {
      await api.patch('/company/source-integration', { integration_id: id })
      setSourceOfTruthId(id)
      toast.success('Master Customer List Set', `${name} is now your master customer list.`)
      fetchIntegrations()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Failed to Update', error.response?.data?.message || error.message)
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
