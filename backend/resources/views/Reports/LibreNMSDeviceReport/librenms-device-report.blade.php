<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LibreNMS Device Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 10px;
            font-size: 12px;
        }
        .header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; text-align: center; }
        .header .company-name { font-size: 16px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .header .meta { font-size: 11px; color: #666; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 40px; }
        .summary-card { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; text-align: center; }
        .summary-card .number { font-size: 28px; font-weight: 600; margin-bottom: 3px; display: block; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
        .section { margin-bottom: 40px; page-break-inside: auto; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; page-break-inside: auto; }
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .uptime-cell { font-family: 'Courier New', monospace; font-weight: 600; }
        .uptime-100 { color: #065f46; }
        .uptime-99 { color: #065f46; }
        .uptime-98 { color: #92400e; }
        .uptime-bad { color: #991b1b; }
        .info-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 20px; }
        .info-card h3 { margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600; color: #1a1a1a; border-bottom: 1px solid #e5e5e5; padding-bottom: 10px; }
        .info-table { width: 100%; font-size: 11px; }
        .info-table td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        .info-table td:first-child { color: #666; }
        .info-table td:last-child { text-align: right; font-weight: 600; color: #1a1a1a; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Device Availability Report</h1>
        <div class="company-name">{{ $branding['company_name'] ?? 'Territory Technology Solutions' }}</div>
        <div class="meta">{{ $customer->name }} • {{ $generated_at }}</div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <span class="number">{{ $summary['total_devices'] }}</span>
            <span class="label">Total Devices</span>
        </div>
        <div class="summary-card">
            <span class="number" style="color: #065f46">{{ $summary['online_devices'] }}</span>
            <span class="label">Online</span>
        </div>
        <div class="summary-card">
            <span class="number" style="color: #991b1b">{{ $summary['offline_devices'] }}</span>
            <span class="label">Offline</span>
        </div>
        <div class="summary-card">
            <span class="number">{{ $summary['avg_uptime_30d'] }}%</span>
            <span class="label">Avg Uptime (30d)</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 40px;">
        <div class="info-card">
            <h3>Device Types</h3>
            <table class="info-table">
                @foreach($summary['device_types'] as $type => $count)
                <tr>
                    <td>{{ $type }}</td>
                    <td>{{ $count }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="info-card">
            <h3>Operating Systems</h3>
            <table class="info-table">
                @foreach($summary['os_types'] as $os => $count)
                <tr>
                    <td>{{ $os }}</td>
                    <td>{{ $count }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    @foreach($groups as $groupName => $devices)
        <div class="section">
            <h2 class="section-title">{{ $groupName }} ({{ count($devices) }})</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%">Device</th>
                        <th style="width: 12%">IP Address</th>
                        <th style="width: 10%">Type</th>
                        <th style="width: 10%">OS</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 9%">24h</th>
                        <th style="width: 9%">7d</th>
                        <th style="width: 9%">30d</th>
                        <th style="width: 9%">365d</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        <tr>
                            <td>
                                <strong>{{ $device['name'] }}</strong>
                            </td>
                            <td>{{ $device['ip'] }}</td>
                            <td>{{ $device['type'] }}</td>
                            <td>{{ $device['os'] }}</td>
                            <td>
                                <span class="badge {{ $device['status_color'] }}">
                                    {{ $device['status'] }}
                                </span>
                            </td>
                            
                            @foreach(['24h', '7d', '30d', '365d'] as $period)
                                @php
                                    $val = $device['uptime_metrics'][$period];
                                    $class = 'uptime-bad';
                                    if ($val >= 99.9) $class = 'uptime-100';
                                    elseif ($val >= 99.0) $class = 'uptime-99';
                                    elseif ($val >= 98.0) $class = 'uptime-98';
                                @endphp
                                <td class="uptime-cell {{ $class }}">
                                    {{ number_format($val, 2) }}%
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
