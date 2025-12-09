<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SaaS Alerts Monthly Security Report - {{ $customer->canonical_name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1a56db' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            padding: 30px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24pt;
            font-weight: 600;
        }
        .header .customer-name {
            font-size: 16pt;
            opacity: 0.95;
            margin: 5px 0;
        }
        .header .period {
            font-size: 12pt;
            opacity: 0.9;
        }
        .content {
            padding: 0 30px 30px 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            background: #f8f9fa;
            border-left: 4px solid {{ $branding['primary_color'] ?? '#1a56db' }};
            padding: 15px;
            border-radius: 4px;
        }
        .summary-card .label {
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .summary-card .value {
            font-size: 24pt;
            font-weight: 700;
            color: {{ $branding['primary_color'] ?? '#1a56db' }};
            line-height: 1;
        }
        .summary-card .subtext {
            font-size: 8pt;
            color: #888;
            margin-top: 5px;
        }
        .section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 16pt;
            font-weight: 600;
            color: {{ $branding['primary_color'] ?? '#1a56db' }};
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .alert-box {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .alert-critical {
            background: #fef2f2;
            border-color: #dc2626;
        }
        .alert-high {
            background: #fef3c7;
            border-color: #f59e0b;
        }
        .alert-medium {
            background: #dbeafe;
            border-color: #3b82f6;
        }
        .alert-low {
            background: #f0fdf4;
            border-color: #10b981;
        }
        .alert-info {
            background: #f0f9ff;
            border-color: #0ea5e9;
        }
        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 11pt;
        }
        .alert-description {
            margin: 5px 0;
            font-size: 10pt;
        }
        .alert-action {
            margin-top: 8px;
            font-size: 9pt;
            font-style: italic;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        table thead {
            background: {{ $branding['primary_color'] ?? '#1a56db' }};
            color: white;
        }
        table th {
            padding: 10px;
            text-align: left;
            font-weight: 600;
            font-size: 9pt;
        }
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        .footer {
            margin-top: 40px;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 3px solid {{ $branding['primary_color'] ?? '#1a56db' }};
            font-size: 9pt;
            color: #666;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .page-break {
            page-break-after: always;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 600;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SaaS Alerts Monthly Security Report</h1>
        <div class="customer-name">{{ $customer->canonical_name }}</div>
        <div class="period">{{ $date_range['start'] ?? 'N/A' }} - {{ $date_range['end'] ?? 'N/A' }}</div>
    </div>

    <div class="content">
        <!-- Executive Summary -->
        <div class="section">
            <div class="section-title">Executive Summary</div>
            <p>This report provides a comprehensive security analysis of user activity, threat detection, and access patterns for {{ $customer->canonical_name }} during the reporting period.</p>
            
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="label">Total Security Events</div>
                    <div class="value">{{ number_format($executive_summary['total_events'] ?? 0) }}</div>
                    <div class="subtext">Monitored activities</div>
                </div>
                <div class="summary-card">
                    <div class="label">Active Users</div>
                    <div class="value">{{ $executive_summary['unique_users'] ?? 0 }}</div>
                    <div class="subtext">Unique user accounts</div>
                </div>
                <div class="summary-card">
                    <div class="label">Failed Logins</div>
                    <div class="value">{{ number_format($executive_summary['failed_logins'] ?? 0) }}</div>
                    <div class="subtext">Authentication failures</div>
                </div>
                <div class="summary-card">
                    <div class="label">Threat Events</div>
                    <div class="value">{{ $executive_summary['threat_events'] ?? 0 }}</div>
                    <div class="subtext">Security threats detected</div>
                </div>
                <div class="summary-card">
                    <div class="label">Mobile Usage</div>
                    <div class="value">{{ $executive_summary['mobile_usage_percent'] ?? 0 }}%</div>
                    <div class="subtext">Mobile device access</div>
                </div>
                <div class="summary-card">
                    <div class="label">VPN Usage</div>
                    <div class="value">{{ number_format($executive_summary['vpn_usage'] ?? 0) }}</div>
                    <div class="subtext">VPN connections</div>
                </div>
            </div>
        </div>

        <!-- Alerts & Recommendations -->
        <div class="section">
            <div class="section-title">Alerts & Recommendations</div>
            @if(!empty($recommendations) && count($recommendations) > 0)
                @foreach($recommendations as $recommendation)
                    <div class="alert-box alert-{{ $recommendation['severity'] }}">
                        <div class="alert-title">{{ $recommendation['title'] }}</div>
                        <div class="alert-description">{{ $recommendation['description'] }}</div>
                        <div class="alert-action"><strong>Action:</strong> {{ $recommendation['action'] }}</div>
                    </div>
                @endforeach
            @else
                <p>No security recommendations at this time.</p>
            @endif
        </div>

        <div class="page-break"></div>

        <!-- User Activity -->
        <div class="section">
            <div class="section-title">Top User Activity</div>
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
                                        <span class="badge badge-danger">{{ $user['failed_logins'] }}</span>
                                    @elseif($user['failed_logins'] > 0)
                                        <span class="badge badge-warning">{{ $user['failed_logins'] }}</span>
                                    @else
                                        {{ $user['failed_logins'] }}
                                    @endif
                                </td>
                                <td>{{ $user['unique_ips'] }}</td>
                                <td>{{ $user['unique_locations'] }}</td>
                                <td>
                                    @if($user['is_licensed'])
                                        <span class="badge badge-success">Licensed</span>
                                    @else
                                        <span class="badge badge-warning">Unlicensed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>No user activity data available.</p>
            @endif
        </div>

        <!-- Geographic Access -->
        <div class="section">
            <div class="section-title">Geographic Access Patterns</div>
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
                                        <span class="badge badge-danger">{{ $location['threat_count'] }}</span>
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
                <p>No geographic data available.</p>
            @endif
        </div>

        <div class="page-break"></div>

        <!-- Application Usage -->
        <div class="section">
            <div class="section-title">Application Usage</div>
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
                <p>No application usage data available.</p>
            @endif
        </div>

        <!-- Threat Statistics -->
        <div class="section">
            <div class="section-title">Security & Threat Analysis</div>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="label">Total Threat Events</div>
                    <div class="value">{{ $threat_statistics['threat_events'] ?? 0 }}</div>
                    <div class="subtext">{{ $threat_statistics['threat_percentage'] ?? 0 }}% of total events</div>
                </div>
                <div class="summary-card">
                    <div class="label">Failed Logins</div>
                    <div class="value">{{ number_format($threat_statistics['failed_logins'] ?? 0) }}</div>
                    <div class="subtext">{{ $threat_statistics['failed_login_percentage'] ?? 0 }}% failure rate</div>
                </div>
                <div class="summary-card">
                    <div class="label">VPN Connections</div>
                    <div class="value">{{ number_format($threat_statistics['vpn_events'] ?? 0) }}</div>
                    <div class="subtext">VPN usage detected</div>
                </div>
                <div class="summary-card">
                    <div class="label">Proxy Connections</div>
                    <div class="value">{{ number_format($threat_statistics['proxy_events'] ?? 0) }}</div>
                    <div class="subtext">Proxy usage detected</div>
                </div>
                <div class="summary-card">
                    <div class="label">High Threat IPs</div>
                    <div class="value">{{ $threat_statistics['high_threat_ips'] ?? 0 }}</div>
                    <div class="subtext">IPs with threat score > 50</div>
                </div>
                <div class="summary-card">
                    <div class="label">Mobile Access</div>
                    <div class="value">{{ $device_statistics['mobile_percentage'] ?? 0 }}%</div>
                    <div class="subtext">{{ number_format($device_statistics['mobile_events'] ?? 0) }} mobile events</div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="footer-grid">
            <div>
                <strong>{{ $branding['company_name'] ?? 'Territory Technology Solutions' }}</strong><br>
                {{ $branding['footer_text'] ?? '© 2025 Territory Technology Solutions' }}
            </div>
            <div style="text-align: right;">
                <strong>Contact</strong><br>
                Phone: {{ $branding['support_phone'] ?? '1300 XXX XXX' }}<br>
                Email: {{ $branding['support_email'] ?? 'support@tts.com.au' }}
            </div>
        </div>
        <div style="margin-top: 15px; text-align: center; font-size: 8pt; color: #999;">
            Report generated on {{ now()->format('F j, Y \a\t g:i A') }}
        </div>
    </div>
</body>
</html>
