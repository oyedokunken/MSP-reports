<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Comprehensive Report - {{ $customer->canonical_name ?? $customer->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        @page {
            margin: 0;
            padding: 0;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: #1e293b;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        .page {
            width: 100%;
            min-height: 297mm;
            padding: 0;
            margin: 0;
            background: white;
            position: relative;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        
        /* Cover Page */
        .cover-page {
            width: 100%;
            height: 297mm;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            overflow: hidden;
            page-break-after: always;
            box-sizing: border-box;
        }
        
        .cover-page::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .cover-page::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 800px;
            height: 800px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }
        
        .cover-content {
            text-align: center;
            z-index: 1;
            padding: 50px;
        }
        
        .cover-logo {
            max-width: 300px;
            max-height: 120px;
            margin-bottom: 60px;
            filter: brightness(0) invert(1);
        }
        
        .cover-title {
            font-size: 56px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        
        .cover-subtitle {
            font-size: 28px;
            font-weight: 300;
            margin-bottom: 60px;
            opacity: 0.9;
        }
        
        .cover-customer {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 40px;
            padding: 20px 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .cover-meta {
            font-size: 18px;
            opacity: 0.85;
            margin-bottom: 10px;
        }
        
        .cover-footer-text {
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 14px;
            opacity: 0.7;
        }
        
        /* Header */
        .header-section {
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            padding: 40px 50px;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .company-logo, .customer-logo {
            max-height: 50px;
            max-width: 180px;
            object-fit: contain;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .report-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .report-subtitle {
            font-size: 16px;
            opacity: 0.95;
            font-weight: 400;
        }
        
        /* Metadata Bar */
        .metadata-bar {
            background: #f8fafc;
            border-left: 4px solid {{ $branding['primary_color'] ?? '#1e40af' }};
            padding: 25px 50px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .meta-value {
            font-size: 14px;
            color: #0f172a;
            font-weight: 500;
        }
        
        /* Content */
        .content {
            padding: 0;
        }
        
        /* Override individual report padding when included */
        .content .page,
        .content > * .page,
        .page .page {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Override any fixed widths in included reports */
        .content * {
            max-width: 100% !important;
        }
        
        /* Hide cover pages from included reports */
        .content .cover-page {
            display: none !important;
        }
        
        /* Hide only footers from included reports - keep headers visible */
        .content .footer,
        .content .footer-section {
            display: none !important;
        }
        
        /* Remove page breaks inside included reports */
        .content .page-break,
        .content [style*="page-break"] {
            display: none !important;
        }
        
        /* Remove padding from nested page containers */
        .content .page > .page {
            padding: 0 !important;
        }
        
        /* Health Score Hero */
        .health-score-hero {
            background: linear-gradient(135deg, {{ $branding['primary_color'] ?? '#1e40af' }} 0%, {{ $branding['secondary_color'] ?? '#7c3aed' }} 100%);
            color: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .health-score-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .health-score-value {
            font-size: 72px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .health-score-label {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .summary-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }
        
        .summary-card.uptime {
            border-left: 4px solid #10b981;
        }
        
        .summary-card.tickets {
            border-left: 4px solid #3b82f6;
        }
        
        .summary-card.backups {
            border-left: 4px solid #8b5cf6;
        }
        
        .summary-card.security {
            border-left: 4px solid #f59e0b;
        }
        
        .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        
        /* Section Headers */
        .section-header {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .section-icon.uptime {
            background: #d1fae5;
            color: #065f46;
        }
        
        .section-icon.tickets {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .section-icon.backups {
            background: #ede9fe;
            color: #6b21a8;
        }
        
        .section-icon.security {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Section Content */
        .section-content {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }
        
        .metric-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .metric-row:last-child {
            margin-bottom: 0;
        }
        
        .metric-item {
            display: flex;
            flex-direction: column;
        }
        
        .metric-item-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .metric-item-value {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.excellent {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.good {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.critical {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Page Break */
        .page-break {
            page-break-after: always;
        }
        
        /* Print Optimization */
        @media print {
            .cover-page {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <!-- Cover Page -->
    <div class="cover-page">
        <div class="cover-content">
            @if(!empty($branding['company_logo']))
                <img src="{{ $branding['company_logo'] }}" alt="Company Logo" class="cover-logo">
            @else
                <div style="font-size: 48px; font-weight: 700; margin-bottom: 60px;">{{ $branding['company_name'] ?? 'Company' }}</div>
            @endif
            
            <h1 class="cover-title">Monthly Comprehensive Report</h1>
            <p class="cover-subtitle">Complete System Performance Overview</p>
            
            <div class="cover-customer">{{ $customer->canonical_name ?? $customer->name }}</div>
            
            <div class="cover-meta">Report Period: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</div>
            <div class="cover-meta">Generated: {{ \Carbon\Carbon::parse($reportData['generated_at'])->format('F j, Y') }}</div>
        </div>
        
        <div class="cover-footer-text">
            {{ $branding['footer_text'] ?? 'Confidential Report' }}
        </div>
    </div>
    
    <!-- Reports Section -->
    <div class="page">
        <!-- Content -->
        <div class="content" style="position: relative;">
            <style>
                /* Force hide only footers and cover pages from included reports */
                .content .footer,
                .content .footer-section,
                .content .cover-page {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
                }
                
                /* Remove page breaks from inside individual report pages */
                .content .page * {
                    page-break-before: auto !important;
                    page-break-after: auto !important;
                }
                
                /* Remove ALL padding and margins from nested pages */
                .content .page {
                    padding: 0 !important;
                    margin: 0 !important;
                    min-height: auto !important;
                }
                
                /* Remove body and html tags styling if they exist */
                .content body,
                .content html {
                    margin: 0 !important;
                    padding: 0 !important;
                }
            </style>
            <!-- Include each individual report -->
            @foreach($included_reports ?? [] as $index => $report)
                @if($index > 0)
                    <div style="page-break-before: always !important; height: 0; margin: 0; padding: 0;"></div>
                @endif
                
                @include($report['template'], [
                    'customer' => $customer,
                    'branding' => $report['data']['branding'] ?? $branding,
                    'period' => $report['data']['period'] ?? $period,
                    'generated_at' => $report['data']['generated_at'] ?? $generated_at,
                    'data' => $report['data']['data'] ?? [],
                    'metrics' => $report['data']['metrics'] ?? [],
                    'reportData' => $report['data']
                ])
            @endforeach
        </div>
    </div>
</body>
</html>
