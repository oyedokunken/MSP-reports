<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Domain Expiry Report - {{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0078d4; }
        .summary { background: #f5f5f5; padding: 20px; margin-bottom: 30px; border-radius: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 15px; }
        .summary-card { text-align: center; padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #0078d4; }
        .summary-card.critical { border-color: #dc3545; }
        .summary-card.warning { border-color: #ffc107; }
        .summary-card.healthy { border-color: #28a745; }
        .summary-card .number { font-size: 28px; font-weight: bold; }
        .summary-card.critical .number { color: #dc3545; }
        .summary-card.warning .number { color: #ffc107; }
        .summary-card.healthy .number { color: #28a745; }
        .summary-card .label { font-size: 13px; color: #666; margin-top: 5px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #0078d4; }
        .alert { padding: 15px; margin-bottom: 15px; border-radius: 5px; border-left: 4px solid; }
        .alert.critical { background: #fff5f5; border-color: #dc3545; }
        .alert.warning { background: #fffbf0; border-color: #ffc107; }
        .alert.info { background: #f0f9ff; border-color: #17a2b8; }
        .alert-title { font-weight: bold; margin-bottom: 5px; }
        .alert.critical .alert-title { color: #dc3545; }
        .alert.warning .alert-title { color: #ffc107; }
        .alert.info .alert-title { color: #17a2b8; }
        .alert-desc { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #0078d4; color: white; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; }
        td { padding: 10px 12px; border-bottom: 1px solid #ddd; font-size: 13px; }
        tr:hover { background: #f9f9f9; }
        .domain-details { font-size: 11px; color: #666; margin-top: 5px; line-height: 1.6; }
        .domain-details strong { color: #333; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-expired { background: #000; color: white; }
        .badge-critical { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-healthy { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Domain Expiry Report</h1>
        <p><strong>{{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</strong></p>
        <p>{{ $reportData['period']['formatted'] ?? $period['formatted'] }}</p>
        <p style="font-size: 12px; color: #666;">Generated: {{ $reportData['generated_at'] ?? $generated_at }}</p>
    </div>

    @php
        $summary = $reportData['summary'] ?? [];
        $domains = $reportData['domains'] ?? [];
        $alerts = $reportData['alerts'] ?? [];
    @endphp

    <div class="summary">
        <h2 style="margin-top: 0;">Executive Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="number">{{ $summary['total_domains'] ?? 0 }}</div>
                <div class="label">Total Domains</div>
            </div>
            <div class="summary-card critical">
                <div class="number">{{ $summary['critical'] ?? 0 }}</div>
                <div class="label">Critical (≤14 days)</div>
            </div>
            <div class="summary-card warning">
                <div class="number">{{ $summary['warning'] ?? 0 }}</div>
                <div class="label">Warning (≤30 days)</div>
            </div>
            <div class="summary-card healthy">
                <div class="number">{{ $summary['healthy'] ?? 0 }}</div>
                <div class="label">Healthy (>60 days)</div>
            </div>
        </div>
    </div>

    @if(count($alerts) > 0)
    <div class="section">
        <div class="section-title">🚨 Alerts</div>
        @foreach($alerts as $alert)
        <div class="alert {{ $alert['severity'] }}">
            <div class="alert-title">{{ $alert['title'] }}</div>
            <div class="alert-desc">{{ $alert['description'] }}</div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="section">
        <div class="section-title">🌐 Domain Details</div>
        <table>
            <thead>
                <tr>
                    <th>Domain Name</th>
                    <th>Registrar</th>
                    <th>Expiry Date</th>
                    <th>Days Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($domains as $domain)
                <tr>
                    <td>
                        <strong>{{ $domain['domain_name'] }}</strong>
                        @if(!empty($domain['organization_name']))
                        <br><small style="color: #0078d4;">📁 {{ $domain['organization_name'] }}</small>
                        @endif
                        @if(!empty($domain['notes']))
                        <div class="domain-details">
                            <strong>Notes:</strong> {{ $domain['notes'] }}
                        </div>
                        @endif
                        @if(!empty($domain['created_at']) || !empty($domain['updated_at']))
                        <div class="domain-details">
                            @if(!empty($domain['created_at']))
                            <strong>Added:</strong> {{ \Carbon\Carbon::parse($domain['created_at'])->format('M d, Y') }}
                            @endif
                            @if(!empty($domain['updated_at']))
                            <br><strong>Updated:</strong> {{ \Carbon\Carbon::parse($domain['updated_at'])->format('M d, Y') }}
                            @endif
                        </div>
                        @endif
                    </td>
                    <td>
                        @if(!empty($domain['registrar']))
                            {{ $domain['registrar'] }}
                        @else
                            <em style="color: #999;">Not recorded in ITGlue</em>
                        @endif
                    </td>
                    <td>
                        @if($domain['expiry_date_formatted'] !== 'Unknown')
                            {{ $domain['expiry_date_formatted'] }}
                        @else
                            <em style="color: #999;">No expiry date on file</em>
                        @endif
                    </td>
                    <td>
                        @if($domain['days_until_expiry'] !== null)
                            @if($domain['days_until_expiry'] <= 0)
                                <strong style="color: #dc3545;">EXPIRED</strong>
                            @else
                                {{ $domain['days_until_expiry'] }} days
                            @endif
                        @else
                            <em style="color: #999;">Please update in ITGlue</em>
                        @endif
                    </td>
                    <td>
                        @if($domain['severity'] === 'expired')
                            <span class="badge badge-expired">EXPIRED</span>
                        @elseif($domain['severity'] === 'critical')
                            <span class="badge badge-critical">CRITICAL</span>
                        @elseif($domain['severity'] === 'warning')
                            <span class="badge badge-warning">WARNING</span>
                        @elseif($domain['severity'] === 'info')
                            <span class="badge badge-info">INFO</span>
                        @else
                            <span class="badge badge-healthy">HEALTHY</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #666; padding: 40px;">
                        No domains found for this customer.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center;">
        <p>This report is generated from IT Glue domain data. Ensure domain information is up-to-date in IT Glue for accurate monitoring.</p>
        <p><strong>Recommendation:</strong> Renew domains at least 30 days before expiry to allow time for DNS propagation and avoid service disruption.</p>
    </div>
</body>
</html>
