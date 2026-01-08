<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Autotask {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary - {{ $customer->canonical_name }}</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-box { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; text-align: center; }
        .stat-box .stat-label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .stat-box .stat-value { font-size: 28px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        h3 { font-size: 14px; font-weight: 600; margin: 20px 0 10px 0; }
        .alert { padding: 12px 15px; margin-bottom: 10px; border-left: 3px solid; background: #f9fafb; }
        .alert.critical { border-color: #dc2626; }
        .alert.warning { border-color: #f59e0b; }
        .alert-title { font-weight: 600; font-size: 11px; margin-bottom: 3px; }
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
        /* .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; font-size: 10px; color: #9ca3af; } */
    </style>
</head>
<body>
    <div class="header">
        <h1>Autotask {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</h1>
        <div class="company-name">{{ is_array($branding) ? ($branding['company_name'] ?? 'Territory Technology Solutions') : 'Territory Technology Solutions' }}</div>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    @if(!isset($sections_to_render) || in_array('executive_summary', $sections_to_render) || in_array('all', $sections_to_render))
    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ $executive_summary['tickets']['total'] }}</div>
            <div class="label">Total Tickets</div>
            <div class="subvalue">
                {{ $executive_summary['tickets']['completed'] }} Completed, 
                {{ $executive_summary['tickets']['open'] }} Open
            </div>
        </div>
        <div class="summary-card">
            <div class="number">{{ number_format($executive_summary['time']['total_hours'], 1) }}</div>
            <div class="label">Total Hours</div>
            <div class="subvalue">
                {{ number_format($executive_summary['time']['billable_hours'], 1) }} Billable
            </div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['time']['billing_efficiency'] }}%</div>
            <div class="label">Billing Efficiency</div>
            <div class="subvalue">Billable vs Total Hours</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['contracts']['total'] }}</div>
            <div class="label">Active Contracts</div>
            @if($executive_summary['contracts']['expiring_soon'] > 0)
                <div class="subvalue">{{ $executive_summary['contracts']['expiring_soon'] }} Expiring Soon</div>
            @endif
        </div>
    </div>
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
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Hours</div>
                <div class="stat-value">{{ number_format($time_entries['summary']['total_hours'], 1) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Billable Hours</div>
                <div class="stat-value">{{ number_format($time_entries['summary']['billable_hours'], 1) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Non-Billable</div>
                <div class="stat-value">{{ number_format($time_entries['summary']['non_billable_hours'], 1) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Efficiency</div>
                <div class="stat-value">{{ $time_entries['summary']['billing_efficiency'] }}%</div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <div style="font-size: 11px; color: #666; margin-bottom: 5px;">Billing Efficiency</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {{ $time_entries['summary']['billing_efficiency'] }}%"></div>
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
</body>
</html>
