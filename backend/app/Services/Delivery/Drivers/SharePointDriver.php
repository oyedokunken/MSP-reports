<?php

namespace App\Services\Delivery\Drivers;

use App\Models\GeneratedReport;
use App\Models\Customer;
use App\Models\Company;
use App\Services\Delivery\Contracts\DeliveryDriverInterface;
use App\Services\ReportNamingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class SharePointDriver implements DeliveryDriverInterface
{
    protected const TOKEN_CACHE_PREFIX = 'sharepoint_token_';
    protected const MAX_SIMPLE_UPLOAD_SIZE = 4 * 1024 * 1024; // 4MB

    public function getIdentifier(): string
    {
        return 'sharepoint';
    }

    public function getDisplayName(): string
    {
        return 'SharePoint';
    }

    public function deliver(GeneratedReport $report, array $config, Company $company, Customer $customer): array
    {
        $this->validateConfigOrThrow($config);

        $filePath = $report->file_path;
        if (!$filePath || !Storage::disk('reports')->exists($filePath)) {
            throw new \Exception("Report file not found: {$filePath}");
        }

        $fullPath = Storage::disk('reports')->path($filePath);
        $fileSize = filesize($fullPath);
        $fileContent = file_get_contents($fullPath);

        // Use ReportNamingService for consistent filename generation
        $namingService = app(ReportNamingService::class);
        $fileName = 'Report.pdf';
        
        if ($report->reportSchedule) {
            $fileName = $namingService->getDownloadName($report->reportSchedule, $company, $report->customer, $report->generated_at) . '.pdf';
        } else {
            $fileName = basename($report->file_path);
        }

        // Get access token
        $accessToken = $this->getAccessToken($config);

        // Build the upload URL
        $uploadUrl = $this->buildUploadUrl($config, $fileName);

        // Use simple upload for files under 4MB, otherwise use upload session
        if ($fileSize <= self::MAX_SIMPLE_UPLOAD_SIZE) {
            $this->simpleUpload($uploadUrl, $fileContent, $accessToken);
        } else {
            $this->uploadWithSession($config, $fileName, $fullPath, $fileSize, $accessToken);
        }

        Log::info('Report uploaded to SharePoint', [
            'report_id' => $report->generated_report_id,
            'customer_id' => $customer->customer_id,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'site' => $config['site_id'] ?? $config['site_url'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'message' => "Report uploaded to SharePoint: {$fileName}",
            'file_name' => $fileName,
        ];
    }

    /**
     * Simple upload for files under 4MB
     * @see https://learn.microsoft.com/en-us/graph/api/driveitem-put-content
     */
    protected function simpleUpload(string $uploadUrl, string $fileContent, string $accessToken): void
    {
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/pdf',
            ])
            ->withBody($fileContent, 'application/pdf')
            ->put($uploadUrl);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \Exception("SharePoint upload failed: {$error}");
        }
    }

    /**
     * Upload using upload session for files over 4MB
     * @see https://learn.microsoft.com/en-us/graph/api/driveitem-createuploadsession
     */
    protected function uploadWithSession(array $config, string $fileName, string $filePath, int $fileSize, string $accessToken): void
    {
        // Create upload session
        $sessionUrl = $this->buildCreateSessionUrl($config, $fileName);
        
        $response = Http::withToken($accessToken)
            ->post($sessionUrl, [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'replace',
                    'name' => $fileName,
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \Exception("Failed to create SharePoint upload session: {$error}");
        }

        $uploadUrl = $response->json('uploadUrl');
        if (!$uploadUrl) {
            throw new \Exception("No upload URL returned from SharePoint");
        }

        // Upload in chunks (10MB chunks)
        $chunkSize = 10 * 1024 * 1024;
        $handle = fopen($filePath, 'rb');
        $offset = 0;

        try {
            while ($offset < $fileSize) {
                $chunk = fread($handle, $chunkSize);
                $chunkLength = strlen($chunk);
                $rangeEnd = $offset + $chunkLength - 1;

                $chunkResponse = Http::withHeaders([
                    'Content-Length' => $chunkLength,
                    'Content-Range' => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                ])
                ->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl);

                if (!$chunkResponse->successful() && $chunkResponse->status() !== 202) {
                    $error = $chunkResponse->json('error.message') ?? $chunkResponse->body();
                    throw new \Exception("SharePoint chunk upload failed: {$error}");
                }

                $offset += $chunkLength;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get OAuth access token for Microsoft Graph API
     */
    protected function getAccessToken(array $config): string
    {
        $cacheKey = self::TOKEN_CACHE_PREFIX . md5($config['client_id'] . $config['tenant_id']);
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $tokenUrl = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token";

        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            $error = $response->json('error_description') ?? $response->body();
            throw new \Exception("Failed to get SharePoint access token: {$error}");
        }

        $accessToken = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 3600) - 300; // Subtract 5 minutes buffer

        // Cache the token
        Cache::put($cacheKey, $accessToken, $expiresIn);

        return $accessToken;
    }

    /**
     * Build the upload URL for simple upload
     */
    protected function buildUploadUrl(array $config, string $fileName): string
    {
        $baseUrl = 'https://graph.microsoft.com/v1.0';
        $folderPath = trim($config['folder_path'] ?? '', '/');
        
        if (!empty($config['site_id']) && !empty($config['drive_id'])) {
            // Using site_id and drive_id
            $path = $folderPath ? "{$folderPath}/{$fileName}" : $fileName;
            return "{$baseUrl}/sites/{$config['site_id']}/drives/{$config['drive_id']}/root:/{$path}:/content";
        }
        
        if (!empty($config['site_id'])) {
            // Using site_id with default drive
            $path = $folderPath ? "{$folderPath}/{$fileName}" : $fileName;
            return "{$baseUrl}/sites/{$config['site_id']}/drive/root:/{$path}:/content";
        }

        throw new \Exception("SharePoint configuration must include site_id");
    }

    /**
     * Build the URL for creating an upload session
     */
    protected function buildCreateSessionUrl(array $config, string $fileName): string
    {
        $baseUrl = 'https://graph.microsoft.com/v1.0';
        $folderPath = trim($config['folder_path'] ?? '', '/');
        
        if (!empty($config['site_id']) && !empty($config['drive_id'])) {
            $path = $folderPath ? "{$folderPath}/{$fileName}" : $fileName;
            return "{$baseUrl}/sites/{$config['site_id']}/drives/{$config['drive_id']}/root:/{$path}:/createUploadSession";
        }
        
        if (!empty($config['site_id'])) {
            $path = $folderPath ? "{$folderPath}/{$fileName}" : $fileName;
            return "{$baseUrl}/sites/{$config['site_id']}/drive/root:/{$path}:/createUploadSession";
        }

        throw new \Exception("SharePoint configuration must include site_id");
    }

    protected function validateConfigOrThrow(array $config): void
    {
        $validation = $this->validateConfig($config);
        if (!$validation['valid']) {
            throw new \Exception("Invalid SharePoint configuration: " . implode(', ', $validation['errors']));
        }
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['tenant_id'])) {
            $errors[] = 'Azure Tenant ID is required';
        }

        if (empty($config['client_id'])) {
            $errors[] = 'Azure Client ID (Application ID) is required';
        }

        if (empty($config['client_secret'])) {
            $errors[] = 'Azure Client Secret is required';
        }

        if (empty($config['site_id'])) {
            $errors[] = 'SharePoint Site ID is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'tenant_id',
                    'label' => 'Azure Tenant ID',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                    'help' => 'Found in Azure Portal > Azure Active Directory > Overview',
                ],
                [
                    'name' => 'client_id',
                    'label' => 'Application (Client) ID',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                    'help' => 'The Application ID from your Azure App Registration',
                ],
                [
                    'name' => 'client_secret',
                    'label' => 'Client Secret',
                    'type' => 'password',
                    'required' => true,
                    'placeholder' => 'Enter client secret',
                    'help' => 'Create in Azure Portal > App Registration > Certificates & secrets',
                ],
                [
                    'name' => 'site_id',
                    'label' => 'SharePoint Site ID',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'contoso.sharepoint.com,guid,guid',
                    'help' => 'Get via Graph Explorer: GET /sites/{hostname}:/{site-path}',
                ],
                [
                    'name' => 'drive_id',
                    'label' => 'Drive ID (Optional)',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'Leave empty for default document library',
                    'help' => 'Specific document library ID. Leave empty to use default.',
                ],
                [
                    'name' => 'folder_path',
                    'label' => 'Folder Path',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'Reports/Monthly',
                    'help' => 'Path within the document library. Created if it does not exist.',
                ],
            ],
        ];
    }

    public function testConnection(array $config, Company $company): array
    {
        $validation = $this->validateConfig($config);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid configuration: ' . implode(', ', $validation['errors']),
            ];
        }

        try {
            // Try to get an access token
            $accessToken = $this->getAccessToken($config);

            // Try to access the site
            $siteUrl = "https://graph.microsoft.com/v1.0/sites/{$config['site_id']}";
            $response = Http::withToken($accessToken)->get($siteUrl);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                return [
                    'success' => false,
                    'message' => "Failed to access SharePoint site: {$error}",
                ];
            }

            $siteName = $response->json('displayName', 'Unknown');

            return [
                'success' => true,
                'message' => "Successfully connected to SharePoint site: {$siteName}",
                'site_name' => $siteName,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection test failed: {$e->getMessage()}",
            ];
        }
    }
}
