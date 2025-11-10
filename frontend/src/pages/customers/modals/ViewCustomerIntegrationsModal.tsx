import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { Badge } from "@/components/ui/badge";
import { Cable, Trash2 } from "lucide-react";
import { api } from "@/lib/api";
import type { Customer } from "../types";

interface IntegrationMapping {
    integration_customer_id: number;
    integration_id: number;
    integration_name: string;
    integration_type: string;
    external_id: string;
    external_name: string;
    mapping_id: number;
    mapped_at: string;
}

interface ViewCustomerIntegrationsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: Customer | null;
    onSuccess?: () => void;
}

export function ViewCustomerIntegrationsModal({
    open,
    onOpenChange,
    customer,
    onSuccess,
}: ViewCustomerIntegrationsModalProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [integrations, setIntegrations] = useState<IntegrationMapping[]>([]);
    const [error, setError] = useState("");
    const [deletingId, setDeletingId] = useState<number | null>(null);

    useEffect(() => {
        if (open && customer) {
            fetchIntegrations();
        }
    }, [open, customer]);

    const fetchIntegrations = async () => {
        if (!customer) return;

        setIsLoading(true);
        setError("");

        try {
            const response = await api.get(
                `/customers/${customer.customer_id}/integrations`
            );
            const data = (response as any).data || response;
            setIntegrations(Array.isArray(data) ? data : []);
        } catch (err: any) {
            const errorMessage =
                err.response?.data?.message ||
                err.message ||
                "Failed to load integrations";
            setError(errorMessage);
        } finally {
            setIsLoading(false);
        }
    };

    const handleDeleteMapping = async (
        customerId: number,
        mappingId: number
    ) => {
        setDeletingId(mappingId);
        try {
            await api.delete(`/customers/${customerId}/mappings/${mappingId}`);
            await fetchIntegrations();
            onSuccess?.();
        } catch (err: any) {
            const errorMessage =
                err.response?.data?.message ||
                err.message ||
                "Failed to delete mapping";
            setError(errorMessage);
        } finally {
            setDeletingId(null);
        }
    };

    const handleClose = () => {
        onOpenChange(false);
    };

    if (!customer) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Cable className="h-5 w-5 text-primary" />
                        <DialogTitle>Customer Integrations</DialogTitle>
                    </div>
                    <DialogDescription>
                        Integrations where {customer.name} appears
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Spinner className="h-8 w-8" />
                        </div>
                    ) : error ? (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                            <p className="text-sm text-destructive font-medium">
                                {error}
                            </p>
                        </div>
                    ) : integrations.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            <Cable className="h-12 w-12 mx-auto mb-2 opacity-50" />
                            <p>No integrations found</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {integrations.map((integration) => (
                                <div
                                    key={integration.integration_customer_id}
                                    className="rounded-lg border border-border p-4"
                                >
                                    <div className="flex items-start justify-between mb-3">
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="font-semibold">
                                                    {
                                                        integration.integration_name
                                                    }
                                                </h3>
                                                <Badge
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {
                                                        integration.integration_type
                                                    }
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                External Name:{" "}
                                                {integration.external_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                External ID:{" "}
                                                {integration.external_id}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleDeleteMapping(
                                                    customer.customer_id,
                                                    integration.mapping_id
                                                )
                                            }
                                            disabled={
                                                deletingId ===
                                                integration.mapping_id
                                            }
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        >
                                            {deletingId ===
                                            integration.mapping_id ? (
                                                <Spinner className="h-4 w-4" />
                                            ) : (
                                                <Trash2 className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Mapped:{" "}
                                        {new Date(
                                            integration.mapped_at
                                        ).toLocaleString()}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex justify-end">
                    <Button variant="outline" onClick={handleClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
