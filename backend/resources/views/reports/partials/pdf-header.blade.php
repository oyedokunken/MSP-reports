<html>
<head>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 8px 15px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            font-size: 9pt;
            width: 100%;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 8px;
            border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }};
            width: 100%;
        }
        .header-left {
            flex: 1;
        }
        .header-right {
            flex: 1;
            text-align: right;
        }
        .company-logo {
            max-height: 40px;
            max-width: 180px;
            display: block;
            margin-bottom: 5px;
        }
        .company-name {
            font-size: 14pt;
            font-weight: 600;
            color: {{ $branding['primary_color'] ?? '#1a56db' }};
            margin: 0 0 3px 0;
        }
        .subtitle {
            font-size: 9pt;
            color: #666;
            font-weight: 400;
        }
        .customer-logo {
            max-height: 40px;
            max-width: 180px;
            display: block;
            margin-left: auto;
            margin-bottom: 5px;
        }
        .label {
            font-weight: 500;
            color: #999;
            text-transform: uppercase;
            font-size: 6.5pt;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .value {
            color: #1a1a1a;
            font-size: 9pt;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="header-left">
            @if(isset($branding['company_logo']) && $branding['company_logo'])
                <img src="{{ $branding['company_logo'] }}" alt="{{ $branding['company_name'] ?? 'Company' }}" class="company-logo">
            @else
                <div class="company-name">{{ $branding['company_name'] ?? 'Company Name' }}</div>
            @endif
            <div class="subtitle">{{ $branding['report_title'] ?? 'Service Report' }}</div>
        </div>
        <div class="header-right">
            @if(isset($branding['customer_logo']) && $branding['customer_logo'])
                <img src="{{ $branding['customer_logo'] }}" alt="{{ $customer->canonical_name }}" class="customer-logo">
            @endif
            <div class="label">Prepared For</div>
            <div class="value">{{ $customer->canonical_name }}</div>
        </div>
    </div>
</body>
</html>
