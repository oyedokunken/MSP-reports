<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ThreatLocker {{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly')) }} Summary - {{ $customer->canonical_name }}</title>
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
            color: #0f172a;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            letter-spacing: -0.01em;
        }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-grid.six-cols { grid-template-columns: repeat(6, 1fr); gap: 10px; }
        .stat-grid.six-cols .stat-box { padding: 12px 5px; }
        .stat-grid.six-cols .stat-value { font-size: 20px; }
        .stat-grid.six-cols .stat-label { font-size: 9px; margin-top: 2px; }
        .stat-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }
        .stat-box .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .stat-box .stat-label { font-size: 11px; color: #64748b; margin-top: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }

        .stat-box.green { border-left: 4px solid #22c55e; }
        .stat-box.green .stat-value { color: #16a34a; }
        .stat-box.red { border-left: 4px solid #ef4444; }
        .stat-box.red .stat-value { color: #dc2626; }
        .stat-box.blue { border-left: 4px solid #3b82f6; }
        .stat-box.blue .stat-value { color: #2563eb; }
        .stat-box.amber { border-left: 4px solid #f59e0b; }
        .stat-box.amber .stat-value { color: #d97706; }
        .stat-box.purple { border-left: 4px solid #8b5cf6; }
        .stat-box.purple .stat-value { color: #7c3aed; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
        th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 9px 14px; border-bottom: 1px solid #f1f5f9; font-size: 11px; color: #334155; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .badge-green { background-color: #dcfce7; color: #166534; }
        .badge-red { background-color: #fee2e2; color: #991b1b; }
        .badge-blue { background-color: #dbeafe; color: #1e40af; }
        .badge-amber { background-color: #fef3c7; color: #92400e; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }
        .badge-purple { background-color: #ede9fe; color: #5b21b6; }

        .chart-container { margin: 20px 0; max-width: 100%; }
        .chart-container canvas { max-height: 280px; }

        .alert-card {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 10px;
            font-size: 12px;
            line-height: 1.5;
            border: 1px solid;
        }
        .alert-info { background-color: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .alert-warning { background-color: #fffbeb; border-color: #fde68a; color: #92400e; }
        .alert-success { background-color: #f0fdf4; border-color: #bbf7d0; color: #166534; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

        .cover-page {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: white;
            padding: 60px;
        }
        .cover-page h1 { font-family: 'Outfit', sans-serif; font-size: 42px; font-weight: 800; margin-bottom: 12px; letter-spacing: -0.03em; }
        .cover-page .subtitle { font-size: 20px; color: #94a3b8; margin-bottom: 40px; font-weight: 400; }
        .cover-page .customer-name { font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 600; color: #60a5fa; margin-bottom: 8px; }
        .cover-page .period { font-size: 16px; color: #94a3b8; }
        .cover-page .logo { margin-bottom: 40px; }
        .cover-page .logo img { max-height: 60px; max-width: 200px; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'cover')
        @include('CoverPages.default')
    @endif

    @if(!isset($render_part) || $render_part === 'all' || $render_part === 'content')
    @php
        $shouldRender = function(string $section) use ($sections_to_render) {
            if (!isset($sections_to_render) || $sections_to_render === null) return true;
            return in_array($section, $sections_to_render) || in_array('all', $sections_to_render);
        };
    @endphp

{{-- EXECUTIVE SUMMARY --}}
@if($shouldRender('executive_summary'))
<div class="section page-break">
    <h2 class="section-title">Executive Summary</h2>
    <div class="stat-grid six-cols">
        <div class="stat-box blue">
            <div class="stat-value">{{ $executive_summary['total_endpoints'] ?? 0 }}</div>
            <div class="stat-label">Total Endpoints</div>
        </div>
        <div class="stat-box green">
            <div class="stat-value">{{ $executive_summary['online_endpoints'] ?? 0 }}</div>
            <div class="stat-label">Online</div>
        </div>
        <div class="stat-box red">
            <div class="stat-value">{{ $executive_summary['offline_endpoints'] ?? 0 }}</div>
            <div class="stat-label">Offline</div>
        </div>
        <div class="stat-box purple">
            <div class="stat-value">{{ $executive_summary['total_policies'] ?? 0 }}</div>
            <div class="stat-label">App Control Policies</div>
        </div>
        <div class="stat-box blue">
            <div class="stat-value">{{ $executive_summary['total_applications'] ?? 0 }}</div>
            <div class="stat-label">Managed Applications</div>
        </div>
        <div class="stat-box amber">
            <div class="stat-value">{{ $executive_summary['denied_events'] ?? 0 }}</div>
            <div class="stat-label">Denied Events</div>
        </div>
    </div>

    @if(!empty($computer_summary['os_distribution']))
    <div class="two-col">
        <div class="chart-container">
            <canvas id="osChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="endpointStatusChart"></canvas>
        </div>
    </div>
    @endif
</div>
@endif

{{-- ENDPOINT OVERVIEW --}}
@if($shouldRender('computers'))
<!-- <div class="section page-break"> -->
<div class="section">
    <h2 class="section-title">Endpoint Overview</h2>
    <div class="stat-grid">
        <div class="stat-box blue">
            <div class="stat-value">{{ $computer_summary['total_computers'] ?? 0 }}</div>
            <div class="stat-label">Total Endpoints</div>
        </div>
        <div class="stat-box green">
            <div class="stat-value">{{ $computer_summary['online_rate'] ?? 0 }}%</div>
            <div class="stat-label">Online Rate</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $computer_summary['servers'] ?? 0 }}</div>
            <div class="stat-label">Servers</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $computer_summary['workstations'] ?? 0 }}</div>
            <div class="stat-label">Workstations</div>
        </div>
    </div>

    @if(!empty($computers_list))
    <table>
        <thead>
            <tr>
                <th>Computer Name</th>
                <th>OS</th>
                <th>Status</th>
                <th>Type</th>
                <th>Mode</th>
                <th>Denies (7d)</th>
                <th>Last Check-In</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($computers_list, 0, 50) as $computer)
            <tr>
                <td><strong>{{ $computer['name'] }}</strong></td>
                <td>{{ $computer['os'] ?? '-' }}</td>
                <td>
                    @if($computer['is_online'] ?? false)
                        <span class="badge badge-green">Online</span>
                    @else
                        <span class="badge badge-red">Offline</span>
                    @endif
                </td>
                <td>
                    @if($computer['is_server'] ?? false)
                        <span class="badge badge-purple">Server</span>
                    @else
                        <span class="badge badge-gray">Workstation</span>
                    @endif
                </td>
                <td>{{ $computer['mode'] ?? '-' }}</td>
                <td>
                    @if(($computer['deny_count_7d'] ?? 0) > 0)
                        <span class="badge badge-amber">{{ $computer['deny_count_7d'] }}</span>
                    @else
                        <span class="badge badge-green">0</span>
                    @endif
                </td>
                <td>{{ $computer['last_check_in'] ? \Carbon\Carbon::parse($computer['last_check_in'])->format('M d, Y H:i') : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($computers_list) > 50)
        <p style="color: #64748b; font-size: 10px; text-align: center;">Showing 50 of {{ count($computers_list) }} endpoints</p>
    @endif
    @endif
</div>
@endif

{{-- APPLICATION CONTROL POLICIES --}}
@if($shouldRender('policies'))
<div class="section page-break">
    <h2 class="section-title">Application Control Policies</h2>
    
    <!-- <div class="two-col" style="margin-bottom: 24px;">
        <div class="chart-container">
            <canvas id="policyStatusChart"></canvas>
        </div>
        @if(!empty($policy_summary['policies_by_action']))
        <div class="chart-container">
            <canvas id="policyActionChart"></canvas>
        </div>
        @endif
    </div> -->

    @if(!empty($policies_list))
    <table>
        <thead>
            <tr>
                <th>Policy Name</th>
                <th>Status</th>
                <th>Action</th>
                <th>Application</th>
                <th>Computer Group</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($policies_list, 0, 50) as $policy)
            <tr>
                <td><strong>{{ $policy['name'] }}</strong></td>
                <td>
                    @if($policy['is_enabled'] ?? true)
                        <span class="badge badge-green">Enabled</span>
                    @else
                        <span class="badge badge-red">Disabled</span>
                    @endif
                </td>
                <td>{{ $policy['action'] ?? '-' }}</td>
                <td>{{ $policy['application_name'] ?? '-' }}</td>
                <td>{{ $policy['computer_group_name'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($policies_list) > 50)
        <p style="color: #64748b; font-size: 10px; text-align: center;">Showing 50 of {{ count($policies_list) }} policies</p>
    @endif
    @endif
</div>
@endif

{{-- MANAGED APPLICATIONS --}}
@if($shouldRender('applications'))
<div class="section page-break">
    <h2 class="section-title">Managed Applications</h2>
    <div class="stat-grid">
        <div class="stat-box blue">
            <div class="stat-value">{{ $application_summary['total_applications'] ?? 0 }}</div>
            <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $application_summary['built_in_applications'] ?? 0 }}</div>
            <div class="stat-label">Built-In</div>
        </div>
        <div class="stat-box purple">
            <div class="stat-value">{{ $application_summary['custom_applications'] ?? 0 }}</div>
            <div class="stat-label">Custom</div>
        </div>
    </div>

    @if(!empty($applications_list))
    <table>
        <thead>
            <tr>
                <th>Application Name</th>
                <th>Type</th>
                <th>Files</th>
                <th>Category</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($applications_list, 0, 30) as $app)
            <tr>
                <td><strong>{{ $app['name'] }}</strong></td>
                <td>
                    @if($app['is_built_in'] ?? false)
                        <span class="badge badge-blue">Built-In</span>
                    @else
                        <span class="badge badge-purple">Custom</span>
                    @endif
                </td>
                <td>{{ $app['file_count'] ?? '-' }}</td>
                <td>{{ $app['category'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($applications_list) > 30)
        <p style="color: #64748b; font-size: 10px; text-align: center;">Showing 30 of {{ count($applications_list) }} applications</p>
    @endif
    @endif
</div>
@endif

{{-- STORAGE CONTROL --}}
@if($shouldRender('storage'))
<div class="section page-break">
    <h2 class="section-title">Storage Control</h2>
    <div class="stat-grid">
        <div class="stat-box blue">
            <div class="stat-value">{{ $storage_summary['total_storage_policies'] ?? 0 }}</div>
            <div class="stat-label">Storage Policies</div>
        </div>
        <div class="stat-box green">
            <div class="stat-value">{{ $storage_summary['enabled_storage_policies'] ?? 0 }}</div>
            <div class="stat-label">Enabled Policies</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $storage_summary['total_storage_devices'] ?? 0 }}</div>
            <div class="stat-label">Storage Devices</div>
        </div>
        <div class="stat-box green">
            <div class="stat-value">{{ $storage_summary['approved_devices'] ?? 0 }}</div>
            <div class="stat-label">Approved Devices</div>
        </div>
    </div>
    @if(($storage_summary['unapproved_devices'] ?? 0) > 0)
        <div class="alert-card alert-warning">
            <strong>{{ $storage_summary['unapproved_devices'] }}</strong> unapproved storage device(s) detected. Review and approve or block as appropriate.
        </div>
    @endif
</div>
@endif

{{-- AUDIT ACTIVITY --}}
@if($shouldRender('audit'))
<div class="section">
    <h2 class="section-title">Audit Activity</h2>
    <div class="stat-grid">
        <div class="stat-box blue">
            <div class="stat-value">{{ $audit_summary['total_events'] ?? 0 }}</div>
            <div class="stat-label">Total Events</div>
        </div>
        <div class="stat-box red">
            <div class="stat-value">{{ $audit_summary['denied_events'] ?? 0 }}</div>
            <div class="stat-label">Denied Events</div>
        </div>
    </div>

    @if(!empty($audit_summary['events_by_action']))
    <div class="chart-container" style="max-width: 500px; margin: 0 auto 24px;">
        <canvas id="auditActionChart"></canvas>
    </div>
    @endif

    @if(!empty($audit_events))
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Computer</th>
                <th>Action</th>
                <th>Application</th>
                <th>User</th>
                <th>Policy</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($audit_events, 0, 30) as $event)
            <tr>
                <td>{{ $event['date'] ? \Carbon\Carbon::parse($event['date'])->format('M d H:i') : '-' }}</td>
                <td>{{ $event['computer_name'] ?? '-' }}</td>
                <td>
                    @if(stripos($event['action'] ?? '', 'denied') !== false || stripos($event['action'] ?? '', 'block') !== false)
                        <span class="badge badge-red">{{ $event['action'] }}</span>
                    @else
                        <span class="badge badge-gray">{{ $event['action'] ?? '-' }}</span>
                    @endif
                </td>
                <td>{{ $event['application'] ?? '-' }}</td>
                <td>{{ $event['user'] ?? '-' }}</td>
                <td>{{ $event['policy_name'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($audit_events) > 30)
        <p style="color: #64748b; font-size: 10px; text-align: center;">Showing 30 of {{ count($audit_events) }} events</p>
    @endif
    @endif
</div>
@endif

{{-- RECOMMENDATIONS --}}
@if($shouldRender('recommendations'))
<div class="section">
    <h2 class="section-title">Security Recommendations</h2>
    @forelse($alerts ?? [] as $rec)
        @if(str_contains($rec, 'healthy') || str_contains($rec, 'posture is healthy'))
            <div class="alert-card alert-success">{{ $rec }}</div>
        @elseif(str_contains($rec, 'offline') || str_contains($rec, 'disabled') || str_contains($rec, 'tamper'))
            <div class="alert-card alert-warning">{{ $rec }}</div>
        @else
            <div class="alert-card alert-info">{{ $rec }}</div>
        @endif
    @empty
        <div class="alert-card alert-success">No recommendations at this time. Security posture is healthy.</div>
    @endforelse
</div>
@endif

@endif {{-- end content --}}

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    const brandColor = '{{ $branding["primary_color"] ?? "#3b82f6" }}';

    // OS Distribution Chart
    @if(!empty($computer_summary['os_distribution']))
    const osCtx = document.getElementById('osChart');
    if (osCtx) {
        new Chart(osCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($computer_summary['os_distribution'])) !!},
                datasets: [{
                    data: {!! json_encode(array_values($computer_summary['os_distribution'])) !!},
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#06b6d4', '#f59e0b', '#ef4444', '#22c55e', '#64748b', '#ec4899'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'OS Distribution', font: { size: 14, weight: 'bold', family: 'Outfit' } },
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }
    @endif

    // Endpoint Status Chart
    const statusCtx = document.getElementById('endpointStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline'],
                datasets: [{
                    data: [{{ $computer_summary['online_computers'] ?? 0 }}, {{ $computer_summary['offline_computers'] ?? 0 }}],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Endpoint Status', font: { size: 14, weight: 'bold', family: 'Outfit' } },
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }

    // Policy Status Chart
    const policyStatusCtx = document.getElementById('policyStatusChart');
    if (policyStatusCtx) {
        new Chart(policyStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Enabled', 'Disabled'],
                datasets: [{
                    data: [{{ $policy_summary['enabled_policies'] ?? 0 }}, {{ $policy_summary['disabled_policies'] ?? 0 }}],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Policy Status', font: { size: 14, weight: 'bold', family: 'Outfit' } },
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }

    // Policy Action Chart
    @if(!empty($policy_summary['policies_by_action']))
    const policyCtx = document.getElementById('policyActionChart');
    if (policyCtx) {
        new Chart(policyCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_keys($policy_summary['policies_by_action'])) !!},
                datasets: [{
                    label: 'Policies',
                    data: {!! json_encode(array_values($policy_summary['policies_by_action'])) !!},
                    backgroundColor: brandColor + 'cc',
                    borderColor: brandColor,
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Policies by Action Type', font: { size: 14, weight: 'bold', family: 'Outfit' } },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
    @endif

    // Audit Action Chart
    @if(!empty($audit_summary['events_by_action']))
    const auditCtx = document.getElementById('auditActionChart');
    if (auditCtx) {
        const auditLabels = {!! json_encode(array_slice(array_keys($audit_summary['events_by_action']), 0, 10)) !!};
        const auditData = {!! json_encode(array_slice(array_values($audit_summary['events_by_action']), 0, 10)) !!};
        new Chart(auditCtx, {
            type: 'bar',
            data: {
                labels: auditLabels,
                datasets: [{
                    label: 'Events',
                    data: auditData,
                    backgroundColor: '#8b5cf6cc',
                    borderColor: '#8b5cf6',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Top Audit Actions', font: { size: 14, weight: 'bold', family: 'Outfit' } },
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
    @endif

    window.chartsRendered = true;
});
</script>

</body>
</html>
