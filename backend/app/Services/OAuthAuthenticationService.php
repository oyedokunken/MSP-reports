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
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('microsoft');
            return $driver
                ->scopes((array)config('auth_methods.oauth.microsoft.scopes'))
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
            /** @var \Laravel\Socialite\Two\User $microsoftUser */
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
        return config('auth_methods.oauth.enabled', false);
    }

    /**
     * Check if auto-provisioning is enabled
     *
     * @return bool
     */
    public function isAutoProvisionEnabled(): bool
    {
        return config('auth_methods.oauth.auto_provision', true);
    }

    /**
     * Validate OAuth configuration
     *
     * @return array
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty(config('services.microsoft.client_id'))) {
            $errors[] = 'AZURE_CLIENT_ID is not configured';
        }

        if (empty(config('services.microsoft.client_secret'))) {
            $errors[] = 'AZURE_CLIENT_SECRET is not configured';
        }

        if (empty(config('services.microsoft.tenant'))) {
            $errors[] = 'AZURE_TENANT_ID is not configured';
        }

        if (empty(config('services.microsoft.redirect'))) {
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
        return config('auth_methods.oauth.keycloak.enabled', false);
    }

    /**
     * Validate Keycloak configuration
     */
    public function validateKeycloakConfiguration(): array
    {
        $errors = [];

        if (empty(config('services.keycloak.client_id'))) {
            $errors[] = 'KEYCLOAK_CLIENT_ID is not configured';
        }

        if (empty(config('services.keycloak.client_secret'))) {
            $errors[] = 'KEYCLOAK_CLIENT_SECRET is not configured';
        }

        if (empty(config('services.keycloak.base_url'))) {
            $errors[] = 'KEYCLOAK_BASE_URL is not configured';
        }

        if (empty(config('services.keycloak.realms'))) {
            $errors[] = 'KEYCLOAK_REALM is not configured';
        }

        if (empty(config('services.keycloak.redirect'))) {
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
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('keycloak');
            return $driver
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
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('keycloak');
            $driver->enablePKCE();
            $code = request()->input('code');
            Log::info('OAuth: Keycloak callback received with code: ' . ($code ? 'present' : 'missing'));
            
            /** @var \Laravel\Socialite\Two\User $keycloakUser */
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
