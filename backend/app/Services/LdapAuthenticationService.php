<?php

namespace App\Services;

use LdapRecord\Connection;
use LdapRecord\Container;
use Illuminate\Support\Facades\Log;
use Exception;

class LdapAuthenticationService
{
    protected Connection $connection;

    public function __construct()
    {
        // Get the default LDAP connection from the container
        $this->connection = Container::getDefaultConnection();
    }

    /**
     * Authenticate a user against LDAP
     *
     * @param string $username
     * @param string $password
     * @return array{username: string, email: string, name: string, first_name: string|null, last_name: string|null, display_name: string|null}|null Returns user data if successful, null if failed
     */
    public function authenticate(string $username, string $password): ?array
    {
        try {
            // Build the user DN based on the username
            $userDn = $this->buildUserDn($username);
            
            Log::info('LDAP: Attempting authentication', [
                'username' => $username,
                'user_dn' => $userDn
            ]);

            // Attempt to bind with the user's credentials
            if (!$this->connection->auth()->attempt($userDn, $password)) {
                Log::warning('LDAP: Authentication failed - invalid credentials', [
                    'username' => $username
                ]);
                return null;
            }

            Log::info('LDAP: Authentication successful', ['username' => $username]);

            // Fetch user details from LDAP
            $userData = $this->getUserData($username);

            if (!$userData) {
                Log::error('LDAP: User authenticated but data retrieval failed', [
                    'username' => $username
                ]);
                return null;
            }

            return $userData;

        } catch (Exception $e) {
            Log::error('LDAP: Authentication error', [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Build the user DN from username
     *
     * @param string $username
     * @return string
     */
    protected function buildUserDn(string $username): string
    {
        $baseDn = config('ldap.connections.default.base_dn');
        $usernameAttribute = config('ldap.user_attribute_username', 'samaccountname');
        
        return "{$usernameAttribute}={$username},{$baseDn}";
    }

    /**
     * Get user data from LDAP
     *
     * @param string $username
     * @return array{username: string, email: string, name: string, first_name: string|null, last_name: string|null, display_name: string|null}|null
     */
    protected function getUserData(string $username): ?array
    {
        try {
            $usernameAttr = config('ldap.user_attributes.username', 'samaccountname');
            $emailAttr = config('ldap.user_attributes.email', 'mail');
            $firstNameAttr = config('ldap.user_attributes.firstname', 'givenname');
            $lastNameAttr = config('ldap.user_attributes.lastname', 'sn');
            $displayNameAttr = config('ldap.user_attributes.displayname', 'displayname');

            // Search for the user
            $results = $this->connection->query()
                ->where($usernameAttr, '=', $username)
                ->get();

            if ($results->isEmpty()) {
                Log::warning('LDAP: User not found in directory', ['username' => $username]);
                return null;
            }

            $user = $results->first();

            // Extract user attributes
            $email = $user->getFirstAttribute($emailAttr);
            $firstName = $user->getFirstAttribute($firstNameAttr);
            $lastName = $user->getFirstAttribute($lastNameAttr);
            $displayName = $user->getFirstAttribute($displayNameAttr);

            // Build full name
            $name = $displayName ?: trim("{$firstName} {$lastName}");
            
            if (empty($name)) {
                $name = $username; // Fallback to username
            }

            if (empty($email)) {
                Log::warning('LDAP: User has no email address', ['username' => $username]);
                // Generate a placeholder email or return null based on requirements
                $email = "{$username}@ldap.local";
            }

            return [
                'username' => $username,
                'email' => strtolower($email),
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => $displayName,
            ];

        } catch (Exception $e) {
            Log::error('LDAP: Error fetching user data', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Test LDAP connection
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            return $this->connection->isConnected();
        } catch (Exception $e) {
            Log::error('LDAP: Connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify user exists in LDAP
     *
     * @param string $username
     * @return bool
     */
    public function userExists(string $username): bool
    {
        try {
            $usernameAttr = config('ldap.user_attributes.username', 'samaccountname');
            
            $results = $this->connection->query()
                ->where($usernameAttr, '=', $username)
                ->get();

            return !$results->isEmpty();
        } catch (Exception $e) {
            Log::error('LDAP: Error checking user existence', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
