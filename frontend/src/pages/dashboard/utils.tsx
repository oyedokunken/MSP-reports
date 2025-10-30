import { CheckCircle2, AlertCircle, XCircle } from "lucide-react";
import React from "react";

export const getIntegrationStatusBadge = (
    status: string,
    syncStatus: string
): string => {
    if (status === "error" || syncStatus === "failed")
        return "bg-red-50 text-red-700";
    if (status === "active") return "bg-green-50 text-green-700";
    if (status === "disconnected") return "bg-gray-50 text-gray-700";
    if (status === "importing_historical") return "bg-blue-50 text-blue-700";
    return "bg-gray-50 text-gray-700";
};

export const getIntegrationStatusText = (
    status: string,
    syncStatus: string
): string => {
    if (status === "error" || syncStatus === "failed") return "Error";
    if (status === "active") return "Active";
    if (status === "disconnected") return "Disconnected";
    if (status === "importing_historical") return "Importing";
    return "Unknown";
};

export const getIntegrationStatusIcon = (
    status: string,
    syncStatus: string
): React.ReactNode => {
    if (status === "error" || syncStatus === "failed")
        return <XCircle className="h-4 w-4 text-red-500" />;
    if (status === "active")
        return <CheckCircle2 className="h-4 w-4 text-green-500" />;
    return <AlertCircle className="h-4 w-4 text-gray-500" />;
};
