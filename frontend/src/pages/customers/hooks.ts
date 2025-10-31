import { useCallback, useMemo, useState, useEffect } from 'react'
import type { CanonicalCustomer, MappingRow, IntegrationCustomer, ExistingCustomer, SuggestedMatch } from './types'
import { api } from '@/lib/api'

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
      console.error('Failed to fetch suggested matches:', err)
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


