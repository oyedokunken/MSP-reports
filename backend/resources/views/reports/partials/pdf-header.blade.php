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
            max-height: 40px;
            height: auto;
            max-width: 150px;
            width: auto;
            display: block;
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
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.png'))) }}" alt="MSP Logo" class="company-logo">
        </div>
        <div class="header-right">
            <div class="prepared-for">Prepared For</div>
            <div class="customer-name">{{ $customer->canonical_name }}</div>
        </div>
    </div>
</body>
</html>
