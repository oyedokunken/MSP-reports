<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly Comprehensive Report - {{ $customer->canonical_name }}</title>
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
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        .page-break { page-break-after: always; }
        
        /* Override styles for included reports */
        .content .header { display: none !important; }
        .content body { padding: 0 !important; margin: 0 !important; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Monthly Comprehensive Report</h1>
        <div class="company-name">{{ $branding['company_name'] ?? 'Territory Technology Solutions' }}</div>
        <div class="meta">{{ $customer->canonical_name }} • {{ $period['formatted'] }}</div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="number">{{ count($included_reports ?? []) }}</div>
            <div class="label">Reports Included</div>
            <div class="sublabel">Comprehensive overview</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $period['start'] ?? 'N/A' }}</div>
            <div class="label">Period Start</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ $period['end'] ?? 'N/A' }}</div>
            <div class="label">Period End</div>
        </div>
        <div class="summary-card">
            <div class="number">{{ \Carbon\Carbon::parse($generated_at)->format('M d') }}</div>
            <div class="label">Generated</div>
            <div class="sublabel">{{ \Carbon\Carbon::parse($generated_at)->format('Y') }}</div>
        </div>
    </div>

    @if(!empty($included_reports) && is_array($included_reports))
    <div class="section">
        <h2 class="section-title">Included Reports</h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 30px;">
            @foreach($included_reports as $report)
            <div style="padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
                <span class="badge info">✓</span>
                <span style="font-size: 11px; font-weight: 500;">{{ $report['title'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="page-break"></div>

    <!-- Include each individual report -->
    @foreach($included_reports ?? [] as $index => $report)
        @if($index > 0)
            <div class="page-break"></div>
        @endif
        
        @include($report['template'], array_merge(
            $report['data'],
            [
                'customer' => $customer,
                'branding' => $branding,
            ]
        ))
    @endforeach
</body>
</html>
