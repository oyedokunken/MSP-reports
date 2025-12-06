<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datto RMM Monthly Summary - {{ $customer->canonical_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #1f2937;
            background: white;
            font-size: 11pt;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            padding: 20px 30px 40px 30px;
        }
        
        /* Header - Minimal */
        .header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .header-content {
            display: block;
        }
        
        .logo {
            max-height: 40px;
            max-width: 150px;
            margin-bottom: 10px;
        }
        
        .report-title h1 {
            font-size: 18pt;
            font-weight: 600;
            color: #111827;
            margin-bottom: 3px;
        }
        
        .report-title .subtitle {
            color: #6b7280;
            font-size: 10pt;
        }
        
        /* Executive Summary - Minimal */
        .executive-summary {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .executive-summary h2 {
            font-size: 12pt;
            font-weight: 600;
            margin-bottom: 10px;
            color: #111827;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .stat-box {
            text-align: center;
            padding: 8px;
        }
        
        .stat-label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        
        .stat-value {
            font-size: 16pt;
            font-weight: 600;
            color: #111827;
        }
        
        /* Section - Minimal */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        
        .section-header h2 {
            font-size: 12pt;
            font-weight: 600;
            color: #111827;
        }
        
        h3 {
            font-size: 10pt;
            font-weight: 600;
            color: #111827;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        
        /* Table - Minimal */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }
        
        th {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
        }
        
        tr:nth-child(even) {
            background: #f9fafb;
        }
        
        /* Badge - Minimal */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 500;
            border: 1px solid;
        }
        
        .badge.success {
            background: white;
            color: #059669;
            border-color: #059669;
        }
        
        .badge.warning {
            background: white;
            color: #d97706;
            border-color: #d97706;
        }
        
        .badge.danger {
            background: white;
            color: #dc2626;
            border-color: #dc2626;
        }
        
        .badge.info {
            background: white;
            color: #1e40af;
            border-color: #1e40af;
        }
        
        /* Progress Bar - Minimal */
        .progress-bar {
            background: #e5e7eb;
            height: 6px;
            margin-top: 5px;
        }
        
        .progress-fill {
            background: #111827;
            height: 100%;
        }
        
        /* Alert Box - Minimal */
        .alert-box {
            border-left: 3px solid;
            padding: 8px 10px;
            margin: 8px 0;
            font-size: 9pt;
        }
        
        .alert-box.critical {
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .alert-box.high {
            border-color: #d97706;
            background: #fffbeb;
        }
        
        .alert-box.medium {
            border-color: #2563eb;
            background: #eff6ff;
        }
        
        .alert-box .alert-title {
            font-weight: 600;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div>
                    @if(isset($branding['logo_url']))
                        <img src="{{ $branding['logo_url'] }}" alt="Company Logo" class="logo">
                    @endif
                </div>
                <div class="report-title">
                    <h1>Datto RMM Monthly Summary</h1>
                    <div class="subtitle">{{ $customer->canonical_name }}</div>
                    <div class="subtitle">{{ $period['formatted'] }}</div>
                </div>
            </div>
        </div>

        <!-- Executive Summary -->
        <div class="executive-summary">
            <h2>Executive Summary</h2>
            <p style="margin-bottom: 10px; font-size: 9pt; color: #4b5563;">
                This report provides a comprehensive overview of your IT infrastructure health, security posture, and compliance status for {{ $period['formatted'] }}.
            </p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Devices</div>
                    <div class="stat-value">{{ $executive_summary['devices']['total'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Uptime</div>
                    <div class="stat-value">{{ $executive_summary['devices']['uptime_percentage'] }}%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Alerts</div>
                    <div class="stat-value">{{ $executive_summary['alerts']['total'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Patch Compliance</div>
                    <div class="stat-value">{{ $executive_summary['compliance']['patch_compliance'] }}%</div>
                </div>
            </div>

            @if($executive_summary['alerts']['critical'] > 0)
            <p style="margin-top: 10px; color: #dc2626; font-weight: 500; font-size: 9pt;">
                ⚠ {{ $executive_summary['alerts']['critical'] }} critical alert(s) require immediate attention.
            </p>
            @endif

            @if($executive_summary['compliance']['devices_needing_reboot'] > 0)
            <p style="margin-top: 5px; color: #d97706; font-weight: 500; font-size: 9pt;">
                ⚠ {{ $executive_summary['compliance']['devices_needing_reboot'] }} device(s) require reboot to complete updates.
            </p>
            @endif
        </div>

        <!-- Alerts & Recommendations -->
        @if(isset($recommendations) && count($recommendations) > 0)
        <div class="section">
            <div class="section-header">
                <h2>Alerts & Recommendations</h2>
            </div>
            
            @foreach($recommendations as $rec)
            <div class="alert-box {{ $rec['severity'] }}">
                <div class="alert-title">{{ $rec['title'] }}</div>
                <div style="color: #4b5563; margin-bottom: 3px;">{{ $rec['description'] }}</div>
                <div style="color: #6b7280; font-size: 8pt;"><strong>Action:</strong> {{ $rec['action'] }}</div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Device Summary -->
        <div class="section">
            <div class="section-header">
                <h2>Device Summary</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Online</div>
                    <div class="stat-value">{{ $devices['summary']['online_devices'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Offline</div>
                    <div class="stat-value">{{ $devices['summary']['offline_devices'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Reboot Required</div>
                    <div class="stat-value">{{ $devices['summary']['devices_needing_reboot'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Suspended</div>
                    <div class="stat-value">{{ $devices['summary']['suspended_devices'] ?? 0 }}</div>
                </div>
            </div>

            @if(isset($devices['data']) && count($devices['data']) > 0)
            <h3>Device Status</h3>
            <table>
                <thead>
                    <tr>
                        <th>Hostname</th>
                        <th>Type</th>
                        <th>Operating System</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Patch Status</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($devices['data'], 0, 15) as $device)
                    <tr>
                        <td><strong>{{ $device['hostname'] }}</strong></td>
                        <td>{{ $device['device_category'] }}</td>
                        <td>{{ Str::limit($device['operating_system'], 35) }}</td>
                        <td style="text-align: center;">
                            @if($device['online'])
                                <span class="badge success">Online</span>
                            @else
                                <span class="badge danger">Offline</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if($device['patch_status'] === 'FullyPatched')
                                <span class="badge success">Patched</span>
                            @elseif($device['patch_status'] === 'RebootRequired')
                                <span class="badge warning">Reboot</span>
                            @else
                                <span class="badge danger">{{ $device['patch_status'] }}</span>
                            @endif
                        </td>
                        <td>{{ $device['last_seen'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <!-- Security & Compliance -->
        <div class="section">
            <div class="section-header">
                <h2>Security & Compliance</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Antivirus Coverage</div>
                    <div class="stat-value">{{ $security['summary']['antivirus_coverage_percentage'] ?? 0 }}%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Patch Compliance</div>
                    <div class="stat-value">{{ $compliance['summary']['patch_compliance_percentage'] ?? 0 }}%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Fully Patched</div>
                    <div class="stat-value">{{ $compliance['summary']['fully_patched_devices'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Pending Patches</div>
                    <div class="stat-value">{{ $compliance['summary']['devices_with_pending_patches'] ?? 0 }}</div>
                </div>
            </div>

            <h3>Antivirus Status</h3>
            <table>
                <thead>
                    <tr>
                        <th>Antivirus Product</th>
                        <th style="text-align: center;">Device Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($security['summary']['antivirus_products'] ?? []) as $product => $count)
                    <tr>
                        <td>{{ $product }}</td>
                        <td style="text-align: center;">
                            <span class="badge">{{ $count }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <h3>Patch Management Status</h3>
            <table>
                <thead>
                    <tr>
                        <th>Patch Status</th>
                        <th style="text-align: center;">Device Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($compliance['summary']['patch_status_breakdown'] ?? []) as $status => $count)
                    <tr>
                        <td>{{ $status }}</td>
                        <td style="text-align: center;">
                            <span class="badge info">{{ $count }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Alert Summary -->
        @if(isset($alerts['data']) && count($alerts['data']) > 0)
        <div class="section">
            <div class="section-header">
                <h2>Alert Summary</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Alerts</div>
                    <div class="stat-value">{{ $alerts['summary']['total_alerts'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Critical</div>
                    <div class="stat-value">{{ $alerts['summary']['critical_alerts'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value">{{ $alerts['summary']['resolved_alerts'] ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Avg Resolution</div>
                    <div class="stat-value">{{ $alerts['summary']['avg_resolution_minutes'] ?? 0 }}m</div>
                </div>
            </div>

            @if(isset($alerts['summary']['top_alerts']) && count($alerts['summary']['top_alerts']) > 0)
            <h3>Top Alert Types</h3>
            <table>
                <thead>
                    <tr>
                        <th>Alert Message</th>
                        <th style="text-align: center;">Priority</th>
                        <th style="text-align: center;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts['summary']['top_alerts'] as $alert)
                    <tr>
                        <td>{{ Str::limit($alert['message'], 60) }}</td>
                        <td style="text-align: center;">
                            @if($alert['priority'] === 'Critical')
                                <span class="badge danger">{{ $alert['priority'] }}</span>
                            @elseif($alert['priority'] === 'High')
                                <span class="badge warning">{{ $alert['priority'] }}</span>
                            @else
                                {{ $alert['priority'] }}
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <span class="badge info">{{ $alert['count'] }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <h3>Recent Alerts</h3>
            <table>
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Alert</th>
                        <th style="text-align: center;">Priority</th>
                        <th>Timestamp</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($alerts['data'], 0, 10) as $alert)
                    <tr>
                        <td><strong>{{ $alert['device_name'] }}</strong></td>
                        <td>{{ Str::limit($alert['diagnostics'], 40) }}</td>
                        <td style="text-align: center;">
                            @if($alert['priority'] === 'Critical')
                                <span class="badge danger">{{ $alert['priority'] }}</span>
                            @elseif($alert['priority'] === 'High')
                                <span class="badge warning">{{ $alert['priority'] }}</span>
                            @else
                                {{ $alert['priority'] }}
                            @endif
                        </td>
                        <td>{{ $alert['timestamp'] }}</td>
                        <td style="text-align: center;">
                            @if($alert['resolved'])
                                <span class="badge success">Resolved</span>
                            @else
                                <span class="badge warning">Open</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</body>
</html>
