<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Security Summary - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            color: #2c3e50;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: {{ $branding['primary_color'] ?? '#dc3545' }};
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header .company {
            font-size: 14px;
            opacity: 0.9;
        }
        .report-info {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 3px solid {{ $branding['primary_color'] ?? '#dc3545' }};
        }
        .report-info h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        .metrics {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
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
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-unit {
            font-size: 14px;
            opacity: 0.8;
        }
        .content {
            padding: 30px;
        }
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid {{ $branding['primary_color'] ?? '#dc3545' }};
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: {{ $branding['primary_color'] ?? '#dc3545' }};
            color: white;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        .severity-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .severity-critical { background: #dc3545; color: white; }
        .severity-high { background: #fd7e14; color: white; }
        .severity-medium { background: #ffc107; color: #000; }
        .severity-low { background: #28a745; color: white; }
        .severity-info { background: #17a2b8; color: white; }
        .alert-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        .alert-critical {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .alert-high {
            background: #ffe5d0;
            border-color: #fd7e14;
            color: #8a3d00;
        }
        .alert-medium {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .alert-low {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .alert-description {
            font-size: 14px;
            line-height: 1.5;
        }
        .no-alerts {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
            text-align: center;
            padding: 30px;
            border-radius: 8px;
            border: 2px solid #28a745;
        }
        .no-alerts-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .footer {
            background: #2c3e50;
            color: white;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
        }
        .footer p {
            margin: 5px 0;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Security Summary Report</h1>
            <div class="company">{{ $branding['company_name'] ?? 'Company' }}</div>
        </div>

        <!-- Report Info -->
        <div class="report-info">
            <h2>Report Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Customer</span>
                    <span class="info-value">{{ $customer->canonical_name ?? $customer->name }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Report Period</span>
                    <span class="info-value">{{ $period['start'] ?? 'N/A' }} to {{ $period['end'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Generated</span>
                    <span class="info-value">{{ $reportData['generated_at'] ?? now()->format('F j, Y') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Report Type</span>
                    <span class="info-value">Security Summary</span>
                </div>
            </div>
        </div>

        <!-- Metrics -->
        @if(isset($metrics) && count($metrics) > 0)
        <div class="metrics">
            @foreach($metrics as $key => $metric)
            <div class="metric-card">
                <div class="metric-label">{{ $metric['label'] ?? 'Metric' }}</div>
                <div class="metric-value">{{ $metric['value'] ?? '0' }}</div>
                <div class="metric-unit">{{ $metric['unit'] ?? '' }}</div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Security Events -->
        @if(isset($data) && count($data) > 0)
        <div class="content">
            <h2 class="section-title">Security Events Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Count</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $event)
                    <tr>
                        <td><strong>{{ $event['type'] ?? 'Unknown' }}</strong></td>
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

        <!-- Security Alerts -->
        <div class="content">
            <h2 class="section-title">Security Alerts & Notifications</h2>
            @if(isset($alerts) && count($alerts) > 0)
                @foreach($alerts as $alert)
                    @php
                        $severity = strtolower($alert['severity'] ?? 'low');
                        $alertClass = 'alert-' . $severity;
                    @endphp
                    <div class="alert-box {{ $alertClass }}">
                        <div class="alert-title">{{ $alert['title'] ?? 'Security Alert' }}</div>
                        <div class="alert-description">{{ $alert['description'] ?? 'No description available' }}</div>
                    </div>
                @endforeach
            @else
                <div class="no-alerts">
                    <div class="no-alerts-icon">✓</div>
                    <div class="alert-title">No Security Alerts</div>
                    <div class="alert-description">No security issues detected during this reporting period. Your systems are operating securely.</div>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>{{ $branding['footer_text'] ?? '© ' . date('Y') . ' ' . ($branding['company_name'] ?? 'Company') }}</p>
            <p>This report was automatically generated by {{ $branding['company_name'] ?? 'Company' }}</p>
        </div>
    </div>
</body>
</html>
