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
    const licenseData = useLicensesStore((state) => state.licenseData);
    const usage = useLicensesStore((state) => state.usage);
    const isLoading = useLicensesStore((state) => state.isLoading);
    const error = useLicensesStore((state) => state.error);
    const fetchLicenses = useLicensesStore((state) => state.fetchLicenses);
    const fetchUsage = useLicensesStore((state) => state.fetchUsage);

    useEffect(() => {
        fetchLicenses();
        fetchUsage();
    }, [fetchLicenses, fetchUsage]);

    return (
        <FloatingLayout>
            <div className="flex flex-col gap-6 pb-8">
                <LicensesHeader />

                <LicenseUsageCard usage={usage} />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2">
                        <LicensesGrid
                            licenses={licenseData?.active_licenses || []}
                            expiringSoon={licenseData?.expiring_soon || []}
                            licenseEnforcementEnabled={
                                licenseData?.license_enforcement_enabled || false
                            }
                            isLoading={isLoading}
                            error={error}
                        />
                    </div>
                    <div className="space-y-1">
                        <LicenseCheckCard />
                    </div>
                </div>
            </div>
        </FloatingLayout>
    );
}
