<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\MeRequest;
use App\Http\Requests\Auth\OAuthCallbackRequest;
use App\Http\Requests\Auth\SamlAcsRequest;
use App\Http\Requests\Auth\SamlSlsRequest;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OAuthAuthenticationService;
use App\Services\SamlAuthenticationService;
use App\Actions\Auth\RequestPasswordResetAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\ChangePasswordAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class AuthController extends Controller
{
    protected OAuthAuthenticationService $oauthService;
    protected SamlAuthenticationService $samlService;
    protected AuthService $authService;

    public function __construct(
        OAuthAuthenticationService $oauthService, 
        SamlAuthenticationService $samlService,
        AuthService $authService
    ) {
        $this->oauthService = $oauthService;
        $this->samlService = $samlService;
        $this->authService = $authService;
    }
    public function authLogin(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        /** @var array{email: string, auth_method: string, password: string} $credentials */
        $credentials = $request->validated();

        $result = $this->authService->login(
            $credentials,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function authLogout(LogoutRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(MeRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user()->load('role', 'company');
        
        return $this->success(new UserResource($user));
    }

    public function requestPasswordReset(RequestPasswordResetRequest $request, RequestPasswordResetAction $action): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        /** @var array{email: string} $validated */
        $result = $action->execute($validated, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'debug_token' => $result['debug_token'] ?? null,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordAction $action): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        /** @var array{email: string, token: string, password: string} $validated */
        $action->execute($validated, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        /** @var array{current_password: string, new_password: string} $validated */
        $action->execute($request->user(), $validated, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.',
        ]);
    }

    public function verifyPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Incorrect password.',
            ], 401);
        }

        // Return the user's API key (if any) from the company
        $apiKey = $user->company->apiKeys()
            ->where('revoked', false)
            ->latest()
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Password verified.',
            'api_key' => $apiKey?->plain_text_key ?? $apiKey?->display_name ?? 'No API key configured',
        ]);
    }

    /**
     * Redirect to Microsoft OAuth provider
     */
    public function redirectToMicrosoft(): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
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
    public function handleMicrosoftCallback(OAuthCallbackRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            // Handle OAuth callback
            $oauthUser = $this->oauthService->handleMicrosoftCallback();

            if (!$oauthUser) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=oauth_failed');
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

                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=user_not_found');
            }

            if (!$user) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=user_not_found');
            }

            // Check company and user status
            if (in_array($user->company->status, ['suspended'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=company_suspended');
            }

            if (in_array($user->status->value, ['inactive', 'locked'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=account_' . $user->status->value);
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
            return redirect(config('auth_methods.frontend_url') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            Log::error('OAuth: Callback handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(config('auth_methods.frontend_url') . '/sign-in?error=oauth_callback_failed');
        }
    }

    /**
     * Redirect to Keycloak OAuth provider
     */
    public function redirectToKeycloak(): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            if (!$this->oauthService->isKeycloakEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keycloak OAuth authentication is not enabled.',
                ], 503);
            }

            $validation = $this->oauthService->validateKeycloakConfiguration();
            if (!$validation['valid']) {
                Log::error('Keycloak OAuth: Configuration invalid', $validation['errors']);
                return response()->json([
                    'success' => false,
                    'message' => 'Keycloak OAuth is not properly configured.',
                    'errors' => $validation['errors']
                ], 500);
            }

            return $this->oauthService->redirectToKeycloak();

        } catch (\Exception $e) {
            Log::error('Keycloak OAuth: Redirect failed', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Keycloak OAuth authentication.',
            ], 500);
        }
    }

    /**
     * Handle Keycloak OAuth callback
     */
    public function handleKeycloakCallback(OAuthCallbackRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Log::info('Keycloak Callback Request:', $request->all());
            
            // Handle OAuth callback
            $oauthUser = $this->oauthService->handleKeycloakCallback();

            if (!$oauthUser) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=keycloak_oauth_failed');
            }

            // Check if user exists - try both 'keycloak' and 'oidc' auth methods
            $user = User::where('email', $oauthUser['email'])
                ->whereIn('auth_method', ['keycloak', 'oidc'])
                ->with('company', 'role')
                ->first();

            if (!$user) {
                Log::warning('Keycloak OAuth: User not found', [
                    'email' => $oauthUser['email']
                ]);
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=user_not_found');
            }

            // Check company and user status
            if (in_array($user->company->status, ['suspended'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=company_suspended');
            }

            if (in_array($user->status->value, ['inactive', 'locked'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=account_' . $user->status->value);
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
                    'auth_method' => 'keycloak',
                    'timestamp' => now()->toDateTimeString(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Redirect to frontend with token
            return redirect(config('auth_methods.frontend_url') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            Log::error('Keycloak OAuth: Callback handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(config('auth_methods.frontend_url') . '/sign-in?error=keycloak_callback_failed');
        }
    }

    /**
     * Get SAML metadata
     */
    public function getSamlMetadata(): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
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
    public function initiateSamlLogin(): \Illuminate\Http\RedirectResponse
    {
        try {
            if (!$this->samlService->isEnabled()) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_not_enabled');
            }

            $validation = $this->samlService->validateConfiguration();
            if (!$validation['valid']) {
                Log::error('SAML: Configuration invalid', $validation['errors']);
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_not_configured');
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
            return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_init_failed');
        }
    }

    /**
     * Handle SAML Assertion Consumer Service (ACS)
     */
    public function handleSamlAcs(SamlAcsRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $samlResponse = $request->input('SAMLResponse');

            if (!$samlResponse) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_no_response');
            }

            // Parse and validate SAML response
            $samlUser = $this->samlService->parseResponse($samlResponse);

            if (!$samlUser) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_invalid_response');
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
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=user_not_found');
            }

            // Check company and user status
            if (in_array($user->company->status, ['suspended'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=company_suspended');
            }

            if (in_array($user->status->value, ['inactive', 'locked'])) {
                return redirect(config('auth_methods.frontend_url') . '/sign-in?error=account_' . $user->status->value);
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
            return redirect(config('auth_methods.frontend_url') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            Log::error('SAML: ACS handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_acs_failed');
        }
    }

    /**
     * Handle SAML Single Logout Service (SLS)
     */
    public function handleSamlSls(SamlSlsRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            // Handle logout request from IdP
            Log::info('SAML: SLS request received');

            // Logout user locally
            if ($request->user()) {
                $request->user()->currentAccessToken()->delete();
            }

            return redirect(config('auth_methods.frontend_url') . '/sign-in?message=logged_out');

        } catch (\Exception $e) {
            Log::error('SAML: SLS handling failed', [
                'error' => $e->getMessage()
            ]);
            return redirect(config('auth_methods.frontend_url') . '/sign-in?error=saml_sls_failed');
        }
    }

    /**
     * Get available authentication methods configuration
     * This endpoint is public so the login page can show appropriate options
     */
    public function getAuthConfig(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'local' => true,
                'oauth' => [
                    'enabled' => (bool) config('auth_methods.oauth.enabled', false),
                    'provider' => 'microsoft',
                ],
                'saml' => [
                    'enabled' => (bool) config('auth_methods.saml.enabled', false),
                ],
                'ldap' => [
                    'enabled' => (bool) config('auth_methods.ldap.enabled', false),
                ],
            ],
        ]);
    }
}
