import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Spinner } from "@/components/ui/spinner";
import { Send, Mail, Cloud, AlertCircle, CheckCircle2, TestTube } from "lucide-react";
import {
    getCustomerDeliveryConfig,
    updateCustomerDeliveryConfig,
    testDeliveryConnection,
    type DeliveryConfig,
    type CustomerDeliveryResponse,
} from "@/lib/api/delivery";
import type { Customer } from "../types";

interface DeliveryConfigModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: Customer | null;
    onSuccess?: () => void;
}

export function DeliveryConfigModal({
    open,
    onOpenChange,
    customer,
    onSuccess,
}: DeliveryConfigModalProps) {
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isTesting, setIsTesting] = useState<string | null>(null);
    const [error, setError] = useState("");
    const [testResult, setTestResult] = useState<{ method: string; success: boolean; message: string } | null>(null);

    const [, setDeliveryData] = useState<CustomerDeliveryResponse | null>(null);

    const [enabledMethods, setEnabledMethods] = useState<string[]>(['email']);
    const [emailDestination, setEmailDestination] = useState("");
    const [emailCc, setEmailCc] = useState("");
    const [spTenantId, setSpTenantId] = useState("");
    const [spClientId, setSpClientId] = useState("");
    const [spClientSecret, setSpClientSecret] = useState("");
    const [spSiteId, setSpSiteId] = useState("");
    const [spDriveId, setSpDriveId] = useState("");
    const [spFolderPath, setSpFolderPath] = useState("");

    useEffect(() => {
        if (open && customer) {
            loadDeliveryConfig();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, customer]);

    const loadDeliveryConfig = async () => {
        if (!customer) return;

        setIsLoading(true);
        setError("");

        try {
            const data = await getCustomerDeliveryConfig(customer.customer_id);
            setDeliveryData(data);

            const config = data.delivery_config;
            setEnabledMethods(config.enabled_methods || (config.method ? [config.method] : ['email']));
            setEmailDestination(config.email?.destination || customer.report_destination || "");
            setEmailCc((config.email?.cc || []).join(", "));
            setSpTenantId(config.sharepoint?.tenant_id || "");
            setSpClientId(config.sharepoint?.client_id || "");
            setSpClientSecret(config.sharepoint?.client_secret || "");
            setSpSiteId(config.sharepoint?.site_id || "");
            setSpDriveId(config.sharepoint?.drive_id || "");
            setSpFolderPath(config.sharepoint?.folder_path || "");
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string };
            setError(error.response?.data?.message || error.message || "Failed to load delivery configuration");
        } finally {
            setIsLoading(false);
        }
    };

    const handleMethodToggle = (method: string, checked: boolean) => {
        if (checked) {
            setEnabledMethods([...enabledMethods, method]);
        } else {
            setEnabledMethods(enabledMethods.filter(m => m !== method));
        }
    };

    const handleTestConnection = async (method: string) => {
        if (!customer) return;

        setIsTesting(method);
        setTestResult(null);

        try {
            let config: Record<string, unknown> = {};

            if (method === 'sharepoint') {
                config = {
                    tenant_id: spTenantId,
                    client_id: spClientId,
                    client_secret: spClientSecret,
                    site_id: spSiteId,
                    drive_id: spDriveId || undefined,
                    folder_path: spFolderPath || undefined,
                };
            } else if (method === 'email') {
                config = {
                    destination: emailDestination,
                    cc: emailCc ? emailCc.split(',').map(e => e.trim()).filter(Boolean) : [],
                };
            }

            const result = await testDeliveryConnection(customer.customer_id, method, config);
            setTestResult({ method, ...result });
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string };
            setTestResult({
                method,
                success: false,
                message: error.response?.data?.message || error.message || "Test failed"
            });
        } finally {
            setIsTesting(null);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!customer) return;

        if (enabledMethods.length === 0) {
            setError("Please enable at least one delivery method");
            return;
        }

        setError("");
        setIsSubmitting(true);

        try {
            const config: DeliveryConfig = {
                enabled_methods: enabledMethods,
                email: enabledMethods.includes('email') ? {
                    destination: emailDestination || undefined,
                    cc: emailCc ? emailCc.split(',').map(e => e.trim()).filter(Boolean) : undefined,
                } : undefined,
                sharepoint: enabledMethods.includes('sharepoint') ? {
                    tenant_id: spTenantId || undefined,
                    client_id: spClientId || undefined,
                    client_secret: spClientSecret || undefined,
                    site_id: spSiteId || undefined,
                    drive_id: spDriveId || undefined,
                    folder_path: spFolderPath || undefined,
                } : undefined,
            };

            await updateCustomerDeliveryConfig(customer.customer_id, config);
            onOpenChange(false);
            onSuccess?.();
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } }; message?: string };
            if (error.response?.data?.errors) {
                const errorMessages = Object.entries(error.response.data.errors)
                    .map(([method, errs]) => `${method}: ${(errs as string[]).join(', ')}`)
                    .join('; ');
                setError(errorMessages);
            } else {
                setError(error.response?.data?.message || error.message || "Failed to update delivery configuration");
            }
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
            <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Send className="h-5 w-5 text-primary" />
                        <DialogTitle>Delivery Configuration</DialogTitle>
                    </div>
                    <DialogDescription>
                        Configure how reports are delivered for {customer.canonical_name || customer.name}
                    </DialogDescription>
                </DialogHeader>

                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Spinner className="h-8 w-8" />
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-3">
                            <Label className="text-sm font-medium">Enabled Delivery Methods</Label>
                            <div className="flex flex-wrap gap-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="method-email"
                                        checked={enabledMethods.includes('email')}
                                        onCheckedChange={(checked) => handleMethodToggle('email', !!checked)}
                                    />
                                    <label htmlFor="method-email" className="flex items-center gap-2 text-sm cursor-pointer">
                                        <Mail className="h-4 w-4" />
                                        Email
                                    </label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="method-sharepoint"
                                        checked={enabledMethods.includes('sharepoint')}
                                        onCheckedChange={(checked) => handleMethodToggle('sharepoint', !!checked)}
                                    />
                                    <label htmlFor="method-sharepoint" className="flex items-center gap-2 text-sm cursor-pointer">
                                        <Cloud className="h-4 w-4" />
                                        SharePoint
                                    </label>
                                </div>
                            </div>
                        </div>

                        <Accordion type="multiple" defaultValue={enabledMethods} className="w-full">
                            {enabledMethods.includes('email') && (
                                <AccordionItem value="email">
                                    <AccordionTrigger className="hover:no-underline">
                                        <div className="flex items-center gap-2">
                                            <Mail className="h-4 w-4" />
                                            Email Configuration
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent className="space-y-4 pt-4">
                                        <div>
                                            <Label htmlFor="email-destination" className="text-sm font-medium mb-2 block">
                                                Email Address *
                                            </Label>
                                            <Input
                                                id="email-destination"
                                                type="email"
                                                placeholder="reports@customer.com"
                                                value={emailDestination}
                                                onChange={(e) => setEmailDestination(e.target.value)}
                                                disabled={isSubmitting}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="email-cc" className="text-sm font-medium mb-2 block">
                                                CC Addresses (comma-separated)
                                            </Label>
                                            <Input
                                                id="email-cc"
                                                type="text"
                                                placeholder="manager@customer.com, admin@customer.com"
                                                value={emailCc}
                                                onChange={(e) => setEmailCc(e.target.value)}
                                                disabled={isSubmitting}
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleTestConnection('email')}
                                            disabled={isTesting !== null}
                                        >
                                            {isTesting === 'email' ? (
                                                <Spinner className="mr-2 h-4 w-4" />
                                            ) : (
                                                <TestTube className="mr-2 h-4 w-4" />
                                            )}
                                            Test Email Config
                                        </Button>
                                    </AccordionContent>
                                </AccordionItem>
                            )}

                            {enabledMethods.includes('sharepoint') && (
                                <AccordionItem value="sharepoint">
                                    <AccordionTrigger className="hover:no-underline">
                                        <div className="flex items-center gap-2">
                                            <Cloud className="h-4 w-4" />
                                            SharePoint Configuration
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent className="space-y-4 pt-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="sp-tenant" className="text-sm font-medium mb-2 block">
                                                    Azure Tenant ID *
                                                </Label>
                                                <Input
                                                    id="sp-tenant"
                                                    type="text"
                                                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                    value={spTenantId}
                                                    onChange={(e) => setSpTenantId(e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="sp-client" className="text-sm font-medium mb-2 block">
                                                    Application (Client) ID *
                                                </Label>
                                                <Input
                                                    id="sp-client"
                                                    type="text"
                                                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                    value={spClientId}
                                                    onChange={(e) => setSpClientId(e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="sp-secret" className="text-sm font-medium mb-2 block">
                                                Client Secret *
                                            </Label>
                                            <Input
                                                id="sp-secret"
                                                type="password"
                                                placeholder="Enter client secret"
                                                value={spClientSecret}
                                                onChange={(e) => setSpClientSecret(e.target.value)}
                                                disabled={isSubmitting}
                                            />
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Create in Azure Portal → App Registration → Certificates & secrets
                                            </p>
                                        </div>
                                        <div>
                                            <Label htmlFor="sp-site" className="text-sm font-medium mb-2 block">
                                                SharePoint Site ID *
                                            </Label>
                                            <Input
                                                id="sp-site"
                                                type="text"
                                                placeholder="contoso.sharepoint.com,guid,guid"
                                                value={spSiteId}
                                                onChange={(e) => setSpSiteId(e.target.value)}
                                                disabled={isSubmitting}
                                            />
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Get via Graph Explorer: GET /sites/{'{hostname}'}:/{'{site-path}'}
                                            </p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="sp-drive" className="text-sm font-medium mb-2 block">
                                                    Drive ID (Optional)
                                                </Label>
                                                <Input
                                                    id="sp-drive"
                                                    type="text"
                                                    placeholder="Leave empty for default"
                                                    value={spDriveId}
                                                    onChange={(e) => setSpDriveId(e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="sp-folder" className="text-sm font-medium mb-2 block">
                                                    Folder Path
                                                </Label>
                                                <Input
                                                    id="sp-folder"
                                                    type="text"
                                                    placeholder="Reports/Monthly"
                                                    value={spFolderPath}
                                                    onChange={(e) => setSpFolderPath(e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleTestConnection('sharepoint')}
                                            disabled={isTesting !== null}
                                        >
                                            {isTesting === 'sharepoint' ? (
                                                <Spinner className="mr-2 h-4 w-4" />
                                            ) : (
                                                <TestTube className="mr-2 h-4 w-4" />
                                            )}
                                            Test SharePoint Connection
                                        </Button>
                                    </AccordionContent>
                                </AccordionItem>
                            )}
                        </Accordion>

                        {testResult && (
                            <div className={`rounded-lg border p-3 flex items-start gap-2 ${testResult.success
                                ? 'border-green-500/50 bg-green-500/10'
                                : 'border-destructive/50 bg-destructive/10'
                                }`}>
                                {testResult.success ? (
                                    <CheckCircle2 className="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                ) : (
                                    <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
                                )}
                                <p className={`text-sm font-medium ${testResult.success ? 'text-green-500' : 'text-destructive'
                                    }`}>
                                    {testResult.message}
                                </p>
                            </div>
                        )}

                        {error && (
                            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
                                <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
                                <p className="text-sm text-destructive font-medium">{error}</p>
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
                                        Saving...
                                    </>
                                ) : (
                                    "Save Configuration"
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
