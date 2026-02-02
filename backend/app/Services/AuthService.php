<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Events\AuditEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\LdapAuthenticationService;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;

final class AuthService
{
    /**
     * Attempt to authenticate a user.
     *
     * @param array{email: string, auth_method: string, password: string} $credentials
     * @param string $ip
     * @param string|null $userAgent
     * @return array{user: User, company: \App\Models\Company, token: string, force_password_change: bool}
     * @throws ValidationException
     */
    public function login(array $credentials, string $ip, ?string $userAgent = null): array
    {
        $email = trim(strtolower($credentials['email']));
        $authMethod = trim($credentials['auth_method']);

        $this->checkRateLimit($ip, $email);

        $user = User::where('email', $email)
                    ->where('auth_method', $authMethod)
                    ->with(['company', 'role'])
                    ->first();

        if (!$user && $authMethod !== 'ldap') {
            $this->recordFailedAttempt($ip, $email);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user) {
            $this->validateUserStatus($user);
        }

        if ($authMethod === 'local') {
            $this->authenticateLocal($user, $credentials['password'], $ip, $email);
        } elseif ($authMethod === 'ldap') {
            $user = $this->authenticateLdap($user, $email, $credentials['password'], $ip);
        }

        // Success
        $this->clearRateLimit($ip);
        
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->logAudit($user, 'user_login', [
            'email' => $user->email,
            'auth_method' => $user->auth_method,
        ], $ip, $userAgent);

        return [
            'user' => $user->makeHidden(['password', 'remember_token']),
            'company' => $user->company,
            'token' => $token,
            'force_password_change' => (bool) $user->force_password_change,
        ];
    }

    /**
     * Log out a user.
     *
     * @param User $user
     * @param string $ip
     * @param string|null $userAgent
     * @return void
     */
    public function logout(User $user, string $ip, ?string $userAgent = null): void
    {
        $this->logAudit($user, 'user_logout', [], $ip, $userAgent);
        $user->currentAccessToken()->delete();
    }

    protected function checkRateLimit(string $ip, string $email): void
    {
        $lockoutKey = 'login_attempts:' . $ip;
        $attempts = (int) Cache::get($lockoutKey, 0);
        
        if ($attempts >= 5) {
            Log::warning('Login attempt blocked due to too many failed attempts', [
                'ip' => $ip,
                'email' => $email
            ]);
            throw ValidationException::withMessages([
                'email' => ['Too many failed login attempts. Please try again in 15 minutes.'],
            ]);
        }
    }

    protected function validateUserStatus(User $user): void
    {
        if (in_array($user->company->status, ['suspended'])) {
            throw ValidationException::withMessages([
                'email' => ['Your company account is currently suspended. Please contact support.'],
            ]);
        }

        if (in_array($user->status->value, ['inactive', 'locked'])) {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently ' . $user->status->value . '. Please contact your administrator.'],
            ]);
        }
    }

    protected function authenticateLocal(User $user, string $password, string $ip, string $email): void
    {
        if (empty($password) || !Hash::check($password, $user->password)) {
            $this->recordFailedAttempt($ip, $email, $user->company_id);
            throw ValidationException::withMessages([
                'password' => ['Invalid credentials.'],
            ]);
        }
    }

    protected function authenticateLdap(?User $user, string $email, string $password, string $ip): User
    {
        if (empty($password)) {
            $this->recordFailedAttempt($ip, $email, $user ? $user->company_id : null);
            throw ValidationException::withMessages([
                'password' => ['Password is required for LDAP authentication.'],
            ]);
        }

        $ldapService = new LdapAuthenticationService();
        $username = explode('@', $email)[0];
        $ldapUser = $ldapService->authenticate($username, $password);
        
        if (!$ldapUser) {
            $this->recordFailedAttempt($ip, $email, $user ? $user->company_id : null);
            throw ValidationException::withMessages([
                'password' => ['Invalid LDAP credentials.'],
            ]);
        }

        if (!$user) {
            // JIT Provisioning would need company ID. 
            // In the original code, it used $user->company_id which would be null if $user is null.
            // This is a bug in original code or it assumed user existed but maybe with wrong auth method?
            // Fixed version should probably handle company discovery or require user record.
            throw ValidationException::withMessages([
                'email' => ['User account not found for LDAP authentication.'],
            ]);
        }

        return $user;
    }

    protected function recordFailedAttempt(string $ip, string $email, ?int $companyId = null): void
    {
        $lockoutKey = 'login_attempts:' . $ip;
        $attempts = (int) Cache::get($lockoutKey, 0) + 1;
        Cache::put($lockoutKey, $attempts, now()->addMinutes(15));

        Log::warning('Failed login attempt', [
            'ip' => $ip,
            'email' => $email,
            'attempts' => $attempts,
        ]);

        if ($companyId) {
            $this->logAudit(null, 'failed_login_attempt', [
                'email' => $email,
                'attempts' => $attempts,
            ], $ip, null, $companyId);
        }
    }

    protected function clearRateLimit(string $ip): void
    {
        Cache::forget('login_attempts:' . $ip);
    }

    /**
     * @param User|null $user
     * @param string $eventType
     * @param array<string, mixed> $data
     * @param string $ip
     * @param string|null $userAgent
     * @param int|null $companyId
     * @return void
     */
    protected function logAudit(?User $user, string $eventType, array $data, string $ip, ?string $userAgent = null, ?int $companyId = null): void
    {
        // Ensure companyId is never null - use 0 for system-level events
        $resolvedCompanyId = $user ? $user->company_id : ($companyId ?? 0);
        
        AuditEvent::dispatch(
            $resolvedCompanyId,
            $eventType,
            array_merge($data, ['timestamp' => now()->toDateTimeString()]),
            $user ? 'User' : 'System',
            $user ? $user->user_id : null,
            null, // targetType
            null, // targetId
            $ip,
            $userAgent
        );
    }
}
