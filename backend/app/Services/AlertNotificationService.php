<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\AlertLog;
use App\Mail\AlertNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertNotificationService
{
    public function sendAlert(AlertLog $alertLog, AlertRule $rule): array
    {
        $status = [];
        $channels = $rule->notification_channels ?? [];

        foreach ($channels as $channel) {
            try {
                $result = match ($channel) {
                    'email' => $this->sendEmailNotification($alertLog, $rule),
                    'slack' => $this->sendSlackNotification($alertLog, $rule),
                    'webhook' => $this->sendWebhookNotification($alertLog, $rule),
                    default => false,
                };

                $status[$channel] = $result ? 'sent' : 'failed';
            } catch (\Exception $e) {
                $status[$channel] = 'error';
                Log::error("Alert notification failed for channel: {$channel}", [
                    'alert_log_id' => $alertLog->alert_log_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $status;
    }

    protected function sendEmailNotification(AlertLog $alertLog, AlertRule $rule): bool
    {
        $config = $rule->notification_config ?? [];
        $recipients = $config['email_recipients'] ?? [];

        if (empty($recipients)) {
            $recipients = $rule->company->users()
                ->whereJsonContains('permissions->company_settings->view', true)
                ->pluck('email')
                ->toArray();
        }

        if (empty($recipients)) {
            return false;
        }

        try {
            Mail::to($recipients)->send(new AlertNotificationMail($alertLog, $rule));
            return true;
        } catch (\Exception $e) {
            Log::error('Alert email failed', [
                'alert_log_id' => $alertLog->alert_log_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function sendSlackNotification(AlertLog $alertLog, AlertRule $rule): bool
    {
        $config = $rule->notification_config ?? [];
        $webhookUrl = $config['slack_webhook_url'] ?? null;

        if (!$webhookUrl) {
            return false;
        }

        $color = match ($alertLog->severity) {
            'critical' => '#dc2626',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            default => '#6b7280',
        };

        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $alertLog->title,
                    'text' => $alertLog->message,
                    'fields' => [
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alertLog->severity),
                            'short' => true,
                        ],
                        [
                            'title' => 'Alert Type',
                            'value' => $alertLog->alert_type,
                            'short' => true,
                        ],
                        [
                            'title' => 'Triggered At',
                            'value' => $alertLog->triggered_at->format('Y-m-d H:i:s'),
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::post($webhookUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Slack notification failed', [
                'alert_log_id' => $alertLog->alert_log_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function sendWebhookNotification(AlertLog $alertLog, AlertRule $rule): bool
    {
        $config = $rule->notification_config ?? [];
        $webhookUrl = $config['webhook_url'] ?? null;

        if (!$webhookUrl) {
            return false;
        }

        $payload = [
            'alert_id' => $alertLog->alert_log_id,
            'severity' => $alertLog->severity,
            'alert_type' => $alertLog->alert_type,
            'title' => $alertLog->title,
            'message' => $alertLog->message,
            'context' => $alertLog->context,
            'triggered_at' => $alertLog->triggered_at->toIso8601String(),
            'company_id' => $alertLog->company_id,
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Webhook notification failed', [
                'alert_log_id' => $alertLog->alert_log_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
