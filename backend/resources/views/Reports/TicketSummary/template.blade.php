<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Summary Report - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
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
            background: #f8fafc;
            padding: 30px 50px;
            border-top: 1px solid #e2e8f0;
            margin-top: auto;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748b;
        }
        
        .footer-notes {
            font-style: italic;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
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
                <span class="meta-value">{{ \Carbon\Carbon::parse($reportData['generated_at'])->format('M j, Y H:i') }}</span>
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
                    <div class="metric-label">Tickets Opened</div>
                    <div class="metric-value">{{ $data['tickets_opened'] ?? '0' }}</div>
                    <div class="metric-unit">tickets</div>
                </div>
                
                <div class="metric-card success">
                    <div class="metric-label">Tickets Closed</div>
                    <div class="metric-value">{{ $data['tickets_closed'] ?? '0' }}</div>
                    <div class="metric-unit">tickets</div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-label">Avg Response Time</div>
                    <div class="metric-value">{{ $data['avg_response_time_hours'] ?? '0' }}</div>
                    <div class="metric-unit">hours</div>
                </div>
                
                <div class="metric-card success">
                    <div class="metric-label">SLA Compliance</div>
                    <div class="metric-value">{{ $data['sla_compliance_rate'] ?? '0' }}<span class="metric-unit">%</span></div>
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
            
            <!-- Top Categories -->
            @if(!empty($data['top_categories']))
            <h2 class="section-header">Top Ticket Categories</h2>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align: right;">Count</th>
                        <th style="width: 40%;">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $maxCount = max(array_values($data['top_categories']));
                    @endphp
                    @foreach($data['top_categories'] as $category => $count)
                    <tr>
                        <td><strong>{{ $category }}</strong></td>
                        <td style="text-align: right;">{{ $count }}</td>
                        <td>
                            @php
                                $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                            @endphp
                            <div class="category-bar" style="width: {{ $percentage }}%;"></div>
                        </td>
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
            @if(!empty($branding['footer_notes']))
            <div class="footer-notes">{{ $branding['footer_notes'] }}</div>
            @endif
            <div class="footer-content">
                <div>{{ $branding['footer_text'] ?? '' }}</div>
                <div>Page 1 of 1</div>
            </div>
        </div>
    </div>
</body>
</html>
