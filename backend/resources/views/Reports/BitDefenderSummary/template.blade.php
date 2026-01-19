<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bitdefender {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</title>
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
        .summary-card { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; }
        .summary-card .number { font-size: 28px; font-weight: 600; margin-bottom: 3px; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .sublabel { font-size: 10px; color: #999; margin-top: 6px; }
        .section { margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 11px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bitdefender {{ ucfirst($options['frequency'] ?? 'Monthly') }} Summary</h1>
        <div class="company-name">{{ is_array($branding) ? ($branding['company_name'] ?? 'Territory Technology Solutions') : 'Territory Technology Solutions' }}</div>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ $executive_summary['total_endpoints'] ?? 0 }}</div>
            <div class="label">Total Endpoints</div>
            <div class="sublabel">{{ $executive_summary['managed_endpoints'] ?? 0 }} managed</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['policy_compliance_rate'] ?? 0 }}%</div>
            <div class="label">Policy Compliance</div>
            <div class="sublabel">{{ $executive_summary['unique_policies'] ?? 0 }} policies</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['unmanaged_endpoints'] ?? 0 }}</div>
            <div class="label">Unmanaged</div>
            <div class="sublabel">Needs attention</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['total_threats'] ?? 0 }}</div>
            <div class="label">Threats Detected</div>
            <div class="sublabel">This period</div>
        </div>
    </div>

    @if(!empty($alerts) && is_array($alerts))
    <div class="section">
        <h2 class="section-title">Alerts & Recommendations</h2>
        <ul>
            @foreach($alerts as $rec)
                <li>{{ $rec }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="section">
        <h2 class="section-title">Policy Compliance Overview</h2>
        @if(!empty($policy_details) && is_array($policy_details) && count($policy_details) > 0)
        <table>
            <thead>
                <tr>
                    <th>Policy Name</th>
                    <th>Total Endpoints</th>
                    <th>Applied</th>
                    <th>Pending</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($policy_details as $policy)
                <tr>
                    <td><strong>{{ $policy['name'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $policy['total'] ?? 0 }}</td>
                    <td>{{ $policy['applied'] ?? 0 }}</td>
                    <td>{{ ($policy['total'] ?? 0) - ($policy['applied'] ?? 0) }}</td>
                    <td>
                        @if(($policy['total'] ?? 0) === ($policy['applied'] ?? 0))
                            <span class="badge success">Fully Applied</span>
                        @elseif(($policy['applied'] ?? 0) > 0)
                            <span class="badge warning">Partially Applied</span>
                        @else
                            <span class="badge danger">Not Applied</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No policy data available</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">Operating System Distribution</h2>
        @if(!empty($os_distribution) && is_array($os_distribution) && count($os_distribution) > 0)
        <table>
            <thead>
                <tr>
                    <th>Operating System</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($os_distribution as $os)
                <tr>
                    <td><strong>{{ $os['os'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $os['count'] ?? 0 }}</td>
                    <td>{{ $os['percentage'] ?? 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No OS data available</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">Endpoint Inventory</h2>
        @if(!empty($endpoints_list) && is_array($endpoints_list) && count($endpoints_list) > 0)
        <table>
            <thead>
                <tr>
                    <th>Endpoint Name</th>
                    <th>IP Address</th>
                    <th>Operating System</th>
                    <th>Policy</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($endpoints_list as $endpoint)
                <tr>
                    <td>
                        <strong>{{ $endpoint['name'] ?? 'Unknown' }}</strong>
                        @if(!empty($endpoint['fqdn']))
                        <div style="font-size: 10px; color: #666;">{{ $endpoint['fqdn'] }}</div>
                        @endif
                    </td>
                    <td>{{ $endpoint['ip'] ?? '—' }}</td>
                    <td>{{ $endpoint['os'] ?? 'Unknown' }}</td>
                    <td>
                        {{ $endpoint['policy_name'] ?? 'No Policy' }}
                        @if(($endpoint['policy_applied'] ?? false))
                            <span class="badge success">Applied</span>
                        @else
                            <span class="badge warning">Pending</span>
                        @endif
                    </td>
                    <td>{{ $endpoint['machine_type'] ?? 'Unknown' }}</td>
                    <td>
                        @if(($endpoint['is_managed'] ?? false))
                            <span class="badge success">Managed</span>
                        @else
                            <span class="badge danger">Unmanaged</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No endpoint data available</div>
        @endif
    </div>

</body>
</html>
