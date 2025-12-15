import FloatingLayout from "@/components/layout/FloatingLayout";
import {
    CustomersHeader,
    CustomersFilters,
    CustomersGrid,
    MapCustomerModal,
    Pagination,
} from "./components";
import { EditCustomerModal } from "./modals/EditCustomerModal";
import { ViewCustomerIntegrationsModal } from "./modals/ViewCustomerIntegrationsModal";
import { MergeCustomersModal } from "./modals/MergeCustomersModal";
import { SplitCustomerModal } from "./modals/SplitCustomerModal";
import { useCustomerList } from "./hooks";

export default function CustomerList() {
    const {
        navigate,
        searchQuery,
        statusFilters,
        reportFilters,
        integrationFilters,
        totalMapped,
        mappedCustomers,
        unmappedCustomers,
        isLoading,
        modals,
        selectedUnmappedCustomer,
        selectedCustomer,
        currentPage,
        totalPages,
        total,
        setSearchQuery,
        setStatusFilters,
        setReportFilters,
        setIntegrationFilters,
        fetchCustomers,
        openModal,
        closeModal,
        handleMapSuccess,
        handleCustomerAction,
        handleModalSuccess,
    } = useCustomerList();

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                <CustomersHeader />

                <CustomersFilters
                    searchQuery={searchQuery}
                    statusFilters={statusFilters}
                    reportFilters={reportFilters}
                    integrationFilters={integrationFilters}
                    totalCustomers={totalMapped}
                    filteredCount={mappedCustomers.length}
                    onSearchChange={setSearchQuery}
                    onStatusFiltersChange={setStatusFilters}
                    onReportFiltersChange={setReportFilters}
                    onIntegrationFiltersChange={setIntegrationFilters}
                />



                <CustomersGrid
                    customers={mappedCustomers}
                    isLoading={isLoading}
                    hasUnmapped={unmappedCustomers.length > 0}
                    onSetupReports={(id) => navigate(`/platform/customers/${id}/configure-reports`)}
                    onViewHistory={(id) => navigate(`/platform/reports?customer=${id}`)}
                    onEdit={(customer) => handleCustomerAction(customer, 'edit')}
                    onViewIntegrations={(customer) => handleCustomerAction(customer, 'integrations')}
                    onMerge={(customer) => handleCustomerAction(customer, 'merge')}
                    onSplit={(customer) => handleCustomerAction(customer, 'split')}
                    onMoreInfo={(id) => navigate(`/platform/customers/${id}/integrations`)}
                />

                {total > 0 && (
                    <Pagination
                        currentPage={currentPage}
                        totalPages={totalPages}
                        total={total}
                        onPageChange={(page) => fetchCustomers(page)}
                        itemLabel="customers"
                    />
                )}

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
                    onSuccess={() => handleModalSuccess('edit', "Customer Updated", "Customer updated successfully")}
                />

                <ViewCustomerIntegrationsModal
                    open={modals.integrations}
                    onOpenChange={(open) => open ? openModal("integrations") : closeModal("integrations")}
                    customer={selectedCustomer}
                    onSuccess={() => handleModalSuccess('integrations', "Mapping Deleted", "Integration mapping removed")}
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
                    onSuccess={() => handleModalSuccess('merge', "Customers Merged", "Customers merged successfully")}
                />

                <SplitCustomerModal
                    open={modals.split}
                    onOpenChange={(open) => open ? openModal("split") : closeModal("split")}
                    customer={selectedCustomer}
                    onSuccess={() => handleModalSuccess('split', "Customer Split", "Customer split successfully")}
                />
            </div>
        </FloatingLayout>
    );
}
