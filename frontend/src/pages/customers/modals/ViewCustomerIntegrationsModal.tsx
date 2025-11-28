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
import { Card, CardContent } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { 
    Cable, 
    Trash2, 
    Eye, 
    Phone, 
    Globe, 
    MapPin, 
    Building2, 
    CreditCard,
    Shield,
    Users,
    FileText,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Briefcase,
    DollarSign,
    Calendar,
    Settings,
    TrendingUp,
    Mail
} from "lucide-react";
import { api } from "@/lib/api";
import type { Customer } from "../types";
import { ViewIntegrationDataModal } from "./ViewIntegrationDataModal";

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

interface MetadataFieldProps {
    label: string;
    value: any;
    icon?: React.ReactNode;
    link?: boolean;
    badge?: boolean;
    currency?: boolean;
}

const MetadataField = ({ label, value, icon, link, badge, currency }: MetadataFieldProps) => {
    if (!value && value !== 0 && value !== false) return null;
    
    return (
        <div className="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
            {icon && <div className="text-primary mt-0.5">{icon}</div>}
            <div className="flex-1 min-w-0">
                <p className="text-xs text-muted-foreground mb-1">{label}</p>
                {badge ? (
                    <Badge variant={value ? "default" : "secondary"} className="text-xs">
                        {value ? "Yes" : "No"}
                    </Badge>
                ) : link ? (
                    <a 
                        href={`https://${value}`} 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="text-sm font-medium text-primary hover:underline truncate flex items-center gap-1"
                    >
                        {value}
                        <ExternalLink className="h-3 w-3" />
                    </a>
                ) : currency ? (
                    <p className="text-sm font-medium">${value}</p>
                ) : typeof value === 'boolean' ? (
                    <p className="text-sm font-medium">{value ? 'Yes' : 'No'}</p>
                ) : value instanceof Date || (typeof value === 'string' && value.includes('T')) ? (
                    <p className="text-sm font-medium">{new Date(value).toLocaleDateString()}</p>
                ) : (
                    <p className="text-sm font-medium truncate">{String(value)}</p>
                )}
            </div>
        </div>
    );
};

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
    const [viewDataModalOpen, setViewDataModalOpen] = useState(false);
    const [selectedIntegration, setSelectedIntegration] = useState<IntegrationMapping | null>(null);
    const [expandedSections, setExpandedSections] = useState<{[key: string]: boolean}>({});
    const [stats, setStats] = useState<{[key: number]: any}>({});
    const [loadingStats, setLoadingStats] = useState<{[key: number]: boolean}>({});

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

    const handleViewData = (integration: IntegrationMapping) => {
        setSelectedIntegration(integration);
        setViewDataModalOpen(true);
    };

    const fetchStats = async (integrationCustomerId: number) => {
        if (!customer || stats[integrationCustomerId] || loadingStats[integrationCustomerId]) return;

        setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }));

        try {
            const response = await api.get(
                `/customers/${customer.customer_id}/integrations/${integrationCustomerId}/stats`
            );
            const data = (response as any).data || response;
            setStats(prev => ({ ...prev, [integrationCustomerId]: data }));
        } catch (err: any) {
            console.error('Failed to load stats:', err);
        } finally {
            setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }));
        }
    };

    const toggleSection = (integrationId: number, section: string) => {
        const key = `${integrationId}-${section}`;
        const isExpanding = !expandedSections[key];
        
        setExpandedSections(prev => ({
            ...prev,
            [key]: isExpanding
        }));

        // Fetch stats when expanding the stats section
        if (isExpanding && section === 'stats') {
            fetchStats(integrationId);
        }
    };

    const isSectionExpanded = (integrationId: number, section: string) => {
        return expandedSections[`${integrationId}-${section}`] || false;
    };

    if (!customer) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-4xl max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Cable className="h-5 w-5 text-primary" />
                        <DialogTitle>Customer Integrations - Complete Details</DialogTitle>
                    </div>
                    <DialogDescription>
                        All integration data for {customer.name}
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
                        <div className="space-y-4">
                            {integrations.map((integration) => (
                                <Card key={integration.integration_customer_id} className="overflow-hidden">
                                    <CardContent className="p-0">
                                        {/* Header */}
                                        <div className="bg-gradient-to-r from-primary/5 to-primary/10 p-4 border-b">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-2">
                                                        <Cable className="h-5 w-5 text-primary" />
                                                        <h3 className="font-semibold text-lg">
                                                            {integration.integration_name}
                                                        </h3>
                                                        <Badge variant="outline" className="capitalize">
                                                            {integration.integration_type}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                        <span>{integration.external_name}</span>
                                                        <span>ID: {integration.external_id}</span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleViewData(integration)}
                                                        className="text-primary hover:text-primary hover:bg-primary/10"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
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
                                        </div>

                                        {/* Connected Data Stats Section */}
                                        <div className="p-4 border-b">
                                            <button
                                                onClick={() => toggleSection(integration.integration_customer_id, 'stats')}
                                                className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors rounded-lg"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <TrendingUp className="h-4 w-4 text-primary" />
                                                    <span className="font-semibold">Connected Data</span>
                                                </div>
                                                {isSectionExpanded(integration.integration_customer_id, 'stats') ? (
                                                    <ChevronUp className="h-4 w-4" />
                                                ) : (
                                                    <ChevronDown className="h-4 w-4" />
                                                )}
                                            </button>
                                            {isSectionExpanded(integration.integration_customer_id, 'stats') && (
                                                <div className="mt-3">
                                                    {loadingStats[integration.integration_customer_id] ? (
                                                        <div className="flex items-center justify-center py-8">
                                                            <Spinner className="h-6 w-6" />
                                                        </div>
                                                    ) : stats[integration.integration_customer_id] ? (
                                                        <div className="grid grid-cols-3 gap-4">
                                                            {/* Tickets */}
                                                            <div className="p-4 rounded-lg bg-muted/50 border">
                                                                <div className="flex items-center gap-2 mb-3">
                                                                    <FileText className="h-5 w-5 text-primary" />
                                                                    <h4 className="font-semibold">Tickets</h4>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Total</span>
                                                                        <span className="text-lg font-bold">
                                                                            {stats[integration.integration_customer_id].tickets?.total || 0}
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Open</span>
                                                                        <span className="text-sm font-medium">
                                                                            {stats[integration.integration_customer_id].tickets?.open || 0}
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Completed</span>
                                                                        <span className="text-sm font-medium">
                                                                            {stats[integration.integration_customer_id].tickets?.completed || 0}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Time Entries */}
                                                            <div className="p-4 rounded-lg bg-muted/50 border">
                                                                <div className="flex items-center gap-2 mb-3">
                                                                    <Calendar className="h-5 w-5 text-primary" />
                                                                    <h4 className="font-semibold">Time Entries</h4>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Total</span>
                                                                        <span className="text-lg font-bold">
                                                                            {stats[integration.integration_customer_id].time_entries?.total || 0}
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Total Hours</span>
                                                                        <span className="text-sm font-medium">
                                                                            {(stats[integration.integration_customer_id].time_entries?.total_hours || 0).toFixed(2)}h
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Billable</span>
                                                                        <span className="text-sm font-medium">
                                                                            {(stats[integration.integration_customer_id].time_entries?.billable_hours || 0).toFixed(2)}h
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Contracts */}
                                                            <div className="p-4 rounded-lg bg-muted/50 border">
                                                                <div className="flex items-center gap-2 mb-3">
                                                                    <Briefcase className="h-5 w-5 text-primary" />
                                                                    <h4 className="font-semibold">Contracts</h4>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Total</span>
                                                                        <span className="text-lg font-bold">
                                                                            {stats[integration.integration_customer_id].contracts?.total || 0}
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex justify-between items-center">
                                                                        <span className="text-sm text-muted-foreground">Active</span>
                                                                        <span className="text-sm font-medium">
                                                                            {stats[integration.integration_customer_id].contracts?.active || 0}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground text-center py-4">
                                                            No data available
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Metadata Sections */}
                                        {integration.metadata && Object.keys(integration.metadata).length > 0 && (
                                            <div className="p-4 space-y-3">
                                                {/* Technicians Section */}
                                                {stats[integration.integration_customer_id]?.tickets?.top_technicians?.length > 0 && (
                                                    <div className="border rounded-lg overflow-hidden">
                                                        <button
                                                            onClick={() => toggleSection(integration.integration_customer_id, 'technicians')}
                                                            className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Users className="h-4 w-4 text-primary" />
                                                                <span className="font-semibold">Assigned Technicians</span>
                                                                <Badge variant="secondary" className="ml-2">
                                                                    {stats[integration.integration_customer_id].tickets.top_technicians.length}
                                                                </Badge>
                                                            </div>
                                                            {isSectionExpanded(integration.integration_customer_id, 'technicians') ? (
                                                                <ChevronUp className="h-4 w-4" />
                                                            ) : (
                                                                <ChevronDown className="h-4 w-4" />
                                                            )}
                                                        </button>
                                                        {isSectionExpanded(integration.integration_customer_id, 'technicians') && (
                                                            <div className="p-3 grid grid-cols-2 gap-3">
                                                                {stats[integration.integration_customer_id].tickets.top_technicians.map((tech: any, idx: number) => (
                                                                    <div key={idx} className="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
                                                                        <Users className="h-4 w-4 text-primary mt-0.5" />
                                                                        <div className="flex-1 min-w-0">
                                                                            <p className="text-sm font-medium truncate">{tech.name}</p>
                                                                            <p className="text-xs text-muted-foreground">
                                                                                {tech.ticket_count} {tech.ticket_count === 1 ? 'ticket' : 'tickets'}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Contact Information Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'contact')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Phone className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Contact Information</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'contact') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'contact') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Phone" value={integration.metadata.phone} icon={<Phone className="h-4 w-4" />} />
                                                            <MetadataField label="Fax" value={integration.metadata.fax} icon={<Phone className="h-4 w-4" />} />
                                                            <MetadataField label="Alt Phone 1" value={integration.metadata.alternate_phone1} icon={<Phone className="h-4 w-4" />} />
                                                            <MetadataField label="Alt Phone 2" value={integration.metadata.alternate_phone2} icon={<Phone className="h-4 w-4" />} />
                                                            <MetadataField label="Website" value={integration.metadata.website} icon={<Globe className="h-4 w-4" />} link />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Address Information Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'address')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <MapPin className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Address Information</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'address') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'address') && (
                                                        <div className="p-3 space-y-4">
                                                            <div>
                                                                <h4 className="text-sm font-semibold mb-2">Primary Address</h4>
                                                                <div className="grid grid-cols-2 gap-3">
                                                                    <MetadataField label="Address 1" value={integration.metadata.address1} />
                                                                    <MetadataField label="Address 2" value={integration.metadata.address2} />
                                                                    <MetadataField label="Additional Info" value={integration.metadata.additional_address_info} />
                                                                    <MetadataField label="City" value={integration.metadata.city} />
                                                                    <MetadataField label="State" value={integration.metadata.state} />
                                                                    <MetadataField label="Postal Code" value={integration.metadata.postal_code} />
                                                                    <MetadataField label="Country ID" value={integration.metadata.country_id} />
                                                                </div>
                                                            </div>
                                                            <Separator />
                                                            <div>
                                                                <h4 className="text-sm font-semibold mb-2">Billing Address</h4>
                                                                <div className="grid grid-cols-2 gap-3">
                                                                    <MetadataField label="Billing Address 1" value={integration.metadata.billing_address1} />
                                                                    <MetadataField label="Billing Address 2" value={integration.metadata.billing_address2} />
                                                                    <MetadataField label="Additional Info" value={integration.metadata.bill_to_additional_address_info} />
                                                                    <MetadataField label="City" value={integration.metadata.bill_to_city} />
                                                                    <MetadataField label="State" value={integration.metadata.bill_to_state} />
                                                                    <MetadataField label="ZIP Code" value={integration.metadata.bill_to_zip_code} />
                                                                    <MetadataField label="Country ID" value={integration.metadata.bill_to_country_id} />
                                                                    <MetadataField label="Attention" value={integration.metadata.bill_to_attention} />
                                                                    <MetadataField label="Address To Use" value={integration.metadata.bill_to_address_to_use} />
                                                                    <MetadataField label="Company Location ID" value={integration.metadata.bill_to_company_location_id} />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Financial Information Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'financial')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <DollarSign className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Financial Information</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'financial') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'financial') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Currency ID" value={integration.metadata.currency_id} icon={<DollarSign className="h-4 w-4" />} />
                                                            <MetadataField label="Tax Exempt" value={integration.metadata.is_tax_exempt} icon={<Shield className="h-4 w-4" />} badge />
                                                            <MetadataField label="Tax ID" value={integration.metadata.tax_id} />
                                                            <MetadataField label="Tax Region ID" value={integration.metadata.tax_region_id} />
                                                            <MetadataField label="Asset Value" value={integration.metadata.asset_value} currency />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Business Details Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'business')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Briefcase className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Business Details</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'business') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'business') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Company Number" value={integration.metadata.company_number} icon={<Building2 className="h-4 w-4" />} />
                                                            <MetadataField label="Company Type" value={integration.metadata.company_type} />
                                                            <MetadataField label="Company Category ID" value={integration.metadata.company_category_id} />
                                                            <MetadataField label="Classification" value={integration.metadata.classification} />
                                                            <MetadataField label="SIC Code" value={integration.metadata.sic_code} />
                                                            <MetadataField label="Stock Symbol" value={integration.metadata.stock_symbol} icon={<TrendingUp className="h-4 w-4" />} />
                                                            <MetadataField label="Stock Market" value={integration.metadata.stock_market} />
                                                            <MetadataField label="Market Segment ID" value={integration.metadata.market_segment_id} />
                                                            <MetadataField label="Territory ID" value={integration.metadata.territory_id} />
                                                            <MetadataField label="Competitor ID" value={integration.metadata.competitor_id} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Account Management Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'account')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Users className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Account Management</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'account') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'account') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Owner Resource ID" value={integration.metadata.owner_resource_id} icon={<Users className="h-4 w-4" />} />
                                                            <MetadataField label="Parent Company ID" value={integration.metadata.parent_company_id} />
                                                            <MetadataField label="Created By Resource ID" value={integration.metadata.created_by_resource_id} />
                                                            <MetadataField label="Impersonator Creator Resource ID" value={integration.metadata.impersonator_creator_resource_id} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Portal & Integration Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'portal')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Shield className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Portal & Integration</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'portal') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'portal') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Client Portal Active" value={integration.metadata.is_client_portal_active} badge />
                                                            <MetadataField label="Enabled for Comanaged" value={integration.metadata.is_enabled_for_comanaged} badge />
                                                            <MetadataField label="TaskFire Active" value={integration.metadata.is_task_fire_active} badge />
                                                            <MetadataField label="API Vendor ID" value={integration.metadata.api_vendor_id} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Invoice & Quote Settings Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'invoice')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Invoice & Quote Settings</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'invoice') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'invoice') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Invoice Method" value={integration.metadata.invoice_method} />
                                                            <MetadataField label="Invoice Template ID" value={integration.metadata.invoice_template_id} />
                                                            <MetadataField label="Invoice Email Message ID" value={integration.metadata.invoice_email_message_id} />
                                                            <MetadataField label="Invoice Non-Contract to Parent" value={integration.metadata.invoice_non_contract_items_to_parent} />
                                                            <MetadataField label="Quote Template ID" value={integration.metadata.quote_template_id} />
                                                            <MetadataField label="Quote Email Message ID" value={integration.metadata.quote_email_message_id} />
                                                            <MetadataField label="Purchase Order Template ID" value={integration.metadata.purchase_order_template_id} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Ratings & Feedback Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'ratings')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <TrendingUp className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Ratings & Feedback</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'ratings') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'ratings') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Survey Company Rating" value={integration.metadata.survey_company_rating} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Timestamps Section */}
                                                <div className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleSection(integration.integration_customer_id, 'timestamps')}
                                                        className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Calendar className="h-4 w-4 text-primary" />
                                                            <span className="font-semibold">Timestamps</span>
                                                        </div>
                                                        {isSectionExpanded(integration.integration_customer_id, 'timestamps') ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    {isSectionExpanded(integration.integration_customer_id, 'timestamps') && (
                                                        <div className="p-3 grid grid-cols-2 gap-3">
                                                            <MetadataField label="Create Date" value={integration.metadata.create_date} icon={<Calendar className="h-4 w-4" />} />
                                                            <MetadataField label="Last Activity Date" value={integration.metadata.last_activity_date} icon={<Calendar className="h-4 w-4" />} />
                                                            <MetadataField label="Last Tracked Modified" value={integration.metadata.last_tracked_modified_date_time} icon={<Calendar className="h-4 w-4" />} />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Custom Fields Section */}
                                                {integration.metadata.user_defined_fields && integration.metadata.user_defined_fields.length > 0 && (
                                                    <div className="border rounded-lg overflow-hidden">
                                                        <button
                                                            onClick={() => toggleSection(integration.integration_customer_id, 'custom')}
                                                            className="w-full flex items-center justify-between p-3 bg-muted/30 hover:bg-muted/50 transition-colors"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Settings className="h-4 w-4 text-primary" />
                                                                <span className="font-semibold">Custom Fields</span>
                                                            </div>
                                                            {isSectionExpanded(integration.integration_customer_id, 'custom') ? (
                                                                <ChevronUp className="h-4 w-4" />
                                                            ) : (
                                                                <ChevronDown className="h-4 w-4" />
                                                            )}
                                                        </button>
                                                        {isSectionExpanded(integration.integration_customer_id, 'custom') && (
                                                            <div className="p-3">
                                                                <pre className="text-xs bg-muted p-3 rounded overflow-auto">
                                                                    {JSON.stringify(integration.metadata.user_defined_fields, null, 2)}
                                                                </pre>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Footer */}
                                        <div className="px-4 py-3 bg-muted/30 border-t">
                                            <p className="text-xs text-muted-foreground">
                                                Mapped: {integration.mapped_at ? new Date(integration.mapped_at).toLocaleString() : 'N/A'}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
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

            <ViewIntegrationDataModal
                open={viewDataModalOpen}
                onOpenChange={setViewDataModalOpen}
                integrationId={selectedIntegration?.integration_id ?? null}
                integrationName={selectedIntegration?.integration_name ?? ""}
                integrationCustomerId={selectedIntegration?.integration_customer_id}
            />
        </Dialog>
    );
}
