<?php
/**
 * DPS POS FBR Integrated - Main Installation Script
 */

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

try {
    // Step 1: Create database configuration
    createDatabaseConfig($data['database']);
    
    // Step 2: Run database migrations
    runDatabaseMigrations($data['database']);
    
    // Step 3: Create super admin user
    createSuperAdmin($data['admin']);
    
    // Step 4: Seed initial data
    seedInitialData();
    
    // Step 5: Create application configuration
    createApplicationConfig($data);
    
    // Step 6: Create necessary directories
    createDirectories();
    
    // Step 7: Create .htaccess files
    createHtaccessFiles();
    
    // Step 8: Create installation lock
    createInstallationLock();
    
    echo json_encode([
        'success' => true,
        'message' => 'Installation completed successfully!',
        'admin_email' => $data['admin']['email']
    ]);

} catch (Exception $e) {
    error_log("Installation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function createDatabaseConfig($dbConfig) {
    $configContent = "<?php\n";
    $configContent .= "/**\n";
    $configContent .= " * DPS POS FBR Integrated - Database Configuration\n";
    $configContent .= " * Generated during installation\n";
    $configContent .= " */\n\n";
    $configContent .= "return [\n";
    $configContent .= "    'default' => 'mysql',\n";
    $configContent .= "    'connections' => [\n";
    $configContent .= "        'mysql' => [\n";
    $configContent .= "            'driver' => 'mysql',\n";
    $configContent .= "            'host' => '{$dbConfig['host']}',\n";
    $configContent .= "            'port' => '3306',\n";
    $configContent .= "            'database' => '{$dbConfig['name']}',\n";
    $configContent .= "            'username' => '{$dbConfig['username']}',\n";
    $configContent .= "            'password' => '{$dbConfig['password']}',\n";
    $configContent .= "            'charset' => 'utf8mb4',\n";
    $configContent .= "            'collation' => 'utf8mb4_unicode_ci',\n";
    $configContent .= "            'prefix' => '',\n";
    $configContent .= "            'strict' => true,\n";
    $configContent .= "            'engine' => null,\n";
    $configContent .= "        ],\n";
    $configContent .= "    ],\n";
    $configContent .= "];\n";
    
    $configDir = dirname(__DIR__) . '/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    file_put_contents($configDir . '/database.php', $configContent);
}

function runDatabaseMigrations($dbConfig) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Read and execute database schema
    $schemaFile = __DIR__ . '/database_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception('Database schema file not found');
    }
    
    $sql = file_get_contents($schemaFile);
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
}

function createSuperAdmin($adminData) {
    $dsn = "mysql:host=localhost;dbname=dpspos_fbr;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $password = password_hash($adminData['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_active, email_verified_at, created_at, updated_at) 
        VALUES (?, ?, ?, 'super_admin', 1, NOW(), NOW(), NOW())
    ");
    
    $stmt->execute([
        $adminData['name'],
        $adminData['email'],
        $password
    ]);
}

function seedInitialData() {
    $dsn = "mysql:host=localhost;dbname=dpspos_fbr;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Insert default provinces
    $provinces = [
        ['name' => 'Punjab', 'code' => 'PUN'],
        ['name' => 'Sindh', 'code' => 'SIN'],
        ['name' => 'Khyber Pakhtunkhwa', 'code' => 'KPK'],
        ['name' => 'Balochistan', 'code' => 'BAL'],
        ['name' => 'Islamabad Capital Territory', 'code' => 'ICT'],
        ['name' => 'Azad Jammu and Kashmir', 'code' => 'AJK'],
        ['name' => 'Gilgit-Baltistan', 'code' => 'GB']
    ];
    
    foreach ($provinces as $province) {
        $stmt = $pdo->prepare("INSERT INTO provinces (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$province['name'], $province['code']]);
    }
    
    // Insert default units of measure
    $uom = [
        ['name' => 'Numbers, pieces, units', 'code' => 'PCE'],
        ['name' => 'Kilograms', 'code' => 'KGM'],
        ['name' => 'Grams', 'code' => 'GRM'],
        ['name' => 'Liters', 'code' => 'LTR'],
        ['name' => 'Meters', 'code' => 'MTR'],
        ['name' => 'Square meters', 'code' => 'MTK'],
        ['name' => 'Cubic meters', 'code' => 'MTQ'],
        ['name' => 'Dozen', 'code' => 'DZN'],
        ['name' => 'Gross', 'code' => 'GRO'],
        ['name' => 'Box', 'code' => 'BX']
    ];
    
    foreach ($uom as $unit) {
        $stmt = $pdo->prepare("INSERT INTO units_of_measure (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$unit['name'], $unit['code']]);
    }
    
    // Insert default HS codes
    $hsCodes = [
        ['code' => '0101.2100', 'description' => 'Live horses, asses, mules and hinnies'],
        ['code' => '0102.2100', 'description' => 'Live bovine animals'],
        ['code' => '0103.2100', 'description' => 'Live swine'],
        ['code' => '0104.2100', 'description' => 'Live sheep and goats'],
        ['code' => '0105.2100', 'description' => 'Live poultry'],
        ['code' => '0201.2100', 'description' => 'Meat of bovine animals, fresh or chilled'],
        ['code' => '0202.2100', 'description' => 'Meat of bovine animals, frozen'],
        ['code' => '0203.2100', 'description' => 'Meat of swine, fresh, chilled or frozen'],
        ['code' => '0204.2100', 'description' => 'Meat of sheep or goats, fresh, chilled or frozen'],
        ['code' => '0205.2100', 'description' => 'Meat of horses, asses, mules or hinnies, fresh, chilled or frozen']
    ];
    
    foreach ($hsCodes as $hsCode) {
        $stmt = $pdo->prepare("INSERT INTO hs_codes (code, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$hsCode['code'], $hsCode['description']]);
    }
}

function createApplicationConfig($data) {
    $appConfig = "<?php\n";
    $appConfig .= "return [\n";
    $appConfig .= "    'name' => 'DPS POS FBR Integrated',\n";
    $appConfig .= "    'env' => 'production',\n";
    $appConfig .= "    'debug' => false,\n";
    $appConfig .= "    'url' => '" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "',\n";
    $appConfig .= "    'timezone' => 'Asia/Karachi',\n";
    $appConfig .= "    'locale' => 'en',\n";
    $appConfig .= "    'fallback_locale' => 'en',\n";
    $appConfig .= "    'key' => '" . bin2hex(random_bytes(32)) . "',\n";
    $appConfig .= "    'cipher' => 'AES-256-CBC',\n";
    $appConfig .= "    'providers' => [\n";
    $appConfig .= "        // Service providers\n";
    $appConfig .= "    ],\n";
    $appConfig .= "    'aliases' => [\n";
    $appConfig .= "        // Class aliases\n";
    $appConfig .= "    ],\n";
    $appConfig .= "];\n";
    
    $configDir = dirname(__DIR__) . '/config';
    file_put_contents($configDir . '/app.php', $appConfig);
}

function createDirectories() {
    $directories = [
        'storage/app',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'public/uploads',
        'public/uploads/products',
        'public/uploads/customers',
        'public/uploads/suppliers',
        'public/uploads/employees',
        'public/uploads/receipts',
        'public/uploads/backups'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = dirname(__DIR__) . '/' . $dir;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
    }
}

function createHtaccessFiles() {
    // Main .htaccess
    $htaccess = "RewriteEngine On\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "RewriteRule ^(.*)$ public/index.php [QSA,L]\n";
    
    file_put_contents(dirname(__DIR__) . '/.htaccess', $htaccess);
    
    // Public .htaccess
    $publicHtaccess = "Options -Indexes\n";
    $publicHtaccess .= "RewriteEngine On\n";
    $publicHtaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $publicHtaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $publicHtaccess .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
    
    file_put_contents(dirname(__DIR__) . '/public/.htaccess', $publicHtaccess);
}

function createInstallationLock() {
    $lockContent = "<?php\n";
    $lockContent .= "// DPS POS FBR Integrated - Installation Lock\n";
    $lockContent .= "// This file prevents re-installation\n";
    $lockContent .= "// Delete this file to re-run installation\n";
    $lockContent .= "define('INSTALLATION_LOCK', true);\n";
    $lockContent .= "define('INSTALLATION_DATE', '" . date('Y-m-d H:i:s') . "');\n";
    
    file_put_contents(dirname(__DIR__) . '/config/install.lock', $lockContent);
}
?>