import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { useToast } from "@/components/ui/Toast";
import { useCustomersStore } from "@/stores/useCustomersStore";

import {
    CustomersHeader,
    CustomersFilters,
    UnmappedBanner,
    CustomersGrid,
    MapCustomerModal,
} from "./components";

import { EditCustomerModal } from "./modals/EditCustomerModal";
import { ViewCustomerIntegrationsModal } from "./modals/ViewCustomerIntegrationsModal";
import { MergeCustomersModal } from "./modals/MergeCustomersModal";
import { SplitCustomerModal } from "./modals/SplitCustomerModal";

export default function CustomerList() {
    const navigate = useNavigate();
    const toast = useToast();

    // Zustand selectors
    const customers = useCustomersStore((state) => state.customers);
    const isLoading = useCustomersStore((state) => state.isLoading);
    const searchQuery = useCustomersStore((state) => state.searchQuery);
    const statusFilter = useCustomersStore((state) => state.statusFilter);
    const reportFilter = useCustomersStore((state) => state.reportFilter);
    const modals = useCustomersStore((state) => state.modals);
    const selectedUnmappedCustomer = useCustomersStore((state) => state.selectedUnmappedCustomer);
    const selectedCustomer = useCustomersStore((state) => state.selectedCustomer);

    const fetchCustomers = useCustomersStore((state) => state.fetchCustomers);
    const setSearchQuery = useCustomersStore((state) => state.setSearchQuery);
    const setStatusFilter = useCustomersStore((state) => state.setStatusFilter);
    const setReportFilter = useCustomersStore((state) => state.setReportFilter);
    const openModal = useCustomersStore((state) => state.openModal);
    const closeModal = useCustomersStore((state) => state.closeModal);
    const setSelectedUnmappedCustomer = useCustomersStore((state) => state.setSelectedUnmappedCustomer);
    const setSelectedCustomer = useCustomersStore((state) => state.setSelectedCustomer);

    const getMappedCustomers = useCustomersStore((state) => state.getMappedCustomers);
    const getUnmappedCustomers = useCustomersStore((state) => state.getUnmappedCustomers);

    // Fetch on mount
    useEffect(() => {
        fetchCustomers().catch((err) => {
            toast.error(
                "Failed to load customers",
                err.response?.data?.message || err.message
            );
        });
    }, [fetchCustomers]);

    const mappedCustomers = getMappedCustomers();
    const unmappedCustomers = getUnmappedCustomers();
    const totalMapped = customers.filter((c) => c.customer_id !== null).length;

    // Start mapping
    const handleStartMapping = () => {
        if (unmappedCustomers.length > 0) {
            setSelectedUnmappedCustomer(unmappedCustomers[0].integrations[0]);
            openModal("map");
        }
    };

    // 🟢 FIXED: This replaces the invalid JSX block
    const handleMapSuccess = async () => {
        await fetchCustomers();
        const updatedUnmapped = getUnmappedCustomers();

        if (updatedUnmapped.length > 0) {
            setSelectedUnmappedCustomer(updatedUnmapped[0].integrations[0]);
        } else {
            closeModal("map");
            toast.success("All Done!", "All customers have been mapped");
        }
    };

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-x-hidden">
                <CustomersHeader />

                <CustomersFilters
                    searchQuery={searchQuery}
                    statusFilter={statusFilter}
                    reportFilter={reportFilter}
                    totalCustomers={totalMapped}
                    filteredCount={mappedCustomers.length}
                    onSearchChange={setSearchQuery}
                    onStatusChange={setStatusFilter}
                    onReportFilterChange={setReportFilter}
                />

                <UnmappedBanner
                    count={unmappedCustomers.length}
                    onStartMapping={handleStartMapping}
                />

                <CustomersGrid
                    customers={mappedCustomers}
                    isLoading={isLoading}
                    hasUnmapped={unmappedCustomers.length > 0}
                    onSetupReports={(id) => navigate(`/customers/${id}/configure-reports`)}
                    onViewHistory={(id) => navigate(`/reports?customer=${id}`)}
                    onEdit={(customer) => {
                        setSelectedCustomer({
                            customer_id: customer.customer_id!,
                            name: customer.canonical_name!,
                            status: customer.status!,
                            integration_count: customer.integrations.length,
                        });
                        openModal("edit");
                    }}
                    onViewIntegrations={(customer) => {
                        setSelectedCustomer({
                            customer_id: customer.customer_id!,
                            name: customer.canonical_name!,
                            status: customer.status!,
                            integration_count: customer.integrations.length,
                        });
                        openModal("integrations");
                    }}
                    onMerge={(customer) => {
                        setSelectedCustomer({
                            customer_id: customer.customer_id!,
                            name: customer.canonical_name!,
                            status: customer.status!,
                            integration_count: customer.integrations.length,
                        });
                        openModal("merge");
                    }}
                    onSplit={(customer) => {
                        setSelectedCustomer({
                            customer_id: customer.customer_id!,
                            name: customer.canonical_name!,
                            status: customer.status!,
                            integration_count: customer.integrations.length,
                        });
                        openModal("split");
                    }}
                />

                {/* ---------------- MODALS ---------------- */}

                <MapCustomerModal
                    open={modals.map}
                    onOpenChange={(open) => open ? openModal("map") : closeModal("map")}
                    integrationCustomer={selectedUnmappedCustomer}
                    existingCustomers={mappedCustomers}
                    onSuccess={handleMapSuccess}
                />

                <EditCustomerModal
                    open={modals.edit}
                    onOpenChange={(open) => open ? openModal("edit") : closeModal("edit")}
                    customer={selectedCustomer}
                    onSuccess={() => {
                        fetchCustomers();
                        closeModal("edit");
                        toast.success("Customer Updated", "Customer updated successfully");
                    }}
                />

                <ViewCustomerIntegrationsModal
                    open={modals.integrations}
                    onOpenChange={(open) => open ? openModal("integrations") : closeModal("integrations")}
                    customer={selectedCustomer}
                    onSuccess={() => {
                        fetchCustomers();
                        closeModal("integrations");
                        toast.success("Mapping Deleted", "Integration mapping removed");
                    }}
                />

                <MergeCustomersModal
                    open={modals.merge}
                    onOpenChange={(open) => open ? openModal("merge") : closeModal("merge")}
                    customer={selectedCustomer}
                    allCustomers={mappedCustomers.map((c) => ({
                        customer_id: c.customer_id!,
                        name: c.canonical_name!,
                        status: c.status!,
                        integration_count: c.integrations.length,
                    }))}
                    onSuccess={() => {
                        fetchCustomers();
                        closeModal("merge");
                        toast.success("Customers Merged", "Customers merged successfully");
                    }}
                />

                <SplitCustomerModal
                    open={modals.split}
                    onOpenChange={(open) => open ? openModal("split") : closeModal("split")}
                    customer={selectedCustomer}
                    onSuccess={() => {
                        fetchCustomers();
                        closeModal("split");
                        toast.success("Customer Split", "Customer split successfully");
                    }}
                />
            </div>
        </FloatingLayout>
    );
}
