<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bitdefender {{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly')) }} Summary - {{ $customer->canonical_name }}</title>
    @if(isset($branding['chart_js_inline']) && $branding['chart_js_inline'])
    <script>
        {!! $branding['chart_js_inline'] !!}
        // Signal that Chart.js is loaded for Gotenberg wait expression
        window.chartJsLoaded = typeof Chart !== 'undefined';
    </script>
    @else
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Signal that Chart.js is loaded for Gotenberg wait expression
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
            font-size: 12px;
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

        .recommendation-list { list-style: none; }
        .recommendation-item { 
            padding: 14px 18px; 
            margin-bottom: 12px; 
            border-left: 4px solid {{ ($branding['primary_color'] ?? '#1a56db') }}; 
            background: #ffffff; 
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border-top: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
        }

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

        /* Print optimization */
        .section { page-break-inside: auto; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        .chart-card, .stat-box, .recommendation-item { page-break-inside: avoid; }
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
        <h1>Bitdefender {{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Service')) }} Review</h1>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    @if(!isset($sections_to_render) || in_array('executive_summary', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Total Endpoints</div>
            <div class="stat-value">{{ $executive_summary['total_endpoints'] ?? 0 }}</div>
            <div class="stat-sub">{{ $executive_summary['managed_endpoints'] ?? 0 }} managed</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Compliance Rate</div>
            <div class="stat-value">{{ $executive_summary['policy_compliance_rate'] ?? 0 }}%</div>
            <div class="stat-sub">{{ $executive_summary['unique_policies'] ?? 0 }} unique policies</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Unmanaged Units</div>
            <div class="stat-value" style="color: #ef4444;">{{ $executive_summary['unmanaged_endpoints'] ?? 0 }}</div>
            <div class="stat-sub">Action required</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Threats Logged</div>
            <div class="stat-value">{{ $executive_summary['total_threats'] ?? 0 }}</div>
            <div class="stat-sub">Recent detections</div>
        </div>
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('recommendations', $sections_to_render) || in_array('all', $sections_to_render))
    @if(!empty($alerts) && is_array($alerts))
    <div class="section">
        <h2 class="section-title">Security Recommendations</h2>
        <div class="recommendation-list">
            @foreach($alerts as $rec)
                <div class="recommendation-item">{{ $rec }}</div>
            @endforeach
        </div>
    </div>
    @endif
    @endif

    @if(!isset($sections_to_render) || in_array('policies', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Security Policy Adherence</h2>
        @if(!empty($policy_details) && is_array($policy_details) && count($policy_details) > 0)
        <table>
            <thead>
                <tr>
                    <th>Policy Description</th>
                    <th style="text-align: center;">Devices</th>
                    <th style="text-align: center;">Applied</th>
                    <th style="text-align: center;">Out of Sync</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($policy_details as $policy)
                <tr>
                    <td><strong>{{ $policy['name'] ?? 'Standard Protection' }}</strong></td>
                    <td style="text-align: center;">{{ $policy['total'] ?? 0 }}</td>
                    <td style="text-align: center;">{{ $policy['applied'] ?? 0 }}</td>
                    <td style="text-align: center;">{{ ($policy['total'] ?? 0) - ($policy['applied'] ?? 0) }}</td>
                    <td style="text-align: center;">
                        @if(($policy['total'] ?? 0) === ($policy['applied'] ?? 0))
                            <span class="badge success">Compliant</span>
                        @elseif(($policy['applied'] ?? 0) > 0)
                            <span class="badge warning">Partial</span>
                        @else
                            <span class="badge danger">Critical</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No active security policy data found for this period.</div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('operating_systems', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Infrastructure Health Trend</h2>
        
        <div class="chart-card" style="margin-bottom: 30px;">
            <div class="chart-header">
                <div class="chart-title">Endpoint Protection Coverage</div>
                <div class="chart-legend" id="trend-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3b82f6"></div>
                        <span>Total Assets</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #10b981"></div>
                        <span>Managed Assets</span>
                    </div>
                </div>
            </div>
            <div style="width: 100%; height: 240px;">
                <canvas id="trendChart"></canvas>
            </div>
            <p style="margin-top: 12px; font-size: 10px; color: #64748b; font-style: italic;">
                The timeline above tracks the daily count of protected endpoints vs. active management coverage across the reporting period.
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Operating System Platform</th>
                    <th style="text-align: center;">Asset Count</th>
                    <th style="text-align: center;">Environment Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach($os_distribution as $os)
                <tr>
                    <td><strong>{{ $os['os'] ?? 'Workstation' }}</strong></td>
                    <td style="text-align: center;">{{ $os['count'] ?? 0 }}</td>
                    <td style="text-align: center;">{{ $os['percentage'] ?? 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('threats', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Threat Detection Activity</h2>
        @if(!empty($threats_list) && is_array($threats_list) && count($threats_list) > 0)
        <table>
            <thead>
                <tr>
                    <th>Security Threat</th>
                    <th>Affected Asset</th>
                    <th>Resolution Action</th>
                    <th style="text-align: center;">Latest Status</th>
                    <th>Detected At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($threats_list as $threat)
                <tr>
                    <td>
                        <strong>{{ $threat['name'] ?? 'Unknown Malicious File' }}</strong>
                        @if(!empty($threat['path']))
                            <div style="font-size: 8px; color: #94a3b8; margin-top: 3px; max-width: 280px; word-break: break-all;">
                                {{ $threat['path'] }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $threat['endpoint'] ?? 'Workstation' }}</td>
                    <td><span style="font-size: 9.5px; color: #64748b;">{{ $threat['action'] ?? 'Audit' }}</span></td>
                    <td style="text-align: center;">
                        @if(stripos($threat['status'] ?? '', 'clean') !== false || stripos($threat['status'] ?? '', 'resolved') !== false || stripos($threat['status'] ?? '', 'deleted') !== false)
                            <span class="badge success">{{ $threat['status'] }}</span>
                        @else
                            <span class="badge warning">{{ $threat['status'] }}</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($threat['timestamp'])->format('M d, H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No security threats detected in the current scope.</div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('endpoints', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Endpoint Security Inventory</h2>
        @if(!empty($endpoints_list) && is_array($endpoints_list) && count($endpoints_list) > 0)
        <table>
            <thead>
                <tr>
                    <th>Device Name</th>
                    <th>Network Address</th>
                    <th>OS Version</th>
                    <th>Assigned Policy</th>
                    <th style="text-align: center;">Protection</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($endpoints_list, 0, 15) as $endpoint)
                <tr>
                    <td>
                        <strong>{{ $endpoint['name'] ?? 'Asset' }}</strong>
                        @if(!empty($endpoint['fqdn']))
                        <div style="font-size: 9px; color: #94a3b8;">{{ $endpoint['fqdn'] }}</div>
                        @endif
                    </td>
                    <td style="font-family: monospace; font-size: 9px;">{{ $endpoint['ip'] ?? 'DHCP' }}</td>
                    <td>{{ Str::limit($endpoint['os'] ?? 'Windows', 30) }}</td>
                    <td>
                        <span style="font-size: 9.5px;">{{ $endpoint['policy_name'] ?? 'Default' }}</span>
                        @if(!($endpoint['policy_applied'] ?? false))
                            <span class="badge danger" style="padding: 1px 4px; font-size: 7px; margin-left: 3px;">Pending</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if(($endpoint['is_managed'] ?? false))
                            <span class="badge success">Managed</span>
                        @else
                            <span class="badge danger">Unmanaged</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if(count($endpoints_list) > 15)
            <div style="text-align: center; color: #94a3b8; font-style: italic; font-size: 9px; margin-top: -10px;">
                Summary view showing 15 of {{ count($endpoints_list) }} total assets.
            </div>
        @endif
        @else
        <div class="empty">No endpoint assets identified in the environment.</div>
        @endif
    </div>
    @endif

    <script>
        (function() {
            try {
                const ctx = document.getElementById('trendChart').getContext('2d');
                const trendData = {!! json_encode($infrastructure_trends ?? []) !!};
                
                if (trendData && trendData.labels && trendData.labels.length > 0) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: trendData.labels,
                            datasets: [
                                {
                                    label: 'Total Assets',
                                    data: trendData.total,
                                    borderColor: '#3b82f6',
                                    backgroundColor: '#3b82f620',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 2,
                                    borderWidth: 2
                                },
                                {
                                    label: 'Managed Assets',
                                    data: trendData.managed,
                                    borderColor: '#10b981',
                                    backgroundColor: '#10b98120',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 2,
                                    borderWidth: 2
                                }
                            ]
                        },
                        options: {
                            animation: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grace: '10%',
                                    grid: { color: '#f1f5f9' },
                                    ticks: { font: { size: 9 }, color: '#94a3b8' }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { 
                                        font: { size: 9 }, 
                                        color: '#94a3b8',
                                        maxTicksLimit: 12,
                                        autoSkip: true
                                    }
                                }
                            }
                        }
                    });
                } else {
                    document.getElementById('trendChart').parentElement.innerHTML = '<div class="empty">No historical data available for trend analysis.</div>';
                }

                // Signal that charts are rendered for Gotenberg
                window.chartsRendered = true;
            } catch (e) {
                console.error('Chart error:', e);
                window.chartsRendered = true;
            }
        })();
    </script>
    @endif
</body>
</html>
