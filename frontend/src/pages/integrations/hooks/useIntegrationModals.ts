import { useState } from 'react'
import type { Integration } from '../types/integration'

export function useIntegrationModals() {
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [deleteModalOpen, setDeleteModalOpen] = useState(false)
  const [syncLogsModalOpen, setSyncLogsModalOpen] = useState(false)
  const [healthModalOpen, setHealthModalOpen] = useState(false)
  const [rateLimitModalOpen, setRateLimitModalOpen] = useState(false)
  const [historicalImportModalOpen, setHistoricalImportModalOpen] = useState(false)
  const [selectedIntegration, setSelectedIntegration] = useState<Integration | null>(null)

  return {
    addModalOpen,
    setAddModalOpen,
    editModalOpen,
    setEditModalOpen,
    deleteModalOpen,
    setDeleteModalOpen,
    syncLogsModalOpen,
    setSyncLogsModalOpen,
    healthModalOpen,
    setHealthModalOpen,
    rateLimitModalOpen,
    setRateLimitModalOpen,
    historicalImportModalOpen,
    setHistoricalImportModalOpen,
    selectedIntegration,
    setSelectedIntegration,
  }
}
