<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\SyncCompleted;
use App\Notifications\ReportGenerated;
use App\Notifications\SystemAlert;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notifications {user_id? : The ID of the user to notify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate test notifications for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');
        
        $users = [];
        if ($userId) {
            $user = User::find($userId);
            if ($user) $users[] = $user;
        } else {
            $users = User::all();
        }

        if (empty($users)) {
            $this->error('No users found to notify.');
            return 1;
        }

        $this->info("Sending test notifications to " . count($users) . " user(s)...");

        foreach ($users as $user) {
            $this->line("- Notifying: {$user->name} ({$user->email})");
            
            // 1. Success Sync
            $user->notify(new SyncCompleted([
                'title' => 'Datto RMM Sync Successful',
                'description' => 'Successfully synchronized 142 devices and 12 customers.',
                'metadata' => ['source' => 'Datto RMM', 'devices_synced' => 142]
            ]));

            // 2. Report Prepared
            $user->notify(new ReportGenerated([
                'title' => 'Executive Summary Ready',
                'description' => 'The Monthly Executive Summary for Global Corp has been generated.',
                'metadata' => ['report_id' => 'REP-' . rand(1000, 9999), 'customer' => 'Global Corp']
            ]));

            // 3. Warning Alert
            $user->notify(new SystemAlert([
                'title' => 'Disk Usage Warning',
                'severity' => 'yellow',
                'description' => 'Server node-01 disk usage has exceeded 85%.',
                'metadata' => ['node' => 'node-01', 'usage' => '87%']
            ]));

            // 4. Critical Alert
            $user->notify(new SystemAlert([
                'title' => 'Integration Authentication Failed',
                'severity' => 'red',
                'description' => 'The API key for Autotask integration has expired or was revoked.',
                'metadata' => ['integration' => 'Autotask', 'error_code' => 'AUTH_REFUSED']
            ]));

            // 5. Info Notification
            $user->notify(new SystemAlert([
                'title' => 'System Maintenance Scheduled',
                'severity' => 'blue',
                'description' => 'Scheduled maintenance will occur on Saturday at 2:00 AM AEST.',
                'metadata' => ['start_time' => '2026-01-17 02:00:00']
            ]));
        }

        $this->info("Sent notifications to all users!");
        return 0;
    }
}
