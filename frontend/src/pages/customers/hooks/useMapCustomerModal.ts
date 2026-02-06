import { useState, useEffect, useMemo } from 'react'
import { logger } from '@/lib/logger'
import useDebounce from '@/hooks/use-debounce'
import { useCustomerInfiniteQuery, useMapCustomerMutation } from './useCustomersQueries'
import type { IntegrationCustomer, ExistingCustomer, SuggestedMatch } from '../types'

export const useMapCustomerModal = (
    open: boolean,
    integrationCustomers: IntegrationCustomer[],
    _existingCustomers: ExistingCustomer[],
    onSuccess?: () => void
) => {
    const [searchTerm, setSearchTerm] = useState('')
    const debouncedSearch = useDebounce(searchTerm, 300)

    const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
    const [createNew, setCreateNew] = useState(false)
    const [currentIndex, setCurrentIndex] = useState(0)
    const [error, setError] = useState('')
    const [success, setSuccess] = useState(false)
    const [mappedCount, setMappedCount] = useState(0)

    const mapMutation = useMapCustomerMutation()

    // Candidates query using TanStack Query
    const {
        data: infiniteData,
        fetchNextPage,
        hasNextPage,
        isFetchingNextPage,
        isLoading: isLoadingCandidates,
    } = useCustomerInfiniteQuery({
        search: debouncedSearch,
        statuses: ['active']
    })

    const candidates = useMemo(() => {
        if (!infiniteData) return []
        return infiniteData.pages.flatMap(page =>
            page.data.map(c => ({
                customer_id: c.customer_id,
                canonical_name: c.canonical_name || '',
                status: c.status,
                integrations: c.integrations || []
            }))
        )
    }, [infiniteData])

    const customers = useMemo(() => integrationCustomers || [], [integrationCustomers])
    const currentCustomer = customers[currentIndex] || null
    const totalCount = customers.length
    const isBulk = totalCount > 1

    // Suggested matches logic
    const suggestedMatches = useMemo((): SuggestedMatch[] => {
        return currentCustomer?.suggested_matches || []
    }, [currentCustomer])

    // Reset state when modal opens
    useEffect(() => {
        if (open) {
            setSearchTerm('')
            setSelectedCustomerId(null)
            setCreateNew(false)
            setCurrentIndex(0)
            setError('')
            setSuccess(false)
            setMappedCount(0)
        }
    }, [open])

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()

        if (!createNew && !selectedCustomerId) {
            setError('Please select a customer to map to')
            return
        }

        setError('')

        try {
            // Bulk mapping logic using the mutation
            const results = await Promise.allSettled(
                customers.map(customer =>
                    mapMutation.mutateAsync({
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

            setTimeout(() => {
                onSuccess?.()
            }, 1500)

        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to map customer'
            setError(errorMessage)
            logger.error("Mapping failed", err)
        }
    }

    return {
        isSubmitting: mapMutation.isPending,
        error,
        success: success || mapMutation.isSuccess,
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
        hasMoreCandidates: hasNextPage,
        isLoadingMoreCandidates: isFetchingNextPage,
        loadMoreCandidates: fetchNextPage,
    }
}

