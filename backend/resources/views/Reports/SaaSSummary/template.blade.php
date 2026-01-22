<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SaaS Alerts {{ ucfirst($options['frequency'] ?? 'Monthly') }} Security Report - {{ $customer->canonical_name }}</title>
    @if(isset($branding['chart_js_inline']) && $branding['chart_js_inline'])
    <script>
        {!! $branding['chart_js_inline'] !!}
        window.chartJsLoaded = typeof Chart !== 'undefined';
    </script>
    @else
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.chartJsLoaded = typeof Chart !== 'undefined';
    </script>
    @endif
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
            font-size: 11px;
            background-color: #ffffff;
        }

        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .header h1 { 
            font-family: 'Outfit', sans-serif;
            font-size: 26px; 
            font-weight: 800; 
            margin-bottom: 6px; 
            color: #0f172a; 
            letter-spacing: -0.02em; 
        }
        .header .meta { font-size: 12px; color: #64748b; font-weight: 500; }

        .section { margin-bottom: 35px; }
        .section-title { 
            font-family: 'Outfit', sans-serif;
            font-size: 18px; 
            font-weight: 700; 
            margin-bottom: 18px; 
            padding-bottom: 8px; 
            border-bottom: 2px solid {{ ($branding['primary_color'] ?? '#1a56db') }}20;
            color: #0f172a;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
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
            min-height: 85px;
        }
        .stat-box .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; font-weight: 700; }
        .stat-box .stat-value { font-size: 24px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; }
        .stat-box .stat-sub { font-size: 9px; color: #94a3b8; margin-top: 2px; }

        .chart-card { 
            margin-bottom: 30px; 
            padding: 22px; 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .chart-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .chart-legend {
            display: flex;
            gap: 15px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }
        .legend-color {
            width: 10px;
            height: 10px;
            border-radius: 2px;
        }

        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            font-size: 10.5px; 
            margin-bottom: 25px; 
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        th { 
            text-align: left; 
            padding: 10px 14px; 
            background: #f8fafc; 
            font-weight: 700; 
            color: #475569; 
            border-bottom: 1px solid #e2e8f0; 
            white-space: nowrap; 
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.025em;
        }
        td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: top; color: #334155; }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 5px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }

        .alert-card { 
            padding: 14px 18px; 
            margin-bottom: 12px; 
            border-left: 4px solid; 
            background: #ffffff; 
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border-top: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }
        .alert-card.critical { border-left-color: #ef4444; }
        .alert-card.high { border-left-color: #f59e0b; }
        .alert-card.medium { border-left-color: #3b82f6; }
        .alert-card.low { border-left-color: #10b981; }
        .alert-card .alert-title { font-weight: 700; font-size: 12px; margin-bottom: 3px; color: #0f172a; }

        .report-bg-shape-1 {
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: {{ ($branding['primary_color'] ?? '#1e293b') }};
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
            background: {{ ($branding['secondary_color'] ?? '#1e293b') }};
            opacity: 0.015;
            clip-path: polygon(0 100%, 100% 100%, 0 0);
            z-index: -1;
            pointer-events: none;
        }

        .page-break { page-break-before: always; }
        .empty { text-align: center; padding: 30px; color: #94a3b8; font-style: italic; }
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
        <h1>SaaS Alerts {{ ucfirst($options['frequency'] ?? 'Monthly') }} Security Report</h1>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($date_range) ? (($date_range['start'] ?? 'N/A') . ' - ' . ($date_range['end'] ?? 'N/A')) : 'N/A' }}
        </div>
    </div>

    @if(!isset($sections_to_render) || in_array('executive_summary', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Executive Security Summary</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Events</div>
                <div class="stat-value">{{ number_format($executive_summary['total_events'] ?? 0) }}</div>
                <div class="stat-sub">Across all platforms</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Active Users</div>
                <div class="stat-value">{{ $executive_summary['unique_users'] ?? 0 }}</div>
                <div class="stat-sub">Identified accounts</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Threat Alerts</div>
                <div class="stat-value" style="color: #ef4444;">{{ $executive_summary['threat_events'] ?? 0 }}</div>
                <div class="stat-sub">High priority events</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Failed Logins</div>
                <div class="stat-value">{{ number_format($executive_summary['failed_logins'] ?? 0) }}</div>
                <div class="stat-sub">Login attempts</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Mobile Usage</div>
                <div class="stat-value">{{ $executive_summary['mobile_usage_percent'] ?? 0 }}%</div>
                <div class="stat-sub">Device breakdown</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">VPN Access</div>
                <div class="stat-value">{{ number_format($executive_summary['vpn_usage'] ?? 0) }}</div>
                <div class="stat-sub">Secure connections</div>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">Daily Security Activity</div>
                <div class="chart-legend">
                    <div class="legend-item"><div class="legend-color" style="background: #3b82f6;"></div><span>Total Events</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #ef4444;"></div><span>Threats</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #f59e0b;"></div><span>Failures</span></div>
                </div>
            </div>
            <div style="width: 100%; height: 230px;">
                <canvas id="dailyTrendsChart"></canvas>
            </div>
        </div>
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('recommendations', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="page-break"></div>
    <div class="section">
        <h2 class="section-title">Security Insights & Recommendations</h2>
        @if(!empty($recommendations) && count($recommendations) > 0)
            @foreach($recommendations as $recommendation)
                <div class="alert-card {{ $recommendation['severity'] }}">
                    <div class="alert-title">{{ $recommendation['title'] }}</div>
                    <div style="color: #4b5563; margin-bottom: 4px;">{{ $recommendation['description'] }}</div>
                    <div style="font-size: 10px; font-weight: 600;">
                        <span style="color: {{ ($branding['primary_color'] ?? '#1a56db') }};">ACTION:</span> {{ $recommendation['action'] }}
                    </div>
                </div>
            @endforeach
        @else
            <div class="empty">No critical security recommendations at this time.</div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('user_activity', $sections_to_render) || in_array('all', $sections_to_render))
    <!-- <div class="page-break"></div> -->
    <div class="section">
        <h2 class="section-title">User Security Profile</h2>
        @if(!empty($user_activity) && count($user_activity) > 0)
            <table>
                <thead>
                    <tr>
                        <th>User Identity</th>
                        <th style="text-align: center;">Events</th>
                        <th style="text-align: center;">Success</th>
                        <th style="text-align: center;">Failures</th>
                        <th style="text-align: center;">IPs</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($user_activity->toArray(), 0, 15) as $user)
                        <tr>
                            <td>
                                <strong>{{ $user['name'] }}</strong><br>
                                <span style="font-size: 8.5px; color: #64748b;">{{ $user['email'] }}</span>
                            </td>
                            <td style="text-align: center;">{{ number_format($user['total_events']) }}</td>
                            <td style="text-align: center;">{{ number_format($user['successful_logins']) }}</td>
                            <td style="text-align: center;">
                                @if($user['failed_logins'] > 5)
                                    <span class="badge danger">{{ $user['failed_logins'] }}</span>
                                @elseif($user['failed_logins'] > 0)
                                    <span class="badge warning">{{ $user['failed_logins'] }}</span>
                                @else
                                    {{ $user['failed_logins'] }}
                                @endif
                            </td>
                            <td style="text-align: center;">{{ $user['unique_ips'] }}</td>
                            <td style="text-align: center;">
                                @if($user['is_licensed'])
                                    <span class="badge success">Licensed</span>
                                @else
                                    <span class="badge info">External</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">No user activity data available for this period.</div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('geographic_data', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Geographic Access Patterns</h2>
        @if(!empty($geographic_data) && count($geographic_data) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Country / Region</th>
                        <th style="text-align: center;">Event Count</th>
                        <th style="text-align: center;">Users</th>
                        <th style="text-align: center;">Threats</th>
                        <th>Top Locations</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($geographic_data->toArray(), 0, 10) as $location)
                        <tr>
                            <td><strong>{{ $location['country'] }}</strong></td>
                            <td style="text-align: center;">{{ number_format($location['event_count']) }}</td>
                            <td style="text-align: center;">{{ $location['unique_users'] }}</td>
                            <td style="text-align: center;">
                                @if($location['threat_count'] > 0)
                                    <span class="badge danger">{{ $location['threat_count'] }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td><span style="font-size: 9px; color: #64748b;">{{ implode(', ', array_slice($location['cities'], 0, 3)) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">No geographic data recorded for this period.</div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('application_usage', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="page-break"></div>
    <div class="section">
        <h2 class="section-title">Application Ecosystem</h2>
        @if(!empty($application_usage) && count($application_usage) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Application Name</th>
                        <th style="text-align: center;">Access Events</th>
                        <th style="text-align: center;">Unique Users</th>
                        <th>Platform Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($application_usage->toArray(), 0, 15) as $app)
                        <tr>
                            <td><strong>{{ $app['app_name'] }}</strong></td>
                            <td style="text-align: center;">{{ number_format($app['event_count']) }}</td>
                            <td style="text-align: center;">{{ $app['unique_users'] }}</td>
                            <td><span class="badge info">{{ $app['product_type'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">No application logs available.</div>
        @endif
    </div>
    @endif

    <script>
        (function() {
            try {
                const ctx = document.getElementById('dailyTrendsChart').getContext('2d');
                const trendData = {!! json_encode($executive_summary['daily_trends'] ?? ['labels' => [], 'total' => [], 'threats' => [], 'failures' => []]) !!};
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: trendData.labels,
                        datasets: [
                            {
                                label: 'Total Events',
                                data: trendData.total,
                                backgroundColor: '#3b82f6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Threats',
                                data: trendData.threats,
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                            },
                            {
                                label: 'Failures',
                                data: trendData.failures,
                                backgroundColor: '#f59e0b',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 10 } },
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grace: '5%',
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { size: 10 }, color: '#94a3b8', stepSize: 1 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { 
                                    font: { size: 9 }, 
                                    color: '#94a3b8',
                                    autoSkip: true,
                                    maxTicksLimit: 12
                                }
                            }
                        }
                    }
                });

                window.chartsRendered = true;
            } catch (e) {
                console.error(e);
                window.chartsRendered = true;
            }
        })();
    </script>
    @endif
</body>
</html>
