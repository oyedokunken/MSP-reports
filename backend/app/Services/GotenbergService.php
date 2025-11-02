<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GotenbergService
{
    private string $baseUrl;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.gotenberg.url', 'http://gotenberg:3000');
        $this->timeout = config('services.gotenberg.timeout', 30);
        $this->enabled = config('services.gotenberg.enabled', true);
    }

    /**
     * Convert HTML to PDF using Gotenberg API
     *
     * @param string $html The HTML content to convert
     * @param array $options Additional options for PDF generation
     * @return string Binary PDF content
     * @throws Exception
     */
    public function htmlToPdf(string $html, array $options = []): string
    {
        if (!$this->enabled) {
            throw new Exception('Gotenberg service is disabled');
        }

        try {
            $endpoint = "{$this->baseUrl}/forms/chromium/convert/html";

            // Prepare multipart form data
            $multipart = [
                [
                    'name' => 'files',
                    'contents' => $html,
                    'filename' => 'index.html',
                ],
            ];

            // Add optional parameters
            if (isset($options['paper_width'])) {
                $multipart[] = [
                    'name' => 'paperWidth',
                    'contents' => $options['paper_width'],
                ];
            }

            if (isset($options['paper_height'])) {
                $multipart[] = [
                    'name' => 'paperHeight',
                    'contents' => $options['paper_height'],
                ];
            }

            if (isset($options['margin_top'])) {
                $multipart[] = [
                    'name' => 'marginTop',
                    'contents' => $options['margin_top'],
                ];
            }

            if (isset($options['margin_bottom'])) {
                $multipart[] = [
                    'name' => 'marginBottom',
                    'contents' => $options['margin_bottom'],
                ];
            }

            if (isset($options['margin_left'])) {
                $multipart[] = [
                    'name' => 'marginLeft',
                    'contents' => $options['margin_left'],
                ];
            }

            if (isset($options['margin_right'])) {
                $multipart[] = [
                    'name' => 'marginRight',
                    'contents' => $options['margin_right'],
                ];
            }

            if (isset($options['prefer_css_page_size'])) {
                $multipart[] = [
                    'name' => 'preferCssPageSize',
                    'contents' => $options['prefer_css_page_size'] ? 'true' : 'false',
                ];
            }

            if (isset($options['print_background'])) {
                $multipart[] = [
                    'name' => 'printBackground',
                    'contents' => $options['print_background'] ? 'true' : 'false',
                ];
            }

            if (isset($options['landscape'])) {
                $multipart[] = [
                    'name' => 'landscape',
                    'contents' => $options['landscape'] ? 'true' : 'false',
                ];
            }

            // Make request to Gotenberg
            $response = Http::timeout($this->timeout)
                ->asMultipart()
                ->attach('files', $html, 'index.html')
                ->post($endpoint, $this->buildFormData($options));

            if (!$response->successful()) {
                throw new Exception(
                    "Gotenberg API error: {$response->status()} - {$response->body()}"
                );
            }

            return $response->body();

        } catch (Exception $e) {
            Log::error('Gotenberg PDF generation failed', [
                'error' => $e->getMessage(),
                'url' => $endpoint ?? null,
            ]);

            throw new Exception("Failed to generate PDF: {$e->getMessage()}");
        }
    }

    /**
     * Build form data for Gotenberg request
     *
     * @param array $options
     * @return array
     */
    private function buildFormData(array $options): array
    {
        $formData = [];

        // A4 paper size by default (8.27 x 11.69 inches)
        $formData['paperWidth'] = $options['paper_width'] ?? '8.27';
        $formData['paperHeight'] = $options['paper_height'] ?? '11.69';

        // Margins (in inches)
        $formData['marginTop'] = $options['margin_top'] ?? '0.39';
        $formData['marginBottom'] = $options['margin_bottom'] ?? '0.39';
        $formData['marginLeft'] = $options['margin_left'] ?? '0.39';
        $formData['marginRight'] = $options['margin_right'] ?? '0.39';

        // Other options
        $formData['preferCssPageSize'] = $options['prefer_css_page_size'] ?? false;
        $formData['printBackground'] = $options['print_background'] ?? true;
        $formData['landscape'] = $options['landscape'] ?? false;

        // Wait delay (useful for JavaScript rendering)
        if (isset($options['wait_delay'])) {
            $formData['waitDelay'] = $options['wait_delay'];
        }

        // Wait for expression (wait for specific element)
        if (isset($options['wait_for_expression'])) {
            $formData['waitForExpression'] = $options['wait_for_expression'];
        }

        return $formData;
    }

    /**
     * Check if Gotenberg service is healthy
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            
            if ($response->successful()) {
                $health = $response->json();
                return isset($health['status']) && $health['status'] === 'up';
            }

            return false;
        } catch (Exception $e) {
            Log::warning('Gotenberg health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get Gotenberg service status with details
     *
     * @return array
     */
    public function getStatus(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            
            if ($response->successful()) {
                return [
                    'available' => true,
                    'health' => $response->json(),
                ];
            }

            return [
                'available' => false,
                'error' => "HTTP {$response->status()}",
            ];
        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
