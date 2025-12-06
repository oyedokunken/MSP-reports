<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autotask Monthly Summary - {{ $customer->canonical_name }}</title>
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
        
        /* Executive Summary - Clean boxes */
        .executive-summary {
            margin-bottom: 30px;
        }
        
        .executive-summary h2 {
            font-size: 14pt;
            font-weight: 600;
            color: #111827;
            margin-bottom: 15px;
            border-bottom: 2px solid #111827;
            padding-bottom: 5px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-card {
            border: 1px solid #e5e7eb;
            padding: 15px;
            background: #fafafa;
        }
        
        .summary-card .label {
            font-size: 9pt;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .summary-card .value {
            font-size: 24pt;
            font-weight: 600;
            color: #111827;
            line-height: 1;
        }
        
        .summary-card .subvalue {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 5px;
        }
        
        /* Alerts - Minimal */
        .alerts {
            margin-bottom: 25px;
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-left: 3px solid;
            background: #fafafa;
        }
        
        .alert.critical {
            border-color: #dc2626;
        }
        
        .alert.warning {
            border-color: #f59e0b;
        }
        
        .alert-title {
            font-weight: 600;
            font-size: 10pt;
            margin-bottom: 3px;
        }
        
        .alert div:last-child {
            font-size: 9pt;
            color: #6b7280;
        }
        
        /* Section Headers */
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-header h2 {
            font-size: 13pt;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
            border-bottom: 2px solid #111827;
            padding-bottom: 5px;
        }
        
        /* Stats Grid - Minimal */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .stat-box {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: center;
            background: #fafafa;
        }
        
        .stat-box .stat-label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .stat-box .stat-value {
            font-size: 18pt;
            font-weight: 600;
            color: #111827;
        }
        
        /* Tables - Clean and minimal */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9pt;
        }
        
        table thead {
            background: #111827;
            color: white;
        }
        
        table th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        table tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        /* Badge - Minimal */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: 600;
            border: 1px solid;
        }
        
        .badge.success {
            background: white;
            color: #065f46;
            border-color: #065f46;
        }
        
        .badge.warning {
            background: white;
            color: #f59e0b;
            border-color: #f59e0b;
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
        
        h3 {
            font-size: 11pt;
            font-weight: 600;
            color: #111827;
            margin: 15px 0 10px 0;
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
                    <h1>Autotask Monthly Summary</h1>
                    <div class="subtitle">{{ $customer->canonical_name }}</div>
                    <div class="subtitle">{{ $period['formatted'] }}</div>
                </div>
            </div>
        </div>

        <!-- Executive Summary -->
        <div class="executive-summary">
            <h2>Executive Summary</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="label">Total Tickets</div>
                    <div class="value">{{ $executive_summary['tickets']['total'] }}</div>
                    <div class="subvalue">
                        {{ $executive_summary['tickets']['completed'] }} Completed, 
                        {{ $executive_summary['tickets']['open'] }} Open
                    </div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Hours</div>
                    <div class="value">{{ number_format($executive_summary['time']['total_hours'], 1) }}</div>
                    <div class="subvalue">
                        {{ number_format($executive_summary['time']['billable_hours'], 1) }} Billable
                    </div>
                </div>
                <div class="summary-card">
                    <div class="label">Billing Efficiency</div>
                    <div class="value">{{ $executive_summary['time']['billing_efficiency'] }}%</div>
                    <div class="subvalue">Billable vs Total Hours</div>
                </div>
                <div class="summary-card">
                    <div class="label">Active Contracts</div>
                    <div class="value">{{ $executive_summary['contracts']['total'] }}</div>
                    @if($executive_summary['contracts']['expiring_soon'] > 0)
                        <div class="subvalue">{{ $executive_summary['contracts']['expiring_soon'] }} Expiring Soon</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Alerts -->
        @if(count($alerts) > 0)
        <div class="alerts">
            <div class="section-header">
                <h2>Alerts & Recommendations</h2>
            </div>
            @foreach($alerts as $alert)
            <div class="alert {{ $alert['severity'] }}">
                <div class="alert-title">{{ $alert['title'] }}</div>
                <div>{{ $alert['description'] }}</div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Ticket Summary -->
        <div class="section">
            <div class="section-header">
                <h2>Ticket Summary</h2>
            </div>
            
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
                        <td>{{ $ticket['completed_date'] ?? '-' }}</td>
                        <td style="text-align: right;">{{ $ticket['resolution_hours'] ? number_format($ticket['resolution_hours'], 1) : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <!-- Time & Billing Summary -->
        <div class="section">
            <div class="section-header">
                <h2>Time & Billing Summary</h2>
            </div>
            
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
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Billing Efficiency</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $time_entries['summary']['billing_efficiency'] }}%"></div>
                </div>
            </div>
        </div>

        <!-- Technician Performance -->
        @if(count($technicians['data']) > 0)
        <div class="section">
            <div class="section-header">
                <h2>Technician Performance</h2>
            </div>
            
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

        <!-- Contracts Overview -->
        @if(count($contracts['data']) > 0)
        <div class="section">
            <div class="section-header">
                <h2>Contracts Overview</h2>
            </div>
            
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
                        <td colspan="8" style="font-size: 8pt; color: #6b7280; padding-top: 0;">
                            {{ Str::limit($contract['description'], 100) }}
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</body>
</html>
