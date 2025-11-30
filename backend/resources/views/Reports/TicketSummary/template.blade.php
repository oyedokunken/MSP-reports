<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Summary - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1a1a1a;
            font-size: 10pt;
            line-height: 1.5;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 15mm;
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 10mm;
        }
        
        .header-left h1 {
            font-size: 24pt;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 3mm;
        }
        
        .header-left .subtitle {
            font-size: 11pt;
            color: #666;
            font-weight: 400;
        }
        
        .header-right {
            text-align: right;
            font-size: 9pt;
            color: #666;
        }
        
        .header-right .label {
            font-weight: 500;
            color: #999;
            text-transform: uppercase;
            font-size: 7pt;
            letter-spacing: 0.5px;
        }
        
        .header-right .value {
            color: #1a1a1a;
            margin-bottom: 2mm;
        }
        
        /* Metrics Grid */
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5mm;
            margin-bottom: 10mm;
        }
        
        .metric {
            background: #fafafa;
            padding: 5mm;
            border-radius: 2mm;
        }
        
        .metric-label {
            font-size: 8pt;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2mm;
            font-weight: 500;
        }
        
        .metric-value {
            font-size: 20pt;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1;
        }
        
        .metric-unit {
            font-size: 9pt;
            color: #666;
            font-weight: 400;
            margin-left: 1mm;
        }
        
        /* Section */
        .section {
            margin-bottom: 8mm;
        }
        
        .section-title {
            font-size: 12pt;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #e5e5e5;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        
        th {
            text-align: left;
            font-size: 8pt;
            font-weight: 500;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2mm 2mm;
            border-bottom: 1px solid #e5e5e5;
        }
        
        td {
            padding: 3mm 2mm;
            font-size: 9pt;
            color: #1a1a1a;
            border-bottom: 1px solid #f5f5f5;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Two Column Layout */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5mm;
            margin-bottom: 8mm;
        }
        
        .breakdown {
            background: #fafafa;
            padding: 5mm;
            border-radius: 2mm;
        }
        
        .breakdown-title {
            font-size: 10pt;
            font-weight: 600;
            margin-bottom: 3mm;
            color: #1a1a1a;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 2mm 0;
            font-size: 9pt;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .breakdown-label {
            color: #666;
        }
        
        .breakdown-value {
            font-weight: 500;
            color: #1a1a1a;
        }
        
        /* Footer */
        .footer {
            margin-top: 15mm;
            padding-top: 3mm;
            border-top: 1px solid #e5e5e5;
            font-size: 8pt;
            color: #999;
            display: flex;
            justify-content: space-between;
        }
        
        @media print {
            .page { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Ticket Summary</h1>
                <div class="subtitle">{{ $customer->canonical_name ?? $customer->name }}</div>
            </div>
            <div class="header-right">
                <div><span class="label">Period</span></div>
                <div class="value">{{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
                <div><span class="label">Generated</span></div>
                <div class="value">{{ \Carbon\Carbon::parse($generated_at)->format('M j, Y') }}</div>
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="metrics">
            <div class="metric">
                <div class="metric-label">Total Tickets</div>
                <div class="metric-value">{{ $data['summary']['total_tickets'] ?? '0' }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Completed</div>
                <div class="metric-value">{{ $data['summary']['completed_tickets'] ?? '0' }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Open</div>
                <div class="metric-value">{{ $data['summary']['open_tickets'] ?? '0' }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Avg Resolution</div>
                <div class="metric-value">{{ number_format($data['summary']['avg_resolution_hours'] ?? 0, 1) }}<span class="metric-unit">hrs</span></div>
            </div>
        </div>
        
        <!-- Support Hours -->
        <div class="section">
            <h2 class="section-title">Support Hours</h2>
            <div class="metrics">
                <div class="metric">
                    <div class="metric-label">Total Hours</div>
                    <div class="metric-value">{{ $data['summary']['total_hours'] ?? '0' }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Billable</div>
                    <div class="metric-value">{{ $data['summary']['billable_hours'] ?? '0' }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Non-Billable</div>
                    <div class="metric-value">{{ $data['summary']['non_billable_hours'] ?? '0' }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Efficiency</div>
                    <div class="metric-value">{{ $data['summary']['billing_efficiency'] ?? '0' }}<span class="metric-unit">%</span></div>
                </div>
            </div>
        </div>
        
        <!-- Priority & Status -->
        <div class="two-col">
            <div class="breakdown">
                <div class="breakdown-title">Priority Breakdown</div>
                @if(!empty($data['priority_breakdown']))
                    @foreach($data['priority_breakdown'] as $priority => $count)
                    <div class="breakdown-item">
                        <span class="breakdown-label">{{ ucfirst($priority) }}</span>
                        <span class="breakdown-value">{{ $count }}</span>
                    </div>
                    @endforeach
                @else
                    <div class="breakdown-item">
                        <span class="breakdown-label">No data</span>
                        <span class="breakdown-value">—</span>
                    </div>
                @endif
            </div>
            
            <div class="breakdown">
                <div class="breakdown-title">Status Breakdown</div>
                @if(!empty($data['status_breakdown']))
                    @foreach($data['status_breakdown'] as $status => $count)
                    <div class="breakdown-item">
                        <span class="breakdown-label">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                        <span class="breakdown-value">{{ $count }}</span>
                    </div>
                    @endforeach
                @else
                    <div class="breakdown-item">
                        <span class="breakdown-label">No data</span>
                        <span class="breakdown-value">—</span>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Top Issues -->
        @if(!empty($data['top_issues']) && count($data['top_issues']) > 0)
        <div class="section">
            <h2 class="section-title">Top Issues</h2>
            <table>
                <thead>
                    <tr>
                        <th>Issue</th>
                        <th style="text-align: right; width: 80px;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['top_issues'] as $issue)
                    <tr>
                        <td>{{ $issue['title'] }}</td>
                        <td style="text-align: right;">{{ $issue['count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <!-- Technician Activity -->
        @if(!empty($data['technicians']) && count($data['technicians']) > 0)
        <div class="section">
            <h2 class="section-title">Technician Activity</h2>
            <table>
                <thead>
                    <tr>
                        <th>Technician</th>
                        <th>Role</th>
                        <th style="text-align: right; width: 80px;">Total Hrs</th>
                        <th style="text-align: right; width: 80px;">Billable</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['technicians'] as $tech)
                    <tr>
                        <td>{{ $tech['name'] }}</td>
                        <td>{{ $tech['title'] ?? 'N/A' }}</td>
                        <td style="text-align: right;">{{ $tech['total_hours'] }}</td>
                        <td style="text-align: right;">{{ $tech['billable_hours'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer">
            <div>{{ $branding['company_name'] ?? 'Company' }} • {{ $branding['footer_text'] ?? 'Confidential' }}</div>
            <div>{{ now()->format('F j, Y') }}</div>
        </div>
    </div>
</body>
</html>
