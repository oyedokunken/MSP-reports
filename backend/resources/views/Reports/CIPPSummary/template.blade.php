<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Microsoft 365 {{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly')) }} Summary Report - {{ $customer->canonical_name }}</title>
    @if(isset($branding['chart_js_inline']) && $branding['chart_js_inline'])
    <script>
        {!! $branding['chart_js_inline'] !!}
        window.chartJsLoaded = typeof Chart !== 'undefined';
    </script>
    @else
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.chartJsLoaded = typeof Chart !== 'undefined';
    </script>
    @endif
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.5;
            color: #1e293b;
            max-width: 1200px;
            margin: 0 auto;
            padding: {{ (isset($render_part) && $render_part === 'cover') ? '0' : '20px 10px' }};
            font-size: 11px;
            background-color: #ffffff;
        }

        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .header .meta { font-size: 12px; color: #64748b; font-weight: 500; }

        .section { margin-bottom: 35px; }
        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 2px solid {{ ($branding['primary_color'] ?? '#1a56db') }}20;
            color: #0f172a;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stats-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box {
            padding: 20px 15px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 85px;
        }
        .stat-box .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; font-weight: 700; }
        .stat-box .stat-value { font-size: 24px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; }
        .stat-box .stat-sub { font-size: 9px; color: #94a3b8; margin-top: 2px; }
        .stat-box.highlight { border-color: {{ $branding['primary_color'] ?? '#1a56db' }}; background: {{ ($branding['primary_color'] ?? '#1a56db') }}08; }
        .stat-box.danger { border-color: #ef4444; background: #fef2f2; }
        .stat-box.success { border-color: #10b981; background: #f0fdf4; }

        .chart-card {
            margin-bottom: 30px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .chart-title { font-size: 13px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 10.5px;
            margin-bottom: 25px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
        th {
            text-align: left;
            padding: 10px 14px;
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.025em;
        }
        td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: top; color: #334155; }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 5px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        .badge.neutral { background: #f1f5f9; color: #475569; }

        .alert-card {
            padding: 14px 18px;
            margin-bottom: 12px;
            border-left: 4px solid;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border-top: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }
        .alert-card.critical { border-left-color: #ef4444; }
        .alert-card.high { border-left-color: #f59e0b; }
        .alert-card.medium { border-left-color: #3b82f6; }
        .alert-card.low { border-left-color: #10b981; }
        .alert-card.info { border-left-color: #6366f1; }
        .alert-card .alert-title { font-weight: 700; font-size: 12px; margin-bottom: 3px; color: #0f172a; }
        .alert-card .alert-desc { font-size: 11px; color: #475569; margin-bottom: 4px; }
        .alert-card .alert-action { font-size: 10px; color: #64748b; font-style: italic; }

        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-bar .fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
        .progress-bar .fill.green { background: #10b981; }
        .progress-bar .fill.yellow { background: #f59e0b; }
        .progress-bar .fill.red { background: #ef4444; }
        .progress-bar .fill.blue { background: {{ $branding['primary_color'] ?? '#1a56db' }}; }

        .cover-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1a56db' }}10 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }}10 100%);
            padding: 60px 40px;
            position: relative;
        }
        .cover-page h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 16px;
            letter-spacing: -0.03em;
        }
        .cover-page .subtitle {
            font-size: 16px;
            color: #64748b;
            max-width: 500px;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        .cover-page .customer-name {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: {{ $branding['primary_color'] ?? '#1a56db' }};
            margin-bottom: 8px;
        }
        .cover-page .period {
            font-size: 14px;
            color: #94a3b8;
        }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

@php
    $renderPart = $render_part ?? 'all';
    $sectionsToRender = $sections_to_render ?? null;
    $frequency = is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly');
    $frequencyLabel = ucfirst($frequency);
@endphp

{{-- COVER PAGE --}}
@if($renderPart === 'cover' || $renderPart === 'all')
<div class="cover-page">
    @if(isset($branding['company_logo']) && $branding['company_logo'])
        <img src="{{ $branding['company_logo'] }}" alt="{{ $branding['company_name'] ?? '' }}" style="max-height: 80px; margin-bottom: 40px;">
    @endif
    <h1>{{ $reportData['cover_title'] ?? 'Microsoft 365 Summary Report' }}</h1>
    <p class="subtitle">{{ $reportData['cover_subtitle'] ?? 'Comprehensive overview of tenant security, identity, licensing, and compliance posture.' }}</p>
    <p class="customer-name">{{ $customer->canonical_name }}</p>
    <p class="period">{{ $period['formatted'] ?? '' }}</p>
</div>
@endif

@if($renderPart === 'content' || $renderPart === 'all')

{{-- EXECUTIVE SUMMARY --}}
@if(!$sectionsToRender || in_array('executive_summary', $sectionsToRender))
<div class="section">
    <h2 class="section-title">Executive Summary</h2>
    <div class="stats-grid">
        <div class="stat-box {{ ($executive_summary['mfa_percentage'] ?? 0) >= 90 ? 'success' : 'danger' }}">
            <div class="stat-label">MFA Coverage</div>
            <div class="stat-value">{{ $executive_summary['mfa_percentage'] ?? 0 }}%</div>
            <div class="stat-sub">{{ $executive_summary['mfa_enabled'] ?? 0 }} of {{ $executive_summary['total_users'] ?? 0 }} users</div>
        </div>
        <div class="stat-box highlight">
            <div class="stat-label">Total Users</div>
            <div class="stat-value">{{ number_format($executive_summary['total_users'] ?? 0) }}</div>
            <div class="stat-sub">{{ $executive_summary['total_assigned_licenses'] ?? 0 }} licenses assigned</div>
        </div>
        <div class="stat-box {{ ($executive_summary['total_alerts'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stat-label">Security Alerts</div>
            <div class="stat-value">{{ $executive_summary['total_alerts'] ?? 0 }}</div>
            <div class="stat-sub">{{ $executive_summary['high_severity_alerts'] ?? 0 }} high severity</div>
        </div>
        <div class="stat-box {{ ($executive_summary['defender_protection_rate'] ?? 0) >= 90 ? 'success' : 'danger' }}">
            <div class="stat-label">Defender Coverage</div>
            <div class="stat-value">{{ $executive_summary['defender_protection_rate'] ?? 0 }}%</div>
            <div class="stat-sub">{{ $executive_summary['defender_devices'] ?? 0 }} devices</div>
        </div>
    </div>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">License Types</div>
            <div class="stat-value">{{ $executive_summary['total_license_types'] ?? 0 }}</div>
            <div class="stat-sub">{{ $executive_summary['total_available_licenses'] ?? 0 }} available</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">CA Policies</div>
            <div class="stat-value">{{ $executive_summary['ca_policies_enabled'] ?? 0 }}</div>
            <div class="stat-sub">of {{ $executive_summary['ca_policies_total'] ?? 0 }} total</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Mailboxes</div>
            <div class="stat-value">{{ number_format($executive_summary['total_mailboxes'] ?? 0) }}</div>
            <div class="stat-sub">{{ $executive_summary['total_storage_gb'] ?? 0 }} GB storage</div>
        </div>
        <div class="stat-box {{ ($executive_summary['total_incidents'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stat-label">Incidents</div>
            <div class="stat-value">{{ $executive_summary['total_incidents'] ?? 0 }}</div>
            <div class="stat-sub">during period</div>
        </div>
    </div>
</div>
@endif

{{-- USERS & IDENTITY --}}
@if(!$sectionsToRender || in_array('user_identity', $sectionsToRender))
<div class="section">
    <h2 class="section-title">Users & Identity (MFA)</h2>

    <div class="stats-grid-3">
        <div class="stat-box">
            <div class="stat-label">Total Users</div>
            <div class="stat-value">{{ $user_identity['total_users'] ?? 0 }}</div>
        </div>
        <div class="stat-box {{ ($user_identity['mfa_percentage'] ?? 0) >= 90 ? 'success' : 'danger' }}">
            <div class="stat-label">MFA Enabled</div>
            <div class="stat-value">{{ $user_identity['mfa_enabled'] ?? 0 }}</div>
            <div class="stat-sub">{{ $user_identity['mfa_percentage'] ?? 0 }}% coverage</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Admin Accounts</div>
            <div class="stat-value">{{ $user_identity['admins'] ?? 0 }}</div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <span class="chart-title">MFA Coverage</span>
        </div>
        <div class="progress-bar" style="height: 12px; margin-bottom: 8px;">
            <div class="fill {{ ($user_identity['mfa_percentage'] ?? 0) >= 80 ? 'green' : (($user_identity['mfa_percentage'] ?? 0) >= 50 ? 'yellow' : 'red') }}" style="width: {{ $user_identity['mfa_percentage'] ?? 0 }}%;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 10px; color: #64748b;">
            <span>{{ $user_identity['mfa_enabled'] ?? 0 }} enabled</span>
            <span>{{ $user_identity['mfa_disabled'] ?? 0 }} without MFA</span>
        </div>
    </div>

    @if(!empty($user_identity['users']))
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>MFA</th>
                <th>Admin</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($user_identity['users'] ?? [], 0, 25) as $user)
            <tr>
                <td style="font-weight: 600;">{{ $user['name'] }}</td>
                <td>{{ $user['email'] }}</td>
                <td>
                    @if(in_array(strtolower((string)($user['mfa_status'] ?? '')), ['enabled', 'enforced', 'true', '1']))
                        <span class="badge success">Enabled</span>
                    @else
                        <span class="badge danger">Disabled</span>
                    @endif
                </td>
                <td>
                    @if($user['is_admin'] ?? false)
                        <span class="badge info">Admin</span>
                    @else
                        <span class="badge neutral">User</span>
                    @endif
                </td>
                <td>
                    @if($user['account_enabled'] ?? true)
                        <span class="badge success">Active</span>
                    @else
                        <span class="badge danger">Blocked</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($user_identity['users'] ?? []) > 25)
        <p style="text-align: center; font-size: 10px; color: #94a3b8;">Showing 25 of {{ count($user_identity['users']) }} users</p>
    @endif
    @endif
</div>
@endif

{{-- LICENSE UTILIZATION --}}
@if(!$sectionsToRender || in_array('license_utilization', $sectionsToRender))
<div class="section page-break">
    <h2 class="section-title">License Utilization</h2>

    @if(!empty($license_utilization['licenses']))
    <table>
        <thead>
            <tr>
                <th>License</th>
                <th>Assigned</th>
                <th>Total</th>
                <th>Available</th>
                <th>Utilization</th>
            </tr>
        </thead>
        <tbody>
            @foreach($license_utilization['licenses'] ?? [] as $license)
            <tr>
                <td style="font-weight: 600;">{{ $license['name'] }}</td>
                <td>{{ number_format($license['assigned']) }}</td>
                <td>{{ number_format($license['total']) }}</td>
                <td>{{ number_format($license['available']) }}</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="progress-bar" style="flex: 1; height: 6px;">
                            <div class="fill {{ $license['utilization'] > 90 ? 'red' : ($license['utilization'] > 70 ? 'yellow' : 'green') }}" style="width: {{ min($license['utilization'], 100) }}%;"></div>
                        </div>
                        <span style="font-size: 10px; font-weight: 600; min-width: 35px;">{{ $license['utilization'] }}%</span>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="text-align: center; color: #94a3b8; padding: 20px;">No license data available for this period.</p>
    @endif
</div>
@endif

{{-- SECURITY OVERVIEW --}}
@if(!$sectionsToRender || in_array('security_overview', $sectionsToRender))
<div class="section">
    <h2 class="section-title">Security Alerts & Incidents</h2>

    <div class="stats-grid">
        <div class="stat-box {{ ($security_overview['severity_counts']['high'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stat-label">High Severity</div>
            <div class="stat-value">{{ $security_overview['severity_counts']['high'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Medium</div>
            <div class="stat-value">{{ $security_overview['severity_counts']['medium'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Low</div>
            <div class="stat-value">{{ $security_overview['severity_counts']['low'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Informational</div>
            <div class="stat-value">{{ $security_overview['severity_counts']['informational'] ?? 0 }}</div>
        </div>
    </div>

    @if(!empty($security_overview['alerts']))
    <h3 style="font-size: 13px; font-weight: 700; margin-bottom: 12px; color: #334155;">Recent Alerts</h3>
    <table>
        <thead>
            <tr>
                <th>Alert</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Category</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($security_overview['alerts'] as $alert)
            <tr>
                <td style="font-weight: 600;">{{ $alert['title'] }}</td>
                <td><span class="badge {{ $alert['severity'] === 'high' ? 'danger' : ($alert['severity'] === 'medium' ? 'warning' : 'info') }}">{{ ucfirst($alert['severity']) }}</span></td>
                <td><span class="badge {{ $alert['status'] === 'resolved' ? 'success' : 'warning' }}">{{ ucfirst($alert['status']) }}</span></td>
                <td>{{ $alert['category'] }}</td>
                <td>{{ \Carbon\Carbon::parse($alert['created_at'])->format('M j, Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($security_overview['incidents']))
    <h3 style="font-size: 13px; font-weight: 700; margin-bottom: 12px; color: #334155;">Security Incidents</h3>
    <table>
        <thead>
            <tr>
                <th>Incident</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Classification</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($security_overview['incidents'] as $incident)
            <tr>
                <td style="font-weight: 600;">{{ $incident['title'] }}</td>
                <td><span class="badge {{ $incident['severity'] === 'high' ? 'danger' : ($incident['severity'] === 'medium' ? 'warning' : 'info') }}">{{ ucfirst($incident['severity']) }}</span></td>
                <td><span class="badge {{ $incident['status'] === 'resolved' ? 'success' : 'warning' }}">{{ ucfirst($incident['status']) }}</span></td>
                <td>{{ $incident['classification'] }}</td>
                <td>{{ \Carbon\Carbon::parse($incident['created_at'])->format('M j, Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(empty($security_overview['alerts']) && empty($security_overview['incidents']))
    <div class="chart-card" style="text-align: center; padding: 30px;">
        <div style="font-size: 32px; margin-bottom: 8px;">&#x2705;</div>
        <div style="font-size: 14px; font-weight: 700; color: #065f46;">No Security Alerts or Incidents</div>
        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">No security events were detected during this reporting period.</div>
    </div>
    @endif
</div>
@endif

{{-- DEFENDER STATUS --}}
@if(!$sectionsToRender || in_array('defender_status', $sectionsToRender))
<div class="section page-break">
    <h2 class="section-title">Microsoft Defender Status</h2>

    <div class="stats-grid-3">
        <div class="stat-box {{ ($defender_status['protection_rate'] ?? 0) >= 90 ? 'success' : 'danger' }}">
            <div class="stat-label">Protection Rate</div>
            <div class="stat-value">{{ $defender_status['protection_rate'] ?? 0 }}%</div>
            <div class="stat-sub">{{ $defender_status['protected'] ?? 0 }} of {{ $defender_status['total_devices'] ?? 0 }} devices</div>
        </div>
        <div class="stat-box {{ ($defender_status['unprotected'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stat-label">Unprotected</div>
            <div class="stat-value">{{ $defender_status['unprotected'] ?? 0 }}</div>
        </div>
        <div class="stat-box {{ ($defender_status['outdated_definitions'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stat-label">Outdated Definitions</div>
            <div class="stat-value">{{ $defender_status['outdated_definitions'] ?? 0 }}</div>
        </div>
    </div>

    @if(!empty($defender_status['devices']))
    <table>
        <thead>
            <tr>
                <th>Device</th>
                <th>OS</th>
                <th>Protected</th>
                <th>Definitions</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($defender_status['devices'] ?? [], 0, 20) as $device)
            <tr>
                <td style="font-weight: 600;">{{ $device['name'] }}</td>
                <td>{{ $device['os'] }}</td>
                <td><span class="badge {{ ($device['is_protected'] ?? false) ? 'success' : 'danger' }}">{{ ($device['is_protected'] ?? false) ? 'Yes' : 'No' }}</span></td>
                <td><span class="badge {{ ($device['definitions_up_to_date'] ?? false) ? 'success' : 'danger' }}">{{ ($device['definitions_up_to_date'] ?? false) ? 'Current' : 'Outdated' }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($defender_status['devices'] ?? []) > 20)
        <p style="text-align: center; font-size: 10px; color: #94a3b8;">Showing 20 of {{ count($defender_status['devices']) }} devices</p>
    @endif
    @else
    <p style="text-align: center; color: #94a3b8; padding: 20px;">No Defender data available.</p>
    @endif
</div>
@endif

{{-- CONDITIONAL ACCESS --}}
@if(!$sectionsToRender || in_array('conditional_access', $sectionsToRender))
<div class="section">
    <h2 class="section-title">Conditional Access Policies</h2>

    <div class="stats-grid-3">
        <div class="stat-box success">
            <div class="stat-label">Enabled</div>
            <div class="stat-value">{{ $conditional_access['enabled'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Report Only</div>
            <div class="stat-value">{{ $conditional_access['report_only'] ?? 0 }}</div>
        </div>
        <div class="stat-box {{ ($conditional_access['disabled'] ?? 0) > 0 ? 'danger' : '' }}">
            <div class="stat-label">Disabled</div>
            <div class="stat-value">{{ $conditional_access['disabled'] ?? 0 }}</div>
        </div>
    </div>

    @if(!empty($conditional_access['policies']))
    <table>
        <thead>
            <tr>
                <th>Policy Name</th>
                <th>State</th>
            </tr>
        </thead>
        <tbody>
            @foreach($conditional_access['policies'] ?? [] as $policy)
            <tr>
                <td style="font-weight: 600;">{{ $policy['name'] }}</td>
                <td>
                    @if(in_array(strtolower($policy['state']), ['enabled', 'on']))
                        <span class="badge success">Enabled</span>
                    @elseif(in_array(strtolower($policy['state']), ['reportonly', 'report-only']))
                        <span class="badge warning">Report Only</span>
                    @else
                        <span class="badge danger">Disabled</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="text-align: center; color: #94a3b8; padding: 20px;">No conditional access policies configured.</p>
    @endif
</div>
@endif

{{-- EMAIL & EXCHANGE PROTECTION --}}
@if(!$sectionsToRender || in_array('email_protection', $sectionsToRender))
<div class="section page-break">
    <h2 class="section-title">Email & Exchange Protection</h2>

    <div class="stats-grid-3">
        <div class="stat-box highlight">
            <div class="stat-label">Total Mailboxes</div>
            <div class="stat-value">{{ number_format($email_protection['total_mailboxes'] ?? 0) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Storage</div>
            <div class="stat-value">{{ $email_protection['total_storage_gb'] ?? 0 }} GB</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Avg per Mailbox</div>
            <div class="stat-value">{{ $email_protection['avg_storage_gb'] ?? 0 }} GB</div>
        </div>
    </div>

    @if(!empty($email_protection['mailboxes']))
    <h3 style="font-size: 13px; font-weight: 700; margin-bottom: 12px; color: #334155;">Top Mailboxes by Storage</h3>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>Storage (GB)</th>
                <th>Items</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($email_protection['mailboxes'] ?? [], 0, 15) as $mailbox)
            <tr>
                <td style="font-weight: 600;">{{ $mailbox['display_name'] }}</td>
                <td>{{ $mailbox['email'] }}</td>
                <td>{{ $mailbox['storage_gb'] }}</td>
                <td>{{ number_format($mailbox['item_count']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endif

{{-- RECOMMENDATIONS --}}
@if(!$sectionsToRender || in_array('recommendations', $sectionsToRender))
<div class="section">
    <h2 class="section-title">Recommendations</h2>

    @forelse($recommendations ?? [] as $rec)
    <div class="alert-card {{ $rec['severity'] }}">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
            <span class="badge {{ $rec['severity'] === 'critical' ? 'danger' : ($rec['severity'] === 'high' ? 'warning' : ($rec['severity'] === 'medium' ? 'info' : ($rec['severity'] === 'info' ? 'info' : 'success'))) }}">{{ ucfirst($rec['severity']) }}</span>
            <span class="alert-title">{{ $rec['title'] }}</span>
        </div>
        <div class="alert-desc">{{ $rec['description'] }}</div>
        <div class="alert-action"><strong>Action:</strong> {{ $rec['action'] }}</div>
    </div>
    @empty
    <div class="chart-card" style="text-align: center; padding: 30px;">
        <div style="font-size: 32px; margin-bottom: 8px;">&#x2705;</div>
        <div style="font-size: 14px; font-weight: 700; color: #065f46;">All Clear</div>
        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">No recommendations at this time. Tenant is well-configured.</div>
    </div>
    @endforelse
</div>
@endif

@endif {{-- end content --}}

<script>
    // Signal that charts are rendered (no charts in this report, but needed for PDF generation)
    window.chartsRendered = true;
</script>

</body>
</html>
