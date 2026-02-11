import { api } from '@/lib/api';
import type { DashboardSummaryResponse } from '@/types/api/dashboard';

export const dashboardApi = {
    /**
     * Get aggregated dashboard summary data
     * This single endpoint replaces multiple API calls for better performance
     */
    getSummary: async (): Promise<DashboardSummaryResponse> => {
        const response = await api.get<DashboardSummaryResponse>('/dashboard/summary');
        return response;
    },
};
