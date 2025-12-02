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

  const fetchSuggestedMatches = async () => {
    if (!integrationCustomer) return

    try {
      setIsLoadingSuggestions(true)
      const response = await api.get(`/customers/unmapped`)
      const rawData = response.data || response
      const data: any[] = Array.isArray(rawData) ? rawData : []

      const currentCustomer = data.find(
        (c: any) => c.integration_customer_id === integrationCustomer.integration_customer_id
      )

      if (currentCustomer && currentCustomer.suggested_matches) {
        setSuggestedMatches(currentCustomer.suggested_matches)
      } else {
        setSuggestedMatches([])
      }
    } catch (err) {
      setSuggestedMatches([])
    } finally {
      setIsLoadingSuggestions(false)
    }
  }

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
      const payload: any = {
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
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to map customer'
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
    if (customerId) {
      fetchIntegrationDetails()
    }
  }, [customerId])

  const fetchIntegrationDetails = async () => {
    try {
      setIsLoading(true)
      setError(null)

      const response = await api.get<any>(`/customers/${customerId}/integrations`)

      let integrationData: import('./types').CustomerIntegrationDetail[] = []

      if (response && (response as any).status === 'success' && Array.isArray((response as any).data)) {
        integrationData = (response as any).data
      }
      else if (response && response.success && Array.isArray(response.data)) {
        integrationData = response.data
      }
      else if (response && Array.isArray(response.data)) {
        integrationData = response.data
      }
      else if (Array.isArray(response)) {
        integrationData = response
      }
      else {
        setError('Failed to load integration details - unexpected response format')
        return
      }

      setIntegrations(integrationData)

      if (integrationData.length > 0) {
        setCustomerName(integrationData[0].name)
        integrationData.forEach(integration => {
          fetchStats(integration.integration_customer_id)
        })
      }
    } catch (err: any) {
      const errorMessage = err.message || err.response?.data?.message || 'Failed to load integration details'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  const fetchStats = async (integrationCustomerId: number) => {
    if (stats[integrationCustomerId] || loadingStats[integrationCustomerId]) return

    setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }))

    try {
      const response = await api.get(
        `/customers/${customerId}/integrations/${integrationCustomerId}/stats`
      )
      const data = (response as any).data || response
      setStats(prev => ({ ...prev, [integrationCustomerId]: data }))
    } catch (err: any) {
      // Stats are optional, fail silently
    } finally {
      setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }))
    }
  }

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
  const statusFilter = useCustomersStore((state) => state.statusFilter)
  const reportFilter = useCustomersStore((state) => state.reportFilter)
  const modals = useCustomersStore((state) => state.modals)
  const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer)
  const selectedCustomer = useCustomersStore((state) => state.selectedCustomer)
  const currentPage = useCustomersStore((state) => state.currentPage)
  const totalPages = useCustomersStore((state) => state.totalPages)
  const total = useCustomersStore((state) => state.total)

  const fetchCustomers = useCustomersStore((state) => state.fetchCustomers)
  const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
  const setStatusFilter = useCustomersStore((state) => state.setStatusFilter)
  const setReportFilter = useCustomersStore((state) => state.setReportFilter)
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
  }, [fetchCustomers])

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
    fetchCustomers()
    closeModal(modalType)
    toast.success(message, description)
  }

  return {
    navigate,
    toast,
    searchQuery,
    statusFilter,
    reportFilter,
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
    setStatusFilter,
    setReportFilter,
    fetchCustomers,
    openModal,
    closeModal,
    handleStartMapping,
    handleMapSuccess,
    handleCustomerAction,
    handleModalSuccess,
  }
}
