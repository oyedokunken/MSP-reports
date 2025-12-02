<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Summary - {{ $customer->canonical_name ?? $customer->name }}</title>
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
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
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
        
        /* Header with Security Theme */
        .header-section {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
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
            background: rgba(255, 255, 255, 0.08);
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
            filter: brightness(0) invert(1);
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
        
        /* Security Alert Banner */
        .alert-banner {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 20px 50px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-icon {
            font-size: 32px;
        }
        
        .alert-content h3 {
            font-size: 16px;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 4px;
        }
        
        .alert-content p {
            font-size: 13px;
            color: #7f1d1d;
        }
        
        /* Report Metadata Bar */
        .metadata-bar {
            background: #f8fafc;
            border-left: 4px solid #dc2626;
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
        
        /* Security Metrics Dashboard */
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
            background: #dc2626;
            border-radius: 2px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
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
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }
        
        .metric-card:nth-child(2) {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }
        
        .metric-card:nth-child(3) {
            background: linear-gradient(135deg, #ca8a04 0%, #a16207 100%);
        }
        
        .metric-card:nth-child(4) {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
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
            background: #dc2626;
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
        
        /* Severity Badges */
        .severity-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .severity-critical {
            background: #dc2626;
            color: white;
        }
        
        .severity-high {
            background: #ea580c;
            color: white;
        }
        
        .severity-medium {
            background: #ca8a04;
            color: white;
        }
        
        .severity-low {
            background: #16a34a;
            color: white;
        }
        
        .severity-info {
            background: #0891b2;
            color: white;
        }
        
        /* Alert Boxes */
        .alerts-section {
            padding: 40px 50px;
            background: #fafafa;
        }
        
        .alert-box {
            background: white;
            border-left: 4px solid;
            border-radius: 8px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .alert-box.alert-critical {
            border-color: #dc2626;
        }
        
        .alert-box.alert-high {
            border-color: #ea580c;
        }
        
        .alert-box.alert-medium {
            border-color: #ca8a04;
        }
        
        .alert-box.alert-low {
            border-color: #16a34a;
        }
        
        .alert-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .alert-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .alert-description {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }
        
        /* No Alerts State */
        .no-alerts {
            background: white;
            border: 2px solid #16a34a;
            border-radius: 12px;
            padding: 50px;
            text-align: center;
        }
        
        .no-alerts-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .no-alerts h3 {
            font-size: 24px;
            font-weight: 700;
            color: #16a34a;
            margin-bottom: 10px;
        }
        
        .no-alerts p {
            font-size: 15px;
            color: #64748b;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Footer */
        .footer-section {
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
            body {
                margin: 0;
                padding: 0;
            }
            
            .page {
                margin: 0;
                border: none;
                box-shadow: none;
            }
            
            .cover-page {
                page-break-after: always;
            }
            
            .content-section, .alerts-section {
                page-break-inside: avoid;
            }
            
            .footer-section {
                position: fixed;
                bottom: 0;
            }
        }
        
        /* Utility Classes */
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .text-muted { color: #64748b; }
    </style>
</head>
<body>
    <!-- Cover Page -->
    <div class="cover-page">
        <div class="cover-content">
            @if(isset($branding['company_logo']) && $branding['company_logo'])
                <img src="{{ $branding['company_logo'] }}" alt="Company Logo" class="cover-logo">
            @else
                <div style="font-size: 48px; font-weight: 700; margin-bottom: 60px;">{{ $branding['company_name'] ?? 'Company' }}</div>
            @endif
            
            <h1 class="cover-title">Security Summary Report</h1>
            <p class="cover-subtitle">Comprehensive Security Analysis</p>
            
            <div class="cover-customer">{{ $customer->canonical_name ?? $customer->name }}</div>
            
            <div class="cover-meta">Report Period: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
            <div class="cover-meta">Generated: {{ $reportData['generated_at'] ?? now()->format('F j, Y') }}</div>
        </div>
        
        <div class="cover-footer-text">
            {{ $branding['footer_text'] ?? 'Confidential Security Report' }}
        </div>
    </div>
    
    <!-- Report Content -->
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
                <h1 class="report-title">Security Summary Report</h1>
                <p class="report-subtitle">Comprehensive security analysis and threat assessment</p>
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
                <span class="meta-value">Security Analysis</span>
            </div>
        </div>
        
        <!-- Security Metrics Dashboard -->
        @if(isset($metrics) && count($metrics) > 0)
        <div class="metrics-dashboard">
            <h2 class="section-header">Security Metrics Overview</h2>
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
        @endif
        
        <!-- Security Events Table -->
        @if(isset($data) && count($data) > 0)
        <div class="content-section">
            <h2 class="section-header">Security Events Summary</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Count</th>
                        <th>Severity Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $event)
                    <tr>
                        <td><span class="font-bold">{{ $event['type'] ?? 'Unknown' }}</span></td>
                        <td>{{ $event['count'] ?? 0 }}</td>
                        <td>
                            @php
                                $severity = strtolower($event['severity'] ?? 'info');
                            @endphp
                            <span class="severity-badge severity-{{ $severity }}">{{ $event['severity'] ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $event['status'] ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <!-- Security Alerts & Notifications -->
        <div class="alerts-section">
            <h2 class="section-header">Security Alerts & Notifications</h2>
            @if(isset($alerts) && count($alerts) > 0)
                @foreach($alerts as $alert)
                    @php
                        $severity = strtolower($alert['severity'] ?? 'low');
                        $alertClass = 'alert-' . $severity;
                    @endphp
                    <div class="alert-box {{ $alertClass }}">
                        <div class="alert-header">
                            <span class="severity-badge severity-{{ $severity }}">{{ strtoupper($alert['severity'] ?? 'INFO') }}</span>
                            <span class="alert-title">{{ $alert['title'] ?? 'Security Alert' }}</span>
                        </div>
                        <div class="alert-description">{{ $alert['description'] ?? 'No description available' }}</div>
                    </div>
                @endforeach
            @else
                <div class="no-alerts">
                    <div class="no-alerts-icon">🛡️</div>
                    <h3>All Clear - No Security Alerts</h3>
                    <p>No security issues or threats were detected during this reporting period. Your systems are operating securely and within normal parameters.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
