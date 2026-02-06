import { useCallback, useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useCustomersStore } from '@/stores/useCustomersStore'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
import type { ExistingCustomer } from '../types'
import { useCustomersQuery, useUnmappedCustomersQuery } from './useCustomersQueries'

export type CustomerAction = 'edit' | 'integrations' | 'merge' | 'split' | 'applyTemplate' | 'delivery'

export const useBaseCustomerList = (sotFilter: boolean | null) => {
    const navigate = useNavigate()
    const toast = useToast()

    // Store state for filters and UI
    const searchQuery = useCustomersStore((state) => state.searchQuery)
    const statusFilters = useCustomersStore((state) => state.statusFilters)
    const reportFilters = useCustomersStore((state) => state.reportFilters)
    const integrationFilters = useCustomersStore((state) => state.integrationFilters)
    const modals = useCustomersStore((state) => state.modals)
    const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer)
    const selectedCustomer = useCustomersStore((state) => state.selectedCustomer)
    const currentPage = useCustomersStore((state) => state.currentPage)

    const openModal = useCustomersStore((state) => state.openModal)
    const closeModal = useCustomersStore((state) => state.closeModal)
    const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer)
    const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer)
    const setCurrentPage = useCustomersStore((state) => state.setCurrentPage)
    const setSearchQuery = useCustomersStore((state) => state.setSearchQuery)
    const setStatusFilters = useCustomersStore((state) => state.setStatusFilters)
    const setReportFilters = useCustomersStore((state) => state.setReportFilters)
    const setIntegrationFilters = useCustomersStore((state) => state.setIntegrationFilters)
    const clearAllFilters = useCustomersStore((state) => state.clearAllFilters)

    const { user } = useAuth()
    const canMap = hasPermission(user, 'customers', 'map')

    // React Query for Customers
    const {
        data: customersResponse,
        isLoading: isLoadingCustomers,
        error: customersError,
        refetch: refetchCustomers,
    } = useCustomersQuery({
        page: currentPage,
        search: searchQuery || undefined,
        statuses: statusFilters.length > 0 ? statusFilters : undefined,
        report_filters: reportFilters.length > 0 ? reportFilters : undefined,
        integration_types: integrationFilters.length > 0 ? integrationFilters : undefined,
        source_of_truth_only: sotFilter,
    })

    // React Query for Unmapped
    const {
        data: unmappedResponse,
        isLoading: isLoadingUnmapped,
        refetch: refetchUnmapped,
    } = useUnmappedCustomersQuery(1) // Usually we only need the first page for the "Start Mapping" feature

    useEffect(() => {
        if (customersError) {
            toast.error("Failed to load customers", customersError instanceof Error ? customersError.message : "An unknown error occurred")
        }
    }, [customersError, toast])

    const customers = useMemo(() => customersResponse?.data || [], [customersResponse])
    const mappedCustomers = useMemo(() => customers.filter((c) => c.customer_id !== null), [customers])
    const unmappedCustomers = useMemo(() => unmappedResponse?.data || [], [unmappedResponse])
    const unmappedCount = useMemo(() => unmappedResponse?.summary?.total_unmapped || unmappedCustomers.length, [unmappedResponse, unmappedCustomers.length])

    const total = customersResponse?.pagination.total || 0
    const totalPages = customersResponse?.pagination.last_page || 1
    const totalMapped = mappedCustomers.length
    const isLoading = isLoadingCustomers || isLoadingUnmapped

    const handleStartMapping = useCallback(() => {
        if (unmappedCustomers.length > 0) {
            const firstUnmapped = unmappedCustomers[0]
            setSelectedUnmappedCustomer({
                integration_customer_id: firstUnmapped.integration_customer_id,
                integration_id: 0,
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
        await Promise.all([refetchCustomers(), refetchUnmapped()])

        // Check if we still have unmapped customers (using fresh data from refetch)
        // Actually, it's better to wait for the refetch to complete or use the new data
        // For now, simpler:
        const updatedUnmapped = unmappedResponse?.data || []

        if (updatedUnmapped.length > 0) {
            const nextUnmapped = updatedUnmapped[0]
            setSelectedUnmappedCustomer({
                integration_customer_id: nextUnmapped.integration_customer_id,
                integration_id: 0,
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
    }, [refetchCustomers, refetchUnmapped, unmappedResponse, setSelectedUnmappedCustomer, closeModal, toast])

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
        refetchCustomers()
        closeModal(modalType)
        toast.success(message, description)
    }, [refetchCustomers, closeModal, toast])

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
        fetchCustomers: setCurrentPage,
        setSearchQuery,
        setStatusFilters,
        setReportFilters,
        setIntegrationFilters,
        clearAllFilters,
        handleStartMapping,
        handleMapSuccess,
        handleCustomerAction,
        handleModalSuccess,
    }
}
