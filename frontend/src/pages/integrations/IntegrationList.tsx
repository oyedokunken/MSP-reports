import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { useToast } from "@/components/ui/Toast";
import { useIntegrationStore } from "@/stores/useIntegrationStore";
import { IntegrationHeader } from "./components/IntegrationHeader";
import { IntegrationGrid } from "./components/IntegrationGrid";
import { Pagination } from "@/pages/customers/components/Pagination";
import {
    AddIntegrationModal,
    EditIntegrationModal,
    DeleteIntegrationModal,
    ViewSyncLogsModal,
    IntegrationHealthModal,
    RateLimitStatusModal,
    HistoricalImportModal,
    ViewHistoricalImportJobsModal,
} from "./modals";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";
import { useIntegrationsQuery, useSyncIntegrationMutation, useSetSourceOfTruthMutation } from "./hooks/useIntegrations";

export default function IntegrationList() {
    const toast = useToast();
    const navigate = useNavigate();
    const { user } = useAuth();

    // React Query
    const { data, isLoading } = useIntegrationsQuery();
    const allIntegrations = data?.integrations || [];
    const availableTypesFromApi = data?.availableTypes || [];
    const syncMutation = useSyncIntegrationMutation();
    const setSourceOfTruthMutation = useSetSourceOfTruthMutation();

    // Zustand store - UI State
    const modals = useIntegrationStore((state) => state.modals);
    const selectedIntegration = useIntegrationStore((state) => state.selectedIntegration);
    const currentPage = useIntegrationStore((state) => state.currentPage);
    const perPage = useIntegrationStore((state) => state.perPage);
    const searchQuery = useIntegrationStore((state) => state.searchQuery);
    const statusFilters = useIntegrationStore((state) => state.statusFilters);
    const integrationTypeFilters = useIntegrationStore((state) => state.integrationTypeFilters);

    const setPage = useIntegrationStore((state) => state.setPage);
    const setSearchQuery = useIntegrationStore((state) => state.setSearchQuery);
    const setStatusFilters = useIntegrationStore((state) => state.setStatusFilters);
    const setIntegrationTypeFilters = useIntegrationStore((state) => state.setIntegrationTypeFilters);
    const openModal = useIntegrationStore((state) => state.openModal);
    const closeModal = useIntegrationStore((state) => state.closeModal);

    // Local state for fake sync progress (visual only)
    const [syncingId, setSyncingId] = useState<number | null>(null);
    const [syncProgress, setSyncProgress] = useState(0);

    // Derived State: Filtering
    const filteredIntegrations = allIntegrations.filter((int) => {
        // Search filter
        const matchesSearch = !searchQuery.trim() ||
            int.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            int.type.toLowerCase().includes(searchQuery.toLowerCase());

        // Status filter
        const matchesStatus = statusFilters.length === 0 ||
            statusFilters.some((status) => int.status.toLowerCase() === status.toLowerCase());

        // Integration type filter
        const matchesType = integrationTypeFilters.length === 0 ||
            integrationTypeFilters.some((type) => int.type.toLowerCase() === type.toLowerCase());

        return matchesSearch && matchesStatus && matchesType;
    });

    // Derived State: Pagination
    const paginatedIntegrations = filteredIntegrations.slice(
        (currentPage - 1) * perPage,
        currentPage * perPage
    );

    // Derived State: Unique values for filters
    const uniqueTypes = [...new Set(allIntegrations.map((int) => int.type))].sort();

    // Derived State: Source of Truth
    // In the old code, this was stored in store. Now we derive it from data.
    // However, the component expects `sourceOfTruthId`.
    const sourceOfTruthId = allIntegrations.find(i => i.isSourceOfTruth)?.id || null;

    // Fake sync progress for UX
    useEffect(() => {
        if (!syncingId) {
            setSyncProgress(0);
            return;
        }

        const interval = setInterval(() => {
            setSyncProgress((prev) => {
                if (prev >= 90) return prev;
                return prev + Math.random() * 15;
            });
        }, 300);

        return () => clearInterval(interval);
    }, [syncingId]);

    const handleSync = async (integrationId: number, name: string) => {
        if (!hasPermission(user, 'integrations', 'sync')) {
            toast.error('Permission Denied', 'You don\'t have permission to sync integrations');
            return;
        }

        setSyncingId(integrationId);
        setSyncProgress(0);

        try {
            await syncMutation.mutateAsync(integrationId);
            setSyncProgress(100);
            // Small delay to show completion before clearing
            setTimeout(() => setSyncingId(null), 500);
            toast.success("Sync Started", `${name} sync has been initiated.`);
        } catch (error) {
            setSyncingId(null);
            const message = error instanceof Error ? error.message : 'Unknown error';
            toast.error("Sync Failed", `${name}: ${message}`);
        }
    };

    const handleSetSourceOfTruth = async (id: number, name: string) => {
        if (!hasPermission(user, 'integrations', 'manage')) {
            toast.error('Permission Denied', 'You don\'t have permission to manage integrations');
            return;
        }

        try {
            await setSourceOfTruthMutation.mutateAsync(id);
            toast.success(
                "Master Customer List Set",
                `${name} is now your master customer list.`
            );
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Failed to set source of truth';
            toast.error("Failed to Update", message);
        }
    };

    return (
        <FloatingLayout>
            <div className="space-y-6">
                <IntegrationHeader
                    onAdd={() => {
                        if (!hasPermission(user, 'integrations', 'manage')) {
                            toast.error('Permission Denied', 'You don\'t have permission to add integrations');
                            return;
                        }
                        openModal("add");
                    }}
                    searchQuery={searchQuery}
                    statusFilters={statusFilters}
                    integrationTypeFilters={integrationTypeFilters}
                    uniqueTypes={uniqueTypes}
                    onSearchChange={setSearchQuery}
                    onStatusFiltersChange={setStatusFilters}
                    onIntegrationTypeFiltersChange={setIntegrationTypeFilters}
                />

                <IntegrationGrid
                    isLoading={isLoading}
                    items={paginatedIntegrations}
                    syncingId={syncingId}
                    progress={syncProgress}
                    settingSourceOfTruthId={setSourceOfTruthMutation.isPending ? setSourceOfTruthMutation.variables : null}
                    onSync={handleSync}
                    onEmptyAction={() => {
                        if (!hasPermission(user, 'integrations', 'manage')) {
                            toast.error('Permission Denied', 'You don\'t have permission to add integrations');
                            return;
                        }
                        openModal("add");
                    }}
                    onViewCustomers={(id) => {
                        const int = allIntegrations.find((i) => i.id === id);
                        if (int) navigate(`/platform/integrations/${int.id}/preview`);
                    }}
                    onSetSourceOfTruth={handleSetSourceOfTruth}
                    onEdit={(int) => {
                        if (!hasPermission(user, 'integrations', 'manage')) {
                            toast.error('Permission Denied', 'You don\'t have permission to edit integrations');
                            return;
                        }
                        openModal("edit", int);
                    }}
                    onViewLogs={(int) => openModal("syncLogs", int)}
                    onHealthCheck={(int) => openModal("health", int)}
                    onRateLimit={(int) => openModal("rateLimit", int)}
                    onHistoricalImport={(int) =>
                        openModal("historicalImport", int)
                    }
                    onViewHistoricalJobs={(int) =>
                        openModal("viewHistoricalJobs", int)
                    }
                    onDelete={(int) => {
                        if (!hasPermission(user, 'integrations', 'manage')) {
                            toast.error('Permission Denied', 'You don\'t have permission to delete integrations');
                            return;
                        }
                        openModal("delete", int);
                    }}
                    onPreviewData={(int) => navigate(`/platform/integrations/${int.id}/preview`)}
                    sourceOfTruthId={sourceOfTruthId}
                />

                {/* Pagination */}
                {!isLoading && filteredIntegrations.length > perPage && (
                    <Pagination
                        currentPage={currentPage}
                        totalPages={Math.ceil(filteredIntegrations.length / perPage)}
                        total={filteredIntegrations.length}
                        onPageChange={setPage}
                        itemLabel="integrations"
                        perPage={perPage}
                    />
                )}

                {/* Modals */}
                <AddIntegrationModal
                    open={modals.add}
                    onOpenChange={(open) =>
                        open ? openModal("add") : closeModal("add")
                    }
                    availableTypes={availableTypesFromApi}
                    onSuccess={() => {
                        closeModal("add");
                        toast.success("Integration added successfully");
                    }}
                />

                <EditIntegrationModal
                    open={modals.edit}
                    onOpenChange={(open) =>
                        open ? openModal("edit") : closeModal("edit")
                    }
                    integration={selectedIntegration}
                    onSuccess={() => {
                        closeModal("edit");
                        toast.success(
                            "Integration Updated",
                            "Integration settings have been updated"
                        );
                    }}
                />

                <DeleteIntegrationModal
                    open={modals.delete}
                    onOpenChange={(open) =>
                        open ? openModal("delete") : closeModal("delete")
                    }
                    integration={selectedIntegration}
                    onSuccess={() => {
                        closeModal("delete");
                        toast.success(
                            "Integration Deleted",
                            "Integration has been removed"
                        );
                    }}
                />

                <ViewSyncLogsModal
                    open={modals.syncLogs}
                    onOpenChange={(open) =>
                        open ? openModal("syncLogs") : closeModal("syncLogs")
                    }
                    integration={selectedIntegration}
                />

                <IntegrationHealthModal
                    open={modals.health}
                    onOpenChange={(open) =>
                        open ? openModal("health") : closeModal("health")
                    }
                    integration={selectedIntegration}
                />

                <RateLimitStatusModal
                    open={modals.rateLimit}
                    onOpenChange={(open) =>
                        open ? openModal("rateLimit") : closeModal("rateLimit")
                    }
                    integration={selectedIntegration}
                />

                <HistoricalImportModal
                    open={modals.historicalImport}
                    onOpenChange={(open) =>
                        open
                            ? openModal("historicalImport")
                            : closeModal("historicalImport")
                    }
                    integration={selectedIntegration}
                    onSuccess={() => {
                        closeModal("historicalImport");
                        toast.success(
                            "Import Started",
                            "Historical data import has been queued"
                        );
                    }}
                />

                <ViewHistoricalImportJobsModal
                    open={modals.viewHistoricalJobs}
                    onOpenChange={(open) =>
                        open
                            ? openModal("viewHistoricalJobs")
                            : closeModal("viewHistoricalJobs")
                    }
                    integration={selectedIntegration}
                />
            </div>
        </FloatingLayout>
    );
}
