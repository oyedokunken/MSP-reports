import { useState, useEffect } from "react";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { MapCustomerModal, Pagination } from "./components";
import { useCustomersStore } from "@/stores/useCustomersStore";
import { MapPin, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import type { IntegrationCustomer } from "./types";

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

    const [searchQuery, setSearchQuery] = useState("");
    const [isLoading, setIsLoading] = useState(false);


    useEffect(() => {
        const loadCustomers = async () => {
            setIsLoading(true);
            await fetchUnmappedCustomers(unmappedCurrentPage);
            setIsLoading(false);
        };
        loadCustomers();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // Only load on mount

    const filteredCustomers = unmappedCustomers.filter(customer =>
        customer.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        customer.integration_type.toLowerCase().includes(searchQuery.toLowerCase()) ||
        customer.external_id.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleMapClick = (customer: IntegrationCustomer) => {
        setSelectedUnmappedCustomer(customer);
        openModal("map");
    };

    const handleMapSuccess = async () => {
        // Refresh the current page
        await fetchUnmappedCustomers(unmappedCurrentPage);
    };

    const handlePageChange = async (page: number) => {
        setIsLoading(true);
        await fetchUnmappedCustomers(page);
        setIsLoading(false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                {/* Header */}
                <div>
                    <div className="flex items-center gap-3">
                        {/* <div className="flex items-center justify-center w-12 h-12 rounded-full bg-primary/10">
                            <MapPin className="h-6 w-6 text-primary" />
                        </div> */}
                        <div className="flex-1">
                            <h1 className="text-3xl font-bold text-foreground">Map Customers</h1>
                            <p className="mt-1 text-muted-foreground">
                                Link integration customers to existing clients or create new ones
                            </p>
                        </div>
                        {/* {unmappedCount > 0 && (
                            <Badge variant="secondary" className="text-lg px-4 py-2">
                                {unmappedCount} unmapped
                            </Badge>
                        )} */}
                    </div>
                </div>

                {unmappedCount > 0 ? (
                    <>
                        <Card>
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
                                            className="pl-9"
                                        />
                                    </div>
                                    {searchQuery && (
                                        <p className="text-sm text-muted-foreground mt-2">
                                            Showing {filteredCustomers.length} of {unmappedCustomers.length} customers on this page
                                        </p>
                                    )}
                                </div>

                                {/* Table */}
                                <div className="border rounded-lg overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead className="bg-muted/50 border-b">
                                                <tr>
                                                    <th className="text-left p-3 font-semibold text-sm">Customer Name</th>
                                                    <th className="text-left p-3 font-semibold text-sm">Integration</th>
                                                    <th className="text-left p-3 font-semibold text-sm">External ID</th>
                                                    <th className="text-left p-3 font-semibold text-sm">Last Seen</th>
                                                    <th className="text-left p-3 font-semibold text-sm">Status</th>
                                                    <th className="text-right p-3 font-semibold text-sm">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {isLoading ? (
                                                    <tr>
                                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                                            Loading...
                                                        </td>
                                                    </tr>
                                                ) : filteredCustomers.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                                            {searchQuery ? `No customers found matching "${searchQuery}"` : 'No customers on this page'}
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    filteredCustomers.map((customer) => (
                                                        <tr key={customer.integration_customer_id} className="hover:bg-accent/50 transition-colors">
                                                            <td className="p-3">
                                                                <div className="font-medium">{customer.name}</div>
                                                            </td>
                                                            <td className="p-3">
                                                                <Badge variant="outline" className="text-xs">
                                                                    {customer.integration_type}
                                                                </Badge>
                                                            </td>
                                                            <td className="p-3">
                                                                <span className="text-sm text-muted-foreground font-mono">
                                                                    {customer.external_id}
                                                                </span>
                                                            </td>
                                                            <td className="p-3">
                                                                <span className="text-sm text-muted-foreground">
                                                                    {customer.last_seen_at
                                                                        ? new Date(customer.last_seen_at).toLocaleDateString()
                                                                        : '—'}
                                                                </span>
                                                            </td>
                                                            <td className="p-3">
                                                                {customer.active ? (
                                                                    <Badge variant="default" className="bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20">
                                                                        Active
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="secondary">
                                                                        Inactive
                                                                    </Badge>
                                                                )}
                                                            </td>
                                                            <td className="p-3 text-right">
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() => handleMapClick(customer)}
                                                                >
                                                                    <MapPin className="h-4 w-4 mr-1" />
                                                                    Map
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
                            <Pagination
                                currentPage={unmappedCurrentPage}
                                totalPages={unmappedTotalPages}
                                total={unmappedCount}
                                onPageChange={handlePageChange}
                                itemLabel="unmapped customers"
                            />
                        )}
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center py-16 text-center border-2 border-dashed border-border rounded-lg bg-accent/20">
                        <div className="flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
                            <MapPin className="h-8 w-8 text-green-600 dark:text-green-400" />
                        </div>
                        <h3 className="text-xl font-semibold text-foreground mb-2">All Customers Mapped!</h3>
                        <p className="text-muted-foreground max-w-md">
                            Great job! All integration customers have been mapped to your client records.
                        </p>
                    </div>
                )}

                <MapCustomerModal
                    open={modals.map}
                    onOpenChange={(open) => open ? openModal("map") : closeModal("map")}
                    integrationCustomer={selectedUnmappedCustomer}
                    existingCustomers={getMappedCustomers()}
                    onSuccess={handleMapSuccess}
                />
            </div>
        </FloatingLayout>
    );
}
