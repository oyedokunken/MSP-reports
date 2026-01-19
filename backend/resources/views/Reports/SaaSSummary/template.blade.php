<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SaaS Alerts {{ ucfirst($options['frequency'] ?? 'Monthly') }} Security Report - {{ $customer->canonical_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 10px;
            font-size: 12px;
        }
        .header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; text-align: center; }
        .header .company-name { font-size: 16px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .header .meta { font-size: 11px; color: #666; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
        .summary-card { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; }
        .summary-card .number { font-size: 28px; font-weight: 600; margin-bottom: 3px; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .subvalue { font-size: 10px; color: #666; margin-top: 3px; }
        .section { margin-bottom: 40px; page-break-inside: auto; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; page-break-after: avoid; }
        .alert-box { border-left: 3px solid; padding: 10px 12px; margin: 10px 0; font-size: 11px; }
        .alert-box.critical { border-color: #dc2626; background: #fef2f2; }
        .alert-box.high { border-color: #d97706; background: #fffbeb; }
        .alert-box.medium { border-color: #2563eb; background: #eff6ff; }
        .alert-box.low { border-color: #10b981; background: #f0fdf4; }
        .alert-box.info { border-color: #0ea5e9; background: #f0f9ff; }
        .alert-box .alert-title { font-weight: 600; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; page-break-inside: auto; }
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .page-break { page-break-after: always; }
        .summary { page-break-inside: avoid; }
        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 11px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 6px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SaaS Alerts {{ ucfirst($options['frequency'] ?? 'Monthly') }} Security Report</h1>
        <div class="company-name">{{ is_array($branding) ? ($branding['company_name'] ?? 'Territory Technology Solutions') : 'Territory Technology Solutions' }}</div>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($date_range) ? (($date_range['start'] ?? 'N/A') . ' - ' . ($date_range['end'] ?? 'N/A')) : 'N/A' }}
        </div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ number_format($executive_summary['total_events'] ?? 0) }}</div>
            <div class="label">Total Security Events</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['unique_users'] ?? 0 }}</div>
            <div class="label">Active Users</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ number_format($executive_summary['failed_logins'] ?? 0) }}</div>
            <div class="label">Failed Logins</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['threat_events'] ?? 0 }}</div>
            <div class="label">Threat Events</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['mobile_usage_percent'] ?? 0 }}%</div>
            <div class="label">Mobile Usage</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ number_format($executive_summary['vpn_usage'] ?? 0) }}</div>
            <div class="label">VPN Usage</div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Alerts & Recommendations</h2>
            @if(!empty($recommendations) && count($recommendations) > 0)
                @foreach($recommendations as $recommendation)
                    <div class="alert-box {{ $recommendation['severity'] }}">
                        <div class="alert-title">{{ $recommendation['title'] }}</div>
                        <div class="alert-description">{{ $recommendation['description'] }}</div>
                        <div class="alert-action"><strong>Action:</strong> {{ $recommendation['action'] }}</div>
                    </div>
                @endforeach
            @else
                <div class="empty">No security recommendations at this time.</div>
            @endif
    </div>

        <!-- <div class="page-break"></div> -->

    <div class="section">
        <h2 class="section-title">Top User Activity</h2>
            @if(!empty($user_activity) && count($user_activity) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Events</th>
                            <th>Successful Logins</th>
                            <th>Failed Logins</th>
                            <th>Unique IPs</th>
                            <th>Locations</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($user_activity->toArray(), 0, 15) as $user)
                            <tr>
                                <td>
                                    <strong>{{ $user['name'] }}</strong><br>
                                    <small style="color: #666;">{{ $user['email'] }}</small>
                                </td>
                                <td>{{ number_format($user['total_events']) }}</td>
                                <td>{{ number_format($user['successful_logins']) }}</td>
                                <td>
                                    @if($user['failed_logins'] > 5)
                                        <span class="badge danger">{{ $user['failed_logins'] }}</span>
                                    @elseif($user['failed_logins'] > 0)
                                        <span class="badge warning">{{ $user['failed_logins'] }}</span>
                                    @else
                                        {{ $user['failed_logins'] }}
                                    @endif
                                </td>
                                <td>{{ $user['unique_ips'] }}</td>
                                <td>{{ $user['unique_locations'] }}</td>
                                <td>
                                    @if($user['is_licensed'])
                                        <span class="badge success">Licensed</span>
                                    @else
                                        <span class="badge warning">Unlicensed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">No user activity data available.</div>
            @endif
    </div>

    <div class="section">
        <h2 class="section-title">Geographic Access Patterns</h2>
            @if(!empty($geographic_data) && count($geographic_data) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Events</th>
                            <th>Unique Users</th>
                            <th>Unique IPs</th>
                            <th>Threat Events</th>
                            <th>Cities</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($geographic_data->toArray(), 0, 10) as $location)
                            <tr>
                                <td><strong>{{ $location['country'] }}</strong></td>
                                <td>{{ number_format($location['event_count']) }}</td>
                                <td>{{ $location['unique_users'] }}</td>
                                <td>{{ $location['unique_ips'] }}</td>
                                <td>
                                    @if($location['threat_count'] > 0)
                                        <span class="badge danger">{{ $location['threat_count'] }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td><small>{{ implode(', ', array_slice($location['cities'], 0, 3)) }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">No geographic data available.</div>
            @endif
    </div>

        <!-- <div class="page-break"></div> -->

    <div class="section">
        <h2 class="section-title">Application Usage</h2>
            @if(!empty($application_usage) && count($application_usage) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Events</th>
                            <th>Unique Users</th>
                            <th>Product Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($application_usage->toArray(), 0, 15) as $app)
                            <tr>
                                <td><strong>{{ $app['app_name'] }}</strong></td>
                                <td>{{ number_format($app['event_count']) }}</td>
                                <td>{{ $app['unique_users'] }}</td>
                                <td>{{ $app['product_type'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">No application usage data available.</div>
            @endif
    </div>

    <div class="section">
        <h2 class="section-title">Security & Threat Analysis</h2>
        <div class="summary">
            <div class="summary-card">
                <div class="number">{{ $threat_statistics['threat_events'] ?? 0 }}</div>
                <div class="label">Total Threat Events</div>
                <div class="subvalue">{{ $threat_statistics['threat_percentage'] ?? 0 }}% of total</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ number_format($threat_statistics['failed_logins'] ?? 0) }}</div>
                <div class="label">Failed Logins</div>
                <div class="subvalue">{{ $threat_statistics['failed_login_percentage'] ?? 0 }}% failure rate</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ number_format($threat_statistics['vpn_events'] ?? 0) }}</div>
                <div class="label">VPN Connections</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ number_format($threat_statistics['proxy_events'] ?? 0) }}</div>
                <div class="label">Proxy Connections</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $threat_statistics['high_threat_ips'] ?? 0 }}</div>
                <div class="label">High Threat IPs</div>
                <div class="subvalue">Threat score > 50</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $device_statistics['mobile_percentage'] ?? 0 }}%</div>
                <div class="label">Mobile Access</div>
                <div class="subvalue">{{ number_format($device_statistics['mobile_events'] ?? 0) }} events</div>
            </div>
        </div>
    </div>
</body>
</html>
