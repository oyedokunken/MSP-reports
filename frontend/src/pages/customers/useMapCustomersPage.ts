import { useState, useEffect, useMemo, useCallback } from "react";
import { useCustomersStore } from "@/stores/useCustomersStore";
import type { IntegrationCustomer } from "./types";

type SortField = 'name' | 'integration_type' | 'external_id' | 'last_seen_at';
type SortDirection = 'asc' | 'desc';

export { type SortField, type SortDirection };

export const useMapCustomersPage = () => {
    // Store state
    const unmappedCustomers = useCustomersStore((state) => state.unmappedCustomers);
    const unmappedCount = useCustomersStore((state) => state.unmappedCount);
    const unmappedCurrentPage = useCustomersStore((state) => state.unmappedCurrentPage);
    const unmappedTotalPages = useCustomersStore((state) => state.unmappedTotalPages);
    const fetchUnmappedCustomers = useCustomersStore((state) => state.fetchUnmappedCustomers);
    const modals = useCustomersStore((state) => state.modals);
    const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer);
    const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer);
    const openModal = useCustomersStore((state) => state.openModal);
    const closeModal = useCustomersStore((state) => state.closeModal);
    const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers);
    const unmappedSearchQuery = useCustomersStore((state) => state.unmappedSearchQuery);
    const setUnmappedSearchQuery = useCustomersStore((state) => state.setUnmappedSearchQuery);

    // Local state
    const [searchQuery, setSearchQuery] = useState(unmappedSearchQuery);
    const [isLoading, setIsLoading] = useState(true);
    const [sortField, setSortField] = useState<SortField>('name');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [view, setView] = useState<'grid' | 'table'>('grid');
    const [integrationFilters, setIntegrationFilters] = useState<string[]>([]);

    // Initial load
    useEffect(() => {
        const loadCustomers = async () => {
            setIsLoading(true);
            await fetchUnmappedCustomers(unmappedCurrentPage, unmappedSearchQuery);
            setIsLoading(false);
        };
        loadCustomers();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== unmappedSearchQuery) {
                setUnmappedSearchQuery(searchQuery);
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [searchQuery, unmappedSearchQuery, setUnmappedSearchQuery]);

    // Derived data
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

    // Selection state
    const isAllSelected = filteredAndSortedCustomers.length > 0 && selectedIds.size === filteredAndSortedCustomers.length;
    const isPartiallySelected = selectedIds.size > 0 && selectedIds.size < filteredAndSortedCustomers.length;

    // Handlers
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
        await fetchUnmappedCustomers(unmappedCurrentPage);
    }, [fetchUnmappedCustomers, unmappedCurrentPage]);

    const handlePageChange = useCallback(async (page: number) => {
        setIsLoading(true);
        setSelectedIds(new Set());
        await fetchUnmappedCustomers(page, unmappedSearchQuery);
        setIsLoading(false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [fetchUnmappedCustomers, unmappedSearchQuery]);

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
        // State
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
        // Store state
        unmappedCount,
        unmappedCurrentPage,
        unmappedTotalPages,
        unmappedSearchQuery,
        modals,
        selectedUnmappedCustomer,
        // Setters
        setSearchQuery,
        setView,
        // Store actions
        openModal,
        closeModal,
        getMappedCustomers,
        // Handlers
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
