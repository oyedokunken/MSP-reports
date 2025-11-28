import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { useToast } from "@/components/ui/Toast";
import { useIntegrationStore } from "@/stores/useIntegrationStore";
import { IntegrationHeader } from "./components/IntegrationHeader";
import { IntegrationGrid } from "./components/IntegrationGrid";
import {
    AddIntegrationModal,
    ViewIntegrationCustomersModal,
    EditIntegrationModal,
    DeleteIntegrationModal,
    ViewSyncLogsModal,
    IntegrationHealthModal,
    RateLimitStatusModal,
    HistoricalImportModal,
    ViewHistoricalImportJobsModal,
} from "./modals";

export default function IntegrationList() {
    const toast = useToast();
    const navigate = useNavigate();

    // Zustand store - select only what we need to prevent unnecessary rerenders
    const integrations = useIntegrationStore((state) => state.integrations);
    const availableTypes = useIntegrationStore((state) => state.availableTypes);
    const sourceOfTruthId = useIntegrationStore(
        (state) => state.sourceOfTruthId
    );
    const isLoading = useIntegrationStore((state) => state.isLoading);
    const syncingId = useIntegrationStore((state) => state.syncingId);
    const syncProgress = useIntegrationStore((state) => state.syncProgress);
    const settingSourceOfTruthId = useIntegrationStore((state) => state.settingSourceOfTruthId);
    const modals = useIntegrationStore((state) => state.modals);
    const selectedIntegration = useIntegrationStore(
        (state) => state.selectedIntegration
    );
    const fetchIntegrations = useIntegrationStore(
        (state) => state.fetchIntegrations
    );
    const setSourceOfTruth = useIntegrationStore(
        (state) => state.setSourceOfTruth
    );
    const syncIntegration = useIntegrationStore(
        (state) => state.syncIntegration
    );
    const openModal = useIntegrationStore((state) => state.openModal);
    const closeModal = useIntegrationStore((state) => state.closeModal);

    // Fetch integrations on mount
    useEffect(() => {
        fetchIntegrations().catch((err) => {
            toast.error(
                "Failed to load integrations",
                err.response?.data?.message || err.message
            );
        });
    }, [fetchIntegrations]);

    // Handler functions
    const handleSync = async (integrationId: number, name: string) => {
        const result = await syncIntegration(integrationId);
        if (result.success) {
            toast.success("Sync Complete", `${name} synced successfully`);
        } else {
            toast.error("Sync Failed", `${name}: ${result.error}`);
        }
    };

    const handleSetSourceOfTruth = async (id: number, name: string) => {
        const result = await setSourceOfTruth(id);
        if (result.success) {
            toast.success(
                "Master Customer List Set",
                `${name} is now your master customer list.`
            );
        } else {
            toast.error("Failed to Update", result.error);
        }
    };

    return (
        <FloatingLayout>
            <div className="space-y-6">
                <IntegrationHeader onAdd={() => openModal("add")} />

                <IntegrationGrid
                    isLoading={isLoading}
                    items={integrations}
                    syncingId={syncingId}
                    progress={syncProgress}
                    settingSourceOfTruthId={settingSourceOfTruthId}
                    onSync={handleSync}
                    onEmptyAction={() => openModal("add")}
                    onViewCustomers={(id) => {
                        const int = integrations.find((i) => i.id === id);
                        if (int) openModal("viewCustomers", int);
                    }}
                    onSetSourceOfTruth={handleSetSourceOfTruth}
                    onEdit={(int) => openModal("edit", int)}
                    onViewLogs={(int) => openModal("syncLogs", int)}
                    onHealthCheck={(int) => openModal("health", int)}
                    onRateLimit={(int) => openModal("rateLimit", int)}
                    onHistoricalImport={(int) =>
                        openModal("historicalImport", int)
                    }
                    onViewHistoricalJobs={(int) =>
                        openModal("viewHistoricalJobs", int)
                    }
                    onDelete={(int) => openModal("delete", int)}
                    onPreviewData={(int) => navigate(`/platform/integrations/${int.id}/preview`)}
                    sourceOfTruthId={sourceOfTruthId}
                />

                {/* Modals */}
                <AddIntegrationModal
                    open={modals.add}
                    onOpenChange={(open) =>
                        open ? openModal("add") : closeModal("add")
                    }
                    availableTypes={availableTypes}
                    onSuccess={() => {
                        closeModal("add");
                        toast.success("Integration added successfully");
                    }}
                />

                <ViewIntegrationCustomersModal
                    open={modals.viewCustomers}
                    onOpenChange={(open) =>
                        open
                            ? openModal("viewCustomers")
                            : closeModal("viewCustomers")
                    }
                    integrationId={selectedIntegration?.id || null}
                    integrationName={selectedIntegration?.name || ""}
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
