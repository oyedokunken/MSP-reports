<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ITGlue Monthly Summary</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 30px;
            font-size: 12px;
        }
        .header { margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid #e5e5e5; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 6px; }
        .header .meta { font-size: 11px; color: #666; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
        .summary-card { padding: 18px; border: 1px solid #e5e5e5; border-radius: 6px; }
        .summary-card .number { font-size: 28px; font-weight: 600; margin-bottom: 3px; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .sublabel { font-size: 10px; color: #999; margin-top: 6px; }
        .alert { padding: 12px 15px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid; font-size: 11px; }
        .alert.critical { background: #fef2f2; border-color: #dc2626; color: #991b1b; }
        .alert.warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
        .alert-title { font-weight: 600; margin-bottom: 3px; }
        .section { margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .detail { font-size: 10px; color: #666; margin-top: 3px; line-height: 1.4; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-healthy { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 11px; }
        .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ITGlue Monthly Summary</h1>
        <div class="meta">{{ $customer->canonical_name }} • {{ $period['formatted'] }}</div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ $licenses['summary']['total_licenses'] ?? 0 }}</div>
            <div class="label">Microsoft Licenses</div>
            <div class="sublabel">{{ $licenses['summary']['over_provisioned'] ?? 0 }} over-provisioned</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $ssl_certificates['summary']['total_certificates'] ?? 0 }}</div>
            <div class="label">SSL Certificates</div>
            <div class="sublabel">{{ $ssl_certificates['summary']['critical'] ?? 0 }} critical</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $domains['summary']['total_domains'] ?? 0 }}</div>
            <div class="label">Domain Registrations</div>
            <div class="sublabel">{{ $domains['summary']['critical'] ?? 0 }} critical</div>
        </div>
    </div>

    @if(!empty($all_alerts) && is_array($all_alerts) && count($all_alerts) > 0)
    <div class="section">
        @foreach($all_alerts as $alert)
        <div class="alert {{ $alert['severity'] ?? 'info' }}">
            <div class="alert-title">{{ $alert['title'] ?? 'Alert' }}</div>
            <div>{{ $alert['description'] ?? '' }}</div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="section">
        <h2 class="section-title">Microsoft License Utilization</h2>
        @if(!empty($licenses['data']) && is_array($licenses['data']) && count($licenses['data']) > 0)
        <table>
            <thead>
                <tr>
                    <th>License</th>
                    <th>Purchased</th>
                    <th>Assigned</th>
                    <th>Available</th>
                    <th>Utilization</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($licenses['data'] as $license)
                <tr>
                    <td><strong>{{ $license['name'] ?? 'Unknown' }}</strong></td>
                    <td>{{ $license['purchased'] ?? '—' }}</td>
                    <td>{{ $license['assigned'] ?? '—' }}</td>
                    <td>{{ $license['available'] ?? '—' }}</td>
                    <td>{{ $license['utilization_percentage'] ?? 0 }}%</td>
                    <td>
                        @if(($license['status'] ?? '') === 'over_provisioned')
                            <span class="badge badge-warning">Over-provisioned</span>
                        @elseif(($license['status'] ?? '') === 'well_utilized')
                            <span class="badge badge-healthy">Well Utilized</span>
                        @else
                            <span class="badge badge-info">Normal</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No license data available</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">SSL Certificates</h2>
        @if(!empty($ssl_certificates['data']) && is_array($ssl_certificates['data']) && count($ssl_certificates['data']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Domain / Host</th>
                    <th>Issuer</th>
                    <th>Expiry</th>
                    <th>Days Left</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ssl_certificates['data'] as $cert)
                <tr>
                    <td>
                        @if(!empty($cert['resource_url']))
                        <strong><a href="{{ $cert['resource_url'] }}" target="_blank" style="color: #0078d4; text-decoration: none;">{{ $cert['domain'] ?? 'Unknown' }}</a></strong>
                        @else
                        <strong>{{ $cert['domain'] ?? 'Unknown' }}</strong>
                        @endif
                        @if(!empty($cert['common_name']) && $cert['common_name'] !== $cert['domain'])
                        <div class="detail">Host: {{ $cert['common_name'] }}</div>
                        @endif
                        @if(!empty($cert['organization_name']))
                        <div class="detail">Org: {{ $cert['organization_name'] }}</div>
                        @endif
                    </td>
                    <td>
                        {{ ($cert['issuer'] ?? 'Not Available') !== 'Not Available' ? $cert['issuer'] : '—' }}
                        @if(!empty($cert['issued_by']))
                        <div class="detail">Issued by: {{ $cert['issued_by'] }}</div>
                        @endif
                        @if(!empty($cert['issuer_country']))
                        <div class="detail">Country: {{ $cert['issuer_country'] }}</div>
                        @endif
                    </td>
                    <td>
                        {{ ($cert['expiry_date_formatted'] ?? 'Unknown') !== 'Unknown' ? $cert['expiry_date_formatted'] : '—' }}
                        @if(!empty($cert['created_at']))
                        <div class="detail">Added: {{ \Carbon\Carbon::parse($cert['created_at'])->format('M d, Y') }}</div>
                        @endif
                    </td>
                    <td>
                        @if(isset($cert['days_until_expiry']) && $cert['days_until_expiry'] !== null)
                            {{ $cert['days_until_expiry'] <= 0 ? 'Expired' : $cert['days_until_expiry'] . ' days' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if(in_array($cert['severity'] ?? '', ['expired', 'critical']))
                            <span class="badge badge-critical">Critical</span>
                        @elseif(($cert['severity'] ?? '') === 'warning')
                            <span class="badge badge-warning">Warning</span>
                        @else
                            <span class="badge badge-healthy">Healthy</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No SSL certificate data available</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">Domain Registrations</h2>
        @if(!empty($domains['data']) && is_array($domains['data']) && count($domains['data']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Registrar</th>
                    <th>Expiry</th>
                    <th>Days Left</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domains['data'] as $domain)
                <tr>
                    <td>
                        @if(!empty($domain['resource_url']))
                        <strong><a href="{{ $domain['resource_url'] }}" target="_blank" style="color: #0078d4; text-decoration: none;">{{ $domain['domain_name'] ?? 'Unknown' }}</a></strong>
                        @else
                        <strong>{{ $domain['domain_name'] ?? 'Unknown' }}</strong>
                        @endif
                        @if(!empty($domain['organization_name']))
                        <div class="detail">Org: {{ $domain['organization_name'] }}</div>
                        @endif
                        @if(!empty($domain['notes']))
                        <div class="detail">{{ $domain['notes'] }}</div>
                        @endif
                    </td>
                    <td>
                        {{ !empty($domain['registrar']) ? $domain['registrar'] : '—' }}
                        @if(!empty($domain['created_at']))
                        <div class="detail">Added: {{ \Carbon\Carbon::parse($domain['created_at'])->format('M d, Y') }}</div>
                        @endif
                        @if(!empty($domain['updated_at']))
                        <div class="detail">Updated: {{ \Carbon\Carbon::parse($domain['updated_at'])->format('M d, Y') }}</div>
                        @endif
                    </td>
                    <td>{{ ($domain['expiry_date_formatted'] ?? 'Unknown') !== 'Unknown' ? $domain['expiry_date_formatted'] : '—' }}</td>
                    <td>
                        @if(isset($domain['days_until_expiry']) && $domain['days_until_expiry'] !== null)
                            {{ $domain['days_until_expiry'] <= 0 ? 'Expired' : $domain['days_until_expiry'] . ' days' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if(in_array($domain['severity'] ?? '', ['expired', 'critical']))
                            <span class="badge badge-critical">Critical</span>
                        @elseif(($domain['severity'] ?? '') === 'warning')
                            <span class="badge badge-warning">Warning</span>
                        @else
                            <span class="badge badge-healthy">Healthy</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No domain registration data available</div>
        @endif
    </div>

    <div class="footer">
        ITGlue Monthly Summary Report • Data sourced from IT Glue
    </div>
</body>
</html>
