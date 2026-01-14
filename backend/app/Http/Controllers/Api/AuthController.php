<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PasswordResetToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\LoginRequest;
use App\Mail\PasswordResetMail;
use App\Rules\StrongPassword;
use App\Services\LdapAuthenticationService;
use App\Services\UserProvisioningService;
use App\Services\OAuthAuthenticationService;
use App\Services\SamlAuthenticationService;

class AuthController extends Controller
{
    protected OAuthAuthenticationService $oauthService;
    protected SamlAuthenticationService $samlService;

    public function __construct(OAuthAuthenticationService $oauthService, SamlAuthenticationService $samlService)
    {
        $this->oauthService = $oauthService;
        $this->samlService = $samlService;
    }
    public function authLogin(LoginRequest $request)
    {
        $validated = $request->validated();
        
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['auth_method'] = trim($validated['auth_method']);

        // Check for too many failed login attempts
        $lockoutKey = 'login_attempts:' . $request->ip();
        $attempts = (int) Cache::get($lockoutKey, 0);
        
        if ($attempts >= 5) {
            Log::warning('Login attempt blocked due to too many failed attempts', [
                'ip' => $request->ip(),
                'email' => $validated['email']
            ]);
            throw ValidationException::withMessages([
                'email' => ['Too many failed login attempts. Please try again in 15 minutes.'],
            ]);
        }

        $user = User::where('email', $validated['email'])
                    ->where('auth_method', $validated['auth_method'])
                    ->with('company', 'role')
                    ->first();

        if (!$user) {
            $this->recordFailedAttempt($request, $validated['email']);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (in_array($user->company->status, ['suspended'])) {
            throw ValidationException::withMessages([
                'email' => ['Your company account is currently suspended. Please contact support.'],
            ]);
        }

        if (in_array($user->status, ['inactive', 'locked'])) {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently ' . $user->status . '. Please contact your administrator.'],
            ]);
        }

        // Handle authentication based on auth_method
        if ($validated['auth_method'] === 'local') {
            if (
                empty($validated['password']) ||
                !Hash::check($validated['password'], $user->password)
            ) {
                $this->recordFailedAttempt($request, $validated['email'], $user->company_id);
                throw ValidationException::withMessages([
                    'password' => ['Invalid credentials.'],
                ]);
            }
        } elseif ($validated['auth_method'] === 'ldap') {
            // LDAP authentication
            if (empty($validated['password'])) {
                $this->recordFailedAttempt($request, $validated['email'], $user->company_id ?? null);
                throw ValidationException::withMessages([
                    'password' => ['Password is required for LDAP authentication.'],
                ]);
            }

            $ldapService = new LdapAuthenticationService();
            
            // Extract username from email (before @)
            $username = explode('@', $validated['email'])[0];
            
            $ldapUser = $ldapService->authenticate($username, $validated['password']);
            
            if (!$ldapUser) {
                $this->recordFailedAttempt($request, $validated['email'], $user->company_id ?? null);
                throw ValidationException::withMessages([
                    'password' => ['Invalid LDAP credentials.'],
                ]);
            }

            // If user doesn't exist locally, provision via JIT
            if (!$user) {
                $provisioningService = new UserProvisioningService();
                $user = $provisioningService->provisionUser(
                    $user->company_id,
                    $ldapUser['email'],
                    $ldapUser['name'],
                    'ldap',
                    [
                        'username' => $ldapUser['username'],
                        'first_name' => $ldapUser['first_name'],
                        'last_name' => $ldapUser['last_name'],
                    ]
                );
                
                // Reload user with relationships
                $user = User::where('user_id', $user->user_id)
                    ->with('company', 'role')
                    ->first();
            }
        }

        // Clear failed login attempts on successful login
        Cache::forget('login_attempts:' . $request->ip());
        
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        AuditLog::create([
            'company_id'  => $user->company_id,
            'event_type'  => 'user_login',
            'actor_type'  => 'User',
            'actor_id'    => $user->user_id,
            'event_data'  => json_encode([
                'email' => $user->email,
                'auth_method' => $user->auth_method,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
        
        $sanitisedUser = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $sanitisedUser,
                'company' => $user->company,
                'token' => $token,
            ],
        ]);
    }

    public function authLogout(Request $request)
    {
        $user = $request->user();

        AuditLog::create([
            'company_id'  => $user->company_id,
            'event_type'  => 'user_logout',
            'actor_type'  => 'User',
            'actor_id'    => $user->user_id,
            'event_data'  => json_encode([
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'company');
        
        $sanitisedUser = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser,
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = trim(strtolower($validated['email']));
        
        $user = User::where('email', $email)
                    ->where('auth_method', 'local')
                    ->first();
        
        if (!$user) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with this email, a password reset link has been sent.',
            ]);
        }
        
        PasswordResetToken::where('email', $email)->delete();
        
        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);
        
        PasswordResetToken::create([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        } catch (\Exception $e) {
            \Log::error('Password reset email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_requested',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an account exists with this email, a password reset link has been sent.',
            
            'debug_token' => config('app.debug') ? $token : null,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'string', 'confirmed', new StrongPassword()],
        ]);

        $email = trim(strtolower($validated['email']));
        $hashedToken = hash('sha256', $validated['token']);

        $resetToken = PasswordResetToken::where('email', $email)
                                        ->where('token', $hashedToken)
                                        ->valid()
                                        ->first();

        if (!$resetToken) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired password reset token.'],
            ]);
        }

        $user = User::where('email', $email)
                    ->where('auth_method', 'local')
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }
        
        $user->password = Hash::make($validated['password']);
        $user->save();
        
        $resetToken->delete();

        $user->tokens()->delete();
        
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_completed',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),  
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        if ($user->auth_method !== 'local') {
            return response()->json([
                'status' => 'error',
                'message' => 'Password changes are not available for ' . $user->auth_method . ' authentication.',
            ], 400);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', new StrongPassword()],
        ]);
        
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }
        
        $user->password = Hash::make($validated['new_password']);
        $user->save();
        
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_changed',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Record failed login attempt and implement rate limiting
     */
    private function recordFailedAttempt(Request $request, string $email, ?int $companyId = null): void
    {
        $lockoutKey = 'login_attempts:' . $request->ip();
        $attempts = (int) Cache::get($lockoutKey, 0) + 1;
        Cache::put($lockoutKey, $attempts, now()->addMinutes(15));

        Log::warning('Failed login attempt', [
            'ip' => $request->ip(),
            'email' => $email,
            'attempts' => $attempts,
            'user_agent' => $request->userAgent()
        ]);

        if ($companyId) {
            AuditLog::create([
                'company_id' => $companyId,
                'event_type' => 'failed_login_attempt',
                'actor_type' => 'System',
                'actor_id' => null,
                'event_data' => json_encode([
                    'email' => $email,
                    'attempts' => $attempts,
                    'timestamp' => now()->toDateTimeString(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }
    }

    /**
     * Redirect to Microsoft OAuth provider
     */
    public function redirectToMicrosoft()
    {
        try {
            if (!$this->oauthService->isEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OAuth authentication is not enabled.',
                ], 503);
            }

            $validation = $this->oauthService->validateConfiguration();
            if (!$validation['valid']) {
                Log::error('OAuth: Configuration invalid', $validation['errors']);
                return response()->json([
                    'success' => false,
                    'message' => 'OAuth is not properly configured.',
                    'errors' => $validation['errors']
                ], 500);
            }

            return $this->oauthService->redirectToMicrosoft();

        } catch (\Exception $e) {
            Log::error('OAuth: Redirect failed', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate OAuth authentication.',
            ], 500);
        }
    }

    /**
     * Handle Microsoft OAuth callback
     */
    public function handleMicrosoftCallback(Request $request)
    {
        try {
            // Handle OAuth callback
            $oauthUser = $this->oauthService->handleMicrosoftCallback();

            if (!$oauthUser) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=oauth_failed');
            }

            // Check if user exists
            $user = User::where('email', $oauthUser['email'])
                ->where('auth_method', 'm365')
                ->with('company', 'role')
                ->first();

            // If user doesn't exist and auto-provision is enabled, create user
            if (!$user && $this->oauthService->isAutoProvisionEnabled()) {
                // For OAuth, we need to determine the company somehow
                // Option 1: Use email domain to match company
                // Option 2: Require pre-registration
                // For now, we'll return an error requiring admin to create the user first
                
                Log::warning('OAuth: User not found and auto-provision requires company context', [
                    'email' => $oauthUser['email']
                ]);

                return redirect(env('FRONTEND_URL') . '/sign-in?error=user_not_found');
            }

            if (!$user) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=user_not_found');
            }

            // Check company and user status
            if (in_array($user->company->status, ['suspended'])) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=company_suspended');
            }

            if (in_array($user->status, ['inactive', 'locked'])) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=account_' . $user->status);
            }

            // Update last login
            $user->last_login_at = now();
            $user->save();

            // Create token
            $token = $user->createToken('api-token')->plainTextToken;

            // Log successful login
            AuditLog::create([
                'company_id' => $user->company_id,
                'event_type' => 'user_login',
                'actor_type' => 'User',
                'actor_id' => $user->user_id,
                'event_data' => json_encode([
                    'email' => $user->email,
                    'auth_method' => 'm365',
                    'timestamp' => now()->toDateTimeString(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Redirect to frontend with token
            return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            Log::error('OAuth: Callback handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL') . '/sign-in?error=oauth_callback_failed');
        }
    }

    /**
     * Get SAML metadata
     */
    public function getSamlMetadata()
    {
        try {
            if (!$this->samlService->isEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'SAML authentication is not enabled.',
                ], 503);
            }

            $validation = $this->samlService->validateConfiguration();
            if (!$validation['valid']) {
                Log::error('SAML: Configuration invalid', $validation['errors']);
                return response()->json([
                    'success' => false,
                    'message' => 'SAML is not properly configured.',
                    'errors' => $validation['errors']
                ], 500);
            }

            $metadata = $this->samlService->generateMetadata();

            return response($metadata, 200)
                ->header('Content-Type', 'application/xml');

        } catch (\Exception $e) {
            Log::error('SAML: Metadata generation failed', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate SAML metadata.',
            ], 500);
        }
    }

    /**
     * Initiate SAML login
     */
    public function initiateSamlLogin()
    {
        try {
            if (!$this->samlService->isEnabled()) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_not_enabled');
            }

            $validation = $this->samlService->validateConfiguration();
            if (!$validation['valid']) {
                Log::error('SAML: Configuration invalid', $validation['errors']);
                return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_not_configured');
            }

            $authnRequest = $this->samlService->generateAuthnRequest();

            Log::info('SAML: Initiating login', [
                'request_id' => $authnRequest['request_id']
            ]);

            return redirect($authnRequest['url']);

        } catch (\Exception $e) {
            Log::error('SAML: Login initiation failed', [
                'error' => $e->getMessage()
            ]);
            return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_init_failed');
        }
    }

    /**
     * Handle SAML Assertion Consumer Service (ACS)
     */
    public function handleSamlAcs(Request $request)
    {
        try {
            $samlResponse = $request->input('SAMLResponse');

            if (!$samlResponse) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_no_response');
            }

            // Parse and validate SAML response
            $samlUser = $this->samlService->parseResponse($samlResponse);

            if (!$samlUser) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_invalid_response');
            }

            // Check if user exists
            $user = User::where('email', $samlUser['email'])
                ->where('auth_method', 'saml')
                ->with('company', 'role')
                ->first();

            if (!$user) {
                Log::warning('SAML: User not found', [
                    'email' => $samlUser['email']
                ]);
                return redirect(env('FRONTEND_URL') . '/sign-in?error=user_not_found');
            }

            // Check company and user status
            if (in_array($user->company->status, ['suspended'])) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=company_suspended');
            }

            if (in_array($user->status, ['inactive', 'locked'])) {
                return redirect(env('FRONTEND_URL') . '/sign-in?error=account_' . $user->status);
            }

            // Update last login
            $user->last_login_at = now();
            $user->save();

            // Create token
            $token = $user->createToken('api-token')->plainTextToken;

            // Log successful login
            AuditLog::create([
                'company_id' => $user->company_id,
                'event_type' => 'user_login',
                'actor_type' => 'User',
                'actor_id' => $user->user_id,
                'event_data' => json_encode([
                    'email' => $user->email,
                    'auth_method' => 'saml',
                    'timestamp' => now()->toDateTimeString(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Redirect to frontend with token
            return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            Log::error('SAML: ACS handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_acs_failed');
        }
    }

    /**
     * Handle SAML Single Logout Service (SLS)
     */
    public function handleSamlSls(Request $request)
    {
        try {
            // Handle logout request from IdP
            Log::info('SAML: SLS request received');

            // Logout user locally
            if ($request->user()) {
                $request->user()->currentAccessToken()->delete();
            }

            return redirect(env('FRONTEND_URL') . '/sign-in?message=logged_out');

        } catch (\Exception $e) {
            Log::error('SAML: SLS handling failed', [
                'error' => $e->getMessage()
            ]);
            return redirect(env('FRONTEND_URL') . '/sign-in?error=saml_sls_failed');
        }
    }
}
