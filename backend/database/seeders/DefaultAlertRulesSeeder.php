<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlertRule;
use App\Models\Company;

class DefaultAlertRulesSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->createDefaultRules($company);
        }
    }

    protected function createDefaultRules(Company $company): void
    {
        $defaultRules = [
            // Critical Alerts
            [
                'name' => 'Database Connectivity Lost',
                'description' => 'Alert when database connection is lost',
                'severity' => 'critical',
                'metric_type' => 'database_connectivity',
                'condition' => [
                    'operator' => '<',
                    'threshold' => 1,
                    'period' => '5m',
                ],
                'notification_channels' => ['email'],
                'notification_config' => [],
                'cooldown_minutes' => 15,
            ],
            [
                'name' => 'Low Disk Space',
                'description' => 'Alert when disk space falls below 10%',
                'severity' => 'critical',
                'metric_type' => 'disk_space',
                'condition' => [
                    'operator' => '<',
                    'threshold' => 10,
                    'period' => '5m',
                ],
                'notification_channels' => ['email'],
                'notification_config' => [],
                'cooldown_minutes' => 60,
            ],
            [
                'name' => 'High Report Generation Failure Rate',
                'description' => 'Alert when report generation fails more than 50% of the time in the last 6 hours',
                'severity' => 'critical',
                'metric_type' => 'report_generation_failure',
                'condition' => [
                    'operator' => '>',
                    'threshold' => 50,
                    'period' => '6h',
                ],
                'notification_channels' => ['email'],
                'notification_config' => [],
                'cooldown_minutes' => 120,
            ],

            // Warning Alerts
            [
                'name' => 'Integration Sync Failures',
                'description' => 'Alert when integration sync failure rate exceeds 20% in the last hour',
                'severity' => 'warning',
                'metric_type' => 'integration_sync_failure',
                'condition' => [
                    'operator' => '>',
                    'threshold' => 20,
                    'period' => '1h',
                ],
                'notification_channels' => ['email'],
                'notification_config' => [],
                'cooldown_minutes' => 240,
            ],
            [
                'name' => 'High Queue Depth',
                'description' => 'Alert when queue depth exceeds 50 jobs',
                'severity' => 'warning',
                'metric_type' => 'queue_depth',
                'condition' => [
                    'operator' => '>',
                    'threshold' => 50,
                    'period' => '5m',
                ],
                'notification_channels' => ['email'],
                'notification_config' => [],
                'cooldown_minutes' => 60,
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            AlertRule::create(array_merge($ruleData, [
                'company_id' => $company->company_id,
                'is_active' => true,
            ]));
        }

        $this->command->info("Created " . count($defaultRules) . " default alert rules for {$company->name}");
    }
}
