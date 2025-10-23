<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Uptime Report - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            background: {{ $branding['primary_color'] ?? '#1a56db' }};
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
            border-bottom: 3px solid {{ $branding['primary_color'] ?? '#1a56db' }};
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
            border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }};
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: {{ $branding['primary_color'] ?? '#1a56db' }};
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-excellent { background: #d4edda; color: #155724; }
        .status-good { background: #d1ecf1; color: #0c5460; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-critical { background: #f8d7da; color: #721c24; }
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
            <h1>Uptime & Performance Report</h1>
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
                    <span class="info-value">Uptime Report</span>
                </div>
            </div>
        </div>

        <!-- Metrics -->
        <div class="metrics">
            @foreach($metrics as $key => $metric)
            <div class="metric-card">
                <div class="metric-label">{{ $metric['label'] ?? 'Metric' }}</div>
                <div class="metric-value">{{ $metric['value'] ?? '0' }}</div>
                <div class="metric-unit">{{ $metric['unit'] ?? '' }}</div>
            </div>
            @endforeach
        </div>

        <!-- Device Details -->
        @if(isset($data) && count($data) > 0)
        <div class="content">
            <h2 class="section-title">Device Performance Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Average Uptime</th>
                        <th>Max Downtime</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $deviceId => $stats)
                    <tr>
                        <td><strong>{{ $deviceId }}</strong></td>
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
        <div class="footer">
            <p>{{ $branding['footer_text'] ?? '© ' . date('Y') . ' ' . ($branding['company_name'] ?? 'Company') }}</p>
            <p>This report was automatically generated by {{ $branding['company_name'] ?? 'Company' }}</p>
        </div>
    </div>
</body>
</html>
