<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\LicenseConsumptionExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class ITGlueReportController extends Controller
{
    /**
     * Download the License Consumption Report
     */
    public function downloadLicenseReport(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $filename = 'license-consumption-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx';

        return Excel::download(new LicenseConsumptionExport($month, $year), $filename);
    }
}
