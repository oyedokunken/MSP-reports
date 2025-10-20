<!DOCTYPE html>
<html>
<head>
    <title>Uptime Report - {{ $customer->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <h1>Uptime Report - {{ $customer->name }}</h1>
    <p>Reporting Period: {{ now()->subDays(30)->format('M d, Y') }} to {{ now()->format('M d, Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Device ID</th>
                <th>Average Uptime (%)</th>
                <th>Max Downtime (minutes)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $deviceId => $stats)
                <tr>
                    <td>{{ $deviceId }}</td>
                    <td>{{ number_format($stats['avg_uptime'], 2) }}</td>
                    <td>{{ $stats['max_downtime'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
