<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Datto Device Report</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #215982;
            padding-bottom: 10px;
        }
        .logo {
            height: 50px;
            float: left;
        }
        .title-container {
            text-align: right;
            float: right;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #215982;
            margin: 0;
        }
        .report-date {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0 0;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .company-section {
            page-break-after: always;
        }
        .company-section:last-child {
            page-break-after: auto;
        }
        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #215982;
            margin-bottom: 10px;
            margin-top: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #215982;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ccc;
        }
        td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f5f5f5;
        }
        .uptime-excellent {
            color: #52A421;
            font-weight: bold;
            background-color: #e8f5e8;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .uptime-good {
            color: #7BA428;
            font-weight: bold;
            background-color: #f0f7e8;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .uptime-warning {
            color: #FF8800;
            font-weight: bold;
            background-color: #fff3e8;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .uptime-critical {
            color: #A42152;
            font-weight: bold;
            background-color: #f5e8eb;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .summary-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .summary-title {
            font-size: 14px;
            font-weight: bold;
            color: #215982;
            margin-bottom: 10px;
        }
        .summary-table {
            width: 300px;
        }
        .summary-table td {
            border: none;
            padding: 4px 0;
        }
        .summary-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    @foreach($groupedDevices as $companyName => $devices)
    <div class="company-section">
        <div class="header clearfix">
            <div class="logo-container">
                <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo" onerror="this.style.display='none'">
                <span style="font-size: 16px; font-weight: bold; color: #215982; line-height: 50px; margin-left: 10px;">
                    @if(!file_exists(public_path('images/logo.png'))) MSP Reports @endif
                </span>
            </div>
            <div class="title-container">
                <h1 class="report-title">Datto Device Report</h1>
                <p class="report-date">Generated: {{ date('Y-m-d') }}</p>
            </div>
        </div>

        <div class="company-title">{{ $companyName }}</div>

        <table>
            <thead>
                <tr>
                    <th>Device Name</th>
                    <th>IP Address</th>
                    <th>Type</th>
                    <th>Uptime %</th>
                    <th>Uptime Hours</th>
                    <th>Downtime Hours</th>
                    <th>Polls</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devices as $device)
                <tr>
                    <td>{{ $device->device_name }}</td>
                    <td>{{ $device->ip_address }}</td>
                    <td>{{ $device->device_type }}</td>
                    <td>
                        <span class="uptime-{{ $device->uptime_status }}">
                            {{ $device->uptime_percentage }}%
                        </span>
                    </td>
                    <td>{{ $device->uptime_hours }}h</td>
                    <td>{{ $device->downtime_hours }}h</td>
                    <td>{{ $device->online_polls }}/{{ $device->total_polls }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-section">
            <div class="summary-title">Summary for {{ $companyName }}</div>
            <table class="summary-table">
                <tr>
                    <td class="summary-label">Total Devices:</td>
                    <td>{{ $devices->count() }}</td>
                </tr>
                <tr>
                    <td class="summary-label">Total Up:</td>
                    <td class="status-up">{{ $devices->where('status', 'Up')->count() }}</td>
                </tr>
                <tr>
                    <td class="summary-label">Total Down:</td>
                    <td class="status-down">{{ $devices->where('status', 'Down')->count() }}</td>
                </tr>
            </table>
        </div>
    </div>
    @endforeach
</body>
</html>
