import FloatingLayout from "@/components/layout/FloatingLayout";
import {
    CustomersFilters,
    CustomersGrid,
    MapCustomerModal,
    Pagination,
} from "./components";
import { EditCustomerModal } from "./modals/EditCustomerModal";
import { ViewCustomerIntegrationsModal } from "./modals/ViewCustomerIntegrationsModal";
import { MergeCustomersModal } from "./modals/MergeCustomersModal";
import { SplitCustomerModal } from "./modals/SplitCustomerModal";
import { CreateStaticCustomerModal } from "./modals/CreateStaticCustomerModal";
import { useMasterCustomerList } from "./hooks";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { UserPlus } from "lucide-react";
import { ApplyTemplateToCustomerModal } from "@/pages/customers/modals/ApplyTemplateToCustomerModal";

export default function MasterCustomerList() {
    const [showCreateStatic, setShowCreateStatic] = useState(false);

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
        sotIntegrationName,
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
    } = useMasterCustomerList();

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-foreground">Master Customer List</h1>
                            {sotIntegrationName && (
                                <span className="px-3 py-1 text-sm font-medium bg-primary/10 text-primary rounded-full border border-primary/20">
                                    {sotIntegrationName}
                                </span>
                            )}
                        </div>
                        <p className="mt-2 text-muted-foreground">
                            Customers from your Source of Truth integration
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateStatic(true)} className="flex items-center gap-2">
                        <UserPlus className="h-4 w-4" />
                        Create Static Customer
                    </Button>
                </div>

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

                <MapCustomerModal
                    open={modals.map}
                    onOpenChange={(open: boolean) => open ? openModal("map") : closeModal("map")}
                    integrationCustomers={selectedUnmappedCustomer ? [selectedUnmappedCustomer] : []}
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

                <ApplyTemplateToCustomerModal
                    open={modals.applyTemplate}
                    onOpenChange={(open) => open ? openModal("applyTemplate") : closeModal("applyTemplate")}
                    customer={selectedCustomer}
                    onSuccess={() => handleModalSuccess('applyTemplate', "Template Applied", "Report template successfully applied")}
                />

                <CreateStaticCustomerModal
                    open={showCreateStatic}
                    onOpenChange={setShowCreateStatic}
                    onSuccess={() => {
                        setShowCreateStatic(false);
                        fetchCustomers(currentPage);
                    }}
                />

            </div>
        </FloatingLayout>
    );
}
