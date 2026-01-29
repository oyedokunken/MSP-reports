import { useCallback, useMemo, useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { logger } from '@/lib/logger'
import type { CanonicalCustomer, MappingRow, IntegrationCustomer, ExistingCustomer, SuggestedMatch } from './types'
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
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
  const [createNew, setCreateNew] = useState(false)
  const [newCustomerName, setNewCustomerName] = useState('')
  const [suggestedMatches, setSuggestedMatches] = useState<SuggestedMatch[]>([])
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState(false)
  const [mappedCount, setMappedCount] = useState(0)

  const [candidates, setCandidates] = useState<ExistingCustomer[]>([])
  const [searchPage, setSearchPage] = useState(1)
  const [hasMoreCandidates, setHasMoreCandidates] = useState(true)
  const [isLoadingCandidates, setIsLoadingCandidates] = useState(false)
  const [isLoadingMoreCandidates, setIsLoadingMoreCandidates] = useState(false)

  const [currentIndex, setCurrentIndex] = useState(0)

  const customers = integrationCustomers || []
  const currentCustomer = customers[currentIndex] || null
  const totalCount = customers.length
  // Flag indicating if we're processing multiple integration customers sequentially
  const isBulk = totalCount > 1

  useEffect(() => {
    if (open) {
      setMappedCount(0)
      setCurrentIndex(0)
      setSuccess(false)
      setError('')
    }
  }, [open])

  useEffect(() => {
    const fetchSuggestedMatches = async () => {
      if (!currentCustomer) return

      try {
        setIsLoadingSuggestions(true)
        const response = await api.get(`/customers/unmapped`)
        const rawData = (response as { data?: unknown }).data || response
        const data: unknown[] = Array.isArray(rawData) ? rawData : []

        const foundCustomer = data.find(
          (c: unknown) => {
            if (typeof c === 'object' && c !== null && 'integration_customer_id' in c) {
              return (c as { integration_customer_id: number }).integration_customer_id === currentCustomer.integration_customer_id
            }
            return false
          }
        ) as { suggested_matches?: SuggestedMatch[] } | undefined

        if (foundCustomer && foundCustomer.suggested_matches) {
          setSuggestedMatches(foundCustomer.suggested_matches)
        } else {
          setSuggestedMatches([])
        }
      } catch (err) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to load suggested matches';
        setError(errorMessage);
        logger.error('Suggested matches fetch error:', err);
      } finally {
        setIsLoadingSuggestions(false)
      }
    }

    if (open && customers.length > 0) {
      setSearchTerm('')
      setSelectedCustomerId(null)
      setError('')
      setCreateNew(false)
      setNewCustomerName(currentCustomer?.name || '')
      setSuggestedMatches([])

      setCandidates([])
      setSearchPage(1)
      setHasMoreCandidates(true)
      fetchCandidates(1, '')

      if (!isBulk) {
        fetchSuggestedMatches()
      }
    }
  }, [customers.length, open, isBulk, currentCustomer])

  const fetchCandidates = async (page: number, search: string, append: boolean = false) => {
    if (append) {
      setIsLoadingMoreCandidates(true)
    } else {
      setIsLoadingCandidates(true)
    }

    try {
      const params = new URLSearchParams({
        page: page.toString(),
        per_page: '20'
      })

      if (search.trim()) {
        params.append('search', search)
      }

      const response = await api.get(`/customers?${params.toString()}`)
      const rawData = response as { data?: ExistingCustomer[]; pagination?: { current_page?: number; last_page?: number } }
      const data = (rawData.data || rawData) as ExistingCustomer[]

      const pagination = rawData.pagination || { current_page: 1, last_page: 1 }

      if (append) {
        setCandidates(prev => [...prev, ...data])
      } else {
        setCandidates(data)
      }

      const currentPageNum = pagination.current_page || page
      const lastPage = pagination.last_page || 1

      setHasMoreCandidates(currentPageNum < lastPage)
      setSearchPage(currentPageNum)
    } catch (err) {
      logger.error("Failed to fetch candidates", err)
      if (!append) setCandidates([])
      setHasMoreCandidates(false)
    } finally {
      setIsLoadingCandidates(false)
      setIsLoadingMoreCandidates(false)
    }
  }

  useEffect(() => {
    if (!open) return

    const timer = setTimeout(() => {
      fetchCandidates(1, searchTerm, false)
    }, 300)

    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchTerm])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!createNew) {
      // Validate that a target customer is selected when mapping to an existing record
      if (!selectedCustomerId || !currentCustomer) {
        setError('Please select a customer to map to')
        return
      }
    }

    setError('')
    setIsSubmitting(true)

    try {
      if (createNew) {
        const promises = customers.map(customer =>
          api.post('/customers/map', {
            integration_customer_id: customer.integration_customer_id,
            mapping_type: 'manual',
            create_new: true,
            canonical_name: customer.name
          })
        )

        await Promise.all(promises)
        setMappedCount(customers.length)

        setSuccess(true)
        setTimeout(() => {
          setSuccess(false)
          onSuccess?.()
        }, 2000)
        return
      }

      const promises = customers.map(customer =>
        api.post('/customers/map', {
          integration_customer_id: customer.integration_customer_id,
          mapping_type: 'manual',
          customer_id: selectedCustomerId
        })
      )

      await Promise.all(promises)
      setMappedCount(customers.length)

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

  const filteredCustomers = candidates

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
    currentCustomer,
    totalCount,
    isBulk,
    mappedCount,
    customers,
    currentIndex,
    isSearching: isLoadingCandidates && searchPage === 1,
    candidates,
    hasMoreCandidates,
    isLoadingMoreCandidates,
    loadMoreCandidates: () => fetchCandidates(searchPage + 1, searchTerm, true),
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
              void 0;
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
  }, [fetchCustomers, currentPage, sotFilter, getUnmappedCustomers, setSelectedUnmappedCustomer, closeModal, toast])

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
  }, [fetchCustomers, currentPage, sotFilter, closeModal, toast])

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
