import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
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

const editCustomerSchema = z.object({
    canonical_name: z.string().min(1, "Customer name is required"),
});

type EditCustomerFormValues = z.infer<typeof editCustomerSchema>;

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
    const updateMutation = useUpdateCustomerMutation();

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors }
    } = useForm<EditCustomerFormValues>({
        resolver: zodResolver(editCustomerSchema),
        defaultValues: {
            canonical_name: "",
        },
    });

    useEffect(() => {
        if (open && customer) {
            reset({
                canonical_name: customer.canonical_name || customer.name || "",
            });
        }
    }, [open, customer, reset]);

    const onSubmit = async (data: EditCustomerFormValues) => {
        if (!customer?.customer_id) return;

        try {
            await updateMutation.mutateAsync({
                customerId: customer.customer_id,
                data: { canonical_name: data.canonical_name.trim() }
            });

            onOpenChange(false);
            onSuccess?.();
        } catch {
            // Error is handled by mutation state
        }
    };

    const handleClose = () => {
        if (!updateMutation.isPending) {
            onOpenChange(false);
        }
    };

    // Extract error message from mutation
    const mutationError = updateMutation.error instanceof Error ? updateMutation.error.message : "";
    const displayError = errors.canonical_name?.message || mutationError;

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

                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    <div>
                        <Label
                            htmlFor="canonical_name"
                            className="text-sm font-medium mb-2 block"
                        >
                            Customer Name
                        </Label>
                        <Input
                            id="canonical_name"
                            type="text"
                            placeholder="Customer Name"
                            {...register("canonical_name")}
                            disabled={updateMutation.isPending}
                            className={`w-full ${errors.canonical_name ? "border-destructive focus-visible:ring-destructive" : ""}`}
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
