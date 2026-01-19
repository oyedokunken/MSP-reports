<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Autotask {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary - {{ $customer->canonical_name }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: {{ (isset($render_part) && $render_part === 'cover') ? '0' : '20px 10px' }};
            font-size: 12px;
        }
        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e5e5; text-align: center; }
        .header .company-name { font-size: 16px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; display: none; }
        .header h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; color: #0f172a; letter-spacing: -0.02em; }
        .header .meta { font-size: 12px; color: #64748b; font-weight: 500; }
        
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
        .section { margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 45px; }
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
        .stat-box .stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; font-weight: 600; }
        .stat-box .stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
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
        h3 { font-size: 14px; font-weight: 700; margin: 45px 0 20px 0; color: #1e293b; text-transform: uppercase; letter-spacing: 0.025em; }
        .alert { 
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
        .alert.critical { border-left-color: #ef4444; }
        .alert.warning { border-left-color: #f59e0b; }
        .alert-title { font-weight: 700; font-size: 12px; margin-bottom: 4px; color: #0f172a; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 600;
            border-radius: 3px;
        }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        .progress-bar { background: #e5e5e5; height: 8px; border-radius: 4px; margin-top: 5px; overflow: hidden; }
        .progress-fill { background: #1a1a1a; height: 100%; }

        /* Report Content Background Shapes */
        .report-bg-shape-1 {
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: #1e293b;
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
            background: #1e293b;
            opacity: 0.015;
            clip-path: polygon(0 100%, 100% 100%, 0 0);
            z-index: -1;
            pointer-events: none;
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
        <h1>Autotask {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</h1>
        {{-- <div class="company-name">{{ is_array($branding) ? ($branding['company_name'] ?? 'Territory Technology Solutions') : 'Territory Technology Solutions' }}</div> --}}
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    @if(!isset($sections_to_render) || in_array('executive_summary', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Executive Summary</h2>
        <table style="margin-bottom: 30px;">
            <tbody>
                <tr>
                    <td style="width: 20%; background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Service Overview</td>
                    <td style="width: 30%;">
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{{ $executive_summary['tickets']['total'] }} Tickets</div>
                        <div style="font-size: 10px; color: #64748b;">{{ $executive_summary['tickets']['completed'] }} Completed, {{ $executive_summary['tickets']['open'] }} Open</div>
                    </td>
                    <td style="width: 20%; background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Time Allocation</td>
                    <td style="width: 30%;">
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{{ number_format($executive_summary['time']['total_hours'], 1) }} Hours</div>
                        <div style="font-size: 10px; color: #64748b;">{{ number_format($executive_summary['time']['billable_hours'], 1) }} Billable ({{ $executive_summary['time']['billing_efficiency'] }}%)</div>
                    </td>
                </tr>
                <tr>
                    <td style="background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Infrastructure</td>
                    <td>
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{{ $executive_summary['contracts']['total'] }} Active Contracts</div>
                        @if($executive_summary['contracts']['expiring_soon'] > 0)
                            <div style="font-size: 10px; color: #ef4444; font-weight: 600;">{{ $executive_summary['contracts']['expiring_soon'] }} Expiring within 30 days</div>
                        @else
                            <div style="font-size: 10px; color: #10b981;">All contracts are up to date</div>
                        @endif
                    </td>
                    <td style="background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Operational Health</td>
                    <td>
                        @if($executive_summary['total_alerts'] > 0)
                            <span class="badge {{ $executive_summary['total_alerts'] > 2 ? 'danger' : 'warning' }}" style="font-size: 11px; padding: 4px 10px;">
                                {{ $executive_summary['total_alerts'] }} Active Alerts
                            </span>
                        @else
                            <span class="badge success" style="font-size: 11px; padding: 4px 10px;">System Healthy</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="summary-chart-container">
        <div class="chart-header">
            <div class="chart-title">Ticket Activity Trend</div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #3b82f6;"></div>
                    <span>Created</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span>Completed</span>
                </div>
            </div>
        </div>
        <div style="width: 100%; height: 250px;">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>

    <script>
        (function() {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            const data = @json($executive_summary['daily_trends']);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Created',
                            data: data.created,
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                            borderWidth: 0
                        },
                        {
                            label: 'Completed',
                            data: data.completed,
                            backgroundColor: '#10b981',
                            borderRadius: 4,
                            borderWidth: 0
                        }
                    ]
                },
                options: {
                    animation: false,
                    responsive: true,
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
        })();
    </script>
    @endif

    @if((!isset($sections_to_render) || in_array('alerts', $sections_to_render) || in_array('all', $sections_to_render)) && count($alerts) > 0)
    <div class="section">
        <h2 class="section-title">Alerts & Recommendations</h2>
        @foreach($alerts as $alert)
        <div class="alert {{ $alert['severity'] }}">
            <div class="alert-title">{{ $alert['title'] }}</div>
            <div>{{ $alert['description'] }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('tickets', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Ticket Summary</h2>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Tickets</div>
                <div class="stat-value">{{ $tickets['summary']['total_tickets'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Completed</div>
                <div class="stat-value">{{ $tickets['summary']['completed_tickets'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Open</div>
                <div class="stat-value">{{ $tickets['summary']['open_tickets'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Avg Resolution</div>
                <div class="stat-value">{{ $tickets['summary']['avg_resolution_hours'] }}h</div>
            </div>
        </div>

        @if(count($tickets['summary']['top_issues']) > 0)
        <h3>Top Issues</h3>
        <table>
            <thead>
                <tr>
                    <th>Issue</th>
                    <th style="text-align: center; width: 100px;">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets['summary']['top_issues'] as $issue)
                <tr>
                    <td>{{ $issue['title'] }}</td>
                    <td style="text-align: center;">
                        <span class="badge info">{{ $issue['count'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(count($tickets['data']) > 0)
        <h3>Recent Tickets</h3>
        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Created</th>
                    <th>Completed</th>
                    <th style="text-align: right;">Hours</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($tickets['data'], 0, 10) as $ticket)
                <tr>
                    <td><strong>{{ $ticket['ticket_number'] }}</strong></td>
                    <td>{{ Str::limit($ticket['title'], 40) }}</td>
                    <td>
                        @if($ticket['status_label'] === 'Complete')
                            <span class="badge success">{{ $ticket['status_label'] }}</span>
                        @elseif($ticket['status_label'] === 'In Progress')
                            <span class="badge info">{{ $ticket['status_label'] }}</span>
                        @else
                            <span class="badge warning">{{ $ticket['status_label'] }}</span>
                        @endif
                    </td>
                    <td>
                        @if($ticket['priority_label'] === 'Critical')
                            <span class="badge danger">{{ $ticket['priority_label'] }}</span>
                        @elseif($ticket['priority_label'] === 'High')
                            <span class="badge warning">{{ $ticket['priority_label'] }}</span>
                        @else
                            {{ $ticket['priority_label'] }}
                        @endif
                    </td>
                    <td>{{ $ticket['created_date'] }}</td>
                    <td>{{ $ticket['completed_date'] ?? 'In Progress' }}</td>
                    <td style="text-align: right;">{{ $ticket['resolution_hours'] ? number_format($ticket['resolution_hours'], 1) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif

    @if(!isset($sections_to_render) || in_array('time_entries', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="section">
        <h2 class="section-title">Time & Billing Summary</h2>
        
        <table style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <td style="width: 20%; background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Total Hours</td>
                    <td style="width: 30%; font-size: 16px; font-weight: 700;">{{ number_format($time_entries['summary']['total_hours'], 1) }} h</td>
                    <td style="width: 20%; background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Billable Hours</td>
                    <td style="width: 30%; font-size: 16px; font-weight: 700; color: #3b82f6;">{{ number_format($time_entries['summary']['billable_hours'], 1) }} h</td>
                </tr>
                <tr>
                    <td style="background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Non-Billable</td>
                    <td style="font-size: 16px; font-weight: 700;">{{ number_format($time_entries['summary']['non_billable_hours'], 1) }} h</td>
                    <td style="background: #f8fafc; font-weight: 600; color: #64748b; font-size: 10px; text-transform: uppercase;">Efficiency</td>
                    <td>
                        <span class="badge {{ $time_entries['summary']['billing_efficiency'] >= 80 ? 'success' : ($time_entries['summary']['billing_efficiency'] >= 60 ? 'warning' : 'danger') }}" style="font-size: 14px; padding: 4px 12px;">
                            {{ $time_entries['summary']['billing_efficiency'] }}%
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="background: #ffffff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Resource Utilization Efficiency</div>
                <div style="font-size: 12px; font-weight: 700; color: #0f172a;">{{ $time_entries['summary']['billing_efficiency'] }}% Target Achievement</div>
            </div>
            <div class="progress-bar" style="height: 12px; background: #f1f5f9; border-radius: 6px;">
                <div class="progress-fill" style="width: {{ $time_entries['summary']['billing_efficiency'] }}%; background: {{ $time_entries['summary']['billing_efficiency'] >= 80 ? '#10b981' : ($time_entries['summary']['billing_efficiency'] >= 60 ? '#f59e0b' : '#ef4444') }}; border-radius: 6px;"></div>
            </div>
            <div style="margin-top: 10px; font-size: 10px; color: #94a3b8; font-style: italic;">
                * Efficiency is calculated based on billable hours vs total hours worked during the reporting period.
            </div>
        </div>
    </div>
    @endif

    @if((!isset($sections_to_render) || in_array('technicians', $sections_to_render) || in_array('all', $sections_to_render)) && count($technicians['data']) > 0)
    <div class="section">
        <h2 class="section-title">Technician Performance</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Technician</th>
                    <th>Title</th>
                    <th style="text-align: center;">Total Hours</th>
                    <th style="text-align: center;">Billable Hours</th>
                    <th style="text-align: center;">Tickets Resolved</th>
                    <th style="text-align: center;">Efficiency</th>
                </tr>
            </thead>
            <tbody>
                @foreach($technicians['data'] as $tech)
                <tr>
                    <td><strong>{{ $tech['name'] }}</strong></td>
                    <td>{{ $tech['title'] }}</td>
                    <td style="text-align: center;">{{ number_format($tech['total_hours'], 1) }}</td>
                    <td style="text-align: center;">{{ number_format($tech['billable_hours'], 1) }}</td>
                    <td style="text-align: center;">
                        <span class="badge info">{{ $tech['tickets_resolved'] }}</span>
                    </td>
                    <td style="text-align: center;">
                        @if($tech['billing_efficiency'] >= 80)
                            <span class="badge success">{{ $tech['billing_efficiency'] }}%</span>
                        @elseif($tech['billing_efficiency'] >= 60)
                            <span class="badge warning">{{ $tech['billing_efficiency'] }}%</span>
                        @else
                            <span class="badge danger">{{ $tech['billing_efficiency'] }}%</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if((!isset($sections_to_render) || in_array('contracts', $sections_to_render) || in_array('all', $sections_to_render)) && count($contracts['data']) > 0)
    <div class="section">
        <h2 class="section-title">Contracts Overview</h2>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Contracts</div>
                <div class="stat-value">{{ $contracts['summary']['total_contracts'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Active</div>
                <div class="stat-value">{{ $contracts['summary']['active_contracts'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-value">{{ $contracts['summary']['expiring_soon'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Value</div>
                <div class="stat-value">${{ number_format($contracts['summary']['total_value'], 0) }}</div>
            </div>
        </div>

        <h3>Contract Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Contract Name</th>
                    <th>Number</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th style="text-align: right;">Est. Hours</th>
                    <th style="text-align: right;">Est. Revenue</th>
                    <th style="text-align: center;">Days Left</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contracts['data'] as $contract)
                <tr>
                    <td>
                        <strong>{{ $contract['contract_name'] }}</strong>
                        @if($contract['is_default_contract'])
                            <span class="badge info" style="margin-left: 5px;">Default</span>
                        @endif
                    </td>
                    <td>{{ $contract['contract_number'] ?? 'N/A' }}</td>
                    <td>{{ $contract['start_date'] }}</td>
                    <td>{{ $contract['end_date'] }}</td>
                    <td style="text-align: right;">{{ number_format($contract['estimated_hours'], 1) }}</td>
                    <td style="text-align: right;">${{ number_format($contract['estimated_revenue'], 0) }}</td>
                    <td style="text-align: center;">
                        @if($contract['days_until_expiry'] !== null)
                            @if($contract['days_until_expiry'] <= 0)
                                <span class="badge danger">Expired</span>
                            @elseif($contract['days_until_expiry'] <= 30)
                                <span class="badge warning">{{ $contract['days_until_expiry'] }}d</span>
                            @else
                                {{ $contract['days_until_expiry'] }}d
                            @endif
                        @else
                            N/A
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if($contract['status'] === 'expired')
                            <span class="badge danger">Expired</span>
                        @elseif($contract['status'] === 'expiring_soon')
                            <span class="badge warning">Expiring Soon</span>
                        @else
                            <span class="badge success">Active</span>
                        @endif
                    </td>
                </tr>
                @if($contract['description'])
                <tr>
                    <td colspan="8" style="font-size: 10px; color: #666; padding-top: 0;">
                        {{ Str::limit($contract['description'], 100) }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- <div class="footer">
        Autotask Monthly Summary • Data sourced from Autotask
    </div> -->
    @endif
</body>
</html>
