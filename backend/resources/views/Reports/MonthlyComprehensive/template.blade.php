<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly')) }} Comprehensive Report - {{ $customer->canonical_name }}</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            color: #1a1a1a;
            font-size: 12px;
        }

        /* ===== COVER PAGE ===== */
        .cover-page {min-height: 70vh;
            padding: 120px 60px; /* top & bottom margin */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .cover-content {
            margin-top: auto;
            margin-bottom: auto;
        }

        .cover-logo {
            margin-bottom: 50px;
        }

        .cover-logo img {
            max-height: 60px;
            max-width: 250px;
        }

        .cover-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .cover-subtitle {
            font-size: 16px;
            color: #666;
            font-weight: 400;
            margin-bottom: 50px;
            letter-spacing: 0.3px;
        }

        .cover-customer-box {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px 32px;
            margin: 0 auto 50px;
            max-width: 500px;
        }

        .cover-customer-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .cover-customer-name {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .cover-period {
            font-size: 13px;
            color: #666;
        }

        .cover-divider {
            width: 60px;
            height: 3px;
            background: #1a1a1a;
            margin: 40px auto;
        }

        .cover-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 700px;
            margin: 0 auto;
            padding: 30px;
            background: #fafafa;
            border-radius: 8px;
        }

        .detail-item {
            text-align: center;
            padding: 15px;
        }

        .detail-label {
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-size: 9px;
            color: #999;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .detail-value {
            font-weight: 700;
            font-size: 18px;
            color: #1a1a1a;
            line-height: 1.3;
        }

        .detail-value.small {
            font-size: 13px;
            font-weight: 600;
        }

        .cover-footer {
            font-size: 10px;
            color: #999;
            margin-top: 60px;
            letter-spacing: 0.5px;
        }

        .report-badge {
            display: inline-block;
            background: #1a1a1a;
            color: white;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 30px;
        }

        /* ===== TABLE OF CONTENTS ===== */
        .table-of-contents {
            padding: 40px 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .toc-header {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #1a1a1a;
        }

        .toc-title {
            font-size: 32px;
            font-weight: 700;
            /* margin-bottom: 8px; */
            letter-spacing: -0.5px;
        }

        .toc-subtitle {
            font-size: 13px;
            color: #666;
            letter-spacing: 0.3px;
        }

        .toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .toc-item {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 16px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .toc-item:last-child {
            border-bottom: none;
        }

        .toc-number {
            font-size: 14px;
            font-weight: 700;
            color: #999;
            margin-right: 16px;
            min-width: 30px;
        }

        .toc-content {
            flex: 1;
            display: flex;
            align-items: baseline;
        }

        .toc-report-title {
            font-size: 12px;
            font-weight: 600;
            color: #1a1a1a;
            flex: 1;
        }

        .toc-dots {
            flex: 1;
            border-bottom: 1px dotted #ccc;
            margin: 0 12px;
            min-width: 40px;
        }

        .toc-page {
            font-size: 13px;
            font-weight: 600;
            color: #666;
            min-width: 40px;
            text-align: right;
        }

        .toc-integration-badge {
            display: inline-block;
            background: #f3f4f6;
            color: #666;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>

    <!-- COVER PAGE -->
    <div class="cover-page">
        <div class="cover-content">
            @if(!empty($branding['company_logo']))
            <div class="cover-logo">
                <img src="{{ $branding['company_logo'] }}" alt="{{ $branding['company_name'] ?? 'Company' }}">
            </div>
            @endif

            <div class="report-badge">Comprehensive Report</div>

            <h1 class="cover-title">{{ ucfirst(is_object($options['frequency'] ?? null) ? $options['frequency']->value : ($options['frequency'] ?? 'Monthly')) }} Performance<br>& Operations Review</h1>
            
            <p class="cover-subtitle">Executive Summary of All Systems & Services</p>

            <div class="cover-divider"></div>

            <div class="cover-customer-box">
                <div class="cover-customer-label">Prepared For</div>
                <div class="cover-customer-name">{{ $customer->canonical_name }}</div>
                <div class="cover-period">{{ $period['formatted'] }}</div>
            </div>

            <div class="cover-details">
                <div class="detail-item">
                    <div class="detail-label">Reports Included</div>
                    <div class="detail-value">{{ count($included_reports ?? []) }}</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Reporting Period</div>
                    <div class="detail-value small">
                        {{ \Carbon\Carbon::parse($period['start'])->format('M d') }} – {{ \Carbon\Carbon::parse($period['end'])->format('M d, Y') }}
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Generated</div>
                    <div class="detail-value small">
                        {{ \Carbon\Carbon::parse($generated_at)->format('M d, Y') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="cover-footer">
            {{ $branding['company_name'] ?? 'Territory Technology Solutions' }} • Confidential
        </div> -->
    </div>

    <div class="page-break"></div>
    
    <!-- TABLE OF CONTENTS PAGE -->
    <div class="table-of-contents">
        <div class="toc-header">
            <h1 class="toc-title">Table of Contents</h1>
            <!-- <p class="toc-subtitle">{{ $customer->canonical_name }} • {{ $period['formatted'] }}</p> -->
        </div>

        <ul class="toc-list">
            @foreach($included_reports ?? [] as $index => $report)
            <li class="toc-item">
                <span class="toc-number">{{ sprintf('%02d', $index + 1) }}</span>
                <div class="toc-content">
                    <span class="toc-report-title">
                        {{ $report['title'] }}
                        @if(isset($report['data']['customer_data']['name']))
                            <span class="toc-integration-badge">
                                {{ strtoupper(str_replace('_', ' ', $report['type'])) }}
                            </span>
                        @endif
                    </span>
                    <span class="toc-dots"></span>
                    <span class="toc-page">{{ $index + 3 }}</span>
                </div>
            </li>
            @endforeach
        </ul>
    </div>

    <div class="page-break"></div>

    <!-- FOLLOWING REPORT PAGES -->
    @foreach($included_reports ?? [] as $index => $report)
        <div class="page-break"></div>

        <!-- SECTION COVER PAGE -->
        <div class="cover-page section-cover">
            <div class="cover-content">
                <div class="report-badge">{{ strtoupper($report['type']) }}</div>
                
                <h1 class="cover-title">{{ $report['title'] }}</h1>
                
                <p class="cover-subtitle">Detailed Performance & Status Report</p>
                
                <div class="cover-divider"></div>
                
                <div class="cover-customer-box">
                    <div class="cover-customer-label">Section {{ $index + 1 }}</div>
                    <div class="cover-customer-name">{{ $report['title'] }}</div>
                </div>
            </div>
        </div>

        <div class="page-break"></div>

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
