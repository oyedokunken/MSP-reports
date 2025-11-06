<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Comprehensive Report - {{ $customer->canonical_name ?? $customer->name }}</title>
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
        
        /* Health Score Hero */
        .health-score-hero {
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .health-score-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .health-score-value {
            font-size: 72px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .health-score-label {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .summary-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }
        
        .summary-card.uptime {
            border-left: 4px solid #10b981;
        }
        
        .summary-card.tickets {
            border-left: 4px solid #3b82f6;
        }
        
        .summary-card.backups {
            border-left: 4px solid #8b5cf6;
        }
        
        .summary-card.security {
            border-left: 4px solid #f59e0b;
        }
        
        .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        
        /* Section Headers */
        .section-header {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .section-icon.uptime {
            background: #d1fae5;
            color: #065f46;
        }
        
        .section-icon.tickets {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .section-icon.backups {
            background: #ede9fe;
            color: #6b21a8;
        }
        
        .section-icon.security {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Section Content */
        .section-content {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }
        
        .metric-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .metric-row:last-child {
            margin-bottom: 0;
        }
        
        .metric-item {
            display: flex;
            flex-direction: column;
        }
        
        .metric-item-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .metric-item-value {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.excellent {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.good {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.critical {
            background: #fee2e2;
            color: #991b1b;
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
        
        /* Page Break */
        .page-break {
            page-break-after: always;
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
            
            <h1 class="cover-title">Monthly Comprehensive Report</h1>
            <p class="cover-subtitle">Complete System Performance Overview</p>
            
            <div class="cover-customer">{{ $customer->canonical_name ?? $customer->name }}</div>
            
            <div class="cover-meta">Report Period: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
            <div class="cover-meta">Generated: {{ \Carbon\Carbon::parse($reportData['generated_at'])->format('F j, Y') }}</div>
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
                <h1 class="report-title">{{ $branding['report_title'] ?? 'Monthly Comprehensive Report' }}</h1>
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
                <span class="meta-value">Comprehensive</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Customer ID</span>
                <span class="meta-value">#{{ $customer->customer_id }}</span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Health Score Hero -->
            <div class="health-score-hero">
                <div class="health-score-value">{{ $data['health_score'] ?? '0' }}/100</div>
                <div class="health-score-label">Overall Health Score</div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card uptime">
                    <div class="summary-label">Average Uptime</div>
                    <div class="summary-value">{{ $data['summary']['avg_uptime'] ?? '0%' }}</div>
                </div>
                
                <div class="summary-card tickets">
                    <div class="summary-label">Tickets Resolved</div>
                    <div class="summary-value">{{ $data['summary']['tickets_resolved'] ?? '0' }}</div>
                </div>
                
                <div class="summary-card backups">
                    <div class="summary-label">Backup Success</div>
                    <div class="summary-value">{{ $data['summary']['backup_success_rate'] ?? '0' }}%</div>
                </div>
                
                <div class="summary-card security">
                    <div class="summary-label">Security Incidents</div>
                    <div class="summary-value">{{ $data['summary']['security_incidents'] ?? '0' }}</div>
                </div>
            </div>
            
            <!-- Uptime Section -->
            @if(!empty($data['uptime']))
            <h2 class="section-header">
                <span class="section-icon uptime">📊</span>
                Uptime & Performance
            </h2>
            <div class="section-content">
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">Average Uptime</span>
                        <span class="metric-item-value">{{ $data['uptime']['avg_uptime'] ?? '0%' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Total Downtime</span>
                        <span class="metric-item-value">{{ $data['uptime']['total_downtime'] ?? '0' }} hrs</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Incidents</span>
                        <span class="metric-item-value">{{ $data['uptime']['incident_count'] ?? '0' }}</span>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Tickets Section -->
            @if(!empty($data['tickets']))
            <h2 class="section-header">
                <span class="section-icon tickets">🎫</span>
                Support Tickets
            </h2>
            <div class="section-content">
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">Tickets Opened</span>
                        <span class="metric-item-value">{{ $data['tickets']['tickets_opened'] ?? '0' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Tickets Closed</span>
                        <span class="metric-item-value">{{ $data['tickets']['tickets_closed'] ?? '0' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Avg Response Time</span>
                        <span class="metric-item-value">{{ $data['tickets']['avg_response_time_hours'] ?? '0' }} hrs</span>
                    </div>
                </div>
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">SLA Compliance</span>
                        <span class="metric-item-value">{{ $data['tickets']['sla_compliance_rate'] ?? '0' }}%</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Backlog</span>
                        <span class="metric-item-value">{{ $data['tickets']['tickets_backlog'] ?? '0' }}</span>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Backups Section -->
            @if(!empty($data['backups']))
            <h2 class="section-header">
                <span class="section-icon backups">💾</span>
                Backup Status
            </h2>
            <div class="section-content">
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">Success Rate</span>
                        <span class="metric-item-value">{{ $data['backups']['success_rate'] ?? '0' }}%</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Total Backups</span>
                        <span class="metric-item-value">{{ $data['backups']['total_backups'] ?? '0' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Failed Backups</span>
                        <span class="metric-item-value">{{ $data['backups']['failed_backups'] ?? '0' }}</span>
                    </div>
                </div>
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">Storage Used</span>
                        <span class="metric-item-value">{{ $data['backups']['storage_used_gb'] ?? '0' }} GB</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Avg Duration</span>
                        <span class="metric-item-value">{{ $data['backups']['avg_duration_minutes'] ?? '0' }} min</span>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Security Section -->
            @if(!empty($data['security']))
            <h2 class="section-header">
                <span class="section-icon security">🔒</span>
                Security Summary
            </h2>
            <div class="section-content">
                <div class="metric-row">
                    <div class="metric-item">
                        <span class="metric-item-label">Critical Alerts</span>
                        <span class="metric-item-value">{{ $data['security']['critical_alerts'] ?? '0' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Vulnerabilities</span>
                        <span class="metric-item-value">{{ $data['security']['vulnerability_count'] ?? '0' }}</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-item-label">Patch Compliance</span>
                        <span class="metric-item-value">{{ $data['security']['patch_compliance'] ?? '0%' }}</span>
                    </div>
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
                        {{ $branding['footer_notes'] ?? 'This comprehensive report was automatically generated and contains complete system performance data.' }}
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
