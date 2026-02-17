<?php

declare(strict_types=1);

namespace App\Reports\SaaSSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SaaSDataGatherer
{
    /**
     * Gather security events for the customer
     * Limited to 10,000 most recent events to prevent memory issues
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherSecurityEvents(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('integration_id', 7) // SaaS Alerts Integration ID
            ->where('data_type', 'saas_security_event')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [
                $startDate->copy()->utc(),
                $endDate->copy()->utc()
            ])
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(function ($item) {
                $payload = $item->data_payload;
                return [
                    'event_id' => $payload['event_id'] ?? null,
                    'correlation_id' => $payload['correlation_id'] ?? null,
                    'type' => $payload['type'] ?? 'Unknown',
                    'joint_type' => $payload['joint_type'] ?? null,
                    'joint_desc' => $payload['joint_desc'] ?? 'No description',
                    'alert_status' => $payload['alert_status'] ?? 'unknown',
                    'timestamp' => $item->data_timestamp,
                    
                    // User information
                    'user_name' => $payload['user']['name'] ?? 'Unknown',
                    'user_email' => $payload['user_email'] ?? ($payload['user']['name'] ?? null),
                    'user_full_name' => $payload['user']['fullName'] ?? null,
                    'user_licensed' => $payload['user']['isLicensed'] ?? false,
                    'user_enabled' => $payload['user']['accountEnabled'] ?? true,
                    
                    // Location information
                    'ip' => $payload['ip'] ?? null,
                    'location_city' => $payload['location']['city'] ?? null,
                    'location_country' => $payload['location']['country'] ?? null,
                    'location_region' => $payload['location']['region'] ?? null,
                    'ip_owner' => $payload['location']['ip_owner'] ?? null,
                    
                    // Threat information
                    'is_threat' => $payload['location']['ipInfo']['threat']['is_threat'] ?? false,
                    'is_vpn' => $payload['location']['ipInfo']['threat']['is_vpn'] ?? false,
                    'is_proxy' => $payload['location']['ipInfo']['threat']['is_proxy'] ?? false,
                    'threat_score' => $payload['location']['ipInfo']['threat']['scores']['threat_score'] ?? 0,
                    'trust_score' => $payload['location']['ipInfo']['threat']['scores']['trust_score'] ?? 100,
                    
                    // Device information
                    'device_os' => $payload['device']['sourceInfo']['os'] ?? null,
                    'device_name' => $payload['device']['sourceInfo']['deviceName'] ?? null,
                    'device_mobile' => $payload['device']['sourceInfo']['isMobile'] ?? false,
                    'device_mapped' => $payload['device']['isMapped'] ?? false,
                    
                    // Application information
                    'app_name' => $payload['app_name'] ?? null,
                    'product_name' => $payload['product']['name'] ?? null,
                    'product_type' => $payload['product']['type'] ?? null,
                ];
            });
    }

    /**
     * Gather user activity statistics
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @return Collection<int, mixed>
     */
    public function gatherUserActivity(Collection $events): Collection
    {
        return $events->groupBy('user_email')
            ->map(function ($userEvents, $email) {
                $successfulLogins = $userEvents->filter(function ($event) {
                    return str_contains($event['joint_type'] ?? '', 'success');
                })->count();
                
                $failedLogins = $userEvents->filter(function ($event) {
                    return str_contains($event['joint_type'] ?? '', 'failure');
                })->count();
                
                return [
                    'email' => $email,
                    'name' => $userEvents->first()['user_full_name'] ?? $userEvents->first()['user_name'] ?? 'Unknown',
                    'total_events' => $userEvents->count(),
                    'successful_logins' => $successfulLogins,
                    'failed_logins' => $failedLogins,
                    'unique_ips' => $userEvents->pluck('ip')->unique()->count(),
                    'unique_locations' => $userEvents->pluck('location_city')->filter()->unique()->count(),
                    'threat_events' => $userEvents->where('is_threat', true)->count(),
                    'vpn_usage' => $userEvents->where('is_vpn', true)->count(),
                    'is_licensed' => $userEvents->first()['user_licensed'] ?? false,
                ];
            })
            ->sortByDesc('total_events')
            ->values();
    }

    /**
     * Gather geographic access patterns
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @return Collection<int, mixed>
     */
    public function gatherGeographicData(Collection $events): Collection
    {
        return $events->filter(function ($event) {
            return !empty($event['location_country']);
        })
        ->groupBy('location_country')
        ->map(function ($countryEvents, $country) {
            return [
                'country' => $country,
                'event_count' => $countryEvents->count(),
                'unique_users' => $countryEvents->pluck('user_email')->unique()->count(),
                'unique_ips' => $countryEvents->pluck('ip')->unique()->count(),
                'cities' => $countryEvents->pluck('location_city')->filter()->unique()->values()->toArray(),
                'threat_count' => $countryEvents->where('is_threat', true)->count(),
            ];
        })
        ->sortByDesc('event_count')
        ->values();
    }

    /**
     * Gather application usage statistics
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @return Collection<int, mixed>
     */
    public function gatherApplicationUsage(Collection $events): Collection
    {
        return $events->filter(function ($event) {
            return !empty($event['app_name']);
        })
        ->groupBy('app_name')
        ->map(function ($appEvents, $appName) {
            return [
                'app_name' => $appName,
                'event_count' => $appEvents->count(),
                'unique_users' => $appEvents->pluck('user_email')->unique()->count(),
                'product_type' => $appEvents->first()['product_name'] ?? 'Unknown',
            ];
        })
        ->sortByDesc('event_count')
        ->values();
    }

    /**
     * Gather threat and security statistics
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function gatherThreatStatistics(Collection $events): array
    {
        $totalEvents = $events->count();
        $threatEvents = $events->where('is_threat', true);
        $vpnEvents = $events->where('is_vpn', true);
        $proxyEvents = $events->where('is_proxy', true);
        $failedLogins = $events->filter(function ($event) {
            return str_contains($event['joint_type'] ?? '', 'failure');
        });
        
        $highThreatIPs = $events->filter(function ($event) {
            return ($event['threat_score'] ?? 0) > 50;
        })->pluck('ip')->unique();
        
        return [
            'total_events' => $totalEvents,
            'threat_events' => $threatEvents->count(),
            'threat_percentage' => $totalEvents > 0 ? round(($threatEvents->count() / $totalEvents) * 100, 2) : 0,
            'vpn_events' => $vpnEvents->count(),
            'proxy_events' => $proxyEvents->count(),
            'failed_logins' => $failedLogins->count(),
            'failed_login_percentage' => $totalEvents > 0 ? round(($failedLogins->count() / $totalEvents) * 100, 2) : 0,
            'high_threat_ips' => $highThreatIPs->count(),
            'unique_threat_ips' => $threatEvents->pluck('ip')->unique()->count(),
        ];
    }

    /**
     * Gather device statistics
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function gatherDeviceStatistics(Collection $events): array
    {
        $totalEvents = $events->count();
        $mobileEvents = $events->where('device_mobile', true);
        $mappedDevices = $events->where('device_mapped', true);
        
        $osBreakdown = $events->filter(function ($event) {
            return !empty($event['device_os']);
        })
        ->groupBy('device_os')
        ->map->count()
        ->sortDesc();
        
        return [
            'total_events' => $totalEvents,
            'mobile_events' => $mobileEvents->count(),
            'mobile_percentage' => $totalEvents > 0 ? round(($mobileEvents->count() / $totalEvents) * 100, 2) : 0,
            'mapped_devices' => $mappedDevices->count(),
            'mapped_percentage' => $totalEvents > 0 ? round(($mappedDevices->count() / $totalEvents) * 100, 2) : 0,
            'os_breakdown' => $osBreakdown->toArray(),
        ];
    }

    /**
     * Gather daily trends for security events
     * 
     * @param Collection<int, array<string, mixed>> $events
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array{labels: array<int, string>, total: array<int, int>, threats: array<int, int>, failures: array<int, int>}
     */
    public function gatherDailyTrends(Collection $events, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [
            'labels' => [],
            'total' => [],
            'threats' => [],
            'failures' => [],
        ];
        $current = $startDate->copy()->startOfDay();
        $target = $endDate->copy()->endOfDay();

        while ($current <= $target) {
            $dateStr = $current->format('Y-m-d');
            $dayEvents = $events->filter(function ($event) use ($dateStr) {
                return Carbon::parse($event['timestamp'])->format('Y-m-d') === $dateStr;
            });

            $trends['labels'][] = $current->format('M d');
            $trends['total'][] = $dayEvents->count();
            $trends['threats'][] = $dayEvents->where('is_threat', true)->count();
            $trends['failures'][] = $dayEvents->filter(function ($event) {
                return str_contains($event['joint_type'] ?? '', 'failure');
            })->count();

            $current->addDay();
        }

        return $trends;
    }
}
