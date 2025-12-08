import { useState } from "react";
import { Plus } from "lucide-react";
import { SelectCustomerModal } from "@/pages/schedules/modals/SelectCustomerModal";

export function DashboardHeaderActions() {
    const [selectCustomerOpen, setSelectCustomerOpen] = useState(false);

    return (
        <>
            <div className="flex flex-col sm:flex-row gap-2">
                <button
                    className="flex items-center justify-center gap-2 rounded-md bg-primary px-3 sm:px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 whitespace-nowrap"
                    onClick={() => setSelectCustomerOpen(true)}
                >
                    <Plus className="h-4 w-4 flex-shrink-0" />
                    <span className="hidden xs:inline">Create Schedule</span>
                    <span className="xs:hidden">Schedule</span>
                </button>
            </div>

            <SelectCustomerModal
                open={selectCustomerOpen}
                onOpenChange={setSelectCustomerOpen}
            />
        </>
    );
}
