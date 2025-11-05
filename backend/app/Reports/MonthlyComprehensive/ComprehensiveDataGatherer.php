<?php

namespace App\Reports\MonthlyComprehensive;

use App\Models\Customer;
use App\Reports\UptimeReport\UptimeDataGatherer;
use App\Reports\TicketSummary\TicketDataGatherer;
use App\Reports\BackupStatus\BackupDataGatherer;
use App\Reports\SecuritySummary\SecurityDataGatherer;

class ComprehensiveDataGatherer
{
    protected Customer $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function getAggregatedData(array $period): array
    {
        // Gather data from all report types
        $uptimeGatherer = new UptimeDataGatherer($this->customer);
        $ticketGatherer = new TicketDataGatherer($this->customer);
        $backupGatherer = new BackupDataGatherer($this->customer);
        $securityGatherer = new SecurityDataGatherer($this->customer);

        $uptimeData = $uptimeGatherer->getAggregatedData($period);
        $ticketData = $ticketGatherer->getAggregatedData($period);
        $backupData = $backupGatherer->getAggregatedData($period);
        $securityData = $securityGatherer->getAggregatedData($period);

        // Calculate overall health score (0-100)
        $healthScore = $this->calculateHealthScore($uptimeData, $ticketData, $backupData, $securityData);

        return [
            'health_score' => $healthScore,
            'uptime' => $uptimeData,
            'tickets' => $ticketData,
            'backups' => $backupData,
            'security' => $securityData,
            'summary' => [
                'total_devices' => $uptimeData['device_count'] ?? 0,
                'avg_uptime' => $uptimeData['avg_uptime'] ?? '0%',
                'tickets_resolved' => $ticketData['tickets_closed'] ?? 0,
                'backup_success_rate' => $backupData['success_rate'] ?? 0,
                'security_incidents' => $securityData['critical_alerts'] ?? 0,
            ],
        ];
    }

    private function calculateHealthScore(array $uptime, array $tickets, array $backups, array $security): int
    {
        $scores = [];

        // Uptime score (30% weight)
        $uptimePercent = floatval(str_replace('%', '', $uptime['avg_uptime'] ?? '0'));
        $scores[] = ($uptimePercent / 100) * 30;

        // Backup score (25% weight)
        $backupRate = $backups['success_rate'] ?? 0;
        $scores[] = ($backupRate / 100) * 25;

        // Ticket score (25% weight) - based on closure rate
        $ticketsOpened = $tickets['tickets_opened'] ?? 1;
        $ticketsClosed = $tickets['tickets_closed'] ?? 0;
        $ticketRate = $ticketsOpened > 0 ? ($ticketsClosed / $ticketsOpened) * 100 : 100;
        $scores[] = ($ticketRate / 100) * 25;

        // Security score (20% weight) - inverse of critical alerts
        $criticalAlerts = $security['critical_alerts'] ?? 0;
        $securityScore = max(0, 100 - ($criticalAlerts * 10)); // Each critical alert reduces score by 10
        $scores[] = ($securityScore / 100) * 20;

        return (int) round(array_sum($scores));
    }
}
