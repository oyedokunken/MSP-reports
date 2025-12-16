<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sendmarc Monthly Summary</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px;
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
        .section { margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 11px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 6px; }
        .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ is_array($branding) ? ($branding['company_name'] ?? 'Territory Technology Solutions') : 'Territory Technology Solutions' }}</div>
        <h1>Sendmarc Monthly Summary</h1>
        <div class="meta">
            {{ is_object($customer) ? $customer->canonical_name : (is_array($customer) ? ($customer['canonical_name'] ?? 'Customer') : 'Customer') }} 
            • 
            {{ is_array($period) ? ($period['formatted'] ?? '') : $period }}
        </div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ $executive_summary['total_emails'] ?? 0 }}</div>
            <div class="label">Total Emails</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['compliance_rate'] ?? 0 }}%</div>
            <div class="label">Compliance Rate</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['complete_passes'] ?? 0 }}</div>
            <div class="label">Complete Passes</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $executive_summary['complete_failures'] ?? 0 }}</div>
            <div class="label">Complete Failures</div>
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
        <h2 class="section-title">Top Senders</h2>
        @if(!empty($top_senders) && is_array($top_senders) && count($top_senders) > 0)
        <table>
            <thead>
                <tr>
                    <th>Sender</th>
                    <th>Category</th>
                    <th>Total</th>
                    <th>Passes</th>
                    <th>Failures</th>
                    <th>Blacklisted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top_senders as $s)
                <tr>
                    <td><strong>{{ $s['sender'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $s['category'] ?? '—' }}</td>
                    <td>{{ $s['total'] ?? 0 }}</td>
                    <td>{{ $s['passes'] ?? 0 }}</td>
                    <td>{{ $s['failures'] ?? 0 }}</td>
                    <td>{{ $s['blacklisted'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No sender data available</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">Top Source IPs</h2>
        @if(!empty($ip_sources) && is_array($ip_sources) && count($ip_sources) > 0)
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Organisation</th>
                    <th>Service</th>
                    <th>Total</th>
                    <th>Passes</th>
                    <th>Failures</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ip_sources as $ip)
                <tr>
                    <td><strong>{{ $ip['ip'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $ip['organisation'] ?? '—' }}</td>
                    <td>{{ $ip['service'] ?? '—' }}</td>
                    <td>{{ $ip['total'] ?? 0 }}</td>
                    <td>{{ $ip['passes'] ?? 0 }}</td>
                    <td>{{ $ip['failures'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No IP source data available</div>
        @endif
    </div>

    <div class="footer">
        Sendmarc Monthly Summary • Data sourced from Sendmarc
    </div>
</body>
</html>
