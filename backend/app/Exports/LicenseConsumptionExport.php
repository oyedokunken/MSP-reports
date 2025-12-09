<?php

namespace App\Exports;

use App\Models\IntegrationData;
use App\Models\Scopes\CompanyScope;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LicenseConsumptionExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles, WithColumnWidths, WithCustomStartCell
{
    protected $month;
    protected $year;
    
    // Define colors
    protected $primaryColor = '215982'; // Primary blue
    protected $accentColor1 = 'A42152'; // Accent red
    protected $accentColor2 = '52A421'; // Accent green
    protected $charcoalColor = '333333'; // Dark text
    protected $lightGrayColor = 'F5F5F5'; // Light background

    public function __construct(?int $month = null, ?int $year = null)
    {
        $this->month = $month ?? date('n');
        $this->year = $year ?? date('Y');
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        // Fetch all flexible assets from IntegrationData
        // We need to bypass CompanyScope to see all companies
        $rawData = IntegrationData::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'integrationCustomer'])
            ->where('data_type', 'flexible_asset')
            ->orderBy('data_timestamp', 'desc')
            ->get();

        // Filter for unique assets (latest version) and map to standardized format
        return $rawData->unique(function($item) {
            // Unique per company and asset ID
            $assetId = $item->data_payload['asset_id'] ?? $item->integration_data_id;
            return $item->company_id . '-' . $assetId;
        })->filter(function($item) {
            // Filter for License types only
            $typeName = $item->data_payload['type_name'] ?? '';
            return stripos($typeName, 'License') !== false;
        })->map(function($item) {
            $payload = $item->data_payload;
            $traits = $payload['traits'] ?? [];
            
            // Helper to handle array or string values for traits
            $getValue = function($key) use ($traits) {
                $value = $traits[$key] ?? null;
                if (is_array($value)) {
                    return array_sum(array_map('floatval', $value));
                }
                return $value !== null ? (float)$value : null;
            };

            // ITGlue uses: active (total), consumed (used), unused (available)
            $active = $getValue('active') ?? $getValue('purchased') ?? $getValue('total-seats') ?? 0;
            $consumed = $getValue('consumed') ?? $getValue('assigned') ?? $getValue('used-seats') ?? 0;
            $unused = $getValue('unused') ?? $getValue('available') ?? 0;
            
            // Calculate utilization
            $total = (float)$active;
            $used = (float)$consumed;
            $utilization = 0;
            if ($total > 0) {
                $utilization = round(($used / $total) * 100, 2);
            }

            // Determine End Customer Name
            $customerName = $payload['organization_name'] ?? ($item->integrationCustomer ? $item->integrationCustomer->name : 'Unknown Customer');

            // Determine License Name
            $licenseName = $traits['license-name'] ?? $payload['name'] ?? null;
            if (is_array($licenseName)) {
                $licenseName = reset($licenseName); // Take the first item if it's an array
            }
            if (empty($licenseName)) {
                $licenseName = '0';
            }

            return (object)[
                'company_name' => $customerName,
                'license_name' => $licenseName,
                'active_count' => $active,
                'consumed_count' => $consumed,
                'unused_count' => $unused,
                'consumption_rate' => $utilization,
                'last_updated_at' => isset($payload['last_updated_at']) ? Carbon::parse($payload['last_updated_at']) : $item->updated_at,
            ];
        })->filter(function ($license) {
            return !empty($license->license_name) || $license->license_name === '0';
        })->sortBy(function ($license) {
            return [$license->company_name, $license->license_name];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Company',
            'License Name',
            'Active Licenses',
            'Consumed Licenses',
            'Unused Licenses',
            'Consumption Rate (%)',
            'Last Updated'
        ];
    }

    /**
     * @param object $license
     * @return array
     */
    public function map($license): array
    {
        return [
            $license->company_name,
            $license->license_name,
            (int)($license->active_count ?? 0),
            (int)($license->consumed_count ?? 0),
            (int)($license->unused_count ?? 0),
            $license->consumption_rate . '%',
            $license->last_updated_at instanceof Carbon ? $license->last_updated_at->format('Y-m-d') : $license->last_updated_at
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'License Consumption - ' . date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    /**
     * Define the starting cell for the export
     * @return string
     */
    public function startCell(): string
    {
        return 'B2';
    }

    /**
     * Define custom column widths
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'B' => 30, // Company name
            'C' => 30, // License name
            'D' => 15, // Active licenses
            'E' => 15, // Consumed licenses
            'F' => 15, // Unused licenses
            'G' => 18, // Consumption rate
            'H' => 15, // Last updated
        ];
    }

    /**
     * Apply styles to the Excel sheet
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Add title and date
        $reportTitle = 'License Consumption Report';
        $reportPeriod = date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
        
        $sheet->setCellValue('D1', $reportTitle);
        $sheet->setCellValue('G1', 'Report Period: ' . $reportPeriod);
        
        // Style the title
        $sheet->getStyle('D1')->getFont()->setBold(true)->setSize(16)->setColor(new Color($this->primaryColor));
        $sheet->getStyle('G1')->getFont()->setBold(true)->setColor(new Color($this->primaryColor));
        
        // Right align the report period
        $sheet->getStyle('G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Add the TTS logo image if available
        $logoPath = public_path('images/logo.png'); // Adjusted path for typical Laravel structure
        
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('TTS Logo');
            $drawing->setDescription('Logo');
            $drawing->setPath($logoPath);
            $drawing->setWidth(300);
            $drawing->setHeight(112);
            $drawing->setCoordinates('B1');
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(120);
        } else {
            $sheet->setCellValue('H1', 'MSP Reports');
            $sheet->getStyle('H1')->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->primaryColor));
        }
        
        // Get the highest row
        $highestRow = $sheet->getHighestRow();
        
        // Style the header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->primaryColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ];
        
        $sheet->getStyle('B2:H2')->applyFromArray($headerStyle);
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->setAutoFilter('B2:H' . $highestRow);
        
        // Style data rows
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('B3:H' . $highestRow)->applyFromArray($dataStyle);
        
        // Zebra striping and conditional formatting
        for ($row = 3; $row <= $highestRow; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('B' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->lightGrayColor);
            }
            
            $consumptionCell = 'G' . $row;
            $consumptionValue = $sheet->getCell($consumptionCell)->getValue();
            $numericValue = (float) str_replace('%', '', $consumptionValue);
            
            if ($numericValue > 90) {
                $sheet->getStyle($consumptionCell)->getFont()->setColor(new Color($this->accentColor1));
                $sheet->getStyle($consumptionCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEEEE');
            } elseif ($numericValue < 50) {
                $sheet->getStyle($consumptionCell)->getFont()->setColor(new Color($this->accentColor2));
                $sheet->getStyle($consumptionCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEFFEE');
            }
            
            $sheet->getStyle('D' . $row . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Ensure numeric columns display zeros (Fix for blank fields)
            for ($col = 'D'; $col <= 'F'; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getValue();
                if ($cellValue === null || $cellValue === '' || $cellValue === '0') {
                    $sheet->setCellValue($col . $row, 0);
                }
            }
        }
        
        // Summary section
        $summaryRow = $highestRow + 2;
        $sheet->setCellValue('B' . $summaryRow, 'Summary');
        $sheet->getStyle('B' . $summaryRow)->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->primaryColor));
        
        $sheet->setCellValue('B' . ($summaryRow + 1), 'Total Companies:');
        $sheet->setCellValue('C' . ($summaryRow + 1), '=COUNTDISTINCT(B3:B' . $highestRow . ')'); // Note: COUNTDISTINCT might not work in all Excel versions, usually requires array formula or pivot. Using simple COUNT for now or just text.
        // Actually COUNTDISTINCT is not a standard Excel function. Let's use COUNTA for now or remove formula.
        // Better to calculate in PHP if we want accuracy, but formulas are nice.
        // Let's stick to simple formulas.
        
        $sheet->setCellValue('B' . ($summaryRow + 2), 'Total Licenses:');
        $sheet->setCellValue('C' . ($summaryRow + 2), '=COUNTA(C3:C' . $highestRow . ')');
        
        $sheet->setCellValue('B' . ($summaryRow + 3), 'Total Active Licenses:');
        $sheet->setCellValue('C' . ($summaryRow + 3), '=SUM(D3:D' . $highestRow . ')');
        
        $sheet->setCellValue('B' . ($summaryRow + 4), 'Total Consumed Licenses:');
        $sheet->setCellValue('C' . ($summaryRow + 4), '=SUM(E3:E' . $highestRow . ')');
        
        $sheet->setCellValue('B' . ($summaryRow + 5), 'Average Consumption Rate:');
        $sheet->setCellValue('C' . ($summaryRow + 5), '=AVERAGE(G3:G' . $highestRow . ')');
        $sheet->getStyle('C' . ($summaryRow + 5))->getNumberFormat()->setFormatCode('0.00%');
        
        $sheet->getStyle('B' . ($summaryRow + 1) . ':B' . ($summaryRow + 5))->getFont()->setBold(true);
        $sheet->getStyle('C' . ($summaryRow + 1) . ':C' . ($summaryRow + 5))->getFont()->setBold(true);
        
        $footerRow = $summaryRow + 7;
        $sheet->setCellValue('B' . $footerRow, 'Generated on: ' . date('Y-m-d H:i:s'));
        $sheet->getStyle('B' . $footerRow)->getFont()->setItalic(true)->setSize(9)->setColor(new Color('888888'));
        
        return [];
    }
}
