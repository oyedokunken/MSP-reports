<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .alert-container {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .alert-critical {
            border-color: #dc2626;
            background-color: #fef2f2;
        }
        .alert-warning {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }
        .alert-info {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .alert-header {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .alert-critical .alert-header {
            color: #dc2626;
        }
        .alert-warning .alert-header {
            color: #f59e0b;
        }
        .alert-info .alert-header {
            color: #3b82f6;
        }
        .alert-message {
            font-size: 16px;
            margin: 15px 0;
        }
        .alert-details {
            background-color: #f9fafb;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .detail-row {
            margin: 8px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #6b7280;
        }
        .detail-value {
            color: #111827;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="alert-container alert-{{ $alertLog->severity }}">
        <div class="alert-header">
            @if($alertLog->severity === 'critical')
                🚨 CRITICAL ALERT
            @elseif($alertLog->severity === 'warning')
                ⚠️ WARNING ALERT
            @else
                ℹ️ INFO ALERT
            @endif
        </div>

        <div class="alert-message">
            <strong>{{ $alertLog->title }}</strong>
        </div>

        <div class="alert-message">
            {{ $alertLog->message }}
        </div>

        <div class="alert-details">
            <div class="detail-row">
                <span class="detail-label">Alert Type:</span>
                <span class="detail-value">{{ ucwords(str_replace('_', ' ', $alertLog->alert_type)) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Severity:</span>
                <span class="detail-value">{{ strtoupper($alertLog->severity) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Triggered At:</span>
                <span class="detail-value">{{ $alertLog->triggered_at->format('Y-m-d H:i:s') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Company:</span>
                <span class="detail-value">{{ $companyName }}</span>
            </div>
            @if($alertLog->context)
                <div class="detail-row">
                    <span class="detail-label">Current Value:</span>
                    <span class="detail-value">{{ $alertLog->context['metric_value'] ?? 'N/A' }}</span>
                </div>
            @endif
        </div>

        @if($alertRule->description)
            <div class="alert-message">
                <strong>Description:</strong><br>
                {{ $alertRule->description }}
            </div>
        @endif

        <div class="alert-message">
            <strong>Next Steps:</strong>
            <ul>
                @if($alertLog->severity === 'critical')
                    <li>Investigate immediately</li>
                    <li>Check system logs for errors</li>
                    <li>Verify system connectivity</li>
                @elseif($alertLog->severity === 'warning')
                    <li>Review within 4 hours</li>
                    <li>Monitor for recurring issues</li>
                    <li>Check related metrics</li>
                @else
                    <li>Review during daily maintenance</li>
                    <li>Document for future reference</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated alert from {{ $companyName }} MSP Reporting Platform.</p>
        <p>Alert Rule: {{ $alertRule->name }}</p>
        <p>Cooldown Period: {{ $alertRule->cooldown_minutes }} minutes</p>
    </div>
</body>
</html>
