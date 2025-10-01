<?php
/**
 * DPS POS FBR Integrated - Core Functions
 */

// Security Functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role($required_roles) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    
    if (!in_array($_SESSION['user_role'], (array)$required_roles)) {
        header('Location: unauthorized.php');
        exit;
    }
}

// User Management Functions
function get_current_user() {
    if (!is_logged_in()) {
        return false;
    }
    
    return db_fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function get_current_tenant() {
    if (!isset($_SESSION['tenant_id'])) {
        return false;
    }
    
    return db_fetch("SELECT * FROM tenants WHERE id = ?", [$_SESSION['tenant_id']]);
}

// FBR Integration Functions
function get_fbr_config($tenant_id) {
    return db_fetch("SELECT * FROM fbr_config WHERE tenant_id = ?", [$tenant_id]);
}

function is_fbr_sandbox_mode($tenant_id) {
    $config = get_fbr_config($tenant_id);
    return $config ? (bool)$config['sandbox_mode'] : true;
}

function get_fbr_bearer_token($tenant_id) {
    $config = get_fbr_config($tenant_id);
    return $config ? $config['bearer_token'] : '';
}

function make_fbr_api_call($endpoint, $data, $tenant_id) {
    $config = get_fbr_config($tenant_id);
    if (!$config || empty($config['bearer_token'])) {
        return ['success' => false, 'error' => 'FBR configuration not found'];
    }
    
    $base_url = is_fbr_sandbox_mode($tenant_id) ? FBR_SANDBOX_BASE_URL : FBR_PRODUCTION_BASE_URL;
    $url = $base_url . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['bearer_token']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'CURL Error: ' . $error];
    }
    
    $decoded_response = json_decode($response, true);
    
    return [
        'success' => $http_code == 200,
        'http_code' => $http_code,
        'data' => $decoded_response,
        'raw_response' => $response
    ];
}

function translate_fbr_error($error_code) {
    $error_messages = [
        '0001' => 'FBR Error: Your business is not registered for sales tax. Please check your NTN in the settings.',
        '0002' => 'FBR Error: The customer\'s NTN or CNIC is invalid. Please use a valid 13-digit CNIC or 7/9-digit NTN.',
        '0021' => 'FBR Error: The \'Value of Sales\' for an item is missing. Please ensure the product has a valid price.',
        '0052' => 'FBR Error: The HS Code for a product is incorrect. Please update it in the product settings.',
        '0053' => 'FBR Error: Invalid invoice date format. Please use YYYY-MM-DD format.',
        '0054' => 'FBR Error: Invalid quantity value. Please enter a valid numeric quantity.',
        '0055' => 'FBR Error: Invalid tax rate. Please check the tax rate configuration.',
        '0056' => 'FBR Error: Missing required field. Please check all required fields are filled.',
        '0057' => 'FBR Error: Invalid province code. Please select a valid province.',
        '0058' => 'FBR Error: Invalid unit of measure. Please select a valid UOM.',
        '0059' => 'FBR Error: Invalid scenario ID. Please check the sale type configuration.',
        '0060' => 'FBR Error: Duplicate invoice reference number. Please use a unique reference number.'
    ];
    
    return isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'FBR Error: ' . $error_code;
}

// Product Tax Category Functions
function get_tax_categories() {
    return [
        'standard_rate' => [
            'name' => 'Standard Rate Goods (18%)',
            'rate' => 18,
            'sale_type' => 'Goods at standard rate (default)',
            'scenario_id' => 'SN001'
        ],
        'third_schedule' => [
            'name' => 'Third Schedule Item (Tax on Retail Price)',
            'rate' => 0,
            'sale_type' => 'Third Schedule (MRP)',
            'scenario_id' => 'SN008'
        ],
        'reduced_rate' => [
            'name' => 'Reduced Rate Goods (5%)',
            'rate' => 5,
            'sale_type' => 'Goods at reduced rate',
            'scenario_id' => 'SN002'
        ],
        'exempt' => [
            'name' => 'Tax-Exempt Item',
            'rate' => 0,
            'sale_type' => 'Exempt',
            'scenario_id' => 'SN006'
        ],
        'steel' => [
            'name' => 'Steel Items',
            'rate' => 18,
            'sale_type' => 'Steel',
            'scenario_id' => 'SN010'
        ]
    ];
}

function build_fbr_invoice_data($sale_data, $tenant_id) {
    $tenant = get_current_tenant();
    $fbr_config = get_fbr_config($tenant_id);
    
    if (!$tenant || !$fbr_config) {
        return false;
    }
    
    $invoice_data = [
        'invoiceType' => 'Sale Invoice',
        'invoiceDate' => date('Y-m-d'),
        'sellerNTNCNIC' => $tenant['ntn'],
        'sellerBusinessName' => $tenant['business_name'],
        'sellerProvince' => $tenant['province'],
        'sellerAddress' => $tenant['address'],
        'buyerNTNCNIC' => $sale_data['customer_ntn'] ?? '0000000000000',
        'buyerBusinessName' => $sale_data['customer_name'] ?? 'Walk-in Customer',
        'buyerProvince' => $sale_data['customer_province'] ?? $tenant['province'],
        'buyerAddress' => $sale_data['customer_address'] ?? 'N/A',
        'buyerRegistrationType' => 'Unregistered',
        'invoiceRefNo' => $sale_data['reference_number'] ?? '',
        'scenarioId' => 'SN001',
        'items' => []
    ];
    
    // Process items
    foreach ($sale_data['items'] as $item) {
        $tax_category = get_tax_categories()[$item['tax_category']];
        
        $fbr_item = [
            'hsCode' => $item['hs_code'],
            'productDescription' => $item['name'],
            'rate' => $tax_category['rate'] . '%',
            'uoM' => $item['unit_of_measure'],
            'quantity' => (float)$item['quantity'],
            'totalValues' => 0.00,
            'valueSalesExcludingST' => 0.00,
            'fixedNotifiedValueOrRetailPrice' => 0.00,
            'salesTaxApplicable' => 0.00,
            'salesTaxWithheldAtSource' => 0.00,
            'extraTax' => 0.00,
            'furtherTax' => 0.00,
            'sroScheduleNo' => '',
            'fedPayable' => 0.00,
            'discount' => 0.00,
            'saleType' => $tax_category['sale_type'],
            'sroItemSerialNo' => ''
        ];
        
        // Calculate values based on tax category
        if ($item['tax_category'] === 'third_schedule') {
            // Third Schedule: valueSalesExcludingST = 0, retail price in fixedNotifiedValueOrRetailPrice
            $fbr_item['valueSalesExcludingST'] = 0.00;
            $fbr_item['fixedNotifiedValueOrRetailPrice'] = (float)$item['price'];
            $fbr_item['salesTaxApplicable'] = (float)$item['price'] * ($tax_category['rate'] / 100);
        } else {
            // Standard calculation
            $fbr_item['valueSalesExcludingST'] = (float)$item['price'];
            $fbr_item['fixedNotifiedValueOrRetailPrice'] = 0.00;
            $fbr_item['salesTaxApplicable'] = (float)$item['price'] * ($tax_category['rate'] / 100);
        }
        
        $fbr_item['totalValues'] = $fbr_item['valueSalesExcludingST'] + $fbr_item['salesTaxApplicable'];
        
        $invoice_data['items'][] = $fbr_item;
    }
    
    return $invoice_data;
}

// Utility Functions
function format_currency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

function format_date($date, $format = DISPLAY_DATE_FORMAT) {
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = DISPLAY_DATETIME_FORMAT) {
    return date($format, strtotime($datetime));
}

function generate_invoice_number($tenant_id) {
    $prefix = 'INV';
    $year = date('Y');
    $month = date('m');
    
    // Get next sequence number for this month
    $last_invoice = db_fetch(
        "SELECT invoice_number FROM sales 
         WHERE tenant_id = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ? 
         ORDER BY id DESC LIMIT 1",
        [$tenant_id, $year, $month]
    );
    
    if ($last_invoice) {
        $last_number = (int)substr($last_invoice['invoice_number'], -4);
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
    
    return $prefix . $year . $month . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}

function generate_qr_code($data, $size = QR_CODE_SIZE) {
    // This would integrate with a QR code library
    // For now, return a placeholder
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
}

// WhatsApp Integration Functions
function send_whatsapp_receipt($phone_number, $invoice_data) {
    $message = "Thank you for your purchase!\n\n";
    $message .= "Invoice: " . $invoice_data['invoice_number'] . "\n";
    $message .= "Date: " . format_date($invoice_data['date']) . "\n";
    $message .= "Total: " . format_currency($invoice_data['total']) . "\n\n";
    $message .= "View receipt: " . APP_URL . "/receipt.php?id=" . $invoice_data['id'];
    
    $whatsapp_url = WHATSAPP_API_URL . "?phone=" . $phone_number . "&text=" . urlencode($message);
    
    return $whatsapp_url;
}

// File Upload Functions
function upload_file($file, $directory = '') {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $upload_dir = UPLOAD_PATH . $directory;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    } else {
        return ['success' => false, 'error' => 'Upload failed'];
    }
}

// Pagination Functions
function paginate($total_records, $current_page = 1, $records_per_page = RECORDS_PER_PAGE) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_records' => $total_records,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}
?>