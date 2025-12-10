<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LibreNMS Device Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .logo {
            max-height: 50px;
            float: right;
        }
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        .report-meta {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        .summary-cards {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        .card-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            display: block;
        }
        .card-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .group-section {
            margin-bottom: 40px;
        }
        .group-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            background: #f8f9fa;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            padding: 12px 8px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
        }
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #eee;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .uptime-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        .uptime-100 { color: #28a745; }
        .uptime-99 { color: #28a745; }
        .uptime-98 { color: #ffc107; }
        .uptime-bad { color: #dc3545; }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(isset($customer->logo_url))
            <img src="{{ $customer->logo_url }}" class="logo" alt="Logo">
        @endif
        <h1 class="report-title">Device Availability Report</h1>
        <div class="report-meta">
            Generated for {{ $customer->name }} • {{ $generated_at }}
        </div>
    </div>

    <div class="summary-cards">
        <div class="card">
            <span class="card-value">{{ $summary['total_devices'] }}</span>
            <span class="card-label">Total Devices</span>
        </div>
        <div class="card">
            <span class="card-value" style="color: #28a745">{{ $summary['online_devices'] }}</span>
            <span class="card-label">Online</span>
        </div>
        <div class="card">
            <span class="card-value" style="color: #dc3545">{{ $summary['offline_devices'] }}</span>
            <span class="card-label">Offline</span>
        </div>
        <div class="card">
            <span class="card-value">{{ $summary['avg_uptime_30d'] }}%</span>
            <span class="card-label">Avg Uptime (30d)</span>
        </div>
    </div>

    <div style="display: flex; gap: 20px; margin-bottom: 40px;">
        <!-- Device Types Card -->
        <div style="flex: 1; background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">Device Types</h3>
            <table style="width: 100%; font-size: 13px;">
                @foreach($summary['device_types'] as $type => $count)
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f8f9fa; color: #555;">{{ $type }}</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f8f9fa; text-align: right; font-weight: bold; color: #2c3e50;">{{ $count }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <!-- OS Types Card -->
        <div style="flex: 1; background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">Operating Systems</h3>
            <table style="width: 100%; font-size: 13px;">
                @foreach($summary['os_types'] as $os => $count)
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f8f9fa; color: #555;">{{ $os }}</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f8f9fa; text-align: right; font-weight: bold; color: #2c3e50;">{{ $count }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    @foreach($groups as $groupName => $devices)
        <div class="group-section">
            <div class="group-title">{{ $groupName }} ({{ count($devices) }})</div>
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
                                <span class="status-badge status-{{ $device['status_color'] }}">
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

    <div class="footer">
        Generated by MSP Reports • {{ date('Y') }}
    </div>
</body>
</html>
