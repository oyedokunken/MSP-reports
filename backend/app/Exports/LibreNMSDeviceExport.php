<?php

declare(strict_types=1);

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

/**
 * @implements WithMapping<object>
 */
final class LibreNMSDeviceExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles, WithColumnWidths, WithCustomStartCell
{
    protected string $primaryColor = '215982'; // Primary blue
    protected string $accentColor1 = 'A42152'; // Accent red (Down)
    protected string $accentColor2 = '52A421'; // Accent green (Up)
    protected string $lightGrayColor = 'F5F5F5'; // Light background

    /**
     * @return Collection<int, mixed>
     */
    public function collection()
    {
        // Fetch all device status records from IntegrationData
        // Bypass CompanyScope to see all companies
        $rawData = IntegrationData::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'integrationCustomer'])
            ->where('data_type', 'device_status')
            ->orderBy('data_timestamp', 'desc')
            ->get();

        // Filter for unique devices (latest version per device)
        return $rawData->unique(function($item) {
            // Unique per company and device UID or hostname
            $payload = $item->data_payload;
            $deviceId = $payload['device_uid'] ?? $payload['hostname'] ?? $item->integration_data_id;
            return $item->company_id . '-' . $deviceId;
        })->map(function($item) {
            $payload = $item->data_payload;

            // Determine End Customer Name
            // For LibreNMS, site_name often contains the customer name
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
        });
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Company',
            'Device Name',
            'IP Address',
            'Type',
            'OS',
            'Status',
            'Last Seen'
        ];
    }

    /**
     * @param mixed $device
     * @return array<int, string|int>
     */
    public function map($device): array
    {
        return [
            $device->company_name,
            $device->device_name,
            $device->ip_address,
            $device->device_type,
            $device->operating_system,
            $device->status,
            $device->last_seen instanceof Carbon ? $device->last_seen->format('Y-m-d H:i:s') : $device->last_seen
        ];
    }

    public function title(): string
    {
        return 'LibreNMS Devices - ' . date('F Y');
    }

    public function startCell(): string
    {
        return 'B2';
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'B' => 30, // Company
            'C' => 25, // Device Name
            'D' => 15, // IP
            'E' => 15, // Type
            'F' => 30, // OS
            'G' => 10, // Status
            'H' => 20, // Last Seen
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet)
    {
        // Add title and date
        $sheet->setCellValue('D1', 'LibreNMS Device Report');
        $sheet->setCellValue('G1', 'Generated: ' . date('Y-m-d'));

        // Style the title
        $sheet->getStyle('D1')->getFont()->setBold(true)->setSize(16)->setColor(new Color($this->primaryColor));
        $sheet->getStyle('G1')->getFont()->setBold(true)->setColor(new Color($this->primaryColor));
        $sheet->getStyle('G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Logo logic (same as ITGlue report)
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
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

        $highestRow = $sheet->getHighestRow();

        // Header Style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->primaryColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ];
        $sheet->getStyle('B2:H2')->applyFromArray($headerStyle);
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->setAutoFilter('B2:H' . $highestRow);

        // Data Style
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('B3:H' . $highestRow)->applyFromArray($dataStyle);

        // Zebra striping and Status formatting
        for ($row = 3; $row <= $highestRow; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('B' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->lightGrayColor);
            }

            $statusCell = 'G' . $row;
            $statusValue = $sheet->getCell($statusCell)->getValue();

            if ($statusValue === 'Down') {
                $sheet->getStyle($statusCell)->getFont()->setColor(new Color($this->accentColor1)); // Red
                $sheet->getStyle($statusCell)->getFont()->setBold(true);
            } elseif ($statusValue === 'Up') {
                $sheet->getStyle($statusCell)->getFont()->setColor(new Color($this->accentColor2)); // Green
            }
            
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Summary
        $summaryRow = $highestRow + 2;
        $sheet->setCellValue('B' . $summaryRow, 'Summary');
        $sheet->getStyle('B' . $summaryRow)->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->primaryColor));

        $sheet->setCellValue('B' . ($summaryRow + 1), 'Total Devices:');
        $sheet->setCellValue('C' . ($summaryRow + 1), '=COUNTA(C3:C' . $highestRow . ')');

        $sheet->setCellValue('B' . ($summaryRow + 2), 'Total Up:');
        $sheet->setCellValue('C' . ($summaryRow + 2), '=COUNTIF(G3:G' . $highestRow . ', "Up")');

        $sheet->setCellValue('B' . ($summaryRow + 3), 'Total Down:');
        $sheet->setCellValue('C' . ($summaryRow + 3), '=COUNTIF(G3:G' . $highestRow . ', "Down")');

        $sheet->getStyle('B' . ($summaryRow + 1) . ':B' . ($summaryRow + 3))->getFont()->setBold(true);
        $sheet->getStyle('C' . ($summaryRow + 1) . ':C' . ($summaryRow + 3))->getFont()->setBold(true);

        return [];
    }
}
