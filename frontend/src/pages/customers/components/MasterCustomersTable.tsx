import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Settings, MoreVertical, Edit, FileText, Cable, Send, CheckCircle, XCircle, GitMerge, GitBranch } from "lucide-react";
import { useNavigate } from "react-router-dom";
import type { MappedCustomer } from "../types";

interface MasterCustomersTableProps {
    customers: MappedCustomer[];
    isLoading: boolean;
    canConfigureReports: boolean;
    canEditCustomers: boolean;
    onCustomerAction: (customer: MappedCustomer, action: any) => void;
}

export function MasterCustomersTable({
    customers,
    isLoading,
    canConfigureReports,
    canEditCustomers,
    onCustomerAction,
}: MasterCustomersTableProps) {
    const navigate = useNavigate();

    return (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th className="text-left p-4 font-semibold text-sm">Customer Name</th>
                            <th className="text-left p-4 font-semibold text-sm">Status</th>
                            <th className="text-left p-4 font-semibold text-sm">Integrations</th>
                            <th className="text-left p-4 font-semibold text-sm">Reports</th>
                            <th className="text-right p-4 font-semibold text-sm">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {isLoading ? (
                            [1, 2, 3, 4, 5].map((i) => (
                                <tr key={i}>
                                    <td className="p-4"><Skeleton className="h-5 w-48" /></td>
                                    <td className="p-4"><Skeleton className="h-5 w-16" /></td>
                                    <td className="p-4"><Skeleton className="h-5 w-32" /></td>
                                    <td className="p-4"><Skeleton className="h-5 w-24" /></td>
                                    <td className="p-4 text-right"><Skeleton className="h-8 w-24 ml-auto" /></td>
                                </tr>
                            ))
                        ) : customers.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="p-12 text-center text-muted-foreground italic">
                                    No customers found
                                </td>
                            </tr>
                        ) : (
                            customers.map((customer) => (
                                <tr key={customer.customer_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td className="p-4">
                                        <div className="font-semibold text-slate-900 dark:text-slate-100">{customer.canonical_name}</div>
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={customer.status === 'active' ? 'default' : 'secondary'}
                                            className={customer.status !== 'active' ? 'bg-purple-600 text-white hover:bg-purple-700' : ''}
                                        >
                                            {customer.status === 'active' ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-1">
                                            {customer.integrations.map((int) => (
                                                <Badge key={int.integration_customer_id} variant="outline" className="text-[10px] bg-slate-50 dark:bg-slate-900/50">
                                                    {int.integration_type}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        {customer.report_schedules_count! > 0 ? (
                                            <div className="flex items-center gap-1.5 text-green-600 font-medium text-sm">
                                                <CheckCircle className="h-3.5 w-3.5" />
                                                {customer.report_schedules_count} Configured
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-1.5 text-orange-600 font-medium text-sm">
                                                <XCircle className="h-3.5 w-3.5" />
                                                None
                                            </div>
                                        )}
                                    </td>
                                    <td className="p-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                className="h-8 px-3"
                                                onClick={canConfigureReports ? () => navigate(`/platform/customers/${customer.customer_id}/configure-reports`) : undefined}
                                                disabled={!canConfigureReports}
                                            >
                                                <Settings className="h-3.5 w-3.5 mr-1" />
                                                Setup
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="outline" size="sm" className="h-8 w-8 p-0">
                                                        <MoreVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => navigate(`/platform/reports?customer=${customer.customer_id}`)}>
                                                        <FileText className="mr-2 h-4 w-4" />
                                                        View History
                                                    </DropdownMenuItem>
                                                    {canEditCustomers && (
                                                        <DropdownMenuItem onClick={() => onCustomerAction(customer, 'edit')}>
                                                            <Edit className="mr-2 h-4 w-4" />
                                                            Edit Customer
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuItem onClick={() => onCustomerAction(customer, 'integrations')}>
                                                        <Cable className="mr-2 h-4 w-4" />
                                                        View Integrations
                                                    </DropdownMenuItem>
                                                    {canConfigureReports && (
                                                        <>
                                                            <DropdownMenuItem onClick={() => onCustomerAction(customer, 'applyTemplate')}>
                                                                <FileText className="mr-2 h-4 w-4" />
                                                                Apply Template
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => onCustomerAction(customer, 'delivery')}>
                                                                <Send className="mr-2 h-4 w-4" />
                                                                Delivery Settings
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                    {canEditCustomers && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => onCustomerAction(customer, 'merge')}>
                                                                <GitMerge className="mr-2 h-4 w-4" />
                                                                Merge Customer
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => onCustomerAction(customer, 'split')}>
                                                                <GitBranch className="mr-2 h-4 w-4" />
                                                                Split Customer
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
