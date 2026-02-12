export interface DashboardStats {
    totalCustomers: number;
    unmappedCustomers: number;
    importedCustomers: number;
    templates: {
        total: number;
        customized: number;
    };
    users: {
        total: number;
        active: number;
    };
}

export interface DashboardIntegration {
    id: number;
    name: string;
    type: string;
    status: 'active' | 'error' | 'warning' | 'importing_historical' | 'disconnected';
    lastSync: string;
    syncStatus: 'success' | 'failed' | 'pending';
    customers: number;
    isSourceOfTruth: boolean;
}

export interface DashboardSchedule {
    id: number;
    name: string;
    customer: string;
    nextRun: string;
    status: 'scheduled';
}

export interface DashboardActivity {
    id: number;
    type: 'sync' | 'alert';
    title: string;
    time: string;
    severity: 'green' | 'yellow' | 'red';
}

export interface MonthlyReportData {
    date: string;
    count: number;
}

export interface ActivityTrendData {
    date: string;
    syncs: number;
    failed_syncs: number;
    rate_limits: number;
    reports: number;
    failed_reports: number;
    logins: number;
    jobs: number;
}

export interface DashboardSummaryResponse {
    status: 'success';
    data: {
        stats: DashboardStats;
        integrations: DashboardIntegration[];
        upcomingSchedules: DashboardSchedule[];
        recentActivity: DashboardActivity[];
        charts: {
            monthlyReports: MonthlyReportData[];
            activityTrends: ActivityTrendData[];
        };
    };
}
