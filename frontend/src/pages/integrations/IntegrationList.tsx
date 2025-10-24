import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import type { Integration } from './types'
import { useIntegrationSync } from './hooks'
import { IntegrationHeader } from './components/IntegrationHeader'
import { IntegrationGrid } from './components/IntegrationGrid'
import { AddIntegrationModal } from '@/components/modals/AddIntegrationModal'
import { ViewIntegrationCustomersModal } from '@/components/modals/ViewIntegrationCustomersModal'
import { EditIntegrationModal } from './modals/EditIntegrationModal'
import { DeleteIntegrationModal } from './modals/DeleteIntegrationModal'
import { ViewSyncLogsModal } from './modals/ViewSyncLogsModal'
import { IntegrationHealthModal } from './modals/IntegrationHealthModal'
import { RateLimitStatusModal } from './modals/RateLimitStatusModal'
import { HistoricalImportModal } from './modals/HistoricalImportModal'
import { api } from '@/lib/api'

export default function IntegrationList() {
  const toast = useToast()
  
  // Handle sync success - just refresh the list from backend
  const handleSyncSuccess = (title: string, description?: string) => {
    toast.success(title, description)
    // Refresh integrations list to get updated sync time from backend
    fetchIntegrations()
  }
  
  const { syncingId, progress, handleSync } = useIntegrationSync(handleSyncSuccess, toast.error)
  const [isLoading, setIsLoading] = useState(true)
  const [integrations, setIntegrations] = useState<Integration[]>([])
  const [availableTypes, setAvailableTypes] = useState<string[]>([])
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [viewCustomersModalOpen, setViewCustomersModalOpen] = useState(false)
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [deleteModalOpen, setDeleteModalOpen] = useState(false)
  const [syncLogsModalOpen, setSyncLogsModalOpen] = useState(false)
  const [healthModalOpen, setHealthModalOpen] = useState(false)
  const [rateLimitModalOpen, setRateLimitModalOpen] = useState(false)
  const [historicalImportModalOpen, setHistoricalImportModalOpen] = useState(false)
  const [selectedIntegration, setSelectedIntegration] = useState<Integration | null>(null)
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
        
        const transformedIntegrations: Integration[] = (responseData.configured || []).map((int: any) => {
          return {
            id: int.integration_id,
            name: int.display_name,
            type: int.integration_type.toLowerCase(),
            status: int.status,
            lastSync: int.last_sync_at ? new Date(int.last_sync_at).toLocaleString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            }) : 'Never',
            syncStatus: int.last_sync_status || 'pending',
            customers: int.unmapped_customers_count || 0,
          }
        })
        
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

  const handleViewCustomers = (id: number) => {
    const integration = integrations.find(int => int.id === id)
    if (integration) {
      setSelectedIntegration(integration)
      setViewCustomersModalOpen(true)
    }
  }

  const handleEdit = (integration: Integration) => {
    setSelectedIntegration(integration)
    setEditModalOpen(true)
  }

  const handleViewLogs = (integration: Integration) => {
    setSelectedIntegration(integration)
    setSyncLogsModalOpen(true)
  }

  const handleHealthCheck = (integration: Integration) => {
    setSelectedIntegration(integration)
    setHealthModalOpen(true)
  }

  const handleRateLimit = (integration: Integration) => {
    setSelectedIntegration(integration)
    setRateLimitModalOpen(true)
  }

  const handleHistoricalImport = (integration: Integration) => {
    setSelectedIntegration(integration)
    setHistoricalImportModalOpen(true)
  }

  const handleDelete = (integration: Integration) => {
    setSelectedIntegration(integration)
    setDeleteModalOpen(true)
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
          onEdit={handleEdit}
          onViewLogs={handleViewLogs}
          onHealthCheck={handleHealthCheck}
          onRateLimit={handleRateLimit}
          onHistoricalImport={handleHistoricalImport}
          onDelete={handleDelete}
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

        <EditIntegrationModal
          open={editModalOpen}
          onOpenChange={setEditModalOpen}
          integration={selectedIntegration}
          onSuccess={() => {
            fetchIntegrations()
            toast.success('Integration Updated', 'Integration settings have been updated')
          }}
        />

        <DeleteIntegrationModal
          open={deleteModalOpen}
          onOpenChange={setDeleteModalOpen}
          integration={selectedIntegration}
          onSuccess={() => {
            fetchIntegrations()
            toast.success('Integration Deleted', 'Integration has been removed')
          }}
        />

        <ViewSyncLogsModal
          open={syncLogsModalOpen}
          onOpenChange={setSyncLogsModalOpen}
          integration={selectedIntegration}
        />

        <IntegrationHealthModal
          open={healthModalOpen}
          onOpenChange={setHealthModalOpen}
          integration={selectedIntegration}
        />

        <RateLimitStatusModal
          open={rateLimitModalOpen}
          onOpenChange={setRateLimitModalOpen}
          integration={selectedIntegration}
        />

        <HistoricalImportModal
          open={historicalImportModalOpen}
          onOpenChange={setHistoricalImportModalOpen}
          integration={selectedIntegration}
          onSuccess={() => {
            toast.success('Import Started', 'Historical data import has been queued')
          }}
        />
      </div>
    </FloatingLayout>
  )
}
