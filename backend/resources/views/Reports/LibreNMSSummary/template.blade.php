<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LibreNMS {{ ucfirst($options['frequency'] ?? 'Executive') }} Summary - {{ $customer->canonical_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            max-width: 1200px;
            margin: 0 auto;
            padding: {{ (isset($render_part) && $render_part === 'cover') ? '0' : '20px 10px' }};
            font-size: 12px;
        }
        .header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; text-align: center; }
        .header .company-name { font-size: 16px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; display: none; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .header .meta { font-size: 11px; color: #666; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-card { padding: 20px; border: 1px solid #e5e5e5; border-radius: 8px; text-align: center; background: #fff; }
        .summary-card .number { font-size: 28px; font-weight: 700; display: block; margin-bottom: 4px; }
        .summary-card .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }

        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #3b82f6; display: flex; align-items: center; }
        
        .rec-card { border-left: 4px solid #ddd; padding: 12px 15px; margin-bottom: 12px; background: #f9fafb; border-radius: 0 6px 6px 0; }
        .rec-card.high { border-left-color: #ef4444; background: #fef2f2; }
        .rec-card.medium { border-left-color: #f59e0b; background: #fffbeb; }
        .rec-card.success { border-left-color: #10b981; background: #ecfdf5; }
        .rec-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
        .rec-desc { color: #4b5563; font-size: 11px; margin-bottom: 6px; }
        .rec-action { font-weight: 600; font-size: 10px; color: #1f2937; text-transform: uppercase; }

        .infrastructure-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
        .info-table tr:last-child td { border-bottom: none; }
        .info-label { color: #6b7280; }
        .info-value { text-align: right; font-weight: 600; }

        .top-issues-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .top-issues-table th { text-align: left; padding: 10px; background: #f9fafb; font-weight: 600; border-bottom: 1px solid #e5e5e5; }
        .top-issues-table td { padding: 10px; border-bottom: 1px solid #f3f4f6; }
        .percentage { font-family: monospace; font-weight: 600; }
        .low-uptime { color: #dc2626; }
    </style>
    <style>
        /* Report Content Background Shapes */
        .report-bg-shape-1 {
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: #1e293b;
            opacity: 0.02;
            clip-path: polygon(100% 0, 0% 0, 100% 100%);
            z-index: -1;
            pointer-events: none;
        }

        .report-bg-shape-2 {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 30%;
            height: 40%;
            background: #1e293b;
            opacity: 0.015;
            clip-path: polygon(0 100%, 100% 100%, 0 0);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'cover')
        @include('CoverPages.default')
    @endif
    
    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'content')
    <div class="report-bg-shape-1"></div>
    <div class="report-bg-shape-2"></div>
    
    <div class="header">
        <h1>LibreNMS {{ ucfirst($options['frequency'] ?? 'Executive') }} Summary</h1>
        <div class="meta">{{ $customer->canonical_name }} • Period: {{ $period['formatted'] }}</div>
    </div>

    {{-- Executive Summary Section --}}
    @if(in_array('summary', $sections_to_render ?? ['summary']))
        <h2 class="section-title">Executive Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <span class="number">{{ $summary['total_devices'] }}</span>
                <span class="label">Managed Devices</span>
            </div>
            <div class="summary-card">
                <span class="number" style="color: #10b981">{{ $summary['online_devices'] }}</span>
                <span class="label">Active Online</span>
            </div>
            <div class="summary-card">
                <span class="number" style="color: {{ $summary['avg_uptime_30d'] >= 99.9 ? '#10b981' : '#f59e0b' }}">
                    {{ number_format($summary['avg_uptime_30d'], 2) }}%
                </span>
                <span class="label">Avg Uptime (30d)</span>
            </div>
            <div class="summary-card">
                <span class="number">{{ $summary['online_percent'] }}%</span>
                <span class="label">Current Reachability</span>
            </div>
        </div>

        <div style="margin-bottom: 30px;">
            <h3 style="font-size: 13px; margin-bottom: 10px; color: #4b5563;">Recommendations & Observations</h3>
            @foreach($recommendations as $rec)
                <div class="rec-card {{ $rec['severity'] }}">
                    <div class="rec-title">{{ $rec['title'] }}</div>
                    <div class="rec-desc">{{ $rec['description'] }}</div>
                    <div class="rec-action">Action: {{ $rec['action'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Infrastructure Breakdown Section --}}
    @if(in_array('infrastructure', $sections_to_render ?? ['infrastructure']))
        <h2 class="section-title">Infrastructure Breakdown</h2>
        <div class="infrastructure-grid">
            <div style="padding: 15px; border: 1px solid #e5e5e5; border-radius: 8px;">
                <h3 style="font-size: 13px; margin-bottom: 12px; font-weight: 600;">Device Types</h3>
                <table class="info-table">
                    @foreach($infrastructure['device_types'] as $type => $count)
                    <tr>
                        <td class="info-label">{{ $type }}</td>
                        <td class="info-value">{{ $count }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <div style="padding: 15px; border: 1px solid #e5e5e5; border-radius: 8px;">
                <h3 style="font-size: 13px; margin-bottom: 12px; font-weight: 600;">Operating Systems</h3>
                <table class="info-table">
                    @foreach($infrastructure['os_types'] as $os => $count)
                    <tr>
                        <td class="info-label">{{ $os }}</td>
                        <td class="info-value">{{ $count }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    {{-- Top Issues Section --}}
    @if(in_array('top_issues', $sections_to_render ?? ['top_issues']))
        <h2 class="section-title">Availability Watchlist</h2>
        <p style="color: #6b7280; font-size: 10px; margin-bottom: 10px;">Devices with the lowest average uptime in the last 30 days.</p>
        <table class="top-issues-table">
            <thead>
                <tr>
                    <th>Device Name</th>
                    <th>Type</th>
                    <th>Operating System</th>
                    <th>Status</th>
                    <th style="text-align: right;">30d Uptime</th>
                </tr>
            </thead>
            <tbody>
                @forelse($top_issues as $device)
                <tr>
                    <td><strong>{{ $device['name'] }}</strong></td>
                    <td>{{ $device['type'] }}</td>
                    <td>{{ $device['os'] }}</td>
                    <td>
                        <span style="color: {{ $device['status'] === 'Online' ? '#10b981' : '#ef4444' }}; font-weight: 600;">
                            {{ $device['status'] }}
                        </span>
                    </td>
                    <td style="text-align: right;" class="percentage {{ $device['uptime_30d'] < 98 ? 'low-uptime' : '' }}">
                        {{ number_format($device['uptime_30d'], 2) }}%
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #9ca3af; padding: 20px;">No availability issues detected.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div style="margin-top: 50px; border-top: 1px solid #e5e5e5; padding-top: 15px; color: #9ca3af; font-size: 9px; text-align: center;">
        This executive summary is generated based on polled data from LibreNMS. All metrics are calculated based on the selected report period.
    </div>
    @endif

    <script>
        // Signal to Gotenberg that the page is ready
        window.chartsRendered = true;
    </script>
</body>
</html>
