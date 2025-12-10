<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationData;
use App\Models\Scopes\CompanyScope;
use Illuminate\Http\Request;
use Gotenberg\Gotenberg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class LibreNMSReportController extends Controller
{
    /**
     * Preview the LibreNMS Device Report (HTML)
     * This is called by Gotenberg to generate the PDF
     */
    public function previewDeviceReport(Request $request)
    {
        $groupId = $request->query('group_id');

        // Fetch data (same logic as Export)
        $query = IntegrationData::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'integrationCustomer'])
            ->where('data_type', 'device_status');

        if ($groupId) {
            $query->where('integration_customer_id', $groupId);
        }

        $rawData = $query->orderBy('data_timestamp', 'desc')->get();

        $devices = $rawData->unique(function($item) {
            $payload = $item->data_payload;
            $deviceId = $payload['device_uid'] ?? $payload['hostname'] ?? $item->integration_data_id;
            return $item->company_id . '-' . $deviceId;
        })->map(function($item) {
            $payload = $item->data_payload;
            $customerName = $payload['site_name'] ?? ($item->integrationCustomer ? $item->integrationCustomer->name : 'Unknown Customer');

            return (object)[
                'company_name' => $customerName,
                'device_name' => $payload['hostname'] ?? 'Unknown Device',
                'ip_address' => $payload['ip_address'] ?? $payload['external_ip'] ?? 'N/A',
                'device_type' => $payload['device_type'] ?? 'Unknown',
                'operating_system' => $payload['operating_system'] ?? 'Unknown',
                'status' => ($payload['online'] ?? false) ? 'Up' : 'Down',
                'last_seen' => isset($payload['last_seen']) ? Carbon::parse($payload['last_seen']) : $item->updated_at,
            ];
        })->sortBy(function ($device) {
            return [$device->company_name, $device->device_name];
        })->groupBy('company_name');

        return view('Reports.LibreNmsReport.librenms-devices', ['groupedDevices' => $devices]);
    }

    /**
     * Download the LibreNMS Device Report (PDF)
     */
    public function downloadDeviceReport(Request $request)
    {
        try {
            $groupId = $request->query('group_id');
            $emailTo = $request->query('email_to');

            // URL that Gotenberg will visit to generate the PDF
            // We use the internal container name 'reporting-app' and port 80 (nginx) or 9000 (php-fpm)? 
            // Nginx is on port 80 in the docker network.
            $previewUrl = 'http://reporting-nginx/api/reports/librenms/devices/preview';
            
            if ($groupId) {
                $previewUrl .= '?group_id=' . $groupId;
            }

            // Gotenberg service URL
            $gotenbergUrl = 'http://reporting-gotenberg:3000';

            $filename = 'librenms_devices_' . ($groupId ? 'group_' . $groupId . '_' : '') . date('Y-m-d_H-i-s') . '.pdf';

            $request = Gotenberg::chromium($gotenbergUrl)
                ->pdf()
                ->margins(0.5, 0.5, 0.5, 0.5)
                ->paperSize(8.27, 11.7) // A4
                ->printBackground()
                ->url($previewUrl);

            $response = Gotenberg::send($request);
            $pdfContent = $response->getBody()->getContents();

            // If email recipient provided, send via email
            if ($emailTo) {
                try {
                    // Get the authenticated user's email as sender
                    $user = auth()->user();
                    $senderEmail = $user ? $user->email : config('mail.from.address');
                    $senderName = $user ? $user->name : config('mail.from.name');
                    
                    Mail::send([], [], function ($message) use ($emailTo, $senderEmail, $senderName, $filename, $pdfContent) {
                        $message->to($emailTo)
                            ->replyTo($senderEmail, $senderName)
                            ->subject('LibreNMS Device Report - ' . date('F Y'))
                            ->attachData(
                                $pdfContent,
                                $filename,
                                ['mime' => 'application/pdf']
                            )
                            ->html(
                                '<h2>LibreNMS Device Report</h2>' .
                                '<p>Please find attached the LibreNMS Device Report.</p>' .
                                '<br><p>Best regards,<br>' . htmlspecialchars($senderName) . '</p>'
                            );
                    });

                    Log::info('LibreNMS device report emailed', [
                        'to' => $emailTo,
                        'from' => $senderEmail,
                    ]);

                    // Continue to download...
                } catch (\Exception $e) {
                    Log::error('Failed to email LibreNMS report', [
                        'error' => $e->getMessage(),
                        'to' => $emailTo,
                    ]);
                    // Continue to download even if email fails
                }
            }

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('PDF Generation Failed: ' . $e->getMessage());
            return response()->json(['error' => 'PDF Generation Failed: ' . $e->getMessage()], 500);
        }
    }
}
