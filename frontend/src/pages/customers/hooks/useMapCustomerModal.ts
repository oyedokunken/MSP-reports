import { useState, useEffect, useMemo, useCallback } from 'react'
import { logger } from '@/lib/logger'
import useDebounce from '@/hooks/use-debounce'
import { customerApi } from '@/services/api/customers'
import type { IntegrationCustomer, ExistingCustomer, SuggestedMatch } from '../types'

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
