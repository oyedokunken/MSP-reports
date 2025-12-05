<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $branding['report_title'] ?? 'Asset Summary' }} - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: {{ $branding['primary_color'] ?? '#1a56db' }};
            --secondary-color: {{ $branding['secondary_color'] ?? '#7c3aed' }};
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1a1a1a;
            font-size: 10pt;
            line-height: 1.5;
        }
        
        .page {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        
        /* Metrics Grid */
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5mm;
            margin-bottom: 10mm;
        }
        
        .metric {
            background: #fafafa;
            padding: 5mm;
            border-radius: 2mm;
        }
        
        .metric-label {
            font-size: 8pt;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2mm;
            font-weight: 500;
        }
        
        .metric-value {
            font-size: 20pt;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1;
        }
        
        .metric-unit {
            font-size: 9pt;
            color: #666;
            font-weight: 400;
            margin-left: 1mm;
        }
        
        /* Section */
        .section {
            margin-bottom: 8mm;
        }
        
        .section-title {
            font-size: 12pt;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #e5e5e5;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        
        th {
            text-align: left;
            font-size: 8pt;
            font-weight: 500;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2mm 2mm;
            border-bottom: 1px solid #e5e5e5;
        }
        
        td {
            padding: 3mm 2mm;
            font-size: 9pt;
            color: #1a1a1a;
            border-bottom: 1px solid #f5f5f5;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: 500;
        }
        
        .badge.success { background: #dcfce7; color: #166534; }
        .badge.warning { background: #fef9c3; color: #854d0e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.neutral { background: #f3f4f6; color: #374151; }
        
        .mono {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
        }
        
        @media print {
            .page { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Report Title and Configuration -->
        <div class="report-info" style="background: #f8fafc; border-left: 4px solid {{ $branding['primary_color'] ?? '#1a56db' }}; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px;">
            <h2 style="font-size: 16pt; font-weight: 600; color: #1a1a1a; margin: 0 0 10px 0;">
                {{ $branding['report_title'] ?? 'Asset Summary Report' }}
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 9pt; color: #666;">
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Type</div>
                    <div style="color: #1a1a1a; font-weight: 500;">Asset Inventory & Warranty Status</div>
                </div>
                @if(isset($period))
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Period</div>
                    <div style="color: #1a1a1a; font-weight: 500;">{{ $period['formatted'] ?? ($period['start'] . ' - ' . $period['end']) }}</div>
                </div>
                @endif
                @if(isset($generated_at))
                <div>
                    <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Generated</div>
                    <div style="color: #1a1a1a; font-weight: 500;">{{ \Carbon\Carbon::parse($generated_at)->format('F j, Y g:i A') }}</div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="metrics">
            <div class="metric">
                <div class="metric-label">Total Assets</div>
                <div class="metric-value">{{ $data['stats']['total'] ?? 0 }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Expiring Soon</div>
                <div class="metric-value" style="{{ ($data['stats']['expiring_soon'] ?? 0) > 0 ? 'color: #d97706;' : '' }}">
                    {{ $data['stats']['expiring_soon'] ?? 0 }}
                </div>
                <div class="metric-unit">(90 Days)</div>
            </div>
            <!-- Placeholders for future metrics -->
            <div class="metric">
                <div class="metric-label">Active</div>
                <div class="metric-value">{{ $data['stats']['total'] ?? 0 }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Retired</div>
                <div class="metric-value">0</div>
            </div>
        </div>
        
        <!-- Asset List -->
        <div class="section">
            <h2 class="section-title">Asset Inventory</h2>
            @if(empty($data['assets']))
                <p style="color: #666; font-style: italic; padding: 10px;">No assets found for this period.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Serial / Tag</th>
                            <th>Status</th>
                            <th>Warranty Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['assets'] as $asset)
                        <tr>
                            <td style="font-weight: 500;">{{ $asset['name'] }}</td>
                            <td>{{ $asset['type'] }}</td>
                            <td class="mono">{{ $asset['serial'] }}</td>
                            <td>
                                <span class="badge {{ strtolower($asset['status']) == 'active' ? 'success' : 'neutral' }}">
                                    {{ $asset['status'] }}
                                </span>
                            </td>
                            <td>
                                @if($asset['warranty_expires_at'])
                                    @php
                                        $expiry = \Carbon\Carbon::parse($asset['warranty_expires_at']);
                                        $isExpiring = $expiry->isFuture() && $expiry->diffInDays(now()) <= 90;
                                        $isExpired = $expiry->isPast();
                                    @endphp
                                    <span class="badge {{ $isExpired ? 'danger' : ($isExpiring ? 'warning' : 'neutral') }}">
                                        {{ $expiry->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span style="color: #999;">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Licenses (Flexible Assets) -->
        @if(!empty($data['licenses']))
        <div class="section">
            <h2 class="section-title">Licenses & Flexible Assets</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Active</th>
                        <th>Consumed</th>
                        <th>Unused</th>
                        <th>Usage</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['licenses'] as $license)
                    <tr>
                        <td style="font-weight: 500;">{{ $license['name'] }}</td>
                        <td>{{ $license['type'] }}</td>
                        
                        @if(isset($license['active_count']))
                            <td>{{ $license['active_count'] }}</td>
                            <td>{{ $license['consumed_count'] }}</td>
                            <td>{{ $license['unused_count'] }}</td>
                            <td>
                                @if($license['consumption_rate'])
                                    <span class="badge {{ floatval($license['consumption_rate']) > 90 ? 'warning' : 'success' }}">
                                        {{ $license['consumption_rate'] }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                        @else
                            <!-- Fallback for non-license flexible assets -->
                            <td colspan="4" style="font-size: 8pt; color: #666;">
                                @if(!empty($license['traits']))
                                    @foreach(array_slice($license['traits'], 0, 3) as $key => $value)
                                        <span style="margin-right: 10px;">
                                            <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ is_array($value) ? implode(', ', $value) : $value }}
                                        </span>
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- SSL Certificates -->
        @if(!empty($data['ssl_certs']))
        <div class="section">
            <h2 class="section-title">SSL Certificates</h2>
            <table>
                <thead>
                    <tr>
                        <th>Common Name</th>
                        <th>Issuer</th>
                        <th>Expires At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['ssl_certs'] as $cert)
                    <tr>
                        <td style="font-weight: 500;">{{ $cert['name'] }}</td>
                        <td>{{ $cert['issuer'] }}</td>
                        <td>
                            @if($cert['expires_at'])
                                @php
                                    $expiry = \Carbon\Carbon::parse($cert['expires_at']);
                                    $isExpiring = $expiry->isFuture() && $expiry->diffInDays(now()) <= 90;
                                    $isExpired = $expiry->isPast();
                                @endphp
                                <span class="badge {{ $isExpired ? 'danger' : ($isExpiring ? 'warning' : 'neutral') }}">
                                    {{ $expiry->format('Y-m-d') }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Domains -->
        @if(!empty($data['domains']))
        <div class="section">
            <h2 class="section-title">Domains</h2>
            <table>
                <thead>
                    <tr>
                        <th>Domain Name</th>
                        <th>Registrar</th>
                        <th>Expires At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['domains'] as $domain)
                    <tr>
                        <td style="font-weight: 500;">{{ $domain['name'] }}</td>
                        <td>{{ $domain['registrar'] }}</td>
                        <td>
                            @if($domain['expires_at'])
                                @php
                                    $expiry = \Carbon\Carbon::parse($domain['expires_at']);
                                    $isExpiring = $expiry->isFuture() && $expiry->diffInDays(now()) <= 90;
                                    $isExpired = $expiry->isPast();
                                @endphp
                                <span class="badge {{ $isExpired ? 'danger' : ($isExpiring ? 'warning' : 'neutral') }}">
                                    {{ $expiry->format('Y-m-d') }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</body>
</html>
