<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Status Report - {{ $customer->canonical_name ?? $customer->name }}</title>
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
        
        /* Header with Logos */
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
        
        /* Report Metadata Bar */
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
            background: linear-gradient(180deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
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
