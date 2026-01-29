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
import { ApplyTemplateToCustomerModal } from "@/pages/customers/modals/ApplyTemplateToCustomerModal";
import { GitMerge, GitBranch, Settings, MoreVertical, Edit, FileText, Cable, Send, CheckCircle, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";

export default function MasterCustomerList() {
    const [showCreateStatic, setShowCreateStatic] = useState(false);
    const [view, setView] = useState<'grid' | 'table'>('grid');

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

    const { user } = useAuth();
    const canConfigureReports = hasPermission(user, 'reports', 'configure');
    const canEditCustomers = hasPermission(user, 'customers', 'edit');
    const canMapCustomers = hasPermission(user, 'customers', 'map');

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
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
                    onCreateStatic={canMapCustomers ? () => setShowCreateStatic(true) : undefined}
                    view={view}
                    onViewChange={setView}
                />



                {view === 'grid' ? (
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
                ) : (
                    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th className="text-left p-4 font-semibold text-sm">Customer Name</th>
                                        <th className="text-left p-4 font-semibold text-sm">Status</th>
                                        <th className="text-left p-4 font-semibold text-sm">Integrations</th>
                                        <th className="text-left p-4 font-semibold text-sm">Reports</th>
                                        <th className="text-right p-4 font-semibold text-sm">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {isLoading ? (
                                        [1, 2, 3, 4, 5].map((i) => (
                                            <tr key={i}>
                                                <td className="p-4"><Skeleton className="h-5 w-48" /></td>
                                                <td className="p-4"><Skeleton className="h-5 w-16" /></td>
                                                <td className="p-4"><Skeleton className="h-5 w-32" /></td>
                                                <td className="p-4"><Skeleton className="h-5 w-24" /></td>
                                                <td className="p-4 text-right"><Skeleton className="h-8 w-24 ml-auto" /></td>
                                            </tr>
                                        ))
                                    ) : mappedCustomers.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="p-12 text-center text-muted-foreground italic">
                                                No customers found
                                            </td>
                                        </tr>
                                    ) : (
                                        mappedCustomers.map((customer) => (
                                            <tr key={customer.customer_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                                <td className="p-4">
                                                    <div className="font-semibold text-slate-900 dark:text-slate-100">{customer.canonical_name}</div>
                                                </td>
                                                <td className="p-4">
                                                    <Badge
                                                        variant={customer.status === 'active' ? 'default' : 'secondary'}
                                                        className={customer.status !== 'active' ? 'bg-purple-600 text-white hover:bg-purple-700' : ''}
                                                    >
                                                        {customer.status === 'active' ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex flex-wrap gap-1">
                                                        {customer.integrations.map((int) => (
                                                            <Badge key={int.integration_customer_id} variant="outline" className="text-[10px] bg-slate-50 dark:bg-slate-900/50">
                                                                {int.integration_type}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    {customer.report_schedules_count! > 0 ? (
                                                        <div className="flex items-center gap-1.5 text-green-600 font-medium text-sm">
                                                            <CheckCircle className="h-3.5 w-3.5" />
                                                            {customer.report_schedules_count} Configured
                                                        </div>
                                                    ) : (
                                                        <div className="flex items-center gap-1.5 text-orange-600 font-medium text-sm">
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            None
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            className="h-8 px-3"
                                                            onClick={canConfigureReports ? () => navigate(`/platform/customers/${customer.customer_id}/configure-reports`) : undefined}
                                                            disabled={!canConfigureReports}
                                                        >
                                                            <Settings className="h-3.5 w-3.5 mr-1" />
                                                            Setup
                                                        </Button>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="outline" size="sm" className="h-8 w-8 p-0">
                                                                    <MoreVertical className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem onClick={() => navigate(`/platform/reports?customer=${customer.customer_id}`)}>
                                                                    <FileText className="mr-2 h-4 w-4" />
                                                                    View History
                                                                </DropdownMenuItem>
                                                                {canEditCustomers && (
                                                                    <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'edit')}>
                                                                        <Edit className="mr-2 h-4 w-4" />
                                                                        Edit Customer
                                                                    </DropdownMenuItem>
                                                                )}
                                                                <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'integrations')}>
                                                                    <Cable className="mr-2 h-4 w-4" />
                                                                    View Integrations
                                                                </DropdownMenuItem>
                                                                {canConfigureReports && (
                                                                    <>
                                                                        <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'applyTemplate')}>
                                                                            <FileText className="mr-2 h-4 w-4" />
                                                                            Apply Template
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'delivery')}>
                                                                            <Send className="mr-2 h-4 w-4" />
                                                                            Delivery Settings
                                                                        </DropdownMenuItem>
                                                                    </>
                                                                )}
                                                                {canEditCustomers && (
                                                                    <>
                                                                        <DropdownMenuSeparator />
                                                                        <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'merge')}>
                                                                            <GitMerge className="mr-2 h-4 w-4" />
                                                                            Merge Customer
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem onClick={() => handleCustomerAction(customer, 'split')}>
                                                                            <GitBranch className="mr-2 h-4 w-4" />
                                                                            Split Customer
                                                                        </DropdownMenuItem>
                                                                    </>
                                                                )}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

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
