import { useCallback, useMemo, useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import type { CanonicalCustomer, MappingRow, IntegrationCustomer, ExistingCustomer, SuggestedMatch } from './types'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { useCustomersStore } from '@/stores/useCustomersStore'

export const useCustomerMapping = (initialRows: MappingRow[], canonical: CanonicalCustomer[]) => {
  const [rows, setRows] = useState<MappingRow[]>(initialRows)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [activeRowId, setActiveRowId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState('')

  const filteredCanonical = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return canonical
    return canonical.filter((c) => c.name.toLowerCase().includes(q))
  }, [search, canonical])

  const getCanonicalName = useCallback((id?: string) => {
    const found = canonical.find((c) => c.id === id)
    return found ? found.name : '—'
  }, [canonical])

  const openMapDialog = useCallback((rowId: number, preselect?: string) => {
    setActiveRowId(rowId)
    setSelectedCustomerId(preselect || '')
    setSearch('')
    setIsDialogOpen(true)
  }, [])

  const closeDialog = useCallback(() => {
    setIsDialogOpen(false)
    setActiveRowId(null)
    setSelectedCustomerId('')
    setSearch('')
  }, [])

  const confirmMapping = useCallback(() => {
    if (activeRowId == null || !selectedCustomerId) return
    setRows((prev) => prev.map((r) => (r.id === activeRowId ? { ...r, mappedCustomerId: selectedCustomerId, status: 'mapped' } : r)))
    closeDialog()
  }, [activeRowId, selectedCustomerId, closeDialog])

  return {
    rows,
    setRows,
    isDialogOpen,
    activeRowId,
    search,
    setSearch,
    selectedCustomerId,
    setSelectedCustomerId,
    filteredCanonical,
    getCanonicalName,
    openMapDialog,
    closeDialog,
    confirmMapping,
  }
}

// Hook for MapCustomerModal
export const useMapCustomerModal = (
  open: boolean,
  integrationCustomer: IntegrationCustomer | null,
  existingCustomers: ExistingCustomer[],
  onSuccess?: () => void
) => {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
  const [createNew, setCreateNew] = useState(false)
  const [newCustomerName, setNewCustomerName] = useState('')
  const [suggestedMatches, setSuggestedMatches] = useState<SuggestedMatch[]>([])
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState(false)

  // Reset form when modal opens
  useEffect(() => {
    const fetchSuggestedMatches = async () => {
      if (!integrationCustomer) return

      try {
        setIsLoadingSuggestions(true)
        const response = await api.get(`/customers/unmapped`)
        const rawData = response.data || response
        const data: unknown[] = Array.isArray(rawData) ? rawData : []

        const currentCustomer = data.find(
          (c: unknown) => {
            if (typeof c === 'object' && c !== null && 'integration_customer_id' in c) {
              return (c as { integration_customer_id: number }).integration_customer_id === integrationCustomer.integration_customer_id
            }
            return false
          }
        ) as { suggested_matches?: SuggestedMatch[] } | undefined

        if (currentCustomer && currentCustomer.suggested_matches) {
          setSuggestedMatches(currentCustomer.suggested_matches)
        } else {
          setSuggestedMatches([])
        }
      } catch {
        setError("Failed to load suggested matches");
      } finally {
        setIsLoadingSuggestions(false)
      }
    }

    if (open && integrationCustomer) {
      setSearchTerm('')
      setSelectedCustomerId(null)
      setError('')
      setSuccess(false)
      setCreateNew(false)
      setNewCustomerName(integrationCustomer.name)
      fetchSuggestedMatches()
    }
  }, [open, integrationCustomer])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (createNew) {
      if (!newCustomerName.trim()) {
        setError('Please enter a customer name')
        return
      }
    } else {
      if (!selectedCustomerId || !integrationCustomer) {
        setError('Please select a customer to map to')
        return
      }
    }

    setError('')
    setIsSubmitting(true)

    try {
      const payload: {
        integration_customer_id: number;
        mapping_type: string;
        create_new?: boolean;
        canonical_name?: string;
        customer_id?: number | null;
      } = {
        integration_customer_id: integrationCustomer!.integration_customer_id,
        mapping_type: 'manual'
      }

      if (createNew) {
        payload.create_new = true
        payload.canonical_name = newCustomerName.trim()
      } else {
        payload.customer_id = selectedCustomerId
      }

      await api.post('/customers/map', payload)

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        onSuccess?.()
      }, 2000)
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to map customer'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredCustomers = existingCustomers.filter(customer =>
    customer.customer_id !== null &&
    customer.canonical_name &&
    customer.canonical_name.toLowerCase().includes(searchTerm.toLowerCase())
  )

  return {
    isSubmitting,
    error,
    success,
    searchTerm,
    setSearchTerm,
    selectedCustomerId,
    setSelectedCustomerId,
    createNew,
    setCreateNew,
    newCustomerName,
    setNewCustomerName,
    suggestedMatches,
    isLoadingSuggestions,
    filteredCustomers,
    handleSubmit,
  }
}

export const useCustomerIntegrationDetails = (customerId: string | undefined) => {
  const [integrations, setIntegrations] = useState<import('./types').CustomerIntegrationDetail[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [customerName, setCustomerName] = useState<string>('')
  const [stats, setStats] = useState<{ [key: number]: import('./types').IntegrationStats }>({})
  const [loadingStats, setLoadingStats] = useState<{ [key: number]: boolean }>({})

  useEffect(() => {
    const fetchIntegrationDetails = async () => {
      if (!customerId) return

      try {
        setIsLoading(true)
        setError(null)

        const response = await api.get(`/customers/${customerId}/integrations`)

        let integrationData: import('./types').CustomerIntegrationDetail[] = []

        // Type guard for response structure
        interface ApiResponse {
          status?: string;
          success?: boolean;
          data?: unknown;
        }

        const apiResponse = response as ApiResponse

        if (apiResponse && apiResponse.status === 'success' && Array.isArray(apiResponse.data)) {
          integrationData = apiResponse.data as import('./types').CustomerIntegrationDetail[]
        }
        else if (apiResponse && apiResponse.success && Array.isArray(apiResponse.data)) {
          integrationData = apiResponse.data as import('./types').CustomerIntegrationDetail[]
        }
        else if (apiResponse && Array.isArray(apiResponse.data)) {
          integrationData = apiResponse.data as import('./types').CustomerIntegrationDetail[]
        }
        else if (Array.isArray(response)) {
          integrationData = response as import('./types').CustomerIntegrationDetail[]
        }
        else {
          setError('Failed to load integration details - unexpected response format')
          return
        }

        setIntegrations(integrationData)

        if (integrationData.length > 0) {
          setCustomerName(integrationData[0].name)

          // Fetch stats for each integration
          integrationData.forEach(async (integration) => {
            const integrationCustomerId = integration.integration_customer_id
            setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }))

            try {
              const statsResponse = await api.get(
                `/customers/${customerId}/integrations/${integrationCustomerId}/stats`
              )
              const data = (statsResponse as { data?: unknown }).data || statsResponse
              setStats(prev => ({ ...prev, [integrationCustomerId]: data as import('./types').IntegrationStats }))
            } catch {
              // Stats are optional, fail silently
            } finally {
              setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }))
            }
          })
        }
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Failed to load integration details'
        setError(errorMessage)
      } finally {
        setIsLoading(false)
      }
    }

    fetchIntegrationDetails()
  }, [customerId])

  return {
    integrations,
    isLoading,
    error,
    customerName,
    stats,
    loadingStats,
  }
}

export const useCustomerList = () => {
  const navigate = useNavigate()
  const toast = useToast()

  const customers = useCustomersStore((state) => state.customers)
  const unmappedCount = useCustomersStore((state) => state.unmappedCount)
  const isLoading = useCustomersStore((state) => state.isLoading)
  const searchQuery = useCustomersStore((state) => state.searchQuery)
  const statusFilters = useCustomersStore((state) => state.statusFilters)
  const reportFilters = useCustomersStore((state) => state.reportFilters)
  const integrationFilters = useCustomersStore((state) => state.integrationFilters)
  const modals = useCustomersStore((state) => state.modals)
  const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer)
  const selectedCustomer = useCustomersStore((state) => state.selectedCustomer)
  const currentPage = useCustomersStore((state) => state.currentPage)
  const totalPages = useCustomersStore((state) => state.totalPages)
  const total = useCustomersStore((state) => state.total)

  const fetchCustomers = useCustomersStore((state) => state.fetchCustomers)
  const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
  const setStatusFilters = useCustomersStore((state) => state.setStatusFilters)
  const setReportFilters = useCustomersStore((state) => state.setReportFilters)
  const setIntegrationFilters = useCustomersStore((state) => state.setIntegrationFilters)
  const clearAllFilters = useCustomersStore((state) => state.clearAllFilters)
  const openModal = useCustomersStore((state) => state.openModal)
  const closeModal = useCustomersStore((state) => state.closeModal)
  const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer)
  const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer)
  const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers)
  const getUnmappedCustomers = useCustomersStore((state) => state.getUnmappedCustomers)

  useEffect(() => {
    fetchCustomers().catch((err) => {
      toast.error(
        "Failed to load customers",
        err.response?.data?.message || err.message
      )
    })
  }, [fetchCustomers, toast])

  const mappedCustomers = getMappedCustomers()
  const unmappedCustomers = getUnmappedCustomers()
  const totalMapped = customers.filter((c) => c.customer_id !== null).length

  const handleStartMapping = () => {
    if (unmappedCustomers.length > 0) {
      const firstUnmapped = unmappedCustomers[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: firstUnmapped.integration_customer_id,
        integration_type: firstUnmapped.integration_type,
        display_name: firstUnmapped.display_name,
        external_id: firstUnmapped.external_id,
        name: firstUnmapped.name,
        active: firstUnmapped.active,
        last_seen_at: firstUnmapped.last_seen_at,
      })
      openModal("map")
    }
  }

  const handleMapSuccess = async () => {
    await fetchCustomers()
    const updatedUnmapped = getUnmappedCustomers()

    if (updatedUnmapped.length > 0) {
      const nextUnmapped = updatedUnmapped[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: nextUnmapped.integration_customer_id,
        integration_type: nextUnmapped.integration_type,
        display_name: nextUnmapped.display_name,
        external_id: nextUnmapped.external_id,
        name: nextUnmapped.name,
        active: nextUnmapped.active,
        last_seen_at: nextUnmapped.last_seen_at,
      })
    } else {
      closeModal("map")
      toast.success("All Done!", "All customers have been mapped")
    }
  }

  const handleCustomerAction = (customer: ExistingCustomer, action: 'edit' | 'integrations' | 'merge' | 'split') => {
    setSelectedCustomer({
      customer_id: customer.customer_id!,
      name: customer.canonical_name!,
      status: customer.status!,
      integration_count: customer.integrations.length,
    })
    openModal(action)
  }

  const handleModalSuccess = (modalType: 'edit' | 'integrations' | 'merge' | 'split', message: string, description: string) => {
    fetchCustomers(currentPage, true)
    closeModal(modalType)
    toast.success(message, description)
  }

  return {
    navigate,
    toast,
    searchQuery,
    statusFilters,
    reportFilters,
    integrationFilters,
    totalMapped,
    mappedCustomers,
    unmappedCustomers,
    unmappedCount,
    isLoading,
    modals,
    selectedUnmappedCustomer,
    selectedCustomer,
    currentPage,
    totalPages,
    total,
    setSearchQuery,
    setStatusFilters,
    setReportFilters,
    setIntegrationFilters,
    clearAllFilters,
    fetchCustomers,
    openModal,
    closeModal,
    handleStartMapping,
    handleMapSuccess,
    handleCustomerAction,
    handleModalSuccess,
  }
}

// Hook for Master Customer List (Source of Truth = true)
export const useMasterCustomerList = () => {
  const navigate = useNavigate()
  const toast = useToast()

  const customers = useCustomersStore((state) => state.customers)
  const unmappedCount = useCustomersStore((state) => state.unmappedCount)
  const isLoading = useCustomersStore((state) => state.isLoading)
  const searchQuery = useCustomersStore((state) => state.searchQuery)
  const statusFilters = useCustomersStore((state) => state.statusFilters)
  const reportFilters = useCustomersStore((state) => state.reportFilters)
  const integrationFilters = useCustomersStore((state) => state.integrationFilters)
  const modals = useCustomersStore((state) => state.modals)
  const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer)
  const selectedCustomer = useCustomersStore((state) => state.selectedCustomer)
  const currentPage = useCustomersStore((state) => state.currentPage)
  const totalPages = useCustomersStore((state) => state.totalPages)
  const total = useCustomersStore((state) => state.total)

  const fetchCustomers = useCustomersStore((state) => state.fetchCustomers)
  const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
  const setStatusFilters = useCustomersStore((state) => state.setStatusFilters)
  const setReportFilters = useCustomersStore((state) => state.setReportFilters)
  const setIntegrationFilters = useCustomersStore((state) => state.setIntegrationFilters)
  const clearAllFilters = useCustomersStore((state) => state.clearAllFilters)
  const openModal = useCustomersStore((state) => state.openModal)
  const closeModal = useCustomersStore((state) => state.closeModal)
  const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer)
  const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer)
  const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers)
  const getUnmappedCustomers = useCustomersStore((state) => state.getUnmappedCustomers)

  useEffect(() => {
    fetchCustomers(1, false, true).catch((err) => {
      toast.error(
        "Failed to load customers",
        err.response?.data?.message || err.message
      )
    })
  }, [fetchCustomers, toast])

  const mappedCustomers = getMappedCustomers()
  const unmappedCustomers = getUnmappedCustomers()
  const totalMapped = customers.filter((c) => c.customer_id !== null).length

  const handleStartMapping = () => {
    if (unmappedCustomers.length > 0) {
      const firstUnmapped = unmappedCustomers[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: firstUnmapped.integration_customer_id,
        integration_type: firstUnmapped.integration_type,
        display_name: firstUnmapped.display_name,
        external_id: firstUnmapped.external_id,
        name: firstUnmapped.name,
        active: firstUnmapped.active,
        last_seen_at: firstUnmapped.last_seen_at,
      })
      openModal("map")
    }
  }

  const handleMapSuccess = async () => {
    await fetchCustomers(currentPage, true, true)
    const updatedUnmapped = getUnmappedCustomers()

    if (updatedUnmapped.length > 0) {
      const nextUnmapped = updatedUnmapped[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: nextUnmapped.integration_customer_id,
        integration_type: nextUnmapped.integration_type,
        display_name: nextUnmapped.display_name,
        external_id: nextUnmapped.external_id,
        name: nextUnmapped.name,
        active: nextUnmapped.active,
        last_seen_at: nextUnmapped.last_seen_at,
      })
    } else {
      closeModal("map")
      toast.success("All Done!", "All customers have been mapped")
    }
  }

  const handleCustomerAction = (customer: ExistingCustomer, action: 'edit' | 'integrations' | 'merge' | 'split') => {
    setSelectedCustomer({
      customer_id: customer.customer_id!,
      name: customer.canonical_name!,
      status: customer.status!,
      integration_count: customer.integrations.length,
    })
    openModal(action)
  }

  const handleModalSuccess = (modalType: 'edit' | 'integrations' | 'merge' | 'split', message: string, description: string) => {
    fetchCustomers(currentPage, true, true)
    closeModal(modalType)
    toast.success(message, description)
  }

  const handlePageChange = (page: number) => {
    fetchCustomers(page, false, true)
  }

  return {
    navigate,
    toast,
    searchQuery,
    statusFilters,
    reportFilters,
    integrationFilters,
    totalMapped,
    mappedCustomers,
    unmappedCustomers,
    unmappedCount,
    isLoading,
    modals,
    selectedUnmappedCustomer,
    selectedCustomer,
    currentPage,
    totalPages,
    total,
    setSearchQuery,
    setStatusFilters,
    setReportFilters,
    setIntegrationFilters,
    clearAllFilters,
    fetchCustomers: handlePageChange,
    openModal,
    closeModal,
    handleStartMapping,
    handleMapSuccess,
    handleCustomerAction,
    handleModalSuccess,
  }
}

// Hook for Secondary Customer List (Source of Truth = false)
export const useSecondaryCustomerList = () => {
  const navigate = useNavigate()
  const toast = useToast()

  const customers = useCustomersStore((state) => state.customers)
  const isLoading = useCustomersStore((state) => state.isLoading)
  const searchQuery = useCustomersStore((state) => state.searchQuery)
  const statusFilters = useCustomersStore((state) => state.statusFilters)
  const reportFilters = useCustomersStore((state) => state.reportFilters)
  const integrationFilters = useCustomersStore((state) => state.integrationFilters)
  const modals = useCustomersStore((state) => state.modals)
  const selectedCustomer = useCustomersStore((state) => state.selectedCustomer)
  const currentPage = useCustomersStore((state) => state.currentPage)
  const totalPages = useCustomersStore((state) => state.totalPages)
  const total = useCustomersStore((state) => state.total)

  const fetchCustomers = useCustomersStore((state) => state.fetchCustomers)
  const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
  const setStatusFilters = useCustomersStore((state) => state.setStatusFilters)
  const setReportFilters = useCustomersStore((state) => state.setReportFilters)
  const setIntegrationFilters = useCustomersStore((state) => state.setIntegrationFilters)
  const clearAllFilters = useCustomersStore((state) => state.clearAllFilters)
  const openModal = useCustomersStore((state) => state.openModal)
  const closeModal = useCustomersStore((state) => state.closeModal)
  const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer)
  const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers)

  useEffect(() => {
    fetchCustomers(1, false, false).catch((err) => {
      toast.error(
        "Failed to load customers",
        err.response?.data?.message || err.message
      )
    })
  }, [fetchCustomers, toast])

  const mappedCustomers = getMappedCustomers()
  const totalMapped = customers.filter((c) => c.customer_id !== null).length

  const handleCustomerAction = (customer: ExistingCustomer, action: 'edit' | 'integrations' | 'merge' | 'split') => {
    setSelectedCustomer({
      customer_id: customer.customer_id!,
      name: customer.canonical_name!,
      status: customer.status!,
      integration_count: customer.integrations.length,
    })
    openModal(action)
  }

  const handleModalSuccess = (modalType: 'edit' | 'integrations' | 'merge' | 'split', message: string, description: string) => {
    fetchCustomers(currentPage, true, false)
    closeModal(modalType)
    toast.success(message, description)
  }

  const handlePageChange = (page: number) => {
    fetchCustomers(page, false, false)
  }

  return {
    navigate,
    toast,
    searchQuery,
    statusFilters,
    reportFilters,
    integrationFilters,
    totalMapped,
    mappedCustomers,
    isLoading,
    modals,
    selectedCustomer,
    currentPage,
    totalPages,
    total,
    setSearchQuery,
    setStatusFilters,
    setReportFilters,
    setIntegrationFilters,
    clearAllFilters,
    fetchCustomers: handlePageChange,
    openModal,
    closeModal,
    handleCustomerAction,
    handleModalSuccess,
  }
}
