<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Microsoft License Utilization Report - {{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0078d4; }
        .summary { background: #f5f5f5; padding: 20px; margin-bottom: 30px; border-radius: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; }
        .summary-card { text-align: center; padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #0078d4; }
        .summary-card .number { font-size: 28px; font-weight: bold; color: #0078d4; }
        .summary-card .label { font-size: 13px; color: #666; margin-top: 5px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #0078d4; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0078d4; color: white; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .recommendation { padding: 15px; margin-bottom: 15px; border-radius: 5px; border-left: 4px solid; }
        .recommendation.high { background: #fff5f5; border-color: #dc3545; }
        .recommendation.warning { background: #fffbf0; border-color: #ffc107; }
        .recommendation.info { background: #f0f9ff; border-color: #17a2b8; }
        .recommendation-title { font-weight: bold; margin-bottom: 5px; }
        .recommendation-desc { font-size: 14px; color: #666; }
        .cost { color: #28a745; font-weight: bold; }
        .waste { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Microsoft License Utilization Report</h1>
        <p><strong>{{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</strong></p>
        <p>{{ $reportData['period']['formatted'] ?? $period['formatted'] }}</p>
        <p style="font-size: 12px; color: #666;">Generated: {{ $reportData['generated_at'] ?? $generated_at }}</p>
    </div>

    @php
        $summary = $reportData['summary'] ?? [];
        $licenses = $reportData['licenses'] ?? [];
        $recommendations = $reportData['recommendations'] ?? [];
    @endphp

    <div class="summary">
        <h2 style="margin-top: 0;">Executive Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="number">{{ $summary['total_licenses'] ?? 0 }}</div>
                <div class="label">Total License Types</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $summary['total_seats'] ?? 0 }}</div>
                <div class="label">Total Seats</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $summary['used_seats'] ?? 0 }}</div>
                <div class="label">Used Seats</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $summary['available_seats'] ?? 0 }}</div>
                <div class="label">Available Seats</div>
            </div>
            <div class="summary-card">
                <div class="number">{{ $summary['utilization_percentage'] ?? 0 }}%</div>
                <div class="label">Overall Utilization</div>
            </div>
        </div>
    </div>

    @if(count($recommendations) > 0)
    <div class="section">
        <div class="section-title">💡 Recommendations</div>
        @foreach($recommendations as $rec)
        <div class="recommendation {{ $rec['severity'] }}">
            <div class="recommendation-title">{{ $rec['title'] }}</div>
            <div class="recommendation-desc">{{ $rec['description'] }}</div>
            @if(isset($rec['wasted_seats']) && $rec['wasted_seats'] > 0)
            <div style="margin-top: 5px; font-weight: bold; color: #dc3545;">
                Wasted Seats: {{ number_format($rec['wasted_seats']) }} unused
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <div class="section">
        <div class="section-title">📊 License Details</div>
        <table>
            <thead>
                <tr>
                    <th>License Type</th>
                    <th>Total Seats</th>
                    <th>Used</th>
                    <th>Available</th>
                    <th>Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                <tr>
                    <td>{{ $license['license_type'] }}</td>
                    <td>{{ $license['total_seats'] }}</td>
                    <td>{{ $license['used_seats'] }}</td>
                    <td>{{ $license['available_seats'] }}</td>
                    <td>
                        @if($license['utilization_percentage'] >= 95)
                            <span class="badge badge-success">{{ $license['utilization_percentage'] }}%</span>
                        @elseif($license['utilization_percentage'] >= 70)
                            <span class="badge badge-info">{{ $license['utilization_percentage'] }}%</span>
                        @elseif($license['utilization_percentage'] > 0)
                            <span class="badge badge-warning">{{ $license['utilization_percentage'] }}%</span>
                        @else
                            <span class="badge badge-danger">0%</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #666; padding: 40px;">
                        No Microsoft licenses found for this customer.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if(count($licenses) > 0)
            <tfoot style="background: #f5f5f5; font-weight: bold;">
                <tr>
                    <td>TOTAL</td>
                    <td>{{ $summary['total_seats'] ?? 0 }}</td>
                    <td>{{ $summary['used_seats'] ?? 0 }}</td>
                    <td>{{ $summary['available_seats'] ?? 0 }}</td>
                    <td>{{ $summary['utilization_percentage'] ?? 0 }}%</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center;">
        <p>This report is generated from IT Glue flexible asset data. Ensure license information is up-to-date in IT Glue for accurate reporting.</p>
    </div>
</body>
</html>
