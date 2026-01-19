<html>
<head>
    <base href="http://reporting-nginx/">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            -webkit-print-color-adjust: exact;
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
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            width: 100%;
        }
        .header-left {
            display: flex;
            align-items: center;
        }
        .company-logo {
            max-height: 35px;
            height: auto;
            max-width: 120px;
            width: auto;
            display: block;
        }
        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-left: 12px;
            letter-spacing: -0.01em;
        }
        .header-right {
            text-align: right;
            opacity: 0.3;
        }
        .msp-logo {
            max-height: 35px;
            width: auto;
            max-width: 120px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="header-left">
            @if(isset($branding['company_logo']) && $branding['company_logo'])
                <img src="{{ $branding['company_logo'] }}" alt="Logo" class="company-logo">
            @endif
            <div class="company-name">{{ $branding['company_name'] ?? 'Reporting' }}</div>
        </div>
        <div class="header-right">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.png'))) }}" alt="MSP Reports" class="msp-logo">
        </div>
    </div>
</body>
</html>
