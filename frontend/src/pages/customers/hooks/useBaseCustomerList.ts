import { useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useCustomersStore } from '@/stores/useCustomersStore'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
import type { ExistingCustomer } from '../types'

export type CustomerAction = 'edit' | 'integrations' | 'merge' | 'split' | 'applyTemplate' | 'delivery'

export const useBaseCustomerList = (sotFilter: boolean | null) => {
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
