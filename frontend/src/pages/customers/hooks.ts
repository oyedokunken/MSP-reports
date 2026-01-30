import { useCallback, useMemo, useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { logger } from '@/lib/logger'
import useDebounce from '@/hooks/use-debounce'
import type { CanonicalCustomer, MappingRow, IntegrationCustomer, ExistingCustomer, SuggestedMatch } from './types'
import { customerApi } from '@/services/api/customers'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { useCustomersStore } from '@/stores/useCustomersStore'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'

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


export const useMapCustomerModal = (
  open: boolean,
  integrationCustomers: IntegrationCustomer[],
  _existingCustomers: ExistingCustomer[],
  onSuccess?: () => void
) => {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebounce(searchTerm, 300)

  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
  const [createNew, setCreateNew] = useState(false)
  const [suggestedMatches, setSuggestedMatches] = useState<SuggestedMatch[]>([])
  const [mappedCount, setMappedCount] = useState(0)

  const [candidates, setCandidates] = useState<ExistingCustomer[]>([])
  const [searchPage, setSearchPage] = useState(1)
  const [hasMoreCandidates, setHasMoreCandidates] = useState(true)
  const [isLoadingCandidates, setIsLoadingCandidates] = useState(false)
  const [isLoadingMoreCandidates, setIsLoadingMoreCandidates] = useState(false)

  const [currentIndex, setCurrentIndex] = useState(0)

  const customers = useMemo(() => integrationCustomers || [], [integrationCustomers])
  const currentCustomer = customers[currentIndex] || null
  const totalCount = customers.length
  const isBulk = totalCount > 1

  // Handle modal open/reset
  useEffect(() => {
    if (open) {
      setMappedCount(0)
      setCurrentIndex(0)
      setSuccess(false)
      setError('')
      setSearchTerm('')
      setSelectedCustomerId(null)
      setCreateNew(false)
      setSearchPage(1)
      setCandidates([])
    }
  }, [open])

  // Get suggested matches from current customer if available
  useEffect(() => {
    if (open && currentCustomer) {
      setSuggestedMatches(currentCustomer.suggested_matches || [])
    }
  }, [open, currentCustomer])

  // Fetch candidates logic
  const fetchCandidates = useCallback(async (page: number, search: string, append: boolean = false) => {
    try {
      if (append) {
        setIsLoadingMoreCandidates(true)
      } else {
        setIsLoadingCandidates(true)
        setSearchPage(1)
      }

      const response = await customerApi.getCustomers({
        page,
        search: search.trim() || undefined,
        statuses: ['active']
      })

      const data: ExistingCustomer[] = response.data.map(c => ({
        customer_id: c.customer_id,
        canonical_name: c.canonical_name || '',
        status: c.status,
        integrations: c.integrations || []
      }))

      if (append) {
        setCandidates(prev => [...prev, ...data])
      } else {
        setCandidates(data)
      }

      setHasMoreCandidates(response.pagination.current_page < response.pagination.last_page)
      setSearchPage(response.pagination.current_page)
    } catch (err) {
      logger.error("Failed to fetch candidates", err)
      if (!append) setCandidates([])
      setHasMoreCandidates(false)
    } finally {
      setIsLoadingCandidates(false)
      setIsLoadingMoreCandidates(false)
    }
  }, [])

  // Refetch candidates when debounced search term changes
  useEffect(() => {
    if (open) {
      fetchCandidates(1, debouncedSearch, false)
    }
  }, [open, debouncedSearch, fetchCandidates])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!createNew && !selectedCustomerId) {
      setError('Please select a customer to map to')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      // Use concurrent mapping for better performance
      const results = await Promise.allSettled(
        customers.map(customer =>
          customerApi.mapCustomer({
            integration_customer_id: customer.integration_customer_id,
            create_new: createNew,
            customer_id: createNew ? undefined : selectedCustomerId!,
            canonical_name: createNew ? customer.name : undefined
          })
        )
      )

      const failures = results.filter(r => r.status === 'rejected')
      if (failures.length > 0) {
        throw new Error(`Failed to map ${failures.length} customers`)
      }

      setMappedCount(customers.length)
      setSuccess(true)

      // Automatically trigger success callback after a short delay
      setTimeout(() => {
        onSuccess?.()
      }, 1500)

    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to map customer'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

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
    suggestedMatches,
    filteredCustomers: candidates,
    handleSubmit,
    currentCustomer,
    totalCount,
    isBulk,
    mappedCount,
    customers,
    currentIndex,
    isSearching: isLoadingCandidates,
    hasMoreCandidates,
    isLoadingMoreCandidates,
    loadMoreCandidates: () => fetchCandidates(searchPage + 1, debouncedSearch, true),
  }
}

export const useCustomerIntegrationDetails = (customerId: string | undefined) => {
  const [integrations, setIntegrations] = useState<IntegrationCustomer[]>([])
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

        const numericId = parseInt(customerId, 10)
        if (isNaN(numericId)) throw new Error('Invalid customer ID')

        // Use the type-safe API service
        const response = await customerApi.getCustomerIntegrations(numericId)

        // Response data is already unwrapped by our apiRequest helper
        const integrationData = response.data || []
        setIntegrations(integrationData)

        if (integrationData.length > 0) {
          setCustomerName(integrationData[0].name)

          // Fetch stats for each integration
          // Using for...of for sequential or Promise.all for parallel
          // Parallel is better here
          await Promise.all(
            integrationData.map(async (integration) => {
              const integrationCustomerId = integration.integration_customer_id
              setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }))

              try {
                const statsResponse = await customerApi.getCustomerIntegrationStats(numericId, integrationCustomerId)
                const data = statsResponse.data
                setStats(prev => ({ ...prev, [integrationCustomerId]: data }))
              } catch (err) {
                logger.error(`Failed to fetch stats for integration ${integrationCustomerId}`, err)
              } finally {
                setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }))
              }
            })
          )
        }
      } catch (err) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to load integration details'
        setError(errorMessage)
        logger.error('fetchIntegrationDetails error:', err)
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


type CustomerAction = 'edit' | 'integrations' | 'merge' | 'split' | 'applyTemplate' | 'delivery'

const useBaseCustomerList = (sotFilter: boolean | null) => {
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
  const openModal = useCustomersStore((state) => state.openModal)
  const closeModal = useCustomersStore((state) => state.closeModal)
  const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer)
  const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer)
  const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers)
  const getUnmappedCustomers = useCustomersStore((state) => state.getUnmappedCustomers)

  const { user } = useAuth()
  const canMap = hasPermission(user, 'customers', 'map')

  useEffect(() => {
    const fetchArgs: [number, boolean, boolean | null, boolean] = sotFilter === null
      ? [1, false, null, !canMap]
      : [1, false, sotFilter, !canMap]

    fetchCustomers(...fetchArgs).catch((err) => {
      // Don't toast 403s if it's just the unmapped check failing
      if (err.response?.status === 403 && !canMap) return;

      toast.error(
        "Failed to load customers",
        err.response?.data?.message || err.message
      )
    })
  }, [fetchCustomers, toast, sotFilter, canMap])

  const mappedCustomers = getMappedCustomers()
  const unmappedCustomers = getUnmappedCustomers()
  const totalMapped = customers.filter((c) => c.customer_id !== null).length

  const handleStartMapping = useCallback(() => {
    if (unmappedCustomers.length > 0) {
      const firstUnmapped = unmappedCustomers[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: firstUnmapped.integration_customer_id,
        integration_id: 0, // Placeholder
        integration_type: firstUnmapped.integration_type,
        display_name: firstUnmapped.display_name,
        external_id: firstUnmapped.external_id,
        name: firstUnmapped.name,
        active: firstUnmapped.active,
        last_seen_at: firstUnmapped.last_seen_at,
      })
      openModal("map")
    }
  }, [unmappedCustomers, setSelectedUnmappedCustomer, openModal])

  const handleMapSuccess = useCallback(async () => {
    await fetchCustomers(currentPage, true, sotFilter, !canMap)
    const updatedUnmapped = getUnmappedCustomers()

    if (updatedUnmapped.length > 0) {
      const nextUnmapped = updatedUnmapped[0]
      setSelectedUnmappedCustomer({
        integration_customer_id: nextUnmapped.integration_customer_id,
        integration_id: 0, // Placeholder
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
  }, [fetchCustomers, currentPage, sotFilter, getUnmappedCustomers, setSelectedUnmappedCustomer, closeModal, toast, canMap])

  const handleCustomerAction = useCallback((customer: ExistingCustomer, action: CustomerAction) => {
    setSelectedCustomer({
      customer_id: customer.customer_id!,
      name: customer.canonical_name!,
      status: customer.status!,
      integration_count: customer.integrations.length,
    })
    openModal(action)
  }, [setSelectedCustomer, openModal])

  const handleModalSuccess = useCallback((modalType: CustomerAction, message: string, description: string) => {
    fetchCustomers(currentPage, true, sotFilter, !canMap)
    closeModal(modalType)
    toast.success(message, description)
  }, [fetchCustomers, currentPage, sotFilter, closeModal, toast, canMap])

  const handlePageChange = useCallback((page: number) => {
    fetchCustomers(page, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  const handleSetSearchQuery = useCallback((query: string) => {
    useCustomersStore.setState({ searchQuery: query, currentPage: 1 })
    fetchCustomers(1, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  const handleSetStatusFilters = useCallback((statuses: string[]) => {
    useCustomersStore.setState({ statusFilters: statuses, currentPage: 1 })
    fetchCustomers(1, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  const handleSetReportFilters = useCallback((filters: string[]) => {
    useCustomersStore.setState({ reportFilters: filters, currentPage: 1 })
    fetchCustomers(1, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  const handleSetIntegrationFilters = useCallback((filters: string[]) => {
    useCustomersStore.setState({ integrationFilters: filters, currentPage: 1 })
    fetchCustomers(1, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  const handleClearAllFilters = useCallback(() => {
    useCustomersStore.setState({ statusFilters: [], reportFilters: [], integrationFilters: [], searchQuery: '', currentPage: 1 })
    fetchCustomers(1, false, sotFilter, !canMap)
  }, [fetchCustomers, sotFilter, canMap])

  return {
    navigate,
    toast,
    canMap,
    customers,
    mappedCustomers,
    unmappedCustomers,
    unmappedCount,
    totalMapped,
    total,
    isLoading,
    searchQuery,
    statusFilters,
    reportFilters,
    integrationFilters,
    modals,
    selectedUnmappedCustomer,
    selectedCustomer,
    currentPage,
    totalPages,
    openModal,
    closeModal,
    fetchCustomers: handlePageChange,
    setSearchQuery: handleSetSearchQuery,
    setStatusFilters: handleSetStatusFilters,
    setReportFilters: handleSetReportFilters,
    setIntegrationFilters: handleSetIntegrationFilters,
    clearAllFilters: handleClearAllFilters,
    handleStartMapping,
    handleMapSuccess,
    handleCustomerAction,
    handleModalSuccess,
  }
}

export const useCustomerList = () => {
  const base = useBaseCustomerList(null)

  const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
  const setStatusFilters = useCustomersStore((state) => state.setStatusFilters)
  const setReportFilters = useCustomersStore((state) => state.setReportFilters)
  const setIntegrationFilters = useCustomersStore((state) => state.setIntegrationFilters)
  const storeFetchCustomers = useCustomersStore((state) => state.fetchCustomers)

  return {
    ...base,
    setSearchQuery,
    setStatusFilters,
    setReportFilters,
    setIntegrationFilters,
    fetchCustomers: (page?: number, silent?: boolean, sot?: boolean | null, skipUnmapped?: boolean) =>
      storeFetchCustomers(page, silent, sot, skipUnmapped ?? !base.canMap),
  }
}

export const useMasterCustomerList = () => {
  const base = useBaseCustomerList(true)
  const [sotIntegrationName, setSotIntegrationName] = useState<string | null>(null)

  const { user } = useAuth()
  const canViewIntegrations = hasPermission(user, 'integrations', 'view')

  useEffect(() => {
    if (!canViewIntegrations) return

    api.get('/integrations').then((response: unknown) => {
      const typedResponse = response as { configured?: Array<{ is_source_of_truth?: boolean; display_name?: string; integration_type?: string }> }
      const sotIntegration = typedResponse.configured?.find((int) => int.is_source_of_truth === true)
      if (sotIntegration) {
        setSotIntegrationName(sotIntegration.display_name || sotIntegration.integration_type || null)
      }
    }).catch((err: unknown) => {
      logger.error('Failed to fetch SOT integration:', err)
    })
  }, [canViewIntegrations])

  return {
    ...base,
    sotIntegrationName,
  }
}

export const useSecondaryCustomerList = () => {
  return useBaseCustomerList(false)
}
