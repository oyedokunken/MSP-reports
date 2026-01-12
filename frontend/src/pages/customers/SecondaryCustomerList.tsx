import FloatingLayout from "@/components/layout/FloatingLayout";
import {
    CustomersHeader,
    CustomersFilters,
    CustomersGrid,
    Pagination,
} from "./components";
import { EditCustomerModal } from "./modals/EditCustomerModal";
import { ViewCustomerIntegrationsModal } from "./modals/ViewCustomerIntegrationsModal";
import { MergeCustomersModal } from "./modals/MergeCustomersModal";
import { SplitCustomerModal } from "./modals/SplitCustomerModal";
import { ApplyTemplateToCustomerModal } from "@/pages/customers/modals/ApplyTemplateToCustomerModal";
import { useSecondaryCustomerList } from "./hooks";

export default function SecondaryCustomerList() {
    const {
        navigate,
        searchQuery,
        statusFilters,
        reportFilters,
        integrationFilters,
        totalMapped,
        mappedCustomers,
        isLoading,
        modals,
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
        handleCustomerAction,
        handleModalSuccess,
    } = useSecondaryCustomerList();

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                <CustomersHeader
                    title="Secondary Customers"
                    subtitle="Customers from non-source of truth integrations"
                />

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
                    hasUnmapped={false}
                    onSetupReports={(id) => navigate(`/platform/customers/${id}/configure-reports`)}
                    onViewHistory={(id) => navigate(`/platform/reports?customer=${id}`)}
                    onEdit={(customer) => handleCustomerAction(customer, 'edit')}
                    onViewIntegrations={(customer) => handleCustomerAction(customer, 'integrations')}
                    onMerge={(customer) => handleCustomerAction(customer, 'merge')}
                    onSplit={(customer) => handleCustomerAction(customer, 'split')}
                    onMoreInfo={(id) => navigate(`/platform/customers/${id}/integrations`)}
                    onApplyTemplate={(customer) => handleCustomerAction(customer, 'applyTemplate')}
                    onConfigureDelivery={(customer) => handleCustomerAction(customer, 'delivery')}
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

                <ApplyTemplateToCustomerModal
                    open={modals.applyTemplate}
                    onOpenChange={(open) => open ? openModal("applyTemplate") : closeModal("applyTemplate")}
                    customer={selectedCustomer}
                    onSuccess={() => handleModalSuccess('applyTemplate', "Template Applied", "Report template successfully applied")}
                />
            </div>
        </FloatingLayout>
    );
}
