<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

class ExportService
{
    /**
     * Export data to PDF
     */
    public function exportToPdf(string $view, array $data, string $filename)
    {
        $html = View::make($view, $data)->render();
        
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download($filename);
    }

    /**
     * Export data to Excel
     */
    public function exportToExcel(array $data, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = array_keys($data[0] ?? []);
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }
        
        // Style headers
        $headerRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
        
        // Add data
        $row = 2;
        foreach ($data as $item) {
            $col = 1;
            foreach ($item as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Add borders to all data
        $dataRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers), count($data) + 1)->getCoordinate();
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
        
        $writer = new Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'export');
        $writer->save($tempFile);
        
        return Response::download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export data to CSV
     */
    public function exportToCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            if (!empty($data)) {
                fputcsv($file, array_keys($data[0]));
            }
            
            // Add data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };
        
        return Response::stream($callback, 200, $headers);
    }

    /**
     * Create ZIP archive of multiple files
     */
    public function createZipArchive(array $files, string $zipFilename)
    {
        $zip = new ZipArchive();
        $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;
        
        if ($zip->open($tempZipPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP file');
        }
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        
        $zip->close();
        
        return Response::download($tempZipPath, $zipFilename)->deleteFileAfterSend(true);
    }

    /**
     * Export sales report to PDF
     */
    public function exportSalesReportToPdf(array $reportData, string $filename)
    {
        $pdf = Pdf::loadView('reports.sales-report-pdf', $reportData);
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download($filename);
    }

    /**
     * Export inventory report to Excel with multiple sheets
     */
    public function exportInventoryReportToExcel(array $reportData, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        
        // Summary sheet
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $this->addSummaryToSheet($summarySheet, $reportData);
        
        // Details sheet
        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Details');
        $this->addDataToSheet($detailsSheet, $reportData['data'] ?? []);
        
        // Charts sheet (if applicable)
        if (isset($reportData['charts'])) {
            $chartsSheet = $spreadsheet->createSheet();
            $chartsSheet->setTitle('Charts');
            $this->addChartsToSheet($chartsSheet, $reportData['charts']);
        }
        
        $writer = new Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inventory_export');
        $writer->save($tempFile);
        
        return Response::download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export financial report to PDF with charts
     */
    public function exportFinancialReportToPdf(array $reportData, string $filename)
    {
        // Generate chart images
        $chartImages = $this->generateChartImages($reportData);
        $reportData['chart_images'] = $chartImages;
        
        $pdf = Pdf::loadView('reports.financial-report-pdf', $reportData);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download($filename);
    }

    /**
     * Export employee performance report to Excel
     */
    public function exportEmployeePerformanceToExcel(array $reportData, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employee Performance');
        
        // Add performance data
        $this->addEmployeePerformanceToSheet($sheet, $reportData);
        
        // Add charts
        $this->addPerformanceChartsToSheet($sheet, $reportData);
        
        $writer = new Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'employee_performance');
        $writer->save($tempFile);
        
        return Response::download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export tax report to PDF
     */
    public function exportTaxReportToPdf(array $reportData, string $filename)
    {
        $pdf = Pdf::loadView('reports.tax-report-pdf', $reportData);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download($filename);
    }

    /**
     * Bulk export multiple reports
     */
    public function bulkExportReports(array $reports, string $format, string $zipFilename)
    {
        $files = [];
        
        foreach ($reports as $report) {
            $filename = $report['type'] . '-report.' . $format;
            
            switch ($format) {
                case 'pdf':
                    $file = $this->exportToPdf('reports.' . $report['type'] . '-pdf', $report['data'], $filename);
                    break;
                case 'excel':
                    $file = $this->exportToExcel($report['data'], $filename);
                    break;
                case 'csv':
                    $file = $this->exportToCsv($report['data'], $filename);
                    break;
                default:
                    continue 2;
            }
            
            $files[] = $file;
        }
        
        return $this->createZipArchive($files, $zipFilename);
    }

    /**
     * Helper methods
     */
    private function addSummaryToSheet($sheet, array $data)
    {
        $row = 1;
        
        // Add title
        $sheet->setCellValue('A1', 'Report Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:D1');
        
        $row = 3;
        
        // Add summary data
        if (isset($data['summary'])) {
            foreach ($data['summary'] as $key => $value) {
                $sheet->setCellValue('A' . $row, ucwords(str_replace('_', ' ', $key)));
                $sheet->setCellValue('B' . $row, $value);
                $row++;
            }
        }
        
        // Style the summary
        $summaryRange = 'A3:B' . ($row - 1);
        $sheet->getStyle($summaryRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
    }

    private function addDataToSheet($sheet, array $data)
    {
        if (empty($data)) return;
        
        $headers = array_keys($data[0]);
        $col = 1;
        
        // Add headers
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }
        
        // Style headers
        $headerRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ]);
        
        // Add data
        $row = 2;
        foreach ($data as $item) {
            $col = 1;
            foreach ($item as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function addChartsToSheet($sheet, array $charts)
    {
        // This would add actual charts to the Excel file
        // Implementation would depend on the charting library used
        $sheet->setCellValue('A1', 'Charts will be implemented here');
    }

    private function generateChartImages(array $reportData)
    {
        // This would generate chart images for PDF export
        // Implementation would depend on the charting library used
        return [];
    }

    private function addEmployeePerformanceToSheet($sheet, array $data)
    {
        $row = 1;
        
        // Add title
        $sheet->setCellValue('A1', 'Employee Performance Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:F1');
        
        $row = 3;
        
        // Add headers
        $headers = ['Employee Name', 'Position', 'Department', 'Total Sales', 'Total Revenue', 'Average Sale'];
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }
        
        // Style headers
        $headerRange = 'A3:F3';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ]);
        
        // Add data
        $row = 4;
        foreach ($data['data'] as $employee) {
            $sheet->setCellValue('A' . $row, $employee->employee_name);
            $sheet->setCellValue('B' . $row, $employee->position);
            $sheet->setCellValue('C' . $row, $employee->department);
            $sheet->setCellValue('D' . $row, $employee->total_sales);
            $sheet->setCellValue('E' . $row, $employee->total_revenue);
            $sheet->setCellValue('F' . $row, $employee->average_sale);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function addPerformanceChartsToSheet($sheet, array $data)
    {
        // This would add performance charts to the Excel file
        // Implementation would depend on the charting library used
        $sheet->setCellValue('A' . ($sheet->getHighestRow() + 2), 'Performance Charts will be implemented here');
    }
}