<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $branding['report_title'] ?? 'Uptime Report' }} - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: {{ $branding['primary_color'] ?? '#1a56db' }};
            --secondary-color: {{ $branding['secondary_color'] ?? '#7c3aed' }};
            --primary-rgb: {{ \App\Helpers\ColorHelper::hexToRgb($branding['primary_color'] ?? '#1a56db') }};
            --secondary-rgb: {{ \App\Helpers\ColorHelper::hexToRgb($branding['secondary_color'] ?? '#7c3aed') }};
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1a1a1a;
            font-size: 10pt;
            line-height: 1.5;
        }
        
        .page {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        
        /* Summary Stats */
        .summary {
            background: rgba(var(--primary-rgb), 0.05);
            padding: 5mm;
            border-radius: 2mm;
            margin-bottom: 10mm;
            display: flex;
            justify-content: space-around;
            border: 1px solid rgba(var(--primary-rgb), 0.1);
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
            color: var(--primary-color);
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
            color: var(--primary-color);
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 2px solid var(--primary-color);
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
            padding: 2mm 2mm;
            border-bottom: 1px solid #e5e5e5;
        }
        
        td {
            padding: 3mm 2mm;
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
        .status-good { background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-critical { background: #fee2e2; color: #991b1b; }
        
        @media print {
            .page { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Report Title and Configuration -->
        <div class="report-info" style="background: #f8fafc; border-left: 4px solid {{ $branding['primary_color'] ?? '#1a56db' }}; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px;">
            <h2 style="font-size: 16pt; font-weight: 600; color: #1a1a1a; margin: 0 0 10px 0;">
                Uptime Report
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 9pt; color: #666;">
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Type</div>
                    <div style="color: #1a1a1a; font-weight: 500;">{{ ucfirst($options['frequency'] ?? 'Monthly') }} Uptime Monitoring</div>
                </div>
                @if(isset($period))
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Period</div>
                    <div style="color: #1a1a1a; font-weight: 500;">{{ $period['formatted'] ?? ($period['start'] . ' - ' . $period['end']) }}</div>
                </div>
                @endif
                @if(isset($generated_at))
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Generated</div>
                    <div style="color: #1a1a1a; font-weight: 500;">{{ \Carbon\Carbon::parse($generated_at)->format('F j, Y g:i A') }}</div>
                </div>
                @endif
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
    </div>
</body>
</html>
