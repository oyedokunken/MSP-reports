<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\LicenseConsumptionExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ITGlueReportController extends Controller
{
    /**
     * Download or Email the License Consumption Report
     */
    public function downloadLicenseReport(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $emailTo = $request->input('email_to');
        
        $filename = 'license-consumption-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx';

        // If email recipient provided, send via email
        if ($emailTo) {
            try {
                // Get the authenticated user's email as sender
                $user = auth()->user();
                $senderEmail = $user->email;
                $senderName = $user->name;
                
                // Generate the Excel file in memory
                $export = new LicenseConsumptionExport($month, $year);
                
                Mail::send([], [], function ($message) use ($emailTo, $senderEmail, $senderName, $filename, $export, $month, $year) {
                    $message->to($emailTo)
                        ->replyTo($senderEmail, $senderName) // Reply to the logged-in user
                        ->subject('M365 License Consumption Report - ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)))
                        ->attachData(
                            Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX),
                            $filename,
                            ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                        )
                        ->html(
                            '<h2>M365 License Consumption Report</h2>' .
                            '<p>Please find attached the M365 License Consumption Report for ' . 
                            date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '.</p>' .
                            '<p>This report contains license consumption data for all customers in your ITGlue integration.</p>' .
                            '<br><p>Best regards,<br>' . htmlspecialchars($senderName) . '</p>'
                        );
                });

                Log::info('License consumption report emailed', [
                    'to' => $emailTo,
                    'from' => $senderEmail,
                    'sender_name' => $senderName,
                    'month' => $month,
                    'year' => $year,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Report sent successfully to ' . $emailTo,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to email license consumption report', [
                    'error' => $e->getMessage(),
                    'to' => $emailTo,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send email: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Otherwise, download the file
        return Excel::download(new LicenseConsumptionExport($month, $year), $filename);
    }
}
