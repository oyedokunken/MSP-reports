import { useState, useEffect, useMemo, useCallback } from "react";
import { useCustomersStore } from "@/stores/useCustomersStore";
import type { IntegrationCustomer } from "./types";
import { useUnmappedCustomersQuery } from "./hooks/useCustomersQueries";

type SortField = 'name' | 'integration_type' | 'external_id' | 'last_seen_at';
type SortDirection = 'asc' | 'desc';

export { type SortField, type SortDirection };

export const useMapCustomersPage = () => {
    // Store state for filters and UI
    const unmappedCurrentPage = useCustomersStore((state) => state.unmappedCurrentPage);
    const unmappedSearchQuery = useCustomersStore((state) => state.unmappedSearchQuery);
    const setUnmappedSearchQuery = useCustomersStore((state) => state.setUnmappedSearchQuery);
    const setUnmappedPage = useCustomersStore((state) => state.setUnmappedPage);

    const modals = useCustomersStore((state) => state.modals);
    const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer);
    const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer);
    const openModal = useCustomersStore((state) => state.openModal);
    const closeModal = useCustomersStore((state) => state.closeModal);

    const [searchQuery, setSearchQuery] = useState(unmappedSearchQuery);
    const [sortField, setSortField] = useState<SortField>('name');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [view, setView] = useState<'grid' | 'table'>('grid');
    const [integrationFilters, setIntegrationFilters] = useState<string[]>([]);

    // React Query for Unmapped - optimized to skip suggestions
    // This significantly speeds up the query as it avoids O(N*M) similarity calculations on the backend
    const {
        data: unmappedResponse,
        isLoading,
        refetch: refetchUnmapped,
    } = useUnmappedCustomersQuery(unmappedCurrentPage, unmappedSearchQuery, true);

    const unmappedCustomers = useMemo(() => unmappedResponse?.data || [], [unmappedResponse]);
    const unmappedCount = unmappedResponse?.summary?.total_unmapped || unmappedCustomers.length;
    const unmappedTotalPages = unmappedResponse?.pagination?.last_page || 1;

    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== unmappedSearchQuery) {
                setUnmappedSearchQuery(searchQuery);
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [searchQuery, unmappedSearchQuery, setUnmappedSearchQuery]);

    const integrationTypes = useMemo(() => {
        return [...new Set(unmappedCustomers.map(c => c.integration_type))];
    }, [unmappedCustomers]);

    const filteredAndSortedCustomers = useMemo(() => {
        let result = [...unmappedCustomers];

        if (integrationFilters.length > 0) {
            result = result.filter(c => integrationFilters.includes(c.integration_type));
        }

        return result.sort((a, b) => {
            const aValue = a[sortField];
            const bValue = b[sortField];

            if (aValue === bValue) return 0;
            if (aValue === null || aValue === undefined) return 1;
            if (bValue === null || bValue === undefined) return -1;

            const comparison = aValue < bValue ? -1 : 1;
            return sortDirection === 'asc' ? comparison : -comparison;
        });
    }, [unmappedCustomers, sortField, sortDirection, integrationFilters]);

    const isAllSelected = filteredAndSortedCustomers.length > 0 && selectedIds.size === filteredAndSortedCustomers.length;
    const isPartiallySelected = selectedIds.size > 0 && selectedIds.size < filteredAndSortedCustomers.length;

    const handleSort = useCallback((field: SortField) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    }, [sortField, sortDirection]);

    const handleMapClick = useCallback((customer: IntegrationCustomer) => {
        setSelectedUnmappedCustomer(customer);
        openModal("map");
    }, [setSelectedUnmappedCustomer, openModal]);

    const handleMapSuccess = useCallback(async () => {
        setSelectedIds(new Set());
        await refetchUnmapped();
    }, [refetchUnmapped]);

    const handlePageChange = useCallback(async (page: number) => {
        setSelectedIds(new Set());
        setUnmappedPage(page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [setUnmappedPage]);

    const handleSelectAll = useCallback(() => {
        if (selectedIds.size === filteredAndSortedCustomers.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(filteredAndSortedCustomers.map(c => c.integration_customer_id)));
        }
    }, [selectedIds, filteredAndSortedCustomers]);

    const handleSelectOne = useCallback((id: number) => {
        setSelectedIds(prev => {
            const newSet = new Set(prev);
            if (newSet.has(id)) {
                newSet.delete(id);
            } else {
                newSet.add(id);
            }
            return newSet;
        });
    }, []);

    const clearSelection = useCallback(() => setSelectedIds(new Set()), []);

    const toggleIntegrationFilter = useCallback((type: string) => {
        setIntegrationFilters(prev =>
            prev.includes(type) ? prev.filter(t => t !== type) : [...prev, type]
        );
    }, []);

    const handleBulkMapClick = useCallback(() => {
        const selectedCustomers = filteredAndSortedCustomers.filter(c => selectedIds.has(c.integration_customer_id));
        if (selectedCustomers.length > 0) {
            setSelectedUnmappedCustomer(selectedCustomers[0]);
            openModal("map");
        }
    }, [filteredAndSortedCustomers, selectedIds, setSelectedUnmappedCustomer, openModal]);

    return {
        isLoading,
        searchQuery,
        sortField,
        sortDirection,
        selectedIds,
        view,
        integrationFilters,
        integrationTypes,
        filteredAndSortedCustomers,
        isAllSelected,
        isPartiallySelected,
        unmappedCount,
        unmappedCurrentPage,
        unmappedTotalPages,
        unmappedSearchQuery,
        modals,
        selectedUnmappedCustomer,
        setSearchQuery,
        setView,
        openModal,
        closeModal,
        handleSort,
        handleMapClick,
        handleMapSuccess,
        handlePageChange,
        handleSelectAll,
        handleSelectOne,
        handleBulkMapClick,
        clearSelection,
        toggleIntegrationFilter,
    };
};
