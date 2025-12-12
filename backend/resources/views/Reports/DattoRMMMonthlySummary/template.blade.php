<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Datto RMM Monthly Summary - {{ $customer->canonical_name }}</title>
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
        
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 40px; }
        .summary-card { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; }
        .summary-card .number { font-size: 28px; font-weight: 600; margin-bottom: 3px; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .subvalue { font-size: 10px; color: #666; margin-top: 3px; }
        
        .section { margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        h3 { font-size: 14px; font-weight: 600; margin: 20px 0 10px 0; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        
        .alert-box { border-left: 3px solid; padding: 10px 12px; margin: 10px 0; font-size: 11px; }
        .alert-box.critical { border-color: #dc2626; background: #fef2f2; }
        .alert-box.high { border-color: #d97706; background: #fffbeb; }
        .alert-box.medium { border-color: #2563eb; background: #eff6ff; }
        .alert-box .alert-title { font-weight: 600; margin-bottom: 3px; }
        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 11px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Datto RMM Monthly Summary</h1>
        <div class="company-name">{{ $branding['company_name'] ?? 'Territory Technology Solutions' }}</div>
        <div class="meta">{{ $customer->canonical_name }} • {{ $period['formatted'] }}</div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ $executive_summary['devices']['total'] }}</div>
            <div class="label">Total Devices</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['devices']['uptime_percentage'] }}%</div>
            <div class="label">Uptime</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['alerts']['total'] }}</div>
            <div class="label">Total Alerts</div>
            @if($executive_summary['alerts']['critical'] > 0)
            <div class="subvalue" style="color: #dc2626;">{{ $executive_summary['alerts']['critical'] }} critical</div>
            @endif
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['compliance']['patch_compliance'] }}%</div>
            <div class="label">Patch Compliance</div>
            @if($executive_summary['compliance']['devices_needing_reboot'] > 0)
            <div class="subvalue" style="color: #d97706;">{{ $executive_summary['compliance']['devices_needing_reboot'] }} need reboot</div>
            @endif
        </div>
    </div>

    @if(isset($recommendations) && count($recommendations) > 0)
    <div class="section">
        <h2 class="section-title">Alerts & Recommendations</h2>
            
            @foreach($recommendations as $rec)
            <div class="alert-box {{ $rec['severity'] }}">
                <div class="alert-title">{{ $rec['title'] }}</div>
                <div style="color: #4b5563; margin-bottom: 3px;">{{ $rec['description'] }}</div>
                <div style="color: #6b7280; font-size: 8pt;"><strong>Action:</strong> {{ $rec['action'] }}</div>
            </div>
            @endforeach
    </div>
    @endif

    <div class="section">
        <h2 class="section-title">Device Summary</h2>
        
        <div class="summary">
            <div class="summary-card">
                <div class="number">{{ $devices['summary']['online_devices'] ?? 0 }}</div>
                <div class="label">Online</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $devices['summary']['offline_devices'] ?? 0 }}</div>
                <div class="label">Offline</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $devices['summary']['devices_needing_reboot'] ?? 0 }}</div>
                <div class="label">Reboot Required</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $devices['summary']['suspended_devices'] ?? 0 }}</div>
                <div class="label">Suspended</div>
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

    <div class="section">
        <h2 class="section-title">Security & Compliance</h2>
        
        <div class="summary">
            <div class="summary-card">
                <div class="number">{{ $security['summary']['antivirus_coverage_percentage'] ?? 0 }}%</div>
                <div class="label">Antivirus Coverage</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $compliance['summary']['patch_compliance_percentage'] ?? 0 }}%</div>
                <div class="label">Patch Compliance</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $compliance['summary']['fully_patched_devices'] ?? 0 }}</div>
                <div class="label">Fully Patched</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $compliance['summary']['devices_with_pending_patches'] ?? 0 }}</div>
                <div class="label">Pending Patches</div>
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
                        <td style="text-align: center;">{{ $count }}</td>
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

    @if(isset($alerts['data']) && count($alerts['data']) > 0)
    <div class="section">
        <h2 class="section-title">Alert Summary</h2>
        
        <div class="summary">
            <div class="summary-card">
                <div class="number">{{ $alerts['summary']['total_alerts'] ?? 0 }}</div>
                <div class="label">Total Alerts</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $alerts['summary']['critical_alerts'] ?? 0 }}</div>
                <div class="label">Critical</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $alerts['summary']['resolved_alerts'] ?? 0 }}</div>
                <div class="label">Resolved</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $alerts['summary']['avg_resolution_minutes'] ?? 0 }}m</div>
                <div class="label">Avg Resolution</div>
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
</body>
</html>
