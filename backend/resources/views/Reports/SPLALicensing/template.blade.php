<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['name'] ?? 'SPLA Licensing Report' }}</title>
    <style>
        body { font-family: sans-serif; }
        .header { margin-bottom: 20px; }
        .summary-box { border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $report['name'] ?? 'SPLA Licensing Report' }}</h1>
        <p>Values for: {{ $customer_data['name'] }}</p>
        <p>Period: {{ $period['formatted'] }}</p>
    </div>

    @if(isset($executive_summary))
    <div class="summary-box">
        <h2>Executive Summary</h2>
        <p><strong>Total SPLA Users:</strong> {{ $executive_summary['total_spla_users'] }}</p>
        <p><strong>Active Products:</strong> {{ implode(', ', $executive_summary['active_products']) }}</p>
    </div>
    @endif

    @foreach($spla_data as $product => $data)
        @if($data['count'] > 0)
        <h3>{{ $product }} ({{ $data['count'] }} users)</h3>
        <table>
            <thead>
                <tr>
                    <th>Identifier (UPN)</th>
                    <th>Name</th>
                    <th>Qualifying Groups</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['users'] as $user)
                <tr>
                    <td>{{ $user['upn'] }}</td>
                    <td>{{ $user['display_name'] }}</td>
                    <td>{{ implode(', ', $user['qualifying_groups']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    @endforeach

    @if(empty($executive_summary['active_products']))
        <p>No SPLA licenses detected for this period.</p>
    @endif

</body>
</html>
