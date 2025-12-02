<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Status Report - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: {{ $branding['primary_color'] ?? '#1e40af' }};
            --secondary-color: {{ $branding['secondary_color'] ?? '#7c3aed' }};
            --primary-rgb: {{ \App\Helpers\ColorHelper::hexToRgb($branding['primary_color'] ?? '#1e40af') }};
            --secondary-rgb: {{ \App\Helpers\ColorHelper::hexToRgb($branding['secondary_color'] ?? '#7c3aed') }};
        }
        
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
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
        
        /* Header with Logos */
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
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
        
        /* Report Metadata Bar */
        .metadata-bar {
            background: rgba(var(--primary-rgb), 0.05);
            border-left: 4px solid var(--primary-color);
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
        
        /* Content Area */
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
        
        .metric-card.danger {
            border-left: 4px solid #ef4444;
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
        
        /* Failures Table */
        .failures-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .failures-table thead {
            background: #f8fafc;
        }
        
        .failures-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .failures-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .failures-table tr:last-child td {
            border-bottom: none;
        }
        
        .failures-table tr:hover {
            background: #f8fafc;
        }
        
        .error-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        /* Trends Chart Placeholder */
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
        
        .trend-bar {
            width: 100%;
            max-width: 60px;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 4px 4px 0 0;
            position: relative;
        }
        
        .trend-bar.failed {
            background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
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
            
            <h1 class="cover-title">Backup Status Report</h1>
            <p class="cover-subtitle">Comprehensive Backup Analysis</p>
            
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
                <h1 class="report-title">{{ $branding['report_title'] ?? 'Backup Status Report' }}</h1>
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
                <span class="meta-value">Backup Status</span>
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
                <div class="metric-card success">
                    <div class="metric-label">Success Rate</div>
                    <div class="metric-value">{{ $data['success_rate'] ?? '0' }}<span class="metric-unit">%</span></div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-label">Total Backups</div>
                    <div class="metric-value">{{ $data['total_backups'] ?? '0' }}</div>
                    <div class="metric-unit">backups</div>
                </div>
                
                <div class="metric-card danger">
                    <div class="metric-label">Failed Backups</div>
                    <div class="metric-value">{{ $data['failed_backups'] ?? '0' }}</div>
                    <div class="metric-unit">failures</div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-label">Storage Used</div>
                    <div class="metric-value">{{ $data['storage_used_gb'] ?? '0' }}</div>
                    <div class="metric-unit">GB</div>
                </div>
            </div>
            
            <!-- Backup Trends -->
            @if(!empty($data['daily_trends']))
            <div class="trends-section">
                <h2 class="section-header">Backup Trends</h2>
                <div class="trend-bars">
                    @foreach($data['daily_trends'] as $trend)
                        <div class="trend-bar-group">
                            @php
                                $maxHeight = 180;
                                $maxBackups = max(array_column($data['daily_trends'], 'total'));
                                $height = $maxBackups > 0 ? ($trend['total'] / $maxBackups) * $maxHeight : 20;
                            @endphp
                            <div class="trend-bar" style="height: {{ $height }}px;"></div>
                            <div class="trend-label">{{ \Carbon\Carbon::parse($trend['date'])->format('M j') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- Recent Failures -->
            @if(!empty($data['recent_failures']) && count($data['recent_failures']) > 0)
            <h2 class="section-header">Recent Backup Failures</h2>
            <table class="failures-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Timestamp</th>
                        <th>Backup Type</th>
                        <th>Error Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['recent_failures'] as $failure)
                    <tr>
                        <td><strong>{{ $failure['device'] }}</strong></td>
                        <td>{{ $failure['timestamp'] }}</td>
                        <td>{{ $failure['backup_type'] }}</td>
                        <td><span class="error-badge">{{ $failure['error'] }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 20px; text-align: center; color: #166534;">
                <strong>✓ No backup failures during this period</strong>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
