<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uptime Report - {{ $customer->canonical_name ?? $customer->name }}</title>
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
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        
        .meta-value {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }
        
        /* Key Metrics Dashboard */
        .metrics-dashboard {
            padding: 40px 50px;
            background: white;
        }
        
        .section-header {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header::before {
            content: '';
            width: 4px;
            height: 24px;
            background: {{ $branding['primary_color'] ?? '#1e40af' }};
            border-radius: 2px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .metric-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        
        .metric-card:nth-child(1) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .metric-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .metric-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .metric-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .metric-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .metric-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }
        
        .metric-unit {
            font-size: 13px;
            opacity: 0.85;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        /* Content Section */
        .content-section {
            padding: 40px 50px;
            background: white;
        }
        
        /* Modern Table */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .data-table thead {
            background: {{ $branding['primary_color'] ?? '#1e40af' }};
            color: white;
        }
        
        .data-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .data-table tbody tr {
            background: white;
            transition: background 0.2s;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        
        .data-table tbody tr:hover {
            background: #f1f5f9;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .status-excellent {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-good {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Footer */
        .footer-section {
            background: #0f172a;
            color: white;
            padding: 30px 50px;
            margin-top: 40px;
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
        
        /* Print Optimization */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .page {
                margin: 0;
                border: none;
                box-shadow: none;
            }
            
            .content-section {
                page-break-inside: avoid;
            }
        }
        
        /* Utility Classes */
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .text-muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header with Branding -->
        <div class="header-section">
            <div class="header-logos">
                @if(isset($branding['company_logo']) && $branding['company_logo'])
                    <img src="{{ $branding['company_logo'] }}" alt="Company Logo" class="company-logo">
                @else
                    <div style="font-size: 20px; font-weight: 700;">{{ $branding['company_name'] ?? 'Company' }}</div>
                @endif
                
                @if(isset($branding['customer_logo']) && $branding['customer_logo'])
                    <img src="{{ $branding['customer_logo'] }}" alt="Customer Logo" class="customer-logo">
                @endif
            </div>
            
            <div class="header-content">
                <h1 class="report-title">Uptime & Performance Report</h1>
                <p class="report-subtitle">Comprehensive system availability and performance analysis</p>
            </div>
        </div>
        
        <!-- Report Metadata -->
        <div class="metadata-bar">
            <div class="meta-item">
                <span class="meta-label">Customer</span>
                <span class="meta-value">{{ $customer->canonical_name ?? $customer->name }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Report Period</span>
                <span class="meta-value">{{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Generated</span>
                <span class="meta-value">{{ $reportData['generated_at'] ?? now()->format('M j, Y') }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Report Type</span>
                <span class="meta-value">Uptime Analysis</span>
            </div>
        </div>
        
        <!-- Key Metrics Dashboard -->
        <div class="metrics-dashboard">
            <h2 class="section-header">Key Performance Indicators</h2>
            <div class="metrics-grid">
                @foreach($metrics as $metric)
                <div class="metric-card">
                    <div class="metric-label">{{ $metric['label'] ?? 'Metric' }}</div>
                    <div class="metric-value">{{ $metric['value'] ?? '0' }}</div>
                    <div class="metric-unit">{{ $metric['unit'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
        </div>
        
        <!-- Detailed Performance Data -->
        @if(isset($data) && count($data) > 0)
        <div class="content-section">
            <h2 class="section-header">Device Performance Details</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Average Uptime</th>
                        <th>Max Downtime</th>
                        <th>Performance Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $deviceId => $stats)
                    <tr>
                        <td><span class="font-bold">{{ $deviceId }}</span></td>
                        <td>{{ number_format($stats['avg_uptime'] ?? 0, 2) }}%</td>
                        <td>{{ $stats['max_downtime'] ?? 0 }} minutes</td>
                        <td>
                            @php
                                $uptime = $stats['avg_uptime'] ?? 0;
                                if ($uptime >= 99.9) {
                                    $statusClass = 'status-excellent';
                                    $statusText = 'Excellent';
                                } elseif ($uptime >= 99) {
                                    $statusClass = 'status-good';
                                    $statusText = 'Good';
                                } elseif ($uptime >= 95) {
                                    $statusClass = 'status-warning';
                                    $statusText = 'Warning';
                                } else {
                                    $statusClass = 'status-critical';
                                    $statusText = 'Critical';
                                }
                            @endphp
                            <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer-section">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="footer-company">{{ $branding['company_name'] ?? 'Company' }}</div>
                    <div class="footer-text">
                        {{ $branding['footer_text'] ?? 'Confidential Report - For Internal Use Only' }}<br>
                        {{ $branding['footer_notes'] ?? 'This report was automatically generated and contains sensitive performance data.' }}
                    </div>
                </div>
                <div class="footer-right">
                    Generated on {{ now()->format('F j, Y \a\t g:i A') }}<br>
                    © {{ date('Y') }} {{ $branding['company_name'] ?? 'Company' }}. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
