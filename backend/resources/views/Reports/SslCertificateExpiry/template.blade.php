<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SSL Certificate Expiry Report - {{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</title>
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
        .alert.high { background: #fff8e1; border-color: #ff9800; }
        .alert.warning { background: #fffbf0; border-color: #ffc107; }
        .alert-title { font-weight: bold; margin-bottom: 5px; }
        .alert.critical .alert-title { color: #dc3545; }
        .alert.high .alert-title { color: #ff9800; }
        .alert.warning .alert-title { color: #ffc107; }
        .alert-desc { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #0078d4; color: white; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; }
        td { padding: 10px 12px; border-bottom: 1px solid #ddd; font-size: 13px; }
        tr:hover { background: #f9f9f9; }
        .cert-details { font-size: 11px; color: #666; margin-top: 5px; line-height: 1.6; }
        .cert-details strong { color: #333; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-expired { background: #000; color: white; }
        .badge-critical { background: #dc3545; color: white; }
        .badge-high { background: #ff9800; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-healthy { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SSL Certificate Expiry Report</h1>
        <p><strong>{{ $reportData['customer_data']['name'] ?? $customer->canonical_name }}</strong></p>
        <p>{{ $reportData['period']['formatted'] ?? $period['formatted'] }}</p>
        <p style="font-size: 12px; color: #666;">Generated: {{ $reportData['generated_at'] ?? $generated_at }}</p>
    </div>

    @php
        $summary = $reportData['summary'] ?? [];
        $certificates = $reportData['certificates'] ?? [];
        $alerts = $reportData['alerts'] ?? [];
    @endphp

    <div class="summary">
        <h2 style="margin-top: 0;">Executive Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="number">{{ $summary['total_certificates'] ?? 0 }}</div>
                <div class="label">Total Certificates</div>
            </div>
            <div class="summary-card critical">
                <div class="number">{{ $summary['critical'] ?? 0 }}</div>
                <div class="label">Critical (≤7 days)</div>
            </div>
            <div class="summary-card warning">
                <div class="number">{{ $summary['warning'] ?? 0 }}</div>
                <div class="label">Warning (≤30 days)</div>
            </div>
            <div class="summary-card healthy">
                <div class="number">{{ $summary['healthy'] ?? 0 }}</div>
                <div class="label">Healthy (>30 days)</div>
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
        <div class="section-title">🔒 Certificate Details</div>
        <table>
            <thead>
                <tr>
                    <th>Domain / Common Name</th>
                    <th>Issuer</th>
                    <th>Expiry Date</th>
                    <th>Days Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $cert)
                <tr>
                    <td>
                        <strong>{{ $cert['domain'] }}</strong>
                        @if($cert['domain'] !== $cert['common_name'])
                        <br><small style="color: #666;">Host: {{ $cert['common_name'] }}</small>
                        @endif
                        @if(!empty($cert['organization_name']))
                        <br><small style="color: #0078d4;">📁 {{ $cert['organization_name'] }}</small>
                        @endif
                        @if(!empty($cert['notes']))
                        <div class="cert-details">
                            <strong>Notes:</strong> {{ $cert['notes'] }}
                        </div>
                        @endif
                    </td>
                    <td>
                        {{ $cert['issuer'] }}
                        @if(!empty($cert['issuer_country']))
                        <br><small style="color: #666;">{{ $cert['issuer_country'] }}</small>
                        @endif
                        @if(!empty($cert['signature_algorithm']))
                        <div class="cert-details">
                            <strong>Algorithm:</strong> {{ $cert['signature_algorithm'] }}
                        </div>
                        @endif
                    </td>
                    <td>{{ $cert['expiry_date_formatted'] }}</td>
                    <td>
                        @if($cert['days_until_expiry'] !== null)
                            @if($cert['days_until_expiry'] <= 0)
                                <strong style="color: #dc3545;">EXPIRED</strong>
                            @else
                                {{ $cert['days_until_expiry'] }} days
                            @endif
                        @else
                            Unknown
                        @endif
                    </td>
                    <td>
                        @if($cert['severity'] === 'expired')
                            <span class="badge badge-expired">EXPIRED</span>
                        @elseif($cert['severity'] === 'critical')
                            <span class="badge badge-critical">CRITICAL</span>
                        @elseif($cert['severity'] === 'high')
                            <span class="badge badge-high">HIGH</span>
                        @elseif($cert['severity'] === 'warning')
                            <span class="badge badge-warning">WARNING</span>
                        @else
                            <span class="badge badge-healthy">HEALTHY</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #666; padding: 40px;">
                        No SSL certificates found for this customer.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center;">
        <p>This report is generated from IT Glue SSL certificate data. Ensure certificate information is up-to-date in IT Glue for accurate monitoring.</p>
        <p><strong>Recommendation:</strong> Renew certificates at least 14 days before expiry to allow time for deployment and testing.</p>
    </div>
</body>
</html>
