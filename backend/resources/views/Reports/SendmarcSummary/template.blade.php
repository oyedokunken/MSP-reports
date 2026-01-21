<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sendmarc {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary - {{ $customer->canonical_name }}</title>
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
            margin-bottom: 35px; 
            padding: 25px; 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
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
        .recommendation-card.warning { border-left-color: #f59e0b; }
        .recommendation-card.info { border-left-color: #3b82f6; }
        .recommendation-card .rec-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; color: #0f172a; }
        
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
        
        .page-break { page-break-before: always; }

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
        <h1>Sendmarc {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</h1>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    @if(!isset($sections_to_render) || in_array('executive_summary', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Executive Summary</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Emails</div>
                <div class="stat-value">{{ $executive_summary['total_emails'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Compliance Rate</div>
                <div class="stat-value" style="color: {{ ($executive_summary['compliance_rate'] ?? 0) >= 90 ? '#10b981' : '#f59e0b' }}">{{ $executive_summary['compliance_rate'] ?? 0 }}%</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Authorized Senders</div>
                <div class="stat-value">{{ $executive_summary['authorized_senders'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">DMARC Failures</div>
                <div class="stat-value" style="color: {{ ($executive_summary['complete_failures'] ?? 0) > 0 ? '#ef4444' : '#10b981' }}">{{ $executive_summary['complete_failures'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('compliance', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">DMARC Compliance Overview</h2>
        <div class="summary-chart-container">
            <div class="chart-header">
                <div class="chart-title">Authentication Results</div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #10b981;"></div>
                        <span>Complete Pass</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f59e0b;"></div>
                        <span>Partial Pass</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #ef4444;"></div>
                        <span>Complete Fail</span>
                    </div>
                </div>
            </div>
            <div style="width: 100%; height: 180px;">
                <canvas id="complianceChart"></canvas>
            </div>
        </div>
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('volume_timeline', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Email Volume Timeline</h2>
        @if(!empty($volume_timeline) && count($volume_timeline) > 0)
        <div class="summary-chart-container">
            <div class="chart-header">
                <div class="chart-title">Daily Email Activity</div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3b82f6;"></div>
                        <span>Total Volume</span>
                    </div>
                </div>
            </div>
            <div style="width: 100%; height: 180px;">
                <canvas id="timelineChart"></canvas>
            </div>
        </div>
        @else
        <div class="stat-box" style="min-height: 100px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center;">
            <div style="text-align: center; padding: 20px;">
                <p style="font-weight: 600; color: #64748b; margin-bottom: 4px;">No Activity Detected</p>
                <p style="color: #94a3b8; font-size: 11px;">We couldn't find any email volume data for the selected period. This usually means no emails were processed by Sendmarc during this timeframe.</p>
            </div>
        </div>
        @endif
    </div>
    @endif


    @if(!isset($sections_to_render) || in_array('senders', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="page-break"></div>
    <div class="section">
        <h2 class="section-title">Sender Analysis</h2>
        @if(!empty($top_senders) && is_array($top_senders) && count($top_senders) > 0)
        <table>
            <thead>
                <tr>
                    <th>Sender Organisation</th>
                    <th>Category</th>
                    <th style="text-align: center;">Total</th>
                    <th style="text-align: center;">Passes</th>
                    <th style="text-align: center;">Failures</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top_senders as $s)
                <tr>
                    <td><strong>{{ $s['sender'] ?? 'Unknown' }}</strong></td>
                    <td style="text-transform: capitalize;">{{ $s['category'] ?? '—' }}</td>
                    <td style="text-align: center;">{{ number_format($s['total'] ?? 0) }}</td>
                    <td style="text-align: center; color: #10b981;">{{ number_format($s['passes'] ?? 0) }}</td>
                    <td style="text-align: center; color: {{ ($s['failures'] ?? 0) > 0 ? '#ef4444' : '#334155' }}">{{ number_format($s['failures'] ?? 0) }}</td>
                    <td style="text-align: center;">
                        @if(($s['category'] ?? '') === 'authorised compliant')
                            <span class="badge success">Authorized</span>
                        @elseif(($s['blacklisted'] ?? 0) > 0)
                            <span class="badge danger">Blacklisted</span>
                        @else
                            <span class="badge warning">Unauthorized</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="stat-box" style="min-height: 60px;">
            <p style="color: #94a3b8;">No sender data available for this period.</p>
        </div>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('ip_sources', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Source IP Analysis</h2>
        @if(!empty($ip_sources) && is_array($ip_sources) && count($ip_sources) > 0)
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Organisation / Service</th>
                    <th style="text-align: center;">Total</th>
                    <th style="text-align: center;">Passes</th>
                    <th style="text-align: center;">Failures</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ip_sources as $ip)
                <tr>
                    <td><strong>{{ $ip['ip'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $ip['organisation'] ?? '—' }} {{ $ip['service'] ? '('.$ip['service'].')' : '' }}</td>
                    <td style="text-align: center;">{{ number_format($ip['total'] ?? 0) }}</td>
                    <td style="text-align: center; color: #10b981;">{{ number_format($ip['passes'] ?? 0) }}</td>
                    <td style="text-align: center; color: {{ ($ip['failures'] ?? 0) > 0 ? '#ef4444' : '#334155' }}">{{ number_format($ip['failures'] ?? 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="stat-box" style="min-height: 60px;">
            <p style="color: #94a3b8;">No source IP data available for this period.</p>
        </div>
        @endif
    </div>
    @endif

    @if((!isset($sections_to_render) || in_array('recommendations', $sections_to_render) || in_array('all', $sections_to_render)) && !empty($alerts) && is_array($alerts))
    <div class="section">
        <h2 class="section-title">Analysis & Recommendations</h2>
        @foreach($alerts as $rec)
        <div class="recommendation-card {{ str_contains(strtolower($rec), 'blacklisted') || str_contains(strtolower($rec), 'high') ? 'critical' : 'info' }}">
            <div class="rec-title">{{ $rec }}</div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        Sendmarc {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary • Data sourced from Sendmarc • {{ now()->format('F d, Y') }}
    </div>

    <script>
        (function() {
            try {
                @if(!isset($sections_to_render) || in_array('compliance', $sections_to_render) || in_array('all', $sections_to_render))
                // Compliance Chart
                const compCtx = document.getElementById('complianceChart').getContext('2d');
                new Chart(compCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Complete Pass', 'Partial Pass', 'Complete Fail'],
                        datasets: [{
                            data: [
                                {{ $executive_summary['complete_passes'] ?? 0 }},
                                {{ $executive_summary['partial_passes'] ?? 0 }},
                                {{ $executive_summary['complete_failures'] ?? 0 }}
                            ],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                            borderRadius: 4,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
                        }
                    }
                });
                @endif

                @if(!isset($sections_to_render) || in_array('volume_timeline', $sections_to_render) || in_array('all', $sections_to_render))
                // Timeline Chart
                const timeCtx = document.getElementById('timelineChart').getContext('2d');
                const timeData = @json($volume_timeline ?? []);
                
                new Chart(timeCtx, {
                    type: 'bar',
                    data: {
                        labels: timeData.map(d => d.date),
                        datasets: [{
                            label: 'Total Volume',
                            data: timeData.map(d => d.total),
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
                        }
                    }
                });
                @endif

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

