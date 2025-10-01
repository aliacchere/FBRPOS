<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class DataExportService
{
    /**
     * Export data to specified format
     */
    public function exportData(int $tenantId, string $type, string $format, array $filters = [])
    {
        $data = $this->getDataForExport($tenantId, $type, $filters);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data, $type);
            case 'excel':
                return $this->exportToExcel($data, $type);
            case 'json':
                return $this->exportToJson($data, $type);
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }
    }

    /**
     * Bulk export all data
     */
    public function bulkExportAll(int $tenantId, string $format, bool $includeFiles = false)
    {
        $exportTypes = [
            'products' => 'Products',
            'customers' => 'Customers',
            'suppliers' => 'Suppliers',
            'employees' => 'Employees',
            'sales' => 'Sales',
            'inventory' => 'Inventory',
            'payroll' => 'Payroll'
        ];
        
        $files = [];
        
        foreach ($exportTypes as $type => $name) {
            try {
                $data = $this->getDataForExport($tenantId, $type);
                $file = $this->exportToFormat($data, $type, $format);
                $files[] = $file;
            } catch (\Exception $e) {
                // Log error but continue with other exports
                \Log::error("Failed to export {$type}: " . $e->getMessage());
            }
        }
        
        if ($includeFiles) {
            $files = array_merge($files, $this->exportUploadedFiles($tenantId));
        }
        
        if ($format === 'zip') {
            return $this->createZipArchive($files, 'bulk-export-' . now()->format('Y-m-d_H-i-s') . '.zip');
        }
        
        return $files;
    }

    /**
     * Get data for export
     */
    private function getDataForExport(int $tenantId, string $type, array $filters = [])
    {
        switch ($type) {
            case 'products':
                return $this->getProductsData($tenantId, $filters);
            case 'customers':
                return $this->getCustomersData($tenantId, $filters);
            case 'suppliers':
                return $this->getSuppliersData($tenantId, $filters);
            case 'employees':
                return $this->getEmployeesData($tenantId, $filters);
            case 'sales':
                return $this->getSalesData($tenantId, $filters);
            case 'inventory':
                return $this->getInventoryData($tenantId, $filters);
            case 'payroll':
                return $this->getPayrollData($tenantId, $filters);
            default:
                throw new \InvalidArgumentException('Unsupported export type');
        }
    }

    /**
     * Get products data
     */
    private function getProductsData(int $tenantId, array $filters)
    {
        $query = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.tenant_id', $tenantId)
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.price',
                'products.cost_price',
                'products.stock_quantity',
                'products.min_stock_level',
                'products.max_stock_level',
                'products.reorder_level',
                'products.tax_category',
                'products.hs_code',
                'products.unit_of_measure',
                'products.description',
                'products.is_active',
                'categories.name as category_name',
                'products.created_at',
                'products.updated_at'
            ]);
        
        if (isset($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }
        
        if (isset($filters['is_active'])) {
            $query->where('products.is_active', $filters['is_active']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('products.created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('products.created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get customers data
     */
    private function getCustomersData(int $tenantId, array $filters)
    {
        $query = DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'address',
                'city',
                'postal_code',
                'credit_limit',
                'customer_group',
                'is_active',
                'created_at',
                'updated_at'
            ]);
        
        if (isset($filters['customer_group'])) {
            $query->where('customer_group', $filters['customer_group']);
        }
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get suppliers data
     */
    private function getSuppliersData(int $tenantId, array $filters)
    {
        $query = DB::table('suppliers')
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'name',
                'contact_person',
                'email',
                'phone',
                'address',
                'city',
                'postal_code',
                'payment_terms',
                'credit_limit',
                'is_active',
                'created_at',
                'updated_at'
            ]);
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get employees data
     */
    private function getEmployeesData(int $tenantId, array $filters)
    {
        $query = DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'employee_id',
                'name',
                'email',
                'phone',
                'address',
                'position',
                'department',
                'salary',
                'hire_date',
                'date_of_birth',
                'cnic',
                'bank_account',
                'bank_name',
                'emergency_contact',
                'emergency_phone',
                'is_active',
                'created_at',
                'updated_at'
            ]);
        
        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get sales data
     */
    private function getSalesData(int $tenantId, array $filters)
    {
        $query = DB::table('sales')
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.tenant_id', $tenantId)
            ->select([
                'sales.id',
                'sales.invoice_number',
                'sales.sale_date',
                'sales.customer_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone',
                'sales.subtotal',
                'sales.tax_amount',
                'sales.discount_amount',
                'sales.total_amount',
                'sales.payment_method',
                'sales.payment_status',
                'sales.fbr_invoice_number',
                'sales.fbr_invoice_date',
                'users.name as cashier_name',
                'sales.notes',
                'sales.created_at',
                'sales.updated_at'
            ]);
        
        if (isset($filters['start_date'])) {
            $query->where('sales.sale_date', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('sales.sale_date', '<=', $filters['end_date']);
        }
        
        if (isset($filters['payment_status'])) {
            $query->where('sales.payment_status', $filters['payment_status']);
        }
        
        if (isset($filters['payment_method'])) {
            $query->where('sales.payment_method', $filters['payment_method']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get inventory data
     */
    private function getInventoryData(int $tenantId, array $filters)
    {
        $query = DB::table('stock_movements')
            ->leftJoin('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('users', 'stock_movements.user_id', '=', 'users.id')
            ->where('stock_movements.tenant_id', $tenantId)
            ->select([
                'stock_movements.id',
                'products.name as product_name',
                'products.sku',
                'stock_movements.movement_type',
                'stock_movements.quantity',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.notes',
                'users.name as user_name',
                'stock_movements.created_at'
            ]);
        
        if (isset($filters['movement_type'])) {
            $query->where('stock_movements.movement_type', $filters['movement_type']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('stock_movements.created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('stock_movements.created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Get payroll data
     */
    private function getPayrollData(int $tenantId, array $filters)
    {
        $query = DB::table('payrolls')
            ->leftJoin('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->where('payrolls.tenant_id', $tenantId)
            ->select([
                'payrolls.id',
                'employees.name as employee_name',
                'employees.employee_id',
                'payrolls.pay_period',
                'payrolls.basic_salary',
                'payrolls.allowances',
                'payrolls.overtime_hours',
                'payrolls.overtime_rate',
                'payrolls.overtime_pay',
                'payrolls.gross_salary',
                'payrolls.tax_deduction',
                'payrolls.other_deductions',
                'payrolls.net_salary',
                'payrolls.status',
                'payrolls.created_at',
                'payrolls.updated_at'
            ]);
        
        if (isset($filters['pay_period'])) {
            $query->where('payrolls.pay_period', 'like', $filters['pay_period'] . '%');
        }
        
        if (isset($filters['status'])) {
            $query->where('payrolls.status', $filters['status']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('payrolls.created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('payrolls.created_at', '<=', $filters['end_date']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(array $data, string $type)
    {
        if (empty($data)) {
            throw new \Exception('No data to export');
        }
        
        $filename = $type . '-export-' . now()->format('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // Add headers
        fputcsv($file, array_keys($data[0]));
        
        // Add data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'mime_type' => 'text/csv'
        ];
    }

    /**
     * Export to Excel
     */
    private function exportToExcel(array $data, string $type)
    {
        if (empty($data)) {
            throw new \Exception('No data to export');
        }
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($type));
        
        // Add headers
        $headers = array_keys($data[0]);
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
        
        $filename = $type . '-export-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filepath = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }

    /**
     * Export to JSON
     */
    private function exportToJson(array $data, string $type)
    {
        $filename = $type . '-export-' . now()->format('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $exportData = [
            'type' => $type,
            'exported_at' => now()->toISOString(),
            'total_records' => count($data),
            'data' => $data
        ];
        
        file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT));
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'mime_type' => 'application/json'
        ];
    }

    /**
     * Export to specified format
     */
    private function exportToFormat(array $data, string $type, string $format)
    {
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data, $type);
            case 'excel':
                return $this->exportToExcel($data, $type);
            case 'json':
                return $this->exportToJson($data, $type);
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }
    }

    /**
     * Export uploaded files
     */
    private function exportUploadedFiles(int $tenantId)
    {
        $files = [];
        $uploadPaths = [
            'public/uploads',
            'storage/app/public',
            'storage/app/tenant_files'
        ];
        
        foreach ($uploadPaths as $path) {
            $fullPath = base_path($path);
            if (file_exists($fullPath)) {
                $files = array_merge($files, $this->getFilesFromDirectory($fullPath));
            }
        }
        
        return $files;
    }

    /**
     * Get files from directory
     */
    private function getFilesFromDirectory(string $directory)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize()
                ];
            }
        }
        
        return $files;
    }

    /**
     * Create ZIP archive
     */
    private function createZipArchive(array $files, string $zipFilename)
    {
        $zip = new \ZipArchive();
        $zipPath = storage_path('app/exports/' . $zipFilename);
        
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }
        
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP file');
        }
        
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                $zip->addFile($file['path'], $file['filename']);
            }
        }
        
        $zip->close();
        
        return [
            'path' => $zipPath,
            'filename' => $zipFilename,
            'mime_type' => 'application/zip'
        ];
    }

    /**
     * Get export options
     */
    public function getExportOptions()
    {
        return [
            'formats' => ['csv', 'excel', 'json'],
            'types' => [
                'products' => 'Products',
                'customers' => 'Customers',
                'suppliers' => 'Suppliers',
                'employees' => 'Employees',
                'sales' => 'Sales',
                'inventory' => 'Inventory',
                'payroll' => 'Payroll'
            ],
            'filters' => [
                'date_range' => 'Date Range',
                'status' => 'Status',
                'category' => 'Category',
                'department' => 'Department'
            ]
        ];
    }

    /**
     * Get recent exports
     */
    public function getRecentExports(int $tenantId)
    {
        return DB::table('export_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Log export
     */
    public function logExport(int $tenantId, string $type, string $format, int $recordCount)
    {
        DB::table('export_logs')->insert([
            'tenant_id' => $tenantId,
            'export_type' => $type,
            'export_format' => $format,
            'record_count' => $recordCount,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Clean up old exports
     */
    public function cleanupOldExports(int $days = 30)
    {
        $cutoffDate = now()->subDays($days);
        
        // Get old export files
        $oldExports = DB::table('export_logs')
            ->where('created_at', '<', $cutoffDate)
            ->get();
        
        $deletedCount = 0;
        
        foreach ($oldExports as $export) {
            $filename = $export->export_type . '-export-' . $export->created_at->format('Y-m-d_H-i-s') . '.' . $export->export_format;
            $filepath = storage_path('app/exports/' . $filename);
            
            if (file_exists($filepath)) {
                unlink($filepath);
                $deletedCount++;
            }
        }
        
        // Delete old export logs
        DB::table('export_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        return [
            'deleted_files' => $deletedCount,
            'deleted_logs' => $oldExports->count()
        ];
    }
}