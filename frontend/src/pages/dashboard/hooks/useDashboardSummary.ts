import { useQuery } from '@tanstack/react-query';
import { dashboardApi } from '@/services/api/dashboard';

export const dashboardKeys = {
    all: ['dashboard'] as const,
    summary: () => [...dashboardKeys.all, 'summary'] as const,
};

/**
 * React Query hook for fetching dashboard summary data
 * Replaces multiple individual API calls with a single optimized endpoint
 * 
 * Features:
 * - 30s stale time for efficient caching
 * - 60s background refetch to keep data fresh
 * - Refetch on window focus for better UX
 * - Automatic retry on failure
 */
export const useDashboardSummaryQuery = (options?: {
    enabled?: boolean;
    refetchInterval?: number | false;
}) => {
    return useQuery({
        queryKey: dashboardKeys.summary(),
        queryFn: dashboardApi.getSummary,
        staleTime: 30000, // 30 seconds - data is considered fresh
        refetchInterval: options?.refetchInterval ?? 60000, // Refetch every 60 seconds
        refetchOnWindowFocus: true,
        retry: 2,
        enabled: options?.enabled ?? true,
    });
};
