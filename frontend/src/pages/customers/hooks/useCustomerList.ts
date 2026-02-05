import { useCustomersStore } from '@/stores/useCustomersStore'
import { useBaseCustomerList } from './useBaseCustomerList'

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
