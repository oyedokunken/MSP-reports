<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Datto RMM {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary - {{ $customer->canonical_name }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.5;
            color: #1e293b;
            max-width: 1200px;
            margin: 0 auto;
            padding: {{ (isset($render_part) && $render_part === 'cover') ? '0' : '20px 10px' }};
            font-size: 12px;
            background-color: #ffffff;
        }
        
        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .header h1 { 
            font-family: 'Outfit', sans-serif;
            font-size: 28px; 
            font-weight: 800; 
            margin-bottom: 8px; 
            color: #0f172a; 
            letter-spacing: -0.02em; 
        }
        .header .meta { font-size: 12px; color: #64748b; font-weight: 500; }
        
        .section { margin-bottom: 40px; }
        .section-title { 
            font-family: 'Outfit', sans-serif;
            font-size: 18px; 
            font-weight: 700; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }}20;
            color: #0f172a;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box { 
            padding: 20px 15px; 
            background: #ffffff;
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            text-align: center;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 90px;
        }
        .stat-box .stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; font-weight: 700; }
        .stat-box .stat-value { font-size: 28px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; }
        .stat-box .stat-sub { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        .summary-chart-container { 
            margin-bottom: 40px; 
            padding: 24px; 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .chart-legend {
            display: flex;
            gap: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            font-size: 11px; 
            margin-bottom: 30px; 
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        th { 
            text-align: left; 
            padding: 12px 15px; 
            background: #f8fafc; 
            font-weight: 700; 
            color: #475569; 
            border-bottom: 1px solid #e2e8f0; 
            white-space: nowrap; 
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.025em;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; vertical-align: top; color: #334155; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        
        .recommendation-card { 
            padding: 16px 20px; 
            margin-bottom: 15px; 
            border-left: 4px solid; 
            background: #ffffff; 
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border-top: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }
        .recommendation-card.critical { border-left-color: #ef4444; }
        .recommendation-card.high { border-left-color: #f59e0b; }
        .recommendation-card.medium { border-left-color: #3b82f6; }
        .recommendation-card .rec-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; color: #0f172a; }
        .recommendation-card .rec-category { font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
        
        .footer { 
            margin-top: 60px; 
            padding-top: 20px; 
            border-top: 1px solid #e2e8f0; 
            text-align: center; 
            color: #94a3b8; 
            font-size: 10px; 
        }

        .report-bg-shape-1 {
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: {{ $branding['primary_color'] ?? '#1e293b' }};
            opacity: 0.02;
            clip-path: polygon(100% 0, 0% 0, 100% 100%);
            z-index: -1;
            pointer-events: none;
        }

        .report-bg-shape-2 {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 30%;
            height: 40%;
            background: {{ $branding['secondary_color'] ?? '#1e293b' }};
            opacity: 0.015;
            clip-path: polygon(0 100%, 100% 100%, 0 0);
            z-index: -1;
            pointer-events: none;
        }
        
        h3 { 
            font-family: 'Outfit', sans-serif;
            font-size: 14px; 
            font-weight: 700; 
            margin: 30px 0 15px 0; 
            color: #1e293b; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
        }
    </style>
</head>
<body>
    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'cover')
        @include('CoverPages.default')
    @endif
    
    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'content')
    <div class="report-bg-shape-1"></div>
    <div class="report-bg-shape-2"></div>
    
    <div class="header">
        <h1>Datto RMM {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</h1>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    <!-- Executive Summary Stats -->
    <!-- <div class="section">
        <h2 class="section-title">Executive Summary</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">System Uptime</div>
                <div class="stat-value">{{ $executive_summary['devices']['uptime_percentage'] }}%</div>
                <div class="stat-sub">Overall availability</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Device Security</div>
                <div class="stat-value">{{ $executive_summary['compliance']['antivirus_coverage'] }}%</div>
                <div class="stat-sub">Antivirus coverage</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Patch Compliance</div>
                <div class="stat-value">{{ $executive_summary['compliance']['patch_compliance'] }}%</div>
                <div class="stat-sub">Environment health</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Alerts</div>
                <div class="stat-value">{{ $executive_summary['alerts']['total'] }}</div>
                <div class="stat-sub">{{ $executive_summary['alerts']['critical'] }} critical alerts</div>
            </div>
        </div>
    </div> -->

    <!-- System Overview Chart -->
    <div class="summary-chart-container">
        <div class="chart-header">
            <div class="chart-title">System Status Overview</div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span>Healthy / Resolved</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Offline / Open</span>
                </div>
            </div>
        </div>
        <div style="width: 100%; height: 250px;">
            <canvas id="systemOverviewChart" width="600" height="250"></canvas>
        </div>
        <p style="margin-top: 10px; font-size: 11px; color: #64748b; line-height: 1.5; padding: 0 5px;">
            This chart visualizes the total count of devices that are currently online versus offline, as well as the proportion of resolved alerts compared to those that remain open. It provides a high-level snapshot of infrastructure availability and the current backlog of unresolved issues.
        </p>
    </div>

    <!-- Alert Trends Chart -->
    <div class="summary-chart-container">
        <div class="chart-header">
            <div class="chart-title">Alert Activity Trend</div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #3b82f6;"></div>
                    <span>All Alerts</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Critical Alerts</span>
                </div>
            </div>
        </div>
        <div style="width: 100%; height: 250px;">
            <canvas id="alertTrendsChart" width="600" height="250"></canvas>
        </div>
        <p style="margin-top: 10px; font-size: 11px; color: #64748b; line-height: 1.5; padding: 0 5px;">
            This timeline tracks the daily volume of system alerts over the reporting period, highlighting spikes in activity. By distinguishing between total alert volume and critical incidents, it helps identify specific days with unusual instability or high operational noise.
        </p>
    </div>

    <!-- Recommendations -->
    @if(isset($recommendations) && count($recommendations) > 0)
    <div class="section">
        <h2 class="section-title">Analysis & Recommendations</h2>
        @foreach($recommendations as $rec)
        <div class="recommendation-card {{ $rec['severity'] }}">
            <div class="rec-category">{{ $rec['category'] }}</div>
            <div class="rec-title">{{ $rec['title'] }}</div>
            <div style="color: #4b5563; margin-bottom: 6px;">{{ $rec['description'] }}</div>
            <div style="font-size: 10px; color: #1e293b; font-weight: 600;">
                <span style="color: {{ $branding['primary_color'] ?? '#1a56db' }}; text-transform: uppercase; font-size: 8px; letter-spacing: 0.05em;">Recommended Action:</span> 
                {{ $rec['action'] }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Device Health -->
    <div class="section">
        <h2 class="section-title">Infrastructure Health</h2>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Devices</div>
                <div class="stat-value">{{ $devices['summary']['total_devices'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Online</div>
                <div class="stat-value">{{ $devices['summary']['online_devices'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Offline</div>
                <div class="stat-value" style="color: #ef4444;">{{ $devices['summary']['offline_devices'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Pending Reboot</div>
                <div class="stat-value" style="color: #f59e0b;">{{ $devices['summary']['devices_needing_reboot'] }}</div>
            </div>
        </div>

        @if(isset($devices['data']) && count($devices['data']) > 0)
        <h3>Detailed Device Inventory</h3>
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
                @foreach(array_slice($devices['data'], 0, 12) as $device)
                <tr>
                    <td><strong>{{ $device['hostname'] }}</strong></td>
                    <td>{{ $device['device_category'] ?? 'Workstation' }}</td>
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
                    <td>{{ \Carbon\Carbon::parse($device['last_seen'])->format('M d, H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if(count($devices['data']) > 12)
        <div style="text-align: center; color: #94a3b8; font-style: italic; font-size: 10px; margin-top: -15px; margin-bottom: 20px;">
            Showing first 12 of {{ count($devices['data']) }} devices. Full list available in digital dashboard.
        </div>
        @endif
        @endif
    </div>

    <!-- Security & Alerts -->
    <div class="section">
        <h2 class="section-title">Security & Security Operations</h2>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Critical Alerts</div>
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
            <div class="stat-box">
                <div class="stat-label">AV Coverage</div>
                <div class="stat-value">{{ $security['summary']['antivirus_coverage_percentage'] ?? 0 }}%</div>
            </div>
        </div>

        @if(isset($alerts['summary']['top_alerts']) && count($alerts['summary']['top_alerts']) > 0)
        <h3>Top Incident Types</h3>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: center;">Priority</th>
                    <th style="text-align: center; width: 100px;">Occurrence</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts['summary']['top_alerts'] as $alert)
                <tr>
                    <td>{{ Str::limit($alert['message'], 100) }}</td>
                    <td style="text-align: center;">
                        @if($alert['priority'] === 'Critical')
                            <span class="badge danger">{{ $alert['priority'] }}</span>
                        @elseif($alert['priority'] === 'High')
                            <span class="badge warning">{{ $alert['priority'] }}</span>
                        @else
                            <span class="badge info">{{ $alert['priority'] }}</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        <span class="badge info" style="background: #f1f5f9; color: #475569;">{{ $alert['count'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="footer">
        Datto RMM Operational Review • Generated on {{ now()->format('F d, Y') }} • Confidential
    </div>

    <script>
        (function() {
            try {
                // System Overview Chart
                const ctx = document.getElementById('systemOverviewChart').getContext('2d');
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Devices', 'Alerts'],
                        datasets: [
                            {
                                label: 'Healthy / Resolved',
                                data: [
                                    {{ $devices['summary']['online_devices'] ?? 0 }},
                                    {{ $alerts['summary']['resolved_alerts'] ?? 0 }}
                                ],
                                backgroundColor: '#10b981',
                                borderRadius: 4,
                                stack: 'Stack 0'
                            },
                            {
                                label: 'Offline / Open',
                                data: [
                                    {{ $devices['summary']['offline_devices'] ?? 0 }},
                                    {{ $alerts['summary']['open_alerts'] ?? 0 }}
                                ],
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                                stack: 'Stack 0'
                            }
                        ]
                    },
                    options: {
                        animation: false,
                        responsive: false,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                grid: {
                                    color: '#f1f5f9'
                                },
                                ticks: { font: { size: 10 }, color: '#94a3b8' }
                            },
                            x: {
                                stacked: true,
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#94a3b8' }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });

                // Alert Trends Chart
                const trendCtx = document.getElementById('alertTrendsChart').getContext('2d');
                const trendData = @json($executive_summary['daily_trends'] ?? ['labels' => [], 'total' => [], 'critical' => []]);
                
                new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: trendData.labels,
                        datasets: [
                            {
                                label: 'All Alerts',
                                data: trendData.total,
                                backgroundColor: '#3b82f6',
                                borderRadius: 4,
                                borderWidth: 0
                            },
                            {
                                label: 'Critical Alerts',
                                data: trendData.critical,
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                                borderWidth: 0
                            }
                        ]
                    },
                    options: {
                        animation: false,
                        responsive: false,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 0,
                                right: 15,
                                top: 10,
                                bottom: 0
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                grace: '5%',
                                border: {
                                    display: true,
                                    color: '#e2e8f0'
                                },
                                grid: { 
                                    color: '#f1f5f9'
                                },
                                ticks: { 
                                    font: { size: 10 }, 
                                    color: '#94a3b8',
                                    stepSize: 1,
                                    padding: 8
                                }
                            },
                            x: {
                                stacked: true,
                                border: {
                                    display: true,
                                    color: '#e2e8f0'
                                },
                                grid: { display: false },
                                ticks: { 
                                    font: { size: 9 }, 
                                    color: '#94a3b8',
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 12,
                                    padding: 8
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error(e);
                document.body.insertAdjacentHTML('afterbegin', '<div style="background:#fee2e2; border:1px solid #ef4444; color:#991b1b; padding:10px; margin:10px;"><strong>Chart Error:</strong> ' + e.message + '</div>');
            }
        })();
    </script>
    @endif
</body>
</html>
