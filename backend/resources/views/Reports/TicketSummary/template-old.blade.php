<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Summary - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: #1e293b;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 0;
            margin: 0 auto;
            background: white;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        /* Cover Page */
        .cover-page {
            width: 210mm;
            height: 297mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            position: relative;
            overflow: hidden;
            page-break-after: always;
        }
        
        .cover-page::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .cover-page::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 800px;
            height: 800px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }
        
        .cover-content {
            text-align: center;
            z-index: 1;
            padding: 50px;
        }
        
        .cover-logo {
            max-width: 300px;
            max-height: 120px;
            margin-bottom: 60px;
            filter: brightness(0) invert(1);
        }
        
        .cover-title {
            font-size: 56px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        
        .cover-subtitle {
            font-size: 28px;
            font-weight: 300;
            margin-bottom: 60px;
            opacity: 0.9;
        }
        
        .cover-customer {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 40px;
            padding: 20px 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .cover-meta {
            font-size: 18px;
            opacity: 0.85;
            margin-bottom: 10px;
        }
        
        .cover-footer-text {
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 14px;
            opacity: 0.7;
        }
        
        /* Header */
        .header-section {
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            padding: 40px 50px;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .company-logo, .customer-logo {
            max-height: 50px;
            max-width: 180px;
            object-fit: contain;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .report-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .report-subtitle {
            font-size: 16px;
            opacity: 0.95;
            font-weight: 400;
        }
        
        /* Metadata Bar */
        .metadata-bar {
            background: #f8fafc;
            border-left: 4px solid {{ $branding['primary_color'] ?? '#1e40af' }};
            padding: 25px 50px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .meta-value {
            font-size: 14px;
            color: #0f172a;
            font-weight: 500;
        }
        
        /* Content */
        .content {
            padding: 40px 50px;
        }
        
        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .metric-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .metric-card.success {
            border-left: 4px solid #10b981;
        }
        
        .metric-card.warning {
            border-left: 4px solid #f59e0b;
        }
        
        .metric-card.info {
            border-left: 4px solid #3b82f6;
        }
        
        .metric-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .metric-unit {
            font-size: 14px;
            color: #64748b;
            font-weight: 400;
        }
        
        /* Section Headers */
        .section-header {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        /* Two Column Layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Priority/Status Breakdown */
        .breakdown-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }
        
        .breakdown-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .breakdown-label {
            font-size: 14px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .breakdown-value {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .priority-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .priority-critical { background: #ef4444; }
        .priority-high { background: #f59e0b; }
        .priority-medium { background: #3b82f6; }
        .priority-low { background: #10b981; }
        
        /* Categories Table */
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .categories-table thead {
            background: #f8fafc;
        }
        
        .categories-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .categories-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .categories-table tr:last-child td {
            border-bottom: none;
        }
        
        .categories-table tr:hover {
            background: #f8fafc;
        }
        
        .category-bar {
            height: 8px;
            background: linear-gradient(90deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            border-radius: 4px;
            margin-top: 4px;
        }
        
        /* Trends Chart */
        .trends-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .trend-bars {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 200px;
            margin-top: 20px;
        }
        
        .trend-bar-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .trend-bars-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
            max-width: 60px;
        }
        
        .trend-bar {
            width: 100%;
            background: linear-gradient(90deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            border-radius: 4px;
        }
        
        .trend-bar.closed {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .trend-label {
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
        
        /* Footer */
        .footer {
            background: #0f172a;
            color: white;
            padding: 30px 50px;
            margin-top: auto;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-left {
            flex: 1;
        }
        
        .footer-company {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .footer-text {
            font-size: 12px;
            opacity: 0.7;
            line-height: 1.6;
        }
        
        .footer-right {
            text-align: right;
            font-size: 11px;
            opacity: 0.6;
        }
        
        .page-number {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        /* Print Optimization */
        @media print {
            .cover-page {
                page-break-after: always;
            }
            
            .footer {
                position: fixed;
                bottom: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Cover Page -->
    <div class="cover-page">
        <div class="cover-content">
            @if(!empty($branding['company_logo']))
                <img src="{{ $branding['company_logo'] }}" alt="Company Logo" class="cover-logo">
            @else
                <div style="font-size: 48px; font-weight: 700; margin-bottom: 60px;">{{ $branding['company_name'] ?? 'Company' }}</div>
            @endif
            
            <h1 class="cover-title">Ticket Summary Report</h1>
            <p class="cover-subtitle">Support Ticket Analysis & Metrics</p>
            
            <div class="cover-customer">{{ $customer->canonical_name ?? $customer->name }}</div>
            
            <div class="cover-meta">Report Period: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
            <div class="cover-meta">Generated: {{ \Carbon\Carbon::parse($generated_at)->format('F j, Y') }}</div>
        </div>
        
        <div class="cover-footer-text">
            {{ $branding['footer_text'] ?? 'Confidential Report' }}
        </div>
    </div>
    
    <!-- Report Content -->
    <div class="page">
        <!-- Header -->
        <div class="header-section">
            <div class="header-logos">
                @if(!empty($branding['company_logo']))
                    <img src="{{ $branding['company_logo'] }}" alt="Company Logo" class="company-logo">
                @else
                    <div style="color: white; font-size: 20px; font-weight: 700;">{{ $branding['company_name'] ?? 'Company' }}</div>
                @endif
                
                @if(!empty($branding['customer_logo']))
                    <img src="{{ $branding['customer_logo'] }}" alt="Customer Logo" class="customer-logo">
                @endif
            </div>
            
            <div class="header-content">
                <h1 class="report-title">{{ $branding['report_title'] ?? 'Ticket Summary Report' }}</h1>
                <p class="report-subtitle">{{ $customer->canonical_name ?? $customer->name }}</p>
            </div>
        </div>
        
        <!-- Metadata Bar -->
        <div class="metadata-bar">
            <div class="meta-item">
                <span class="meta-label">Report Period</span>
                <span class="meta-value">{{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Generated</span>
                <span class="meta-value">{{ \Carbon\Carbon::parse($generated_at)->format('M j, Y H:i') }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Report Type</span>
                <span class="meta-value">Ticket Summary</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Customer ID</span>
                <span class="meta-value">#{{ $customer->customer_id }}</span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card info">
                    <div class="metric-label">Total Tickets</div>
                    <div class="metric-value">{{ $data['summary']['total_tickets'] ?? '0' }}</div>
                    <div class="metric-unit">tickets</div>
                </div>
                
                <div class="metric-card success">
                    <div class="metric-label">Tickets Completed</div>
                    <div class="metric-value">{{ $data['summary']['completed_tickets'] ?? '0' }}</div>
                    <div class="metric-unit">tickets</div>
                </div>
                
                <div class="metric-card warning">
                    <div class="metric-label">Tickets Open</div>
                    <div class="metric-value">{{ $data['summary']['open_tickets'] ?? '0' }}</div>
                    <div class="metric-unit">tickets</div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-label">Avg Resolution Time</div>
                    <div class="metric-value">{{ $data['summary']['avg_resolution_hours'] ?? '0' }}</div>
                    <div class="metric-unit">hours</div>
                </div>
            </div>
            
            <!-- Support Hours -->
            <h2 class="section-header">Support Hours</h2>
            <div class="metrics-grid">
                <div class="metric-card info">
                    <div class="metric-label">Total Hours</div>
                    <div class="metric-value">{{ $data['summary']['total_hours'] ?? '0' }}</div>
                    <div class="metric-unit">hours</div>
                </div>
                
                <div class="metric-card success">
                    <div class="metric-label">Billable Hours</div>
                    <div class="metric-value">{{ $data['summary']['billable_hours'] ?? '0' }}</div>
                    <div class="metric-unit">hours</div>
                </div>
                
                <div class="metric-card warning">
                    <div class="metric-label">Non-Billable Hours</div>
                    <div class="metric-value">{{ $data['summary']['non_billable_hours'] ?? '0' }}</div>
                    <div class="metric-unit">hours</div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-label">Billing Efficiency</div>
                    <div class="metric-value">{{ $data['summary']['billing_efficiency'] ?? '0' }}<span class="metric-unit">%</span></div>
                </div>
            </div>
            
            <!-- Priority & Status Breakdown -->
            <div class="two-column">
                <div class="breakdown-card">
                    <div class="breakdown-title">Priority Breakdown</div>
                    @if(!empty($data['priority_breakdown']))
                        @foreach($data['priority_breakdown'] as $priority => $count)
                        <div class="breakdown-item">
                            <span class="breakdown-label">
                                <span class="priority-badge priority-{{ strtolower($priority) }}"></span>
                                {{ ucfirst($priority) }}
                            </span>
                            <span class="breakdown-value">{{ $count }}</span>
                        </div>
                        @endforeach
                    @endif
                </div>
                
                <div class="breakdown-card">
                    <div class="breakdown-title">Status Breakdown</div>
                    @if(!empty($data['status_breakdown']))
                        @foreach($data['status_breakdown'] as $status => $count)
                        <div class="breakdown-item">
                            <span class="breakdown-label">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="breakdown-value">{{ $count }}</span>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
            
            <!-- Top Issues -->
            @if(!empty($data['top_issues']) && count($data['top_issues']) > 0)
            <h2 class="section-header">Top Issues</h2>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Issue</th>
                        <th style="text-align: right;">Count</th>
                        <th style="width: 40%;">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $maxCount = count($data['top_issues']) > 0 ? max(array_column($data['top_issues'], 'count')) : 1;
                    @endphp
                    @foreach($data['top_issues'] as $issue)
                    <tr>
                        <td><strong>{{ $issue['title'] }}</strong></td>
                        <td style="text-align: right;">{{ $issue['count'] }}</td>
                        <td>
                            @php
                                $percentage = $maxCount > 0 ? ($issue['count'] / $maxCount) * 100 : 0;
                            @endphp
                            <div class="category-bar" style="width: {{ $percentage }}%;"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
            
            <!-- Technician Activity -->
            @if(!empty($data['technicians']) && count($data['technicians']) > 0)
            <h2 class="section-header">Technician Activity</h2>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Technician</th>
                        <th>Role</th>
                        <th style="text-align: right;">Total Hours</th>
                        <th style="text-align: right;">Billable Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['technicians'] as $tech)
                    <tr>
                        <td><strong>{{ $tech['name'] }}</strong></td>
                        <td>{{ $tech['title'] ?? 'N/A' }}</td>
                        <td style="text-align: right;">{{ $tech['total_hours'] }}</td>
                        <td style="text-align: right;">{{ $tech['billable_hours'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
            
            <!-- Ticket Trends -->
            @if(!empty($data['daily_trends']))
            <div class="trends-section">
                <h2 class="section-header">Daily Ticket Trends</h2>
                <div class="trend-bars">
                    @foreach($data['daily_trends'] as $trend)
                        <div class="trend-bar-group">
                            @php
                                $maxHeight = 180;
                                $maxTickets = max(array_column($data['daily_trends'], 'opened'));
                                $openedHeight = $maxTickets > 0 ? ($trend['opened'] / $maxTickets) * $maxHeight : 20;
                                $closedHeight = $maxTickets > 0 ? ($trend['closed'] / $maxTickets) * $maxHeight : 20;
                            @endphp
                            <div class="trend-bars-container">
                                <div class="trend-bar" style="height: {{ $openedHeight }}px;" title="Opened: {{ $trend['opened'] }}"></div>
                                <div class="trend-bar closed" style="height: {{ $closedHeight }}px;" title="Closed: {{ $trend['closed'] }}"></div>
                            </div>
                            <div class="trend-label">{{ \Carbon\Carbon::parse($trend['date'])->format('M j') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="footer-company">{{ $branding['company_name'] ?? 'Company' }}</div>
                    <div class="footer-text">
                        {{ $branding['footer_text'] ?? 'Confidential Report - For Internal Use Only' }}<br>
                        {{ $branding['footer_notes'] ?? 'This report was automatically generated and contains support ticket analysis data.' }}
                    </div>
                </div>
                <div class="footer-right">
                    <div class="page-number">Page 2 of 2</div>
                    Generated on {{ now()->format('F j, Y \a\t g:i A') }}<br>
                    © {{ date('Y') }} {{ $branding['company_name'] ?? 'Company' }}. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
