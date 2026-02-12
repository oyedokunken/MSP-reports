<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Services\RolePermissionService;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed users based on realistic MSP personas.
     */
    public function run(): void
    {
        $company = Company::where('name', 'Territory Technology Solutions')->first();

        if (!$company) {
            $this->command->error('Company not found. Run CompanySeeder first.');
            return;
        }

        $roles = Role::all();
        $commonPassword = 'password123'; // Default for demo

        $this->command->info('Seeding Realistic MSP Personas...');

        // ----------------------------------------------------------------
        // 1. THE "POWER USER" - Service Delivery Manager (SDM)
        // ----------------------------------------------------------------
        // ROLE: Manager
        // WHY: This is your ideal user. They set up the templates, 
        // configure the report schedules, and ensure all clients are mapped.
        // They need access to everything EXCEPT destroying system config (API Keys).
        $this->createUser(
            $company,
            $roles,
            'Manager',
            'sdm@tts.com.au',
            'Liam (Service Delivery Manager)',
            $commonPassword,
            [
                'theme' => 'light',
                'notifications' => ['email' => true, 'browser' => true],
                'dashboard_layout' => 'executive_summary'
            ]
        );

        // ----------------------------------------------------------------
        // 2. THE "TECHNICAL LEAD" - System Administrator
        // ----------------------------------------------------------------
        // ROLE: Technician / Admin
        // WHY: They fix the broken syncs. If "ViewSyncLogsModal" shows errors,
        // this is the person who logs in to fix the API credentials or mappings.
        $this->createUser(
            $company,
            $roles,
            'Technician',
            'sysadmin@tts.com.au',
            'Marcus (System Admin)',
            $commonPassword,
            [
                'theme' => 'dark', // Techs love dark mode
                'notifications' => ['email' => true, 'browser' => false]
            ]
        );

        // ----------------------------------------------------------------
        // 3. THE "CLIENT FACING" - vCIO / Account Manager
        // ----------------------------------------------------------------
        // ROLE: Viewer
        // WHY: They don't configure reports. They just log in 5 minutes before
        // a meeting to "View" the dashboard and print a PDF to show the client.
        $this->createUser(
            $company,
            $roles,
            'Viewer',
            'vcio@tts.com.au',
            'Sarah (Account Manager)',
            $commonPassword,
            [
                'theme' => 'light',
                'notifications' => ['email' => false, 'browser' => false]
            ]
        );

        // ----------------------------------------------------------------
        // 4. THE "SUPER USER" - Owner / CTO
        // ----------------------------------------------------------------
        // ROLE: Admin
        // WHY: Full access to verify billing, licenses, and system security.
        $this->createUser(
            $company,
            $roles,
            'Admin',
            'admin@tts.com.au',
            'TTS Administrator',
            'ChangeMe123!',
            ['theme' => 'light']
        );

        // Kristian (Your requested Admin user)
        $this->createUser(
            $company,
            $roles,
            'Admin',
            'kristianbas14@gmail.com',
            'Kristian',
            'password123',
            ['theme' => 'dark']
        );

        // ----------------------------------------------------------------
        // 5. THE "END CLIENT" - Customer Portal
        // ----------------------------------------------------------------
        // ROLE: Customer
        // WHY: A client who wants to see their own SLAs without calling you.
        $this->createUser(
            $company,
            $roles,
            'Customer',
            'client@bigcorp.com',
            'Big Corp CEO',
            $commonPassword,
            ['theme' => 'light'],
            false,
            [1] // Scoped ONLY to their own company data
        );

        $this->command->table(
            ['Persona', 'Role', 'Email', 'Password', 'Primary Goal'],
            [
                ['Service Delivery Manager', 'Manager', 'sdm@tts.com.au', $commonPassword, 'Manage Reports & Schedules'],
                ['System Admin', 'Technician', 'sysadmin@tts.com.au', $commonPassword, 'Fix Integrations & Syncs'],
                ['Account Manager', 'Viewer', 'vcio@tts.com.au', $commonPassword, 'Present Data to Clients'],
                ['Owner / CTO', 'Admin', 'admin@tts.com.au', 'ChangeMe123!', 'Full Control'],
                ['End Client', 'Customer', 'client@bigcorp.com', $commonPassword, 'View Own Stats'],
            ]
        );
    }

    private function createUser(
        Company $company,
        $roles,
        string $roleName,
        string $email,
        string $name,
        string $password,
        array $settings = [],
        bool $forcePasswordChange = false,
        array $scopedCustomers = []
    ): void {
        $role = $roles->where('name', $roleName)->first();
        if (!$role)
            return;

        User::firstOrCreate(
            ['email' => $email],
            [
                'company_id' => $company->company_id,
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
                'auth_method' => 'local',
                'permissions' => RolePermissionService::getDefaultPermissions($roleName),
                'settings' => $settings,
                'email_verified_at' => now(),
                'force_password_change' => $forcePasswordChange,
                'scoped_customers' => $scopedCustomers,
            ]
        );
    }
}
