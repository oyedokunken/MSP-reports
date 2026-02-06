<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #ffffff; color: #1f2937; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 48px 24px; }
        .header { margin-bottom: 32px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; background-color: #fee2e2; color: #dc2626; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }
        .title { font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 12px 0; letter-spacing: -0.025em; }
        .description { font-size: 16px; line-height: 1.6; color: #4b5563; margin: 0; }
        .card { background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 12px; padding: 24px; margin: 32px 0; }
        .details-table { width: 100%; border-collapse: collapse; }
        .details-table td { padding: 6px 0; font-size: 14px; }
        .label { color: #6b7280; width: 140px; }
        .value { color: #111827; font-weight: 500; }
        .error-container { margin-top: 24px; padding: 16px; background-color: #ffffff; border: 1px solid #fee2e2; border-radius: 8px; border-left: 4px solid #ef4444; }
        .error-label { font-size: 12px; font-weight: 600; color: #991b1b; text-transform: uppercase; margin-bottom: 4px; }
        .error-message { font-family: 'ui-monospace', 'SFMono-Regular', Menlo, Monaco, Consolas, monospace; font-size: 13px; color: #b91c1c; margin: 0; word-break: break-all; }
        .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid #f3f4f6; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="badge">Sync Failure</div>
            <h1 class="title">Integration Disabled</h1>
            <p class="description">
                The integration <strong>{{ $integration->display_name }}</strong> has been disabled after {{ $attempts }} consecutive failed sync attempts.
            </p>
        </div>

        <div class="card">
            <table class="details-table">
                <tr>
                    <td class="label">Integration</td>
                    <td class="value">{{ $integration->integration_type }}</td>
                </tr>
                <tr>
                    <td class="label">Company</td>
                    <td class="value">{{ $companyName }}</td>
                </tr>
                <tr>
                    <td class="label">Last Success</td>
                    <td class="value">{{ $integration->last_sync_at ? $integration->last_sync_at->format('M j, Y H:i') : 'Never' }}</td>
                </tr>
                <tr>
                    <td class="label">Failed At</td>
                    <td class="value">{{ now()->format('M j, Y H:i') }}</td>
                </tr>
            </table>

            <div class="error-container">
                <div class="error-label">Error Details</div>
                <p class="error-message">{{ $exception->getMessage() }}</p>
            </div>
        </div>

        <div class="footer">
            <p>Managed by {{ $companyName }} MSP Reporting System</p>
            <p style="margin-top: 4px;">Integration ID: {{ $integration->integration_id }}</p>
        </div>
    </div>
</body>
</html>

