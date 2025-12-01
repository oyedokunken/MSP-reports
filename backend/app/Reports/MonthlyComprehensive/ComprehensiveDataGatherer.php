<?php

namespace App\Reports\MonthlyComprehensive;

use App\Reports\Contracts\BaseDataGatherer;
use App\Reports\UptimeReport\UptimeDataGatherer;
use App\Reports\TicketSummary\TicketDataGatherer;
use App\Reports\BackupStatus\BackupDataGatherer;
use App\Reports\SecuritySummary\SecurityDataGatherer;

class ComprehensiveDataGatherer extends BaseDataGatherer
{
    /**
     * Get Uptime section data
     */
    public function getUptimeSection(): array
    {
        try {
            $uptimeGatherer = new UptimeDataGatherer($this->customer, $this->startDate, $this->endDate);
            $metrics = $uptimeGatherer->getUptimeMetrics();
            
            if (empty($metrics) || ($metrics['average_uptime'] ?? 0) == 0) {
                return [];
            }
            
            return [
                'title' => 'System Uptime',
                'metrics' => $metrics,
                'devices' => $uptimeGatherer->getDeviceBreakdown(),
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get uptime section: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Tickets section data
     */
    public function getTicketSection(): array
    {
        try {
            $ticketGatherer = new TicketDataGatherer($this->customer);
            $period = [
                'start' => $this->startDate,
                'end' => $this->endDate,
            ];
            $data = $ticketGatherer->getAggregatedData($period);
            
            if (empty($data) || ($data['tickets_opened'] ?? 0) == 0) {
                return [];
            }
            
            return [
                'title' => 'Support Tickets',
                'summary' => $data,
                'by_priority' => $data['priority_breakdown'] ?? [],
                'by_status' => $data['status_breakdown'] ?? [],
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get ticket section: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Backups section data
     */
    public function getBackupSection(): array
    {
        try {
            $backupGatherer = new BackupDataGatherer($this->customer);
            $period = [
                'start' => $this->startDate,
                'end' => $this->endDate,
            ];
            $data = $backupGatherer->getAggregatedData($period);
            
            if (empty($data) || ($data['total_backups'] ?? 0) == 0) {
                return [];
            }
            
            return [
                'title' => 'Backup Status',
                'summary' => $data,
                'devices' => $data['device_breakdown'] ?? [],
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get backup section: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Security section data
     */
    public function getSecuritySection(): array
    {
        try {
            $securityGatherer = new SecurityDataGatherer($this->customer);
            $period = [
                'start' => $this->startDate,
                'end' => $this->endDate,
            ];
            $data = $securityGatherer->getAggregatedData($period);
            
            if (empty($data) || ($data['total_alerts'] ?? 0) == 0) {
                return [];
            }
            
            return [
                'title' => 'Security Alerts',
                'summary' => $data,
                'by_severity' => $data['severity_breakdown'] ?? [],
                'by_type' => $data['type_breakdown'] ?? [],
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get security section: ' . $e->getMessage());
            return [];
        }
    }
}
