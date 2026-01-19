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
            color: #94a3b8;
            padding: 10px 30px;
        }
        .footer-container {
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }
        .footer-right {
            text-align: right;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="footer-container">
        <div class="footer-right">
            Page <span class="pageNumber"></span> of <span class="totalPages"></span>
        </div>
    </div>
</body>
</html>
