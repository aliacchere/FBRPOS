<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$database = $input['database'] ?? [];
$admin = $input['admin'] ?? [];
$license = $input['license'] ?? [];

// Validate required fields
if (empty($database['host']) || empty($database['name']) || empty($database['username'])) {
    echo json_encode(['success' => false, 'message' => 'Database configuration is incomplete']);
    exit;
}

if (empty($admin['name']) || empty($admin['email']) || empty($admin['password'])) {
    echo json_encode(['success' => false, 'message' => 'Admin configuration is incomplete']);
    exit;
}

if (empty($license['key'])) {
    echo json_encode(['success' => false, 'message' => 'License key is required']);
    exit;
}

try {
    // Step 1: Test database connection
    $dsn = "mysql:host={$database['host']};port={$database['port']};dbname={$database['name']};charset={$database['charset']}";
    $pdo = new PDO($dsn, $database['username'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Step 2: Create database tables
    $tables = createDatabaseTables($pdo);
    
    // Step 3: Create Super Admin user
    $adminId = createSuperAdmin($pdo, $admin);
    
    // Step 4: Create default tenant
    $tenantId = createDefaultTenant($pdo, $admin);
    
    // Step 5: Create configuration files
    createConfigurationFiles($database, $admin, $license);
    
    // Step 6: Set up file permissions
    setupFilePermissions();
    
    // Step 7: Create installation lock file
    createInstallationLock();
    
    echo json_encode([
        'success' => true,
        'message' => 'Installation completed successfully',
        'details' => [
            'admin_id' => $adminId,
            'tenant_id' => $tenantId,
            'tables_created' => count($tables),
            'admin_email' => $admin['email'],
            'database_name' => $database['name']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Installation failed: ' . $e->getMessage()
    ]);
}

function createDatabaseTables($pdo) {
    $tables = [];
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NULL,
            role ENUM('super_admin', 'tenant_admin', 'cashier', 'manager') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            email_verified_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'users';
    
    // Tenants table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            domain VARCHAR(255) UNIQUE NULL,
            database_name VARCHAR(255) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            subscription_plan VARCHAR(50) DEFAULT 'basic',
            subscription_expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_domain (domain),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'tenants';
    
    // Products table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) UNIQUE NOT NULL,
            barcode VARCHAR(100) NULL,
            description TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock_quantity INT NOT NULL DEFAULT 0,
            min_stock_level INT NOT NULL DEFAULT 0,
            category_id BIGINT UNSIGNED NULL,
            supplier_id BIGINT UNSIGNED NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_sku (sku),
            INDEX idx_barcode (barcode),
            INDEX idx_category_id (category_id),
            INDEX idx_supplier_id (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'products';
    
    // Sales table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            fbr_invoice_number VARCHAR(50) NULL,
            customer_id BIGINT UNSIGNED NULL,
            cashier_id BIGINT UNSIGNED NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method ENUM('cash', 'card', 'bank_transfer', 'mobile_payment') NOT NULL,
            payment_reference VARCHAR(100) NULL,
            status ENUM('draft', 'completed', 'cancelled', 'refunded') DEFAULT 'draft',
            fbr_status ENUM('pending', 'validated', 'posted', 'failed') DEFAULT 'pending',
            fbr_error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_invoice_number (invoice_number),
            INDEX idx_fbr_invoice_number (fbr_invoice_number),
            INDEX idx_customer_id (customer_id),
            INDEX idx_cashier_id (cashier_id),
            INDEX idx_status (status),
            INDEX idx_fbr_status (fbr_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'sales';
    
    // Sale Items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sale_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            sale_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_sale_id (sale_id),
            INDEX idx_product_id (product_id),
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'sale_items';
    
    // FBR Integration Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fbr_integration_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            bearer_token TEXT NOT NULL,
            environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
            is_active BOOLEAN DEFAULT TRUE,
            last_sync_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            UNIQUE KEY unique_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'fbr_integration_settings';
    
    // System Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NULL,
            setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            is_public BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_setting_key (setting_key),
            UNIQUE KEY unique_tenant_setting (tenant_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $tables[] = 'system_settings';
    
    return $tables;
}

function createSuperAdmin($pdo, $admin) {
    $hashedPassword = password_hash($admin['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (tenant_id, name, email, password, phone, role, is_active, email_verified_at) 
        VALUES (1, ?, ?, ?, ?, 'super_admin', TRUE, NOW())
    ");
    
    $stmt->execute([
        $admin['name'],
        $admin['email'],
        $hashedPassword,
        $admin['phone'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function createDefaultTenant($pdo, $admin) {
    $stmt = $pdo->prepare("
        INSERT INTO tenants (name, domain, is_active, subscription_plan) 
        VALUES (?, ?, TRUE, 'unlimited')
    ");
    
    $companyName = $admin['company'] ?? 'Default Company';
    $stmt->execute([$companyName, null]);
    
    $tenantId = $pdo->lastInsertId();
    
    // Update the super admin to belong to this tenant
    $stmt = $pdo->prepare("UPDATE users SET tenant_id = ? WHERE role = 'super_admin'");
    $stmt->execute([$tenantId]);
    
    return $tenantId;
}

function createConfigurationFiles($database, $admin, $license) {
    // Create .env file
    $envContent = "APP_NAME=\"DPS POS FBR Integrated\"
APP_ENV=production
APP_KEY=" . base64_encode(random_bytes(32)) . "
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST={$database['host']}
DB_PORT={$database['port']}
DB_DATABASE={$database['name']}
DB_USERNAME={$database['username']}
DB_PASSWORD={$database['password']}

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME=\"\${APP_NAME}\"

FBR_LICENSE_KEY={$license['key']}
FBR_SANDBOX_URL=https://gw.fbr.gov.pk/pdi/v1
FBR_PRODUCTION_URL=https://gw.fbr.gov.pk/pdi/v1
";
    
    file_put_contents('../.env', $envContent);
    
    // Create config/database.php
    $dbConfig = "<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],
];
";
    
    if (!is_dir('../config')) {
        mkdir('../config', 0755, true);
    }
    file_put_contents('../config/database.php', $dbConfig);
}

function setupFilePermissions() {
    $directories = [
        '../storage',
        '../storage/app',
        '../storage/framework',
        '../storage/framework/cache',
        '../storage/framework/sessions',
        '../storage/framework/views',
        '../storage/logs',
        '../bootstrap/cache',
        '../public/uploads',
        '../public/storage'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        chmod($dir, 0755);
    }
}

function createInstallationLock() {
    $lockContent = "<?php
// DPS POS FBR Integrated Installation Lock
// This file prevents accidental re-installation
// Delete this file to allow re-installation

return [
    'installed' => true,
    'installed_at' => '" . date('Y-m-d H:i:s') . "',
    'version' => '1.0.0'
];
";
    
    file_put_contents('../storage/installed.lock', $lockContent);
}
?>