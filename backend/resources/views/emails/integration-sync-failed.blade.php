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
            border: 2px solid #dc2626;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            background-color: #fef2f2;
        }
        .alert-header {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #dc2626;
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
        .error-box {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .action-list {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 15px 0;
        }
        .action-list ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .action-list li {
            margin: 5px 0;
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
    <div class="alert-container">
        <div class="alert-header">
            🚨 CRITICAL: Integration Sync Failed
        </div>

        <div class="alert-message">
            <strong>{{ $integration->display_name }}</strong> has failed to sync after {{ $attempts }} attempts and has been marked as <strong>ERROR</strong>.
        </div>

        <div class="alert-message">
            The integration will not automatically sync until the issue is resolved and the integration is manually re-enabled.
        </div>

        <div class="alert-details">
            <div class="detail-row">
                <span class="detail-label">Integration Type:</span>
                <span class="detail-value">{{ $integration->integration_type }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Integration Name:</span>
                <span class="detail-value">{{ $integration->display_name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Company:</span>
                <span class="detail-value">{{ $companyName }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Last Successful Sync:</span>
                <span class="detail-value">
                    @if($integration->last_sync_at)
                        {{ $integration->last_sync_at->format('Y-m-d H:i:s') }}
                    @else
                        Never
                    @endif
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Failed At:</span>
                <span class="detail-value">{{ now()->format('Y-m-d H:i:s') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Retry Attempts:</span>
                <span class="detail-value">{{ $attempts }} (all failed)</span>
            </div>
        </div>

        <div class="alert-message">
            <strong>Error Details:</strong>
        </div>

        <div class="error-box">
            {{ $exception->getMessage() }}
        </div>

        <div class="action-list">
            <strong>⚠️ Required Actions:</strong>
            <ul>
                <li><strong>Investigate immediately</strong> - This is a critical issue affecting data synchronization</li>
                <li><strong>Check integration credentials</strong> - Verify API keys, usernames, and passwords are still valid</li>
                <li><strong>Verify API connectivity</strong> - Ensure the external service is accessible and responding</li>
                <li><strong>Review error message</strong> - The error above may indicate the specific issue (authentication, rate limits, network, etc.)</li>
                <li><strong>Check system logs</strong> - Review application logs for additional context</li>
                <li><strong>Test integration health</strong> - Use the integration health check feature in the platform</li>
                <li><strong>Re-enable integration</strong> - Once resolved, update the integration status from ERROR to ACTIVE</li>
            </ul>
        </div>

        <div class="alert-message">
            <strong>Common Causes:</strong>
            <ul>
                <li>Expired or revoked API credentials</li>
                <li>API rate limits exceeded</li>
                <li>Network connectivity issues</li>
                <li>External service downtime or maintenance</li>
                <li>Changes to external API endpoints or authentication</li>
                <li>Insufficient permissions for API account</li>
            </ul>
        </div>

        <div class="alert-message">
            <strong>Impact:</strong>
            <ul>
                <li>Customer data will not be updated until sync resumes</li>
                <li>Reports may contain outdated information</li>
                <li>Historical data import may be paused</li>
                <li>Customer mappings will not be updated</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated critical alert from {{ $companyName }} MSP Reporting Platform.</p>
        <p>Integration ID: {{ $integration->integration_id }}</p>
        <p>If you need assistance, please contact your system administrator or Athena Networks support.</p>
    </div>
</body>
</html>
