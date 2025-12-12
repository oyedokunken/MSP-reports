<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            padding: 5px 30px;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e5;
            width: 100%;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .company-logo {
            max-height: 35px;
            max-width: 120px;
        }
        .company-info {
            display: flex;
            flex-direction: column;
        }
        .company-name {
            font-size: 13px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        .report-title {
            font-size: 9px;
            color: #666;
        }
        .header-right {
            text-align: right;
        }
        .customer-name {
            font-size: 11px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        .prepared-for {
            font-size: 8px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="header-left">
            @if(isset($branding['company_logo']) && $branding['company_logo'])
                <img src="{{ $branding['company_logo'] }}" alt="{{ $branding['company_name'] ?? 'Company' }}" class="company-logo">
            @endif
            <div class="company-info">
                @if(!isset($branding['company_logo']) || !$branding['company_logo'])
                    <div class="company-name">{{ $branding['company_name'] ?? 'Company Name' }}</div>
                @endif
                <div class="report-title">{{ $branding['report_title'] ?? 'Service Report' }}</div>
            </div>
        </div>
        <div class="header-right">
            <div class="prepared-for">Prepared For</div>
            <div class="customer-name">{{ $customer->canonical_name }}</div>
        </div>
    </div>
</body>
</html>
