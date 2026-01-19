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

            // Build the multipart form data properly
            $multipart = [];

            // Add main HTML file
            $multipart[] = [
                'name' => 'files',
                'contents' => $html,
                'filename' => 'index.html',
            ];

            // Add header HTML if provided
            if (isset($options['header_html']) && !empty($options['header_html'])) {
                $multipart[] = [
                    'name' => 'files',
                    'contents' => $options['header_html'],
                    'filename' => 'header.html',
                ];
            }

            // Add footer HTML if provided
            if (isset($options['footer_html']) && !empty($options['footer_html'])) {
                $multipart[] = [
                    'name' => 'files',
                    'contents' => $options['footer_html'],
                    'filename' => 'footer.html',
                ];
            }

            // Add all form parameters
            $formData = $this->buildFormData($options);
            foreach ($formData as $key => $value) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                ];
            }

            // Make request to Gotenberg
            $response = Http::timeout($this->timeout)
                ->asMultipart()
                ->post($endpoint, $multipart);

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
     * Merge multiple PDF binaries into a single PDF using Gotenberg
     *
     * @param array $pdfBinaries Array of binary PDF strings
     * @return string Merged binary PDF content
     * @throws Exception
     */
    public function mergePdfs(array $pdfBinaries): string
    {
        if (!$this->enabled) {
            throw new Exception('Gotenberg service is disabled');
        }

        try {
            $endpoint = "{$this->baseUrl}/forms/pdfengines/merge";

            $request = Http::timeout($this->timeout)->asMultipart();

            foreach ($pdfBinaries as $index => $binary) {
                $request->attach('files', $binary, "pdf_{$index}.pdf");
            }

            $response = $request->post($endpoint);

            if (!$response->successful()) {
                throw new Exception(
                    "Gotenberg Merge API error: {$response->status()} - {$response->body()}"
                );
            }

            return $response->body();
        } catch (Exception $e) {
            Log::error('Gotenberg PDF merge failed', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to merge PDFs: {$e->getMessage()}");
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

        // Allow loading external resources (images, fonts, etc.)
        $formData['skipNetworkIdleEvent'] = true;
        $formData['failOnHttpStatusCodes'] = '[499,599]';

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
