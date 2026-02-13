import { useNavigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";
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
import { useCustomerIntegrationsQuery, useDeleteMappingMutation } from "../hooks/useCustomersQueries";
import type { Customer } from "../types";

interface IntegrationMapping {
    integration_customer_id: number;
    integration_id: number;
    integration_type: string;
    display_name: string;
    external_id: string;
    name: string;
    active: boolean;
    mapping: {
        mapping_id: number;
        mapped_at: string;
        // other fields if needed
    } | null;
    metadata?: Record<string, unknown>;
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
    const { user } = useAuth();
    const canEdit = hasPermission(user, "customers", "edit");

    const {
        data: integrationsResponse,
        isLoading,
        error: queryError
    } = useCustomerIntegrationsQuery(open ? customer?.customer_id : undefined);

    const deleteMutation = useDeleteMappingMutation();

    const integrations = (integrationsResponse?.data || []) as unknown as IntegrationMapping[];

    const handleDeleteMapping = async (
        customerId: number,
        mappingId: number
    ) => {
        try {
            await deleteMutation.mutateAsync({ customerId, mappingId });
            onSuccess?.();
        } catch {
            // Error managed by mutation
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

    const displayError = (queryError instanceof Error ? queryError.message : "") ||
        (deleteMutation.error instanceof Error ? deleteMutation.error.message : "");

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
                    ) : displayError ? (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                            <p className="text-sm text-destructive font-medium">
                                {displayError}
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
                                    <CardContent className="pl-4 pr-4">
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Cable className="h-4 w-4 text-primary flex-shrink-0" />
                                                    <h3 className="font-semibold truncate">
                                                        {integration.display_name}
                                                    </h3>
                                                    <Badge variant="outline" className="capitalize flex-shrink-0">
                                                        {integration.integration_type}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                                    <span className="truncate">{integration.name}</span>
                                                    <span className="flex-shrink-0">ID: {integration.external_id}</span>
                                                </div>
                                                <div className="mt-2 text-xs text-muted-foreground">
                                                    Mapped: {integration.mapping?.mapped_at ? new Date(integration.mapping.mapped_at).toLocaleDateString() : 'N/A'}
                                                </div>
                                            </div>
                                            {canEdit && integration.mapping?.mapping_id && (
                                                <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDeleteMapping(
                                                                customer.customer_id,
                                                                integration.mapping!.mapping_id
                                                            )
                                                        }
                                                        disabled={deleteMutation.isPending && deleteMutation.variables?.mappingId === integration.mapping?.mapping_id}
                                                        className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                                    >
                                                        {deleteMutation.isPending && deleteMutation.variables?.mappingId === integration.mapping?.mapping_id ? (
                                                            <Spinner className="h-4 w-4" />
                                                        ) : (
                                                            <Trash2 className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </div>
                                            )}
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
