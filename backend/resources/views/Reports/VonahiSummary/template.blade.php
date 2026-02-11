<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Vonahi vPenTest Summary - {{ $customer->canonical_name }}</title>
    @if (isset($branding['chart_js_inline']) && $branding['chart_js_inline'])
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.5;
            color: #1e293b;
            max-width: 1200px;
            margin: 0 auto;
            padding: {{ isset($render_part) && $render_part === 'cover' ? '0' : '20px 10px' }};
            font-size: 11px;
            background-color: #ffffff;
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }

        .header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .header .meta {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .section {
            margin-bottom: 35px;
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }}20;
            color: #0f172a;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

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

        .stat-box .stat-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            font-weight: 700;
        }

        .stat-box .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
        }

        .stat-box .stat-sub {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .chart-card {
            margin-bottom: 30px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
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

        td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge.critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.high {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge.medium {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge.low {
            background: #f0fdf4;
            color: #166534;
        }

        .badge.informational {
            background: #f0f9ff;
            color: #075985;
        }

        .recommendation-item {
            padding: 14px 18px;
            margin-bottom: 12px;
            border-left: 4px solid {{ $branding['primary_color'] ?? '#1a56db' }};
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border: 1px solid #f1f5f9;
            border-left-width: 4px;
        }

        .severity-chart-container {
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 20px;
        }

        .page-break {
            page-break-before: always;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
            font-style: italic;
        }

        /* Print optimization */
        .section {
            page-break-inside: auto;
        }

        table {
            page-break-inside: avoid;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        thead {
            display: table-header-group;
        }
    </style>
</head>

<body>
    @if (!isset($render_part) || $render_part === 'all' || $render_part === 'cover')
        @include('CoverPages.default')
    @endif

    @if (!isset($render_part) || $render_part === 'all' || $render_part === 'content')
        <div class="header">
            <h1>Vonahi vPenTest Security Review</h1>
            <div class="meta">
                {{ $customer->canonical_name }} • {{ $period['formatted'] }}
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Assessments</div>
                <div class="stat-value">{{ $executive_summary['total_assessments'] }}</div>
                <div class="stat-sub">Recent pen tests</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Critical/High Risks</div>
                <div class="stat-value"
                    style="color: {{ $executive_summary['critical_high_findings'] > 0 ? '#ef4444' : '#10b981' }};">
                    {{ $executive_summary['critical_high_findings'] }}
                </div>
                <div class="stat-sub">Action items</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Findings</div>
                <div class="stat-value">{{ $executive_summary['total_findings'] }}</div>
                <div class="stat-sub">Across all scopes</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Active Agents</div>
                <div class="stat-value">{{ $executive_summary['active_agents'] }}</div>
                <div class="stat-sub">Network presence</div>
            </div>
        </div>

        @if (!empty($recommendations))
            <div class="section">
                <h2 class="section-title">Key Security Insights</h2>
                @foreach ($recommendations as $rec)
                    <div class="recommendation-item">{{ $rec }}</div>
                @endforeach
            </div>
        @endif

        <div class="section">
            <h2 class="section-title">Vulnerability Distribution</h2>
            <div class="chart-card">
                <div class="severity-chart-container">
                    <div style="width: 300px; height: 180px;">
                        <canvas id="severityChart"></canvas>
                    </div>
                    <div style="flex: 1; margin-left: 40px;">
                        <table style="margin-bottom: 0; box-shadow: none;">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th style="text-align: center;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($severity_distribution as $sev => $count)
                                    <tr>
                                        <td><span class="badge {{ $sev }}">{{ $sev }}</span></td>
                                        <td style="text-align: center; font-weight: 700;">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($assessments))
            <div class="section">
                <h2 class="section-title">Penetration Test History</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Assessment Name</th>
                            <th>Date</th>
                            <th style="text-align: center;">Risk Score</th>
                            <th style="text-align: center;">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assessments as $assess)
                            <tr>
                                <td><strong>{{ $assess['name'] }}</strong></td>
                                <td>{{ $assess['date'] }}</td>
                                <td style="text-align: center;"><span
                                        class="badge {{ $assess['severity'] }}">{{ $assess['severity'] }}</span></td>
                                <td style="text-align: center;">
                                    {{ $assess['remediation'] ? 'Remediation' : 'Full Test' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="page-break"></div>

        @if (!empty($findings))
            <div class="section">
                <h2 class="section-title">Security Findings Detail</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">Finding Name</th>
                            <th style="text-align: center;">Severity</th>
                            <th style="text-align: center;">Impact</th>
                            <th>Remediation Strategy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($findings as $find)
                            <tr>
                                <td>
                                    <strong>{{ $find['name'] }}</strong>
                                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">
                                        Affected: {{ $find['hosts_count'] }} host(s)
                                        @if (!empty($find['hosts_ips']))
                                            ({{ implode(', ', $find['hosts_ips']) }})
                                        @endif
                                    </div>
                                </td>
                                <td style="text-align: center;"><span
                                        class="badge {{ $find['severity'] }}">{{ $find['severity'] }}</span></td>
                                <td style="font-size: 9px; color: #475569;">{{ $find['impact'] }}</td>
                                <td style="font-size: 9px; color: #065f46; font-style: italic;">
                                    {{ $find['recommendation'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (!empty($agents))
            <div class="section">
                <h2 class="section-title">vPenTest Agent Infrastructure</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>IP Address</th>
                            <th>Operating System</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                            <tr>
                                <td><strong>{{ $agent['name'] }}</strong></td>
                                <td>{{ $agent['ip'] }}</td>
                                <td>{{ $agent['os'] }}</td>
                                <td style="text-align: center;">
                                    <span class="badge {{ $agent['status'] === 'active' ? 'low' : 'informational' }}">
                                        {{ $agent['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <script>
            (function() {
                try {
                    const ctx = document.getElementById('severityChart').getContext('2d');
                    const counts = {!! json_encode($severity_distribution) !!};

                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Critical', 'High', 'Medium', 'Low', 'Informational'],
                            datasets: [{
                                data: [
                                    counts.critical || 0,
                                    counts.high || 0,
                                    counts.medium || 0,
                                    counts.low || 0,
                                    counts.informational || 0
                                ],
                                backgroundColor: [
                                    '#fee2e2', // critical
                                    '#ffedd5', // high
                                    '#fef9c3', // medium
                                    '#f0fdf4', // low
                                    '#f0f9ff' // informational
                                ],
                                borderColor: [
                                    '#ef4444',
                                    '#f97316',
                                    '#eab308',
                                    '#22c55e',
                                    '#3b82f6'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            animation: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            cutout: '70%'
                        }
                    });

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
