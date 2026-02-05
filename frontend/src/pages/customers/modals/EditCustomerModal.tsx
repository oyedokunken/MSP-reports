import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { Edit, AlertCircle } from "lucide-react";
import { useUpdateCustomerMutation } from "../hooks/useCustomersQueries";
import type { Customer } from "../types";

interface EditCustomerModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: Customer | null;
    onSuccess?: () => void;
}

export function EditCustomerModal({
    open,
    onOpenChange,
    customer,
    onSuccess,
}: EditCustomerModalProps) {
    const [name, setName] = useState("");
    const [localError, setLocalError] = useState("");

    const updateMutation = useUpdateCustomerMutation();

    useEffect(() => {
        if (open && customer) {
            setName(customer.canonical_name || customer.name || "");
            setLocalError("");
        }
    }, [open, customer]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            setLocalError("Please enter a customer name");
            return;
        }

        if (!customer?.customer_id) return;

        setLocalError("");

        try {
            await updateMutation.mutateAsync({
                customerId: customer.customer_id,
                data: { canonical_name: name.trim() }
            });

            onOpenChange(false);
            onSuccess?.();
        } catch {
            // Error is handled by mutation state, but we can catch to avoid unhandled rejection
        }
    };

    const handleClose = () => {
        if (!updateMutation.isPending) {
            onOpenChange(false);
        }
    };

    // Extract error message from mutation
    const mutationError = updateMutation.error instanceof Error ? updateMutation.error.message : "";
    const displayError = localError || mutationError;

    if (!customer) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Edit className="h-5 w-5 text-primary" />
                        <DialogTitle>Edit Customer</DialogTitle>
                    </div>
                    <DialogDescription>
                        Update customer details
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label
                            htmlFor="name"
                            className="text-sm font-medium mb-2 block"
                        >
                            Customer Name
                        </Label>
                        <Input
                            id="name"
                            type="text"
                            placeholder="Customer Name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            disabled={updateMutation.isPending}
                            className="w-full"
                        />
                    </div>

                    {displayError && (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
                            <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
                            <p className="text-sm text-destructive font-medium">
                                {displayError}
                            </p>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={updateMutation.isPending}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={updateMutation.isPending}>
                            {updateMutation.isPending ? (
                                <>
                                    <Spinner className="mr-2 h-4 w-4" />
                                    Updating...
                                </>
                            ) : (
                                "Update Customer"
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
