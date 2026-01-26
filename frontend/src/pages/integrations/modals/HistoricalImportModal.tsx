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
import { CheckCircle2, Database, AlertCircle } from "lucide-react";
import { api } from "@/lib/api";
import type { Integration } from "../types/integration";

interface HistoricalImportModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    integration: Integration | null;
    onSuccess?: () => void;
}

export function HistoricalImportModal({
    open,
    onOpenChange,
    integration,
    onSuccess,
}: HistoricalImportModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState("");
    const [success, setSuccess] = useState(false);
    const [days, setDays] = useState(
        integration?.type === "itglue" ? "30" : "90",
    );

    useEffect(() => {
        if (integration) {
            setDays(integration.type === "itglue" ? "30" : "90");
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [integration?.type]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!integration) return;

        const daysNum = parseInt(days);
        if (isNaN(daysNum) || daysNum < 1) {
            setError("Please enter a valid number of days (minimum 1)");
            return;
        }

        if (daysNum > 365) {
            setError("Maximum 365 days allowed");
            return;
        }

        setError("");
        setIsSubmitting(true);

        try {
            // End date should be end of TODAY (includes all of today's data)
            const endDate = new Date();
            endDate.setHours(23, 59, 59, 999);

            // Start date: "90 days" means 90 days back from TODAY
            // Dec 16 - 89 days = Sep 17 (because we count today as day 1)
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - daysNum + 1);
            startDate.setHours(0, 0, 0, 0);

            await api.post(
                `/integrations/${integration.id}/historical-import`,
                {
                    start_date: startDate.toISOString().split("T")[0],
                    end_date: endDate.toISOString().split("T")[0],
                },
            );

            setSuccess(true);
            setTimeout(() => {
                setSuccess(false);
                setDays(integration?.type === "itglue" ? "30" : "90");
                onOpenChange(false);
                onSuccess?.();
            }, 3000);
        } catch (err: unknown) {
            const error = err as {
                response?: { data?: { message?: string } };
                message?: string;
            };
            const errorMessage =
                error.response?.data?.message ||
                error.message ||
                "Failed to start historical import";
            setError(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        if (!isSubmitting) {
            setDays(integration?.type === "itglue" ? "30" : "90");
            setError("");
            setSuccess(false);
            onOpenChange(false);
        }
    };

    if (success) {
        return (
            <Dialog open={open} onOpenChange={handleClose}>
                <DialogContent className="sm:max-w-md">
                    <div className="flex flex-col items-center justify-center py-8">
                        <div className="mb-4 rounded-full bg-green-500/10 p-3">
                            <CheckCircle2 className="h-12 w-12 text-green-500" />
                        </div>
                        <DialogTitle className="text-2xl text-green-600 mb-2">
                            Import Started!
                        </DialogTitle>
                        <DialogDescription className="text-center">
                            Historical data import has been queued. This will
                            run in the background and may take several hours
                            depending on the amount of data.
                        </DialogDescription>
                    </div>
                </DialogContent>
            </Dialog>
        );
    }

    if (!integration) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Database className="h-5 w-5 text-primary" />
                        <DialogTitle>Import Historical Data</DialogTitle>
                    </div>
                    <DialogDescription>
                        Import past data from {integration.name}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label
                            htmlFor="days"
                            className="text-sm font-medium mb-2 block"
                        >
                            Number of Days
                        </Label>
                        <Input
                            id="days"
                            type="number"
                            min="1"
                            max="365"
                            placeholder="90"
                            value={days}
                            onChange={(e) => setDays(e.target.value)}
                            disabled={isSubmitting}
                            className="w-full"
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            Import data from the last {days || "0"} days (max 365)
                        </p>
                    </div>

                    <div className="rounded-lg border bg-muted/50 p-4 space-y-3">
                        {integration.type === "itglue" && (
                            <div className="flex gap-2">
                                <span className="text-lg">💡</span>
                                <div className="flex-1 space-y-1">
                                    <p className="text-sm font-medium">
                                        IT Glue Asset Import
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        This will import{" "}
                                        <strong>
                                            all Microsoft licenses, SSL
                                            certificates, domains, and other
                                            assets
                                        </strong>{" "}
                                        that were updated within the specified
                                        date range.
                                    </p>
                                </div>
                            </div>
                        )}

                        {(integration.type === "datto" ||
                            integration.type === "datto_rmm") && (
                            <div className="flex gap-2">
                                <span className="text-lg">💡</span>
                                <div className="flex-1 space-y-1">
                                    <p className="text-sm font-medium">
                                        Datto Historical Import
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Fetches historical alerts and backfills
                                        device uptime snapshots for trend
                                        analysis.
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex gap-2 pt-2 border-t">
                            <span className="text-lg">⚠️</span>
                            <div className="flex-1">
                                <p className="text-xs text-muted-foreground">
                                    Runs in background (may take hours).
                                    Respects API limits. Monitor progress in
                                    sync logs.
                                </p>
                            </div>
                        </div>
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
                                    Starting...
                                </>
                            ) : (
                                <>
                                    <Database className="mr-2 h-4 w-4" />
                                    Start Import
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
