import { useEffect } from "react";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { useLicensesStore } from "@/stores/useLicensesStore";
import {
    LicensesHeader,
    LicenseUsageCard,
    LicensesGrid,
    LicenseCheckCard,
} from "./components";

export default function Licenses() {
    // Zustand store - individual selectors for optimal performance
    const licenseData = useLicensesStore((state) => state.licenseData);
    const usage = useLicensesStore((state) => state.usage);
    const isLoading = useLicensesStore((state) => state.isLoading);
    const error = useLicensesStore((state) => state.error);
    const fetchLicenses = useLicensesStore((state) => state.fetchLicenses);
    const fetchUsage = useLicensesStore((state) => state.fetchUsage);

    // Fetch data on mount
    useEffect(() => {
        fetchLicenses();
        fetchUsage();
    }, [fetchLicenses, fetchUsage]);

    return (
        <FloatingLayout>
            <div className="space-y-8">
                <LicensesHeader />

                <LicenseUsageCard usage={usage} />

                <LicenseCheckCard />

                <LicensesGrid
                    licenses={licenseData?.active_licenses || []}
                    expiringSoon={licenseData?.expiring_soon || []}
                    userLicenseStatus={licenseData?.user_license_status || null}
                    licenseEnforcementEnabled={
                        licenseData?.license_enforcement_enabled || false
                    }
                    isLoading={isLoading}
                    error={error}
                />
            </div>
        </FloatingLayout>
    );
}
