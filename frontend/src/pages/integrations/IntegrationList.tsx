import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import type { Integration } from './types'
import { useIntegrationSync } from './hooks'
import { IntegrationHeader } from './components/IntegrationHeader'
import { IntegrationGrid } from './components/IntegrationGrid'
import { AddIntegrationModal } from '@/components/modals/AddIntegrationModal'
import { ViewIntegrationCustomersModal } from '@/components/modals/ViewIntegrationCustomersModal'
import { api } from '@/lib/api'

export default function IntegrationList() {
  const toast = useToast()
  const { syncingId, progress, handleSync } = useIntegrationSync(toast.success, toast.error)
  const [isLoading, setIsLoading] = useState(true)
  const [integrations, setIntegrations] = useState<Integration[]>([])
  const [availableTypes, setAvailableTypes] = useState<string[]>([])
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [viewCustomersModalOpen, setViewCustomersModalOpen] = useState(false)
  const [selectedIntegration, setSelectedIntegration] = useState<{ id: number, name: string } | null>(null)
  const [sourceOfTruthId, setSourceOfTruthId] = useState<number | null>(null)

  useEffect(() => {
    fetchIntegrations()
  }, [])

  const fetchIntegrations = async () => {
    try {
      setIsLoading(true)
      const data = await api.get<{ available_types: string[], configured: any[] }>('/integrations')
      
      if (data) {
        const responseData = (data as any).data || data
        setAvailableTypes(responseData.available_types || [])
        
        const transformedIntegrations: Integration[] = (responseData.configured || []).map((int: any) => ({
          id: int.integration_id,
          name: int.display_name,
          type: int.integration_type.toLowerCase(),
          status: int.status,
          lastSync: 'Never',
          syncStatus: 'success',
          customers: 0,
        }))
        
        setIntegrations(transformedIntegrations)
        
        // Find source of truth integration
        const sourceOfTruth = responseData.configured.find((int: any) => int.is_source_of_truth)
        if (sourceOfTruth) {
          setSourceOfTruthId(sourceOfTruth.integration_id)
        }
      }
    } catch (err: any) {
      console.error('Failed to load integrations:', err)
      toast.error('Failed to load integrations', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleViewCustomers = (id: number, name: string) => {
    setSelectedIntegration({ id, name })
    setViewCustomersModalOpen(true)
  }

  const handleSetSourceOfTruth = async (id: number, name: string) => {
    try {
      const response = await api.patch('/company/source-integration', {
        integration_id: id
      })
      
      const data = (response as any).data || response
      if (data) {
        setSourceOfTruthId(id)
        toast.success('Master Customer List Set', `${name} is now your master customer list. Other integrations will map to these customers.`)
        fetchIntegrations() // Refresh to get updated data
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to set master customer list'
      toast.error('Failed to Update', errorMessage)
    }
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <IntegrationHeader onAdd={() => setAddModalOpen(true)} />
        <IntegrationGrid
          isLoading={isLoading}
          items={integrations}
          syncingId={syncingId}
          progress={progress}
          onSync={handleSync}
          onEmptyAction={() => setAddModalOpen(true)}
          onViewCustomers={handleViewCustomers}
          onSetSourceOfTruth={handleSetSourceOfTruth}
          sourceOfTruthId={sourceOfTruthId}
        />
        
        <AddIntegrationModal
          open={addModalOpen}
          onOpenChange={setAddModalOpen}
          availableTypes={availableTypes}
          onSuccess={() => {
            fetchIntegrations()
            toast.success('Integration added successfully')
          }}
        />

        <ViewIntegrationCustomersModal
          open={viewCustomersModalOpen}
          onOpenChange={setViewCustomersModalOpen}
          integrationId={selectedIntegration?.id || null}
          integrationName={selectedIntegration?.name || ''}
        />
      </div>
    </FloatingLayout>
  )
}
