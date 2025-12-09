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
            padding: 8px 15px 5px 15px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            font-size: 7.5pt;
            color: #666;
            width: 100%;
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
            margin-bottom: 8px;
        }
        .footer-left {
            flex: 1;
            min-width: 200px;
        }
        .footer-right {
            flex: 1;
            min-width: 200px;
            text-align: right;
            font-style: italic;
            color: #999;
        }
        .footer-text {
            margin-bottom: 3px;
            font-weight: 500;
        }
        .contact-info {
            line-height: 1.5;
        }
        .contact-info strong {
            font-weight: 600;
        }
        .page-number {
            text-align: center;
            font-size: 7pt;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-left">
                @if(isset($branding['footer_text']) && $branding['footer_text'])
                    <div class="footer-text">{{ $branding['footer_text'] }}</div>
                @endif
                
                @if(isset($branding['support_email']) || isset($branding['support_phone']))
                    <div class="contact-info">
                        @if(isset($branding['support_phone']) && $branding['support_phone'])
                            <div><strong>Phone:</strong> {{ $branding['support_phone'] }}</div>
                        @endif
                        @if(isset($branding['support_email']) && $branding['support_email'])
                            <div><strong>Email:</strong> {{ $branding['support_email'] }}</div>
                        @endif
                    </div>
                @endif
            </div>
            
            @if(isset($branding['footer_notes']) && $branding['footer_notes'])
                <div class="footer-right">
                    {{ $branding['footer_notes'] }}
                </div>
            @endif
        </div>
        
        <div class="page-number">
            Page <span class="pageNumber"></span> of <span class="totalPages"></span>
        </div>
    </div>
</body>
</html>
