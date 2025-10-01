<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Employee;
use App\Models\Category;
use Carbon\Carbon;

class DataImportService
{
    /**
     * Get available import templates
     */
    public function getImportTemplates()
    {
        return [
            'products' => [
                'name' => 'Products',
                'description' => 'Import products with inventory data',
                'fields' => ['name', 'sku', 'barcode', 'price', 'cost_price', 'stock_quantity', 'min_stock_level', 'tax_category', 'hs_code', 'unit_of_measure'],
                'required' => ['name', 'price', 'tax_category', 'hs_code', 'unit_of_measure']
            ],
            'customers' => [
                'name' => 'Customers',
                'description' => 'Import customer information',
                'fields' => ['name', 'email', 'phone', 'address', 'city', 'postal_code', 'credit_limit', 'customer_group'],
                'required' => ['name']
            ],
            'suppliers' => [
                'name' => 'Suppliers',
                'description' => 'Import supplier information',
                'fields' => ['name', 'contact_person', 'email', 'phone', 'address', 'city', 'postal_code', 'payment_terms', 'credit_limit'],
                'required' => ['name', 'contact_person']
            ],
            'employees' => [
                'name' => 'Employees',
                'description' => 'Import employee information',
                'fields' => ['employee_id', 'name', 'email', 'phone', 'address', 'position', 'department', 'salary', 'hire_date', 'date_of_birth', 'cnic'],
                'required' => ['employee_id', 'name']
            ]
        ];
    }

    /**
     * Generate import template
     */
    public function generateImportTemplate(string $type)
    {
        $templates = $this->getImportTemplates();
        
        if (!isset($templates[$type])) {
            throw new \InvalidArgumentException('Invalid import type');
        }

        $template = $templates[$type];
        $filename = $type . '_import_template.csv';
        $filepath = storage_path('app/templates/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        // Create CSV file with headers
        $file = fopen($filepath, 'w');
        
        // Add headers
        fputcsv($file, $template['fields']);
        
        // Add sample data
        $sampleData = $this->getSampleData($type);
        fputcsv($file, $sampleData);
        
        fclose($file);

        return [
            'path' => $filepath,
            'filename' => $filename
        ];
    }

    /**
     * Validate import file
     */
    public function validateImportFile(string $type, $file)
    {
        $templates = $this->getImportTemplates();
        $template = $templates[$type];
        
        $data = $this->parseFile($file);
        
        if (empty($data)) {
            throw new \Exception('File is empty or could not be parsed');
        }

        $errors = [];
        $warnings = [];
        
        // Check required fields
        $requiredFields = $template['required'];
        $headers = array_keys($data[0]);
        
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        // Validate each row
        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 because of header and 0-based index
            
            // Check required fields have values
            foreach ($requiredFields as $field) {
                if (in_array($field, $headers) && empty($row[$field])) {
                    $errors[] = "Row {$rowNumber}: Required field '{$field}' is empty";
                }
            }

            // Type-specific validation
            $rowErrors = $this->validateRow($type, $row, $rowNumber);
            $errors = array_merge($errors, $rowErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'row_count' => count($data),
            'headers' => $headers
        ];
    }

    /**
     * Import data
     */
    public function importData(int $tenantId, string $type, $file, bool $updateExisting = false)
    {
        $validation = $this->validateImportFile($type, $file);
        
        if (!$validation['valid']) {
            throw new \Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }

        $data = $this->parseFile($file);
        
        DB::beginTransaction();
        
        try {
            $result = $this->processImportData($tenantId, $type, $data, $updateExisting);
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Parse uploaded file
     */
    private function parseFile($file)
    {
        $extension = $file->getClientOriginalExtension();
        
        if ($extension === 'csv') {
            return $this->parseCsvFile($file);
        } else {
            return $this->parseExcelFile($file);
        }
    }

    /**
     * Parse CSV file
     */
    private function parseCsvFile($file)
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if ($handle === false) {
            throw new \Exception('Could not read CSV file');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new \Exception('Could not read CSV headers');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Parse Excel file
     */
    private function parseExcelFile($file)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $data = [];

        $headers = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // Get headers from first row
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[] = $worksheet->getCell($col . '1')->getValue();
        }

        // Get data from remaining rows
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $colIndex = 0;
            
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $rowData[$headers[$colIndex]] = $worksheet->getCell($col . $row)->getValue();
                $colIndex++;
            }
            
            $data[] = $rowData;
        }

        return $data;
    }

    /**
     * Validate individual row
     */
    private function validateRow(string $type, array $row, int $rowNumber)
    {
        $errors = [];

        switch ($type) {
            case 'products':
                $errors = array_merge($errors, $this->validateProductRow($row, $rowNumber));
                break;
            case 'customers':
                $errors = array_merge($errors, $this->validateCustomerRow($row, $rowNumber));
                break;
            case 'suppliers':
                $errors = array_merge($errors, $this->validateSupplierRow($row, $rowNumber));
                break;
            case 'employees':
                $errors = array_merge($errors, $this->validateEmployeeRow($row, $rowNumber));
                break;
        }

        return $errors;
    }

    /**
     * Validate product row
     */
    private function validateProductRow(array $row, int $rowNumber)
    {
        $errors = [];

        if (isset($row['price']) && (!is_numeric($row['price']) || $row['price'] < 0)) {
            $errors[] = "Row {$rowNumber}: Price must be a positive number";
        }

        if (isset($row['cost_price']) && (!is_numeric($row['cost_price']) || $row['cost_price'] < 0)) {
            $errors[] = "Row {$rowNumber}: Cost price must be a positive number";
        }

        if (isset($row['stock_quantity']) && (!is_numeric($row['stock_quantity']) || $row['stock_quantity'] < 0)) {
            $errors[] = "Row {$rowNumber}: Stock quantity must be a positive number";
        }

        if (isset($row['min_stock_level']) && (!is_numeric($row['min_stock_level']) || $row['min_stock_level'] < 0)) {
            $errors[] = "Row {$rowNumber}: Min stock level must be a positive number";
        }

        if (isset($row['tax_category']) && !in_array($row['tax_category'], ['standard_rate', 'third_schedule', 'reduced_rate', 'exempt', 'steel'])) {
            $errors[] = "Row {$rowNumber}: Invalid tax category";
        }

        if (isset($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$rowNumber}: Invalid email format";
        }

        return $errors;
    }

    /**
     * Validate customer row
     */
    private function validateCustomerRow(array $row, int $rowNumber)
    {
        $errors = [];

        if (isset($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$rowNumber}: Invalid email format";
        }

        if (isset($row['credit_limit']) && (!is_numeric($row['credit_limit']) || $row['credit_limit'] < 0)) {
            $errors[] = "Row {$rowNumber}: Credit limit must be a positive number";
        }

        return $errors;
    }

    /**
     * Validate supplier row
     */
    private function validateSupplierRow(array $row, int $rowNumber)
    {
        $errors = [];

        if (isset($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$rowNumber}: Invalid email format";
        }

        if (isset($row['credit_limit']) && (!is_numeric($row['credit_limit']) || $row['credit_limit'] < 0)) {
            $errors[] = "Row {$rowNumber}: Credit limit must be a positive number";
        }

        return $errors;
    }

    /**
     * Validate employee row
     */
    private function validateEmployeeRow(array $row, int $rowNumber)
    {
        $errors = [];

        if (isset($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$rowNumber}: Invalid email format";
        }

        if (isset($row['salary']) && (!is_numeric($row['salary']) || $row['salary'] < 0)) {
            $errors[] = "Row {$rowNumber}: Salary must be a positive number";
        }

        if (isset($row['hire_date']) && !$this->isValidDate($row['hire_date'])) {
            $errors[] = "Row {$rowNumber}: Invalid hire date format";
        }

        if (isset($row['date_of_birth']) && !$this->isValidDate($row['date_of_birth'])) {
            $errors[] = "Row {$rowNumber}: Invalid date of birth format";
        }

        return $errors;
    }

    /**
     * Process import data
     */
    private function processImportData(int $tenantId, string $type, array $data, bool $updateExisting)
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            try {
                switch ($type) {
                    case 'products':
                        $result = $this->importProduct($tenantId, $row, $updateExisting);
                        break;
                    case 'customers':
                        $result = $this->importCustomer($tenantId, $row, $updateExisting);
                        break;
                    case 'suppliers':
                        $result = $this->importSupplier($tenantId, $row, $updateExisting);
                        break;
                    case 'employees':
                        $result = $this->importEmployee($tenantId, $row, $updateExisting);
                        break;
                    default:
                        throw new \InvalidArgumentException('Invalid import type');
                }

                if ($result['action'] === 'created') {
                    $imported++;
                } else {
                    $updated++;
                }

            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_processed' => count($data)
        ];
    }

    /**
     * Import product
     */
    private function importProduct(int $tenantId, array $row, bool $updateExisting)
    {
        $productData = [
            'tenant_id' => $tenantId,
            'name' => $row['name'],
            'sku' => $row['sku'] ?? null,
            'barcode' => $row['barcode'] ?? null,
            'price' => $row['price'],
            'cost_price' => $row['cost_price'] ?? 0,
            'stock_quantity' => $row['stock_quantity'] ?? 0,
            'min_stock_level' => $row['min_stock_level'] ?? 0,
            'tax_category' => $row['tax_category'],
            'hs_code' => $row['hs_code'],
            'unit_of_measure' => $row['unit_of_measure'],
            'description' => $row['description'] ?? null,
            'is_active' => true
        ];

        $existingProduct = null;
        
        if (isset($row['sku']) && !empty($row['sku'])) {
            $existingProduct = Product::where('tenant_id', $tenantId)
                ->where('sku', $row['sku'])
                ->first();
        } elseif (isset($row['barcode']) && !empty($row['barcode'])) {
            $existingProduct = Product::where('tenant_id', $tenantId)
                ->where('barcode', $row['barcode'])
                ->first();
        }

        if ($existingProduct && $updateExisting) {
            $existingProduct->update($productData);
            return ['action' => 'updated', 'id' => $existingProduct->id];
        } elseif ($existingProduct) {
            throw new \Exception('Product with SKU/Barcode already exists');
        } else {
            $product = Product::create($productData);
            return ['action' => 'created', 'id' => $product->id];
        }
    }

    /**
     * Import customer
     */
    private function importCustomer(int $tenantId, array $row, bool $updateExisting)
    {
        $customerData = [
            'tenant_id' => $tenantId,
            'name' => $row['name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'postal_code' => $row['postal_code'] ?? null,
            'credit_limit' => $row['credit_limit'] ?? 0,
            'customer_group' => $row['customer_group'] ?? 'default',
            'is_active' => true
        ];

        $existingCustomer = Customer::where('tenant_id', $tenantId)
            ->where('email', $row['email'])
            ->first();

        if ($existingCustomer && $updateExisting) {
            $existingCustomer->update($customerData);
            return ['action' => 'updated', 'id' => $existingCustomer->id];
        } elseif ($existingCustomer) {
            throw new \Exception('Customer with email already exists');
        } else {
            $customer = Customer::create($customerData);
            return ['action' => 'created', 'id' => $customer->id];
        }
    }

    /**
     * Import supplier
     */
    private function importSupplier(int $tenantId, array $row, bool $updateExisting)
    {
        $supplierData = [
            'tenant_id' => $tenantId,
            'name' => $row['name'],
            'contact_person' => $row['contact_person'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'postal_code' => $row['postal_code'] ?? null,
            'payment_terms' => $row['payment_terms'] ?? 'net_30',
            'credit_limit' => $row['credit_limit'] ?? 0,
            'is_active' => true
        ];

        $existingSupplier = Supplier::where('tenant_id', $tenantId)
            ->where('email', $row['email'])
            ->first();

        if ($existingSupplier && $updateExisting) {
            $existingSupplier->update($supplierData);
            return ['action' => 'updated', 'id' => $existingSupplier->id];
        } elseif ($existingSupplier) {
            throw new \Exception('Supplier with email already exists');
        } else {
            $supplier = Supplier::create($supplierData);
            return ['action' => 'created', 'id' => $supplier->id];
        }
    }

    /**
     * Import employee
     */
    private function importEmployee(int $tenantId, array $row, bool $updateExisting)
    {
        $employeeData = [
            'tenant_id' => $tenantId,
            'employee_id' => $row['employee_id'],
            'name' => $row['name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'position' => $row['position'] ?? null,
            'department' => $row['department'] ?? null,
            'salary' => $row['salary'] ?? 0,
            'hire_date' => isset($row['hire_date']) ? Carbon::parse($row['hire_date'])->format('Y-m-d') : null,
            'date_of_birth' => isset($row['date_of_birth']) ? Carbon::parse($row['date_of_birth'])->format('Y-m-d') : null,
            'cnic' => $row['cnic'] ?? null,
            'is_active' => true
        ];

        $existingEmployee = Employee::where('tenant_id', $tenantId)
            ->where('employee_id', $row['employee_id'])
            ->first();

        if ($existingEmployee && $updateExisting) {
            $existingEmployee->update($employeeData);
            return ['action' => 'updated', 'id' => $existingEmployee->id];
        } elseif ($existingEmployee) {
            throw new \Exception('Employee with ID already exists');
        } else {
            $employee = Employee::create($employeeData);
            return ['action' => 'created', 'id' => $employee->id];
        }
    }

    /**
     * Get sample data for template
     */
    private function getSampleData(string $type)
    {
        switch ($type) {
            case 'products':
                return ['Sample Product', 'SKU001', '1234567890123', '100.00', '80.00', '50', '10', 'standard_rate', '1234.56.78', 'PCS'];
            case 'customers':
                return ['John Doe', 'john@example.com', '+1234567890', '123 Main St', 'City', '12345', '1000.00', 'premium'];
            case 'suppliers':
                return ['ABC Suppliers', 'Jane Smith', 'jane@supplier.com', '+1234567890', '456 Supplier St', 'City', '54321', 'net_30', '5000.00'];
            case 'employees':
                return ['EMP001', 'John Employee', 'john@company.com', '+1234567890', '789 Employee St', 'Manager', 'Sales', '50000.00', '2024-01-01', '1990-01-01', '12345-1234567-1'];
            default:
                return [];
        }
    }

    /**
     * Check if date is valid
     */
    private function isValidDate($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get recent imports
     */
    public function getRecentImports(int $tenantId)
    {
        return DB::table('import_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
}