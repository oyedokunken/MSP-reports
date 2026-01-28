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
import { api } from "@/lib/api";
import { useCustomersStore } from "@/stores/useCustomersStore";
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
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState("");
    const [name, setName] = useState("");

    const updateCustomer = useCustomersStore((state) => state.updateCustomer);

    useEffect(() => {
        if (open && customer) {
            setName(customer.canonical_name || customer.name || "");
            setError("");
        }
    }, [open, customer]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            setError("Please enter a customer name");
            return;
        }

        setError("");
        setIsSubmitting(true);

        try {
            await api.patch(`/customers/${customer?.customer_id}`, {
                canonical_name: name.trim(),
            });

            if (customer?.customer_id) {
                updateCustomer(customer.customer_id, {
                    canonical_name: name.trim(),
                });
            }

            onOpenChange(false);
            onSuccess?.();
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string };
            const errorMessage =
                error.response?.data?.message ||
                error.message ||
                "Failed to update customer";
            setError(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        if (!isSubmitting) {
            onOpenChange(false);
        }
    };

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
                            disabled={isSubmitting}
                            className="w-full"
                        />
                    </div>

                    {error && (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
                            <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
                            <p className="text-sm text-destructive font-medium">
                                {error}
                            </p>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? (
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
