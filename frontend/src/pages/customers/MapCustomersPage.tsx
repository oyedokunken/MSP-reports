import { useState, useEffect, useMemo } from "react";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { MapCustomerModal, Pagination } from "./components";
import { useCustomersStore } from "@/stores/useCustomersStore";
import { MapPin, Search, ChevronUp, ChevronDown, ArrowUpDown, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import type { IntegrationCustomer } from "./types";

type SortField = 'name' | 'integration_type' | 'external_id' | 'last_seen_at';
type SortDirection = 'asc' | 'desc';

export default function MapCustomersPage() {
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

    const [searchQuery, setSearchQuery] = useState(unmappedSearchQuery);
    const [isLoading, setIsLoading] = useState(true);
    const [sortField, setSortField] = useState<SortField>('name');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

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

    // Update local search when store changes (e.g. on clear)
    useEffect(() => {
        setSearchQuery(unmappedSearchQuery);
    }, [unmappedSearchQuery]);

    const sortedCustomers = useMemo(() => {
        // Backend now handles filtering, so we only need to sort the result
        return [...unmappedCustomers].sort((a, b) => {
            const aValue = a[sortField];
            const bValue = b[sortField];

            if (aValue === bValue) return 0;
            if (aValue === null || aValue === undefined) return 1;
            if (bValue === null || bValue === undefined) return -1;

            const comparison = aValue < bValue ? -1 : 1;
            return sortDirection === 'asc' ? comparison : -comparison;
        });
    }, [unmappedCustomers, sortField, sortDirection]);

    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const SortIcon = ({ field }: { field: SortField }) => {
        if (sortField !== field) return <ArrowUpDown className="h-4 w-4 ml-1 opacity-50" />;
        return sortDirection === 'asc'
            ? <ChevronUp className="h-4 w-4 ml-1 text-primary" />
            : <ChevronDown className="h-4 w-4 ml-1 text-primary" />;
    };

    const handleMapClick = (customer: IntegrationCustomer) => {
        setSelectedUnmappedCustomer(customer);
        openModal("map");
    };

    const handleMapSuccess = async () => {
        // Refresh the current page and clear selection
        setSelectedIds(new Set());
        await fetchUnmappedCustomers(unmappedCurrentPage);
    };

    const handlePageChange = async (page: number) => {
        setIsLoading(true);
        setSelectedIds(new Set()); // Clear selection on page change
        await fetchUnmappedCustomers(page, unmappedSearchQuery);
        setIsLoading(false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Bulk selection handlers
    const handleSelectAll = () => {
        if (selectedIds.size === sortedCustomers.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(sortedCustomers.map(c => c.integration_customer_id)));
        }
    };

    const handleSelectOne = (id: number) => {
        const newSet = new Set(selectedIds);
        if (newSet.has(id)) {
            newSet.delete(id);
        } else {
            newSet.add(id);
        }
        setSelectedIds(newSet);
    };

    const isAllSelected = sortedCustomers.length > 0 && selectedIds.size === sortedCustomers.length;
    const isPartiallySelected = selectedIds.size > 0 && selectedIds.size < sortedCustomers.length;

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                {/* Header */}
                <div>
                    <div className="flex items-center gap-4">
                        {/* <div className="flex items-center justify-center w-12 h-12 rounded-xl bg-primary/10 flex-shrink-0">
                            <MapPin className="h-6 w-6 text-primary" />
                        </div> */}
                        <div className="flex-1">
                            <h1 className="text-3xl font-bold text-foreground">Map Customers</h1>
                            <p className="mt-1 text-muted-foreground">
                                Link integration customers to existing clients or create new ones
                            </p>
                        </div>
                        {unmappedCount > 0 && (
                            <Badge variant="secondary" className="text-sm px-3 py-1 font-semibold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200">
                                {unmappedCount} total unmapped
                            </Badge>
                        )}
                    </div>
                </div>

                {unmappedCount > 0 || unmappedSearchQuery ? (
                    <>
                        <Card className="border-none shadow-md bg-white dark:bg-slate-900">
                            <CardContent className="p-6">
                                {/* Search Bar */}
                                <div className="mb-6">
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            type="text"
                                            placeholder="Search by customer name, integration, or ID..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="pl-9 bg-white dark:bg-slate-800"
                                        />
                                    </div>
                                    {unmappedSearchQuery && (
                                        <p className="text-sm text-muted-foreground mt-2">
                                            Found {unmappedCount} total unmapped customers matching your search
                                        </p>
                                    )}
                                </div>

                                {/* Bulk Action Bar */}
                                {selectedIds.size > 0 && (
                                    <div className="mb-4 p-3 bg-primary/10 rounded-lg flex items-center justify-between">
                                        <span className="text-sm font-medium">
                                            {selectedIds.size} customer{selectedIds.size > 1 ? 's' : ''} selected
                                        </span>
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setSelectedIds(new Set())}
                                            >
                                                <X className="h-4 w-4 mr-1" />
                                                Clear
                                            </Button>
                                            <Button
                                                size="sm"
                                                onClick={() => {
                                                    // Get ALL selected customers and open map modal
                                                    const selectedCustomers = sortedCustomers.filter(c => selectedIds.has(c.integration_customer_id));
                                                    if (selectedCustomers.length > 0) {
                                                        // Set first one for backward compatibility, but modal now uses array
                                                        setSelectedUnmappedCustomer(selectedCustomers[0]);
                                                        openModal("map");
                                                    }
                                                }}
                                            >
                                                <MapPin className="h-4 w-4 mr-1" />
                                                Map Selected
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {/* Table */}
                                <div className="border rounded-xl overflow-hidden bg-white dark:bg-slate-800">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead className="bg-slate-50 dark:bg-slate-700/50 border-b">
                                                <tr>
                                                    <th className="w-12 p-4">
                                                        <Checkbox
                                                            checked={isAllSelected}
                                                            onCheckedChange={handleSelectAll}
                                                            aria-label="Select all"
                                                            className={isPartiallySelected ? "opacity-50" : ""}
                                                        />
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('name')}
                                                    >
                                                        <div className="flex items-center">
                                                            Customer Name <SortIcon field="name" />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('integration_type')}
                                                    >
                                                        <div className="flex items-center">
                                                            Integration <SortIcon field="integration_type" />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('external_id')}
                                                    >
                                                        <div className="flex items-center">
                                                            External ID <SortIcon field="external_id" />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('last_seen_at')}
                                                    >
                                                        <div className="flex items-center">
                                                            Last Seen <SortIcon field="last_seen_at" />
                                                        </div>
                                                    </th>
                                                    <th className="text-left p-4 font-semibold text-sm">Status</th>
                                                    <th className="text-right p-4 font-semibold text-sm">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                                {isLoading ? (
                                                    <>
                                                        {[1, 2, 3, 4, 5].map((i) => (
                                                            <tr key={i}>
                                                                <td className="w-12 p-4">
                                                                    <Skeleton className="h-4 w-4" />
                                                                </td>
                                                                <td className="p-4">
                                                                    <Skeleton className="h-5 w-48" />
                                                                </td>
                                                                <td className="p-4">
                                                                    <Skeleton className="h-5 w-20" />
                                                                </td>
                                                                <td className="p-4">
                                                                    <Skeleton className="h-5 w-24" />
                                                                </td>
                                                                <td className="p-4">
                                                                    <Skeleton className="h-4 w-28" />
                                                                </td>
                                                                <td className="p-4">
                                                                    <Skeleton className="h-5 w-16" />
                                                                </td>
                                                                <td className="p-4 text-right">
                                                                    <Skeleton className="h-8 w-28 ml-auto" />
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </>
                                                ) : sortedCustomers.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={7} className="p-12 text-center text-muted-foreground">
                                                            {searchQuery ? `No customers found matching "${searchQuery}"` : 'No customers on this page'}
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    sortedCustomers.map((customer) => (
                                                        <tr key={customer.integration_customer_id} className={`hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors ${selectedIds.has(customer.integration_customer_id) ? 'bg-primary/5' : ''}`}>
                                                            <td className="w-12 p-4">
                                                                <Checkbox
                                                                    checked={selectedIds.has(customer.integration_customer_id)}
                                                                    onCheckedChange={() => handleSelectOne(customer.integration_customer_id)}
                                                                    aria-label={`Select ${customer.name}`}
                                                                />
                                                            </td>
                                                            <td className="p-4">
                                                                <div className="font-semibold text-slate-900 dark:text-slate-100">{customer.name}</div>
                                                            </td>
                                                            <td className="p-4">
                                                                <Badge variant="outline" className="text-xs bg-slate-50 dark:bg-slate-900/50">
                                                                    {customer.integration_type}
                                                                </Badge>
                                                            </td>
                                                            <td className="p-4">
                                                                <span className="text-xs text-muted-foreground font-mono bg-slate-100 dark:bg-slate-900 px-2 py-1 rounded">
                                                                    {customer.external_id}
                                                                </span>
                                                            </td>
                                                            <td className="p-4">
                                                                <span className="text-sm text-slate-600 dark:text-slate-400">
                                                                    {customer.last_seen_at
                                                                        ? new Date(customer.last_seen_at).toLocaleDateString()
                                                                        : '—'}
                                                                </span>
                                                            </td>
                                                            <td className="p-4">
                                                                {customer.active ? (
                                                                    <Badge variant="default" className="bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20 shadow-none">
                                                                        Active
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="secondary" className="shadow-none">
                                                                        Inactive
                                                                    </Badge>
                                                                )}
                                                            </td>
                                                            <td className="p-4 text-right">
                                                                <Button
                                                                    size="sm"
                                                                    className="shadow-sm font-medium"
                                                                    onClick={() => handleMapClick(customer)}
                                                                >
                                                                    <MapPin className="h-4 w-4 mr-1.5" />
                                                                    Map Client
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Pagination */}
                        {unmappedTotalPages > 1 && (
                            <div className="mt-4">
                                <Pagination
                                    currentPage={unmappedCurrentPage}
                                    totalPages={unmappedTotalPages}
                                    total={unmappedCount}
                                    onPageChange={handlePageChange}
                                    itemLabel="unmapped customers"
                                />
                            </div>
                        )}
                    </>
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <MapPin className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-lg font-medium">No Unmapped Customers</p>
                            <p className="text-sm text-muted-foreground">
                                All integration customers have been mapped
                            </p>
                        </CardContent>
                    </Card>
                )}

                <MapCustomerModal
                    open={modals.map}
                    onOpenChange={(open) => open ? openModal("map") : closeModal("map")}
                    integrationCustomers={selectedIds.size > 0
                        ? sortedCustomers.filter(c => selectedIds.has(c.integration_customer_id))
                        : selectedUnmappedCustomer ? [selectedUnmappedCustomer] : []
                    }
                    existingCustomers={getMappedCustomers()}
                    onSuccess={handleMapSuccess}
                />
            </div>
        </FloatingLayout>
    );
}
