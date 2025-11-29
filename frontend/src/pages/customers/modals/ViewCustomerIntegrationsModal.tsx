import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { 
    Cable, 
    Trash2, 
    Info,
} from "lucide-react";
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
    metadata?: any;
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
    const navigate = useNavigate();
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

    const handleViewFullDetails = () => {
        if (customer) {
            navigate(`/platform/customers/${customer.customer_id}/integrations`);
            handleClose();
        }
    };

    if (!customer) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-3xl max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Cable className="h-5 w-5 text-primary" />
                        <DialogTitle>Customer Integrations</DialogTitle>
                    </div>
                    <DialogDescription>
                        Manage integration mappings for {customer.name}
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
                                <Card key={integration.integration_customer_id} className="overflow-hidden">
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Cable className="h-4 w-4 text-primary flex-shrink-0" />
                                                    <h3 className="font-semibold truncate">
                                                        {integration.integration_name}
                                                    </h3>
                                                    <Badge variant="outline" className="capitalize flex-shrink-0">
                                                        {integration.integration_type}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                                    <span className="truncate">{integration.external_name}</span>
                                                    <span className="flex-shrink-0">ID: {integration.external_id}</span>
                                                </div>
                                                <div className="mt-2 text-xs text-muted-foreground">
                                                    Mapped: {new Date(integration.mapped_at).toLocaleDateString()}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleDeleteMapping(
                                                            customer.customer_id,
                                                            integration.mapping_id
                                                        )
                                                    }
                                                    disabled={deletingId === integration.mapping_id}
                                                    className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                                >
                                                    {deletingId === integration.mapping_id ? (
                                                        <Spinner className="h-4 w-4" />
                                                    ) : (
                                                        <Trash2 className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <DialogFooter className="flex items-center justify-between sm:justify-between">
                    <Button
                        variant="outline"
                        onClick={handleViewFullDetails}
                        className="gap-2"
                    >
                        <Info className="h-4 w-4" />
                        View Full Details
                    </Button>
                    <Button variant="outline" onClick={handleClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
