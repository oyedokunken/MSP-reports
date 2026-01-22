<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $companyName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #1a56db;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #1a56db;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1348b8;
        }
        .credentials-box {
            background-color: #f8f9fa;
            border-left: 4px solid #1a56db;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .credentials-box .label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        .credentials-box .value {
            font-family: monospace;
            font-size: 14px;
            background-color: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            display: block;
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }}</h1>
        </div>

        <div class="content">
            <h2>Welcome, {{ $user->name }}!</h2>
            
            <p>Your account has been created on the {{ $companyName }} reporting platform. You can now access the platform using the credentials below.</p>
            
            <div class="credentials-box">
                <span class="label">Email Address:</span>
                <span class="value">{{ $user->email }}</span>
                
                <span class="label">Temporary Password:</span>
                <span class="value">{{ $plainPassword }}</span>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $loginUrl }}" class="button">Login to Platform</a>
            </div>
            
            <div class="warning">
                <strong>⚠️ Security Recommendation</strong><br>
                For your security, we recommend changing your password after your first login. Keep your credentials confidential and do not share them with anyone.
            </div>
            
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #1a56db;">{{ $loginUrl }}</p>
        </div>

        <div class="footer">
            <p>This is an automated message from {{ $companyName }}.</p>
            <p>If you need assistance, please contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
