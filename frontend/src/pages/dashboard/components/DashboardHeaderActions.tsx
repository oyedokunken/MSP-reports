import { useNavigate } from "react-router-dom";
import { Cable, Plus } from "lucide-react";

type DashboardHeaderActionsProps = {
    onToastSuccess: (title: string, description?: string) => void;
    onToastInfo: (title: string, description?: string) => void;
};

export function DashboardHeaderActions(_props: DashboardHeaderActionsProps) {
    const navigate = useNavigate();

    return (
        <div className="flex flex-col sm:flex-row gap-2">
            <button
                className="flex items-center justify-center gap-2 rounded-md bg-primary px-3 sm:px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 whitespace-nowrap"
                onClick={() => navigate("/platform/schedules")}
            >
                <Plus className="h-4 w-4 text-white flex-shrink-0" />
                <span className="hidden xs:inline">Create Schedule</span>
                <span className="xs:hidden">Schedule</span>
            </button>
            <button
                className="flex items-center justify-center gap-2 rounded-md border border-border bg-background px-3 sm:px-4 py-2 text-sm font-medium text-foreground hover:bg-muted whitespace-nowrap"
                onClick={() => navigate("/platform/integrations")}
            >
                <Cable className="h-4 w-4 flex-shrink-0" />
                <span className="hidden xs:inline">Add Integration</span>
                <span className="xs:hidden">Integration</span>
            </button>
        </div>
    );
}
