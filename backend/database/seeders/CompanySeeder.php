<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Seed the first customer company with unlimited license.
     * Territory Technology Solutions - First Customer
     */
    public function run(): void
    {
        Company::create([
            'name' => 'Territory Technology Solutions',
            'abn' => '12345678901',
            'contact_email' => 'admin@tts.com.au',
            'contact_phone' => '1300 XXX XXX',
            'status' => 'active',
            'subscription_tier' => 'unlimited',
            'settings' => [
                'authentication' => [
                    'auth_methods' => ['local'],
                    'require_2fa' => false,
                    'session_timeout_minutes' => 60,
                ],
                'integrations' => [
                    'source_of_truth_integration_id' => null,
                    'auto_sync_enabled' => true,
                    'sync_schedule' => '0 */4 * * *',
                    'sync_failure_notifications' => ['admin@tts.com.au'],
                ],
                'customer_mapping' => [
                    'auto_match_enabled' => true,
                    'auto_match_threshold' => 85,
                    'manual_match_required' => false,
                ],
                'reports' => [
                    'default_report_destination' => 'reports@tts.com.au',
                    'report_timezone' => 'Australia/Brisbane',
                    'report_retention_days' => 1095,
                    'report_format' => [
                        'default_format' => 'pdf',
                        'pdf_page_size' => 'A4',
                        'include_raw_data' => false,
                        'compress_attachments' => true,
                    ],
                ],
                'white_label' => [
                    'enabled' => true,
                    'company_name' => 'Territory Technology Solutions',
                    'logo_url' => null,
                    'primary_color' => '#1a56db',
                    'secondary_color' => '#7c3aed',
                    'favicon_url' => null,
                    'email_from_name' => 'TTS Reports',
                    'email_from_address' => 'reports@tts.com.au',
                    'support_email' => 'support@tts.com.au',
                    'support_phone' => '1300 XXX XXX',
                    'footer_text' => '© 2025 Territory Technology Solutions',
                    'report_header_text' => 'Monthly Service Report',
                ],
                'data_retention' => [
                    'default_months' => 36,
                    'audit_logs_months' => 84,
                    'generated_reports_months' => 36,
                    'integration_sync_logs_months' => 12,
                    'historical_data' => [
                        'raw_events_days' => 90,
                        'aggregated_daily_days' => 365,
                        'aggregated_monthly_days' => 1095,
                    ],
                ],
                'security' => [
                    'allowed_ip_ranges' => [],
                    'encryption_key_id' => 'tts_key_001',
                ],
                'licensing' => [
                    'deployment_type' => 'on_premise',
                    'license_enforcement' => false, // Disabled for first customer
                ],
                'historical_data' => [
                    'import_on_activation' => true,
                    'default_import_range_days' => 90,
                    'aggregation_enabled' => true,
                ],
            ],
        ]);

        $this->command->info('First customer company created: Territory Technology Solutions');
    }
}
