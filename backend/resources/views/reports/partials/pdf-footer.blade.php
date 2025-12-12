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
            font-size: 9px;
            color: #666;
            padding: 8px 30px 10px 30px;
        }
        .footer-container {
            border-top: 1px solid #e5e5e5;
            padding-top: 8px;
            width: 100%;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .footer-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .footer-left div {
            font-size: 9px;
            color: #666;
            line-height: 1.4;
        }
        .footer-right {
            text-align: right;
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-left">
                <div>{{ $branding['footer_text'] ?? '© 2025 Territory Technology Solutions' }}</div>
                <div>{{ $branding['support_phone'] ?? '1300 XXX XXX' }}</div>
                <div>{{ $branding['support_email'] ?? 'support@tts.com.au' }}</div>
            </div>
            
            <div class="footer-right">
                Page <span class="pageNumber"></span> of <span class="totalPages"></span>
            </div>
        </div>
    </div>
</body>
</html>
