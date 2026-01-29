<?php

namespace App\Integrations\SharePoint;
use App\Integrations\Concerns\HasHeartbeat;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointIntegration
{
    /** @var Integration */
    protected $integration;
    /** @var string */
    protected $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata()
    {
        return require __DIR__ . '/config.php';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields()
    {
        $config = $this->getMetadata();
        return $config['credential_fields'] ?? [];
    }

    public function authenticate(): bool
    {
        try {
            $token = $this->getAccessToken();
            return !empty($token);
        } catch (\Exception $e) {
            Log::error('SharePoint authentication failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getAccessToken()
    {
        $credentials = $this->integration->credentials;
        if (empty($credentials['client_id']) || empty($credentials['client_secret']) || empty($credentials['tenant_id'])) {
            throw new \Exception('Missing required credentials');
        }

        // Cache token for 55 minutes (3300 seconds) to avoid rate limits
        $cacheKey = 'sharepoint_token_' . ($this->integration->integration_id ?? 'temp');

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3300, function () use ($credentials) {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$credentials['tenant_id']}/oauth2/v2.0/token", [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get the Drive ID for the configured site URL
     * 
     * @return string
     * @throws \Exception
     */
    public function getDriveId()
    {
        $config = $this->integration->credentials;
        $siteUrl = $config['site_url'] ?? '';

        // If it's already a full drive ID, return it (rare, users usually provide URL)
        // Extract hostname and site path from URL
        // Format: https://{hostname}/sites/{site-path}
        $parts = parse_url($siteUrl);
        $hostname = $parts['host'] ?? '';
        $path = trim($parts['path'] ?? '', '/');

        if (empty($hostname) || empty($path)) {
             throw new \Exception('Invalid SharePoint Site URL');
        }

        $token = $this->getAccessToken();
        
        // Lookup site to get ID
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/sites/{$hostname}:/{$path}");

        if ($response->failed()) {
             throw new \Exception('Failed to find SharePoint Site: ' . $response->body());
        }

        $siteId = $response->json('id');

        // Get default drive (Documents library) for the site
        $driveResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/sites/{$siteId}/drive");

        if ($driveResponse->failed()) {
             throw new \Exception('Failed to find default Document Library for site');
        }

        return $driveResponse->json('id');
    }

    /**
     * Browse contents of a folder
     * @param string $path - Relative path from root, or empty for root
     */
    /**
     * Browse contents of a folder
     * @param string $path - Relative path from root, or empty for root
     * @return array<int, array<string, mixed>>
     * @throws \Exception
     */
    public function browse(string $path = '')
    {
        $token = $this->getAccessToken();
        $driveId = $this->getDriveId();

        $cleanPath = trim($path, '/');
        $endpoint = $cleanPath 
            ? "{$this->baseUrl}/drives/{$driveId}/root:/{$cleanPath}:/children"
            : "{$this->baseUrl}/drives/{$driveId}/root/children";

        $response = Http::withToken($token)->get($endpoint);

        if ($response->failed()) {
             throw new \Exception('Failed to browse folder: ' . $response->body());
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('value');
        
        return collect($items)->map(function($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => isset($item['folder']) ? 'folder' : 'file',
                'size' => $item['size'] ?? 0,
                'last_modified' => $item['lastModifiedDateTime'] ?? null,
                'web_url' => $item['webUrl'] ?? ''
            ];
        })->values()->all();
    }

    /**
     * Create a new folder
     */
    /**
     * Create a new folder
     * 
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function createFolder(string $path, string $name)
    {
        $token = $this->getAccessToken();
        $driveId = $this->getDriveId();
        
        // Ensure path ends cleanly
        $cleanPath = trim($path, '/');
        $endpoint = $cleanPath
            ? "{$this->baseUrl}/drives/{$driveId}/root:/{$cleanPath}:/children"
            : "{$this->baseUrl}/drives/{$driveId}/root/children";

        $response = Http::withToken($token)->post($endpoint, [
            'name' => $name,
            'folder' => (object)[], // Empty object denotes a folder
            '@microsoft.graph.conflictBehavior' => 'rename'
        ]);

        if ($response->failed()) {
             throw new \Exception('Failed to create folder: ' . $response->body());
        }

        $item = $response->json();
        
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'type' => 'folder',
            'web_url' => $item['webUrl']
        ];
    }

    /**
     * Upload a file to SharePoint
     * @param string $content File content
     * @param string $filename Name of the file
     * @param string $path Target folder path (relative to root)
     */
    /**
     * Upload a file to SharePoint
     * @param string $content File content
     * @param string $filename Name of the file
     * @param string $path Target folder path (relative to root)
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function uploadFile(string $content, string $filename, string $path = '')
    {
        $token = $this->getAccessToken();
        $driveId = $this->getDriveId();
        
        $cleanPath = trim($path, '/');
        // If path is provided, construct URL with path, otherwise just filename at root
        $endpoint = $cleanPath 
            ? "{$this->baseUrl}/drives/{$driveId}/root:/{$cleanPath}/{$filename}:/content"
            : "{$this->baseUrl}/drives/{$driveId}/root:/{$filename}:/content";

        // Regular upload (suitable for files < 4MB)
        // For larger files, we would need to create an upload session
        $response = Http::withToken($token)
            ->withBody($content, 'application/pdf') // Assuming PDF for reports, or generic application/octet-stream
            ->put($endpoint);

        if ($response->failed()) {
             throw new \Exception('Failed to upload file to SharePoint: ' . $response->body());
        }

        $item = $response->json();
        
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'web_url' => $item['webUrl']
        ];
    }
}
