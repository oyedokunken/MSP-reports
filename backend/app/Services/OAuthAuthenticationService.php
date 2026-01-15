<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Exception;

class OAuthAuthenticationService
{
    /**
     * Redirect to Microsoft OAuth provider
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToMicrosoft()
    {
        try {
            return Socialite::driver('microsoft')
                ->scopes(explode(' ', env('AZURE_SCOPES', 'openid profile email User.Read')))
                ->redirect();
        } catch (Exception $e) {
            Log::error('OAuth: Failed to redirect to Microsoft', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Microsoft OAuth callback
     *
     * @return array|null Returns user data if successful, null if failed
     */
    public function handleMicrosoftCallback(): ?array
    {
        try {
            $microsoftUser = Socialite::driver('microsoft')->user();

            Log::info('OAuth: Microsoft authentication successful', [
                'email' => $microsoftUser->getEmail(),
                'name' => $microsoftUser->getName()
            ]);

            return [
                'email' => strtolower($microsoftUser->getEmail()),
                'name' => $microsoftUser->getName(),
                'external_id' => $microsoftUser->getId(),
                'avatar' => $microsoftUser->getAvatar(),
                'token' => $microsoftUser->token,
                'refresh_token' => $microsoftUser->refreshToken,
                'expires_in' => $microsoftUser->expiresIn,
            ];

        } catch (Exception $e) {
            Log::error('OAuth: Microsoft callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Check if OAuth is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return env('OAUTH_ENABLED', false) === true || env('OAUTH_ENABLED', false) === 'true';
    }

    /**
     * Check if auto-provisioning is enabled
     *
     * @return bool
     */
    public function isAutoProvisionEnabled(): bool
    {
        return env('OAUTH_AUTO_PROVISION', true) === true || env('OAUTH_AUTO_PROVISION', true) === 'true';
    }

    /**
     * Validate OAuth configuration
     *
     * @return array
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty(env('AZURE_CLIENT_ID'))) {
            $errors[] = 'AZURE_CLIENT_ID is not configured';
        }

        if (empty(env('AZURE_CLIENT_SECRET'))) {
            $errors[] = 'AZURE_CLIENT_SECRET is not configured';
        }

        if (empty(env('AZURE_TENANT_ID'))) {
            $errors[] = 'AZURE_TENANT_ID is not configured';
        }

        if (empty(env('AZURE_REDIRECT_URI'))) {
            $errors[] = 'AZURE_REDIRECT_URI is not configured';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // ============================
    // Keycloak OAuth Methods
    // ============================

    /**
     * Check if Keycloak OAuth is enabled
     */
    public function isKeycloakEnabled(): bool
    {
        return env('KEYCLOAK_ENABLED', false) === true || env('KEYCLOAK_ENABLED', false) === 'true';
    }

    /**
     * Validate Keycloak configuration
     */
    public function validateKeycloakConfiguration(): array
    {
        $errors = [];

        if (empty(env('KEYCLOAK_CLIENT_ID'))) {
            $errors[] = 'KEYCLOAK_CLIENT_ID is not configured';
        }

        if (empty(env('KEYCLOAK_CLIENT_SECRET'))) {
            $errors[] = 'KEYCLOAK_CLIENT_SECRET is not configured';
        }

        if (empty(env('KEYCLOAK_BASE_URL'))) {
            $errors[] = 'KEYCLOAK_BASE_URL is not configured';
        }

        if (empty(env('KEYCLOAK_REALM'))) {
            $errors[] = 'KEYCLOAK_REALM is not configured';
        }

        if (empty(env('KEYCLOAK_REDIRECT_URI'))) {
            $errors[] = 'KEYCLOAK_REDIRECT_URI is not configured';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Redirect to Keycloak OAuth provider
     */
    public function redirectToKeycloak()
    {
        try {
            return Socialite::driver('keycloak')
                ->enablePKCE()
                ->scopes(['openid', 'profile', 'email'])
                ->redirect();
        } catch (Exception $e) {
            Log::error('OAuth: Failed to redirect to Keycloak', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Keycloak OAuth callback
     */
    public function handleKeycloakCallback(): ?array
    {
        try {
            $driver = Socialite::driver('keycloak')->enablePKCE();
            $code = request()->input('code');
            Log::info('OAuth: Keycloak callback received with code: ' . ($code ? 'present' : 'missing'));
            
            $keycloakUser = $driver->user();

            return [
                'email' => strtolower($keycloakUser->getEmail()),
                'name' => $keycloakUser->getName(),
                'external_id' => $keycloakUser->getId(),
                'avatar' => $keycloakUser->getAvatar(),
                'token' => $keycloakUser->token,
                'refresh_token' => $keycloakUser->refreshToken,
                'expires_in' => $keycloakUser->expiresIn,
            ];

        } catch (Exception $e) {
            Log::error('OAuth: Keycloak callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
