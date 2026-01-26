import { cn } from "@/lib/utils";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { MapCustomerModal, Pagination } from "./components";
import { MapPin, Search, ChevronUp, ChevronDown, ArrowUpDown, X, LayoutGrid, List, Building2, CheckCircle2, Filter } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuCheckboxItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useMapCustomersPage, type SortField } from "./useMapCustomersPage";

const SortIcon = ({ field, sortField, sortDirection }: { field: SortField; sortField: SortField; sortDirection: 'asc' | 'desc' }) => {
    if (sortField !== field) return <ArrowUpDown className="h-4 w-4 ml-1 opacity-50" />;
    return sortDirection === 'asc'
        ? <ChevronUp className="h-4 w-4 ml-1 text-primary" />
        : <ChevronDown className="h-4 w-4 ml-1 text-primary" />;
};

export default function MapCustomersPage() {
    const {
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
        getMappedCustomers,
        handleSort,
        handleMapClick,
        handleMapSuccess,
        handlePageChange,
        handleSelectAll,
        handleSelectOne,
        handleBulkMapClick,
        clearSelection,
        toggleIntegrationFilter,
    } = useMapCustomersPage();

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
                        {/* Controls: Filters, View Toggle, Search */}
                        <div className="flex flex-col md:flex-row gap-3 mb-6">
                            {/* Filters Dropdown */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" className="h-10">
                                        <Filter className="h-4 w-4 mr-2" />
                                        Filters
                                        {integrationFilters.length > 0 && (
                                            <Badge variant="secondary" className="ml-2 px-1.5 py-0 h-5 min-w-5 justify-center bg-primary text-primary-foreground">
                                                {integrationFilters.length}
                                            </Badge>
                                        )}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="w-56">
                                    <DropdownMenuLabel>Integration Type</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {integrationTypes.length > 0 ? (
                                        integrationTypes.map((type) => (
                                            <DropdownMenuCheckboxItem
                                                key={type}
                                                checked={integrationFilters.includes(type)}
                                                onCheckedChange={() => toggleIntegrationFilter(type)}
                                                className="capitalize"
                                            >
                                                {type}
                                            </DropdownMenuCheckboxItem>
                                        ))
                                    ) : (
                                        <div className="px-2 py-1.5 text-xs text-muted-foreground italic">
                                            No integrations found
                                        </div>
                                    )}
                                    {integrationFilters.length > 0 && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-center h-8 text-xs"
                                                onClick={() => toggleIntegrationFilter('')}
                                            >
                                                Clear Filters
                                            </Button>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>

                            {/* View Toggle */}
                            <Tabs value={view} onValueChange={(v) => setView(v as 'grid' | 'table')} className="w-auto">
                                <TabsList className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 h-10">
                                    <TabsTrigger value="grid" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md">
                                        <LayoutGrid className="h-4 w-4" />
                                        <span className="ml-2 hidden sm:inline">Grid</span>
                                    </TabsTrigger>
                                    <TabsTrigger value="table" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md">
                                        <List className="h-4 w-4" />
                                        <span className="ml-2 hidden sm:inline">Table</span>
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>

                            {/* Search Bar */}
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Search by customer name, integration, or ID..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-9 h-10 bg-white dark:bg-slate-800 focus-visible:ring-primary"
                                />
                            </div>
                        </div>
                        {unmappedSearchQuery && (
                            <p className="text-sm text-muted-foreground mt-[-1rem] mb-4">
                                Found {unmappedCount} total unmapped customers matching your search
                            </p>
                        )}

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
                                        onClick={clearSelection}
                                    >
                                        <X className="h-4 w-4 mr-1" />
                                        Clear
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={handleBulkMapClick}
                                    >
                                        <MapPin className="h-4 w-4 mr-1" />
                                        Map Selected
                                    </Button>
                                </div>
                            </div>
                        )}

                        <Tabs value={view} className="w-full">
                            <TabsContent value="grid" className="mt-0">
                                {isLoading ? (
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                                        {[1, 2, 3, 4, 5, 6].map((i) => (
                                            <AnimatedCard key={i} className="p-6" hoverLift={false}>
                                                <div className="flex items-start justify-between gap-3 mb-2">
                                                    <div className="flex items-center gap-3 flex-1">
                                                        <Skeleton className="h-10 w-10 rounded-lg" />
                                                        <div className="flex-1 space-y-2">
                                                            <Skeleton className="h-5 w-3/4" />
                                                            <Skeleton className="h-4 w-1/2" />
                                                        </div>
                                                    </div>
                                                    <Skeleton className="h-6 w-16 rounded-full" />
                                                </div>
                                                <Skeleton className="h-3 w-32 mt-1" />
                                                <Skeleton className="h-10 w-full rounded-lg mt-4" />
                                            </AnimatedCard>
                                        ))}
                                    </div>
                                ) : filteredAndSortedCustomers.length === 0 ? (
                                    <div className="py-20 text-center bg-slate-50 dark:bg-slate-900/30 rounded-xl border-2 border-dashed">
                                        <Search className="h-10 w-10 text-muted-foreground mx-auto mb-3 opacity-20" />
                                        <p className="text-muted-foreground">
                                            {searchQuery || integrationFilters.length > 0 ? `No customers found matching your criteria` : 'No unmapped customers found'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch max-w-full overflow-hidden">
                                        {filteredAndSortedCustomers.map((customer) => {
                                            const isSelected = selectedIds.has(customer.integration_customer_id);
                                            return (
                                                <AnimatedCard
                                                    key={customer.integration_customer_id}
                                                    onClick={() => handleSelectOne(customer.integration_customer_id)}
                                                    hoverLift={false}
                                                    className={cn(
                                                        "group p-6 h-full flex flex-col transition-all duration-300 relative overflow-hidden cursor-pointer",
                                                        isSelected
                                                            ? "border-primary bg-primary/[0.03] shadow-md shadow-primary/5"
                                                            : "hover:border-slate-300 dark:hover:border-slate-700"
                                                    )}
                                                >
                                                    {/* Selection Indicator Overlay */}
                                                    {isSelected && (
                                                        <div className="absolute top-2 right-2 z-10">
                                                            <CheckCircle2 className="h-5 w-5 text-primary fill-white" />
                                                        </div>
                                                    )}

                                                    {/* Header */}
                                                    <div className="flex items-start justify-between gap-3 mb-1">
                                                        <div className="flex items-center gap-3 min-w-0 flex-1">
                                                            <div className={cn(
                                                                "rounded-lg p-2 flex-shrink-0 transition-colors",
                                                                isSelected ? "bg-primary/20" : "bg-primary/10"
                                                            )}>
                                                                <Building2 className="h-6 w-6 text-primary" />
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <h3 className="font-semibold text-foreground break-words leading-tight" title={customer.name}>
                                                                    {customer.name}
                                                                </h3>
                                                                <p className="text-xs text-muted-foreground mt-1 truncate">
                                                                    {customer.integration_type}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <Badge
                                                            variant={customer.active ? 'default' : 'secondary'}
                                                            className={cn(
                                                                "flex-shrink-0",
                                                                !customer.active && "bg-purple-600 text-white hover:bg-purple-700"
                                                            )}
                                                        >
                                                            {customer.active ? 'Active' : 'Inactive'}
                                                        </Badge>
                                                    </div>

                                                    {/* Info Section */}
                                                    <div>
                                                        <p className="text-[11px] text-muted-foreground/60 font-mono truncate">
                                                            External ID: {customer.external_id}
                                                        </p>
                                                    </div>

                                                    {/* Footer Action */}
                                                    <div className="mt-auto pt-4">
                                                        <Button
                                                            className="w-full gap-2 font-semibold shadow-sm bg-primary text-primary-foreground hover:bg-primary/90 transition-all"
                                                            onClick={(e) => {
                                                                e.stopPropagation(); // Prevent toggling selection when clicking Map button
                                                                handleMapClick(customer);
                                                            }}
                                                        >
                                                            <MapPin className="h-4 w-4" />
                                                            Map Client
                                                        </Button>
                                                    </div>
                                                </AnimatedCard>
                                            );
                                        })}
                                    </div>
                                )}
                            </TabsContent>

                            <TabsContent value="table" className="mt-0">
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
                                                            Customer Name <SortIcon field="name" sortField={sortField} sortDirection={sortDirection} />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('integration_type')}
                                                    >
                                                        <div className="flex items-center">
                                                            Integration <SortIcon field="integration_type" sortField={sortField} sortDirection={sortDirection} />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('external_id')}
                                                    >
                                                        <div className="flex items-center">
                                                            External ID <SortIcon field="external_id" sortField={sortField} sortDirection={sortDirection} />
                                                        </div>
                                                    </th>
                                                    <th
                                                        className="text-left p-4 font-semibold text-sm cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                        onClick={() => handleSort('last_seen_at')}
                                                    >
                                                        <div className="flex items-center">
                                                            Last Seen <SortIcon field="last_seen_at" sortField={sortField} sortDirection={sortDirection} />
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
                                                ) : filteredAndSortedCustomers.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={7} className="p-12 text-center text-muted-foreground">
                                                            {searchQuery || integrationFilters.length > 0 ? `No customers found matching your criteria` : 'No customers on this page'}
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    filteredAndSortedCustomers.map((customer) => (
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
                            </TabsContent>
                        </Tabs>

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
                        ? filteredAndSortedCustomers.filter(c => selectedIds.has(c.integration_customer_id))
                        : selectedUnmappedCustomer ? [selectedUnmappedCustomer] : []
                    }
                    existingCustomers={getMappedCustomers()}
                    onSuccess={handleMapSuccess}
                />
            </div>
        </FloatingLayout>
    );
}
