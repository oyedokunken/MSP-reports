<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Uptime Report - {{ $customer->canonical_name ?? $customer->name }}</title>
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
        
        /* Summary Stats */
        .summary {
            background: #fafafa;
            padding: 5mm;
            border-radius: 2mm;
            margin-bottom: 10mm;
            display: flex;
            justify-content: space-around;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 8pt;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2mm;
            font-weight: 500;
        }
        
        .summary-value {
            font-size: 20pt;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .summary-unit {
            font-size: 9pt;
            color: #666;
            font-weight: 400;
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
            padding: 2mm 0;
            border-bottom: 1px solid #e5e5e5;
        }
        
        td {
            padding: 3mm 0;
            font-size: 9pt;
            color: #1a1a1a;
            border-bottom: 1px solid #f5f5f5;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badge */
        .status {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 1mm;
            font-size: 8pt;
            font-weight: 500;
        }
        
        .status-excellent { background: #d1fae5; color: #065f46; }
        .status-good { background: #dbeafe; color: #1e40af; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-critical { background: #fee2e2; color: #991b1b; }
        
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
                <h1>Uptime Report</h1>
                <div class="subtitle">{{ $customer->canonical_name ?? $customer->name }}</div>
            </div>
            <div class="header-right">
                <div><span class="label">Period</span></div>
                <div class="value">{{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
                <div><span class="label">Generated</span></div>
                <div class="value">{{ \Carbon\Carbon::parse($generated_at)->format('M j, Y') }}</div>
            </div>
        </div>
        
        <!-- Summary -->
        @if(isset($data['devices']) && count($data['devices']) > 0)
        @php
            $totalDevices = count($data['devices']);
            $activeDevices = collect($data['devices'])->filter(fn($d) => ($d['average_uptime'] ?? 0) > 0)->count();
            $avgUptime = collect($data['devices'])->avg('average_uptime') ?? 0;
        @endphp
        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">Total Devices</div>
                <div class="summary-value">{{ $totalDevices }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Active Devices</div>
                <div class="summary-value">{{ $activeDevices }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Average Uptime</div>
                <div class="summary-value">{{ number_format($avgUptime, 1) }}<span class="summary-unit">%</span></div>
            </div>
        </div>
        
        <!-- Device Performance -->
        <div class="section">
            <h2 class="section-title">Device Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Device Name</th>
                        <th style="text-align: right; width: 80px;">Uptime</th>
                        <th style="text-align: right; width: 80px;">Downtime</th>
                        <th style="text-align: right; width: 80px;">Checks</th>
                        <th style="width: 100px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['devices'] as $device)
                    <tr>
                        <td>{{ $device['device_name'] ?? 'Unknown' }}</td>
                        <td style="text-align: right;">{{ number_format($device['average_uptime'] ?? 0, 1) }}%</td>
                        <td style="text-align: right;">{{ round($device['total_downtime_minutes'] ?? 0, 1) }}m</td>
                        <td style="text-align: right;">{{ $device['measurement_count'] ?? 0 }}</td>
                        <td>
                            @php
                                $uptime = $device['average_uptime'] ?? 0;
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
                            <span class="status {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">No Data Available</div>
                <div class="summary-value" style="font-size: 12pt; color: #999;">—</div>
            </div>
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
